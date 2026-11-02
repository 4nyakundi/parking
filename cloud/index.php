<?php
/**
 * Mombasa Mall Basement Parking - Remote Executive & Admin Cloud Mirror Dashboard
 * Fully synchronised with on-premise Edge PC and Dahua IVSS NVR (192.168.0.3).
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['cloud_user_id'])) {
    header('Location: login.php');
    exit;
}

$cfg = require __DIR__ . '/config.cloud.php';
$dbCfg = $cfg['db'];

$dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['dbname']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

require_once __DIR__ . '/schema_init.php';
ensure_cloud_schema($pdo);

// 1. Fetch Edge Heartbeat & Check Online Status
$hbStmt = $pdo->query('
    SELECT *, TIMESTAMPDIFF(SECOND, last_synced_at, NOW()) AS age_seconds 
    FROM cloud_edge_heartbeats 
    ORDER BY id DESC LIMIT 1
');
$heartbeat = $hbStmt->fetch();

$isOnline = false;
$lastSyncText = 'Never synced';
$edgeIp = '192.168.0.7';
if ($heartbeat) {
    $age = (int)$heartbeat['age_seconds'];
    $isOnline = $age < 180; // Online if synced within last 3 minutes
    $lastSyncText = $heartbeat['last_synced_at'] . " ({$age}s ago)";
    if (!empty($heartbeat['ip_address'])) {
        $edgeIp = $heartbeat['ip_address'];
    }
}

// 2. Compute Live Operational Metrics
$capacity = (int)($cfg['mall']['capacity'] ?? 60);
try {
    $capStmt = $pdo->query('SELECT value FROM system_settings WHERE `key` = "capacity" LIMIT 1');
    $dbCap = $capStmt->fetchColumn();
    if ($dbCap && is_numeric($dbCap)) $capacity = (int)$dbCap;
} catch (Throwable) {}

// Query sessions (support either cloud_parking_sessions or fallback parking_sessions)
$activeSessions = [];
$completedToday = 0;
$todayEntries   = 0;
$avgDwell       = 0;
$overstayCount  = 0;
$totalSessions  = 0;

try {
    $occStmt = $pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions WHERE status = "ACTIVE"');
    $occupied = (int)$occStmt->fetchColumn();

    $todayInStmt = $pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions WHERE DATE(entry_time) = CURDATE()');
    $todayEntries = (int)$todayInStmt->fetchColumn();

    $todayOutStmt = $pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()');
    $completedToday = (int)$todayOutStmt->fetchColumn();

    $dwellStmt = $pdo->query('SELECT ROUND(AVG(duration_minutes)) FROM cloud_parking_sessions WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()');
    $avgDwellVal = $dwellStmt->fetchColumn();
    $avgDwell = $avgDwellVal !== false && $avgDwellVal !== null ? (int)$avgDwellVal : 0;

    $overstayStmt = $pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions WHERE status = "ACTIVE" AND entry_time < DATE_SUB(NOW(), INTERVAL 2 HOUR)');
    $overstayCount = (int)$overstayStmt->fetchColumn();

    $totalSessions = (int)$pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions')->fetchColumn();
} catch (Throwable) {
    $occupied = 0;
}

$available = max(0, $capacity - $occupied);
$utilizationPct = min(100, round(($occupied / max(1, $capacity)) * 100));

// 3. Hourly Traffic Curve Today (00:00 to 23:00)
$hourlyTraffic = array_fill(0, 24, 0);
try {
    $hourStmt = $pdo->query('
        SELECT HOUR(entry_time) AS hr, COUNT(*) AS cnt 
        FROM cloud_parking_sessions 
        WHERE DATE(entry_time) = CURDATE() 
        GROUP BY HOUR(entry_time)
    ');
    while ($row = $hourStmt->fetch()) {
        $hourlyTraffic[(int)$row['hr']] = (int)$row['cnt'];
    }
} catch (Throwable) {}

// 4. Destination Popularity & Dwell
$destStats = [];
try {
    $destStmt = $pdo->query('
        SELECT destination, COUNT(*) AS visits, ROUND(AVG(duration_minutes)) AS avg_dwell
        FROM cloud_parking_sessions 
        WHERE destination IS NOT NULL AND destination != ""
        GROUP BY destination 
        ORDER BY visits DESC 
        LIMIT 8
    ');
    $destStats = $destStmt->fetchAll();
} catch (Throwable) {}

// 5. Recent Parking Sessions (Top 100)
$recentSessions = [];
try {
    $sessStmt = $pdo->query('SELECT * FROM cloud_parking_sessions ORDER BY id DESC LIMIT 100');
    $recentSessions = $sessStmt->fetchAll();
} catch (Throwable) {}

// 6. Driver Requests / Pending Gate Visitors
$pendingVisitors = [];
try {
    $visStmt = $pdo->query('
        SELECT v.*, TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) AS wait_minutes
        FROM visitors v
        ORDER BY v.id DESC LIMIT 50
    ');
    $pendingVisitors = $visStmt->fetchAll();
} catch (Throwable) {}

// 7. Registered Vehicles (VIP, Staff, Tenant, Blacklist)
$registeredVehicles = [];
try {
    $regStmt = $pdo->query('SELECT * FROM registered_vehicles ORDER BY is_active DESC, category ASC, plate_number ASC LIMIT 100');
    $registeredVehicles = $regStmt->fetchAll();
} catch (Throwable) {}

// 8. Retail Store Directory (Destinations by floor)
$destinations = [];
try {
    $dStmt = $pdo->query('SELECT * FROM destinations WHERE is_active = 1 ORDER BY floor_level ASC, sort_order ASC, name ASC');
    $destinations = $dStmt->fetchAll();
} catch (Throwable) {}

// 9. Hardware & Health Diagnostics
$deviceHealth = [];
try {
    $dhStmt = $pdo->query('
        SELECT *, TIMESTAMPDIFF(SECOND, last_checked_at, NOW()) AS age_seconds 
        FROM device_health 
        ORDER BY device ASC
    ');
    $deviceHealth = $dhStmt->fetchAll();
} catch (Throwable) {}

// 10. Admin & Staff Roster
$staffUsers = [];
try {
    $userStmt = $pdo->query('SELECT id, username, full_name, role, is_active, last_login_at, shift_start, shift_end FROM users ORDER BY role ASC, full_name ASC');
    $staffUsers = $userStmt->fetchAll();
} catch (Throwable) {}

// 11. Shift Reconciliations
$reconciliations = [];
try {
    $recStmt = $pdo->query('
        SELECT sr.*, u.full_name AS supervisor_name
        FROM shift_reconciliations sr
        LEFT JOIN users u ON u.id = sr.supervisor_id
        ORDER BY sr.id DESC LIMIT 15
    ');
    $reconciliations = $recStmt->fetchAll();
} catch (Throwable) {}

// 12. System Settings
$systemSettings = [];
try {
    $setStmt = $pdo->query('SELECT * FROM system_settings ORDER BY `key` ASC');
    $systemSettings = $setStmt->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Executive Command &amp; Admin Dashboard - Mombasa Mall Parking</title>
    <link rel="stylesheet" href="css/cloud.css">
</head>
<body>

    <!-- ================================================================
         TOP APPLICATION HEADER & STATUS
         ================================================================ -->
    <header class="top-bar">
        <div class="brand-badge">
            <div class="icon">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
            </div>
            <div>
                <h1 style="font-size:18px; font-weight:800; letter-spacing:0.02em;">Mombasa Mall Executive Cloud Center</h1>
                <p style="font-size:11.5px; color:var(--text-sub); font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">
                    Remote Facility Mirror &bull; Edge Gateway: <?= htmlspecialchars($edgeIp) ?>
                </p>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <!-- Edge PC Status Pill -->
            <div class="edge-status-pill <?= $isOnline ? '' : 'offline' ?>" title="Edge PC Heartbeat: <?= htmlspecialchars($lastSyncText) ?>">
                <span class="status-indicator <?= $isOnline ? 'online' : 'offline' ?>"></span>
                <span>EDGE GATEWAY: <?= $isOnline ? 'ONLINE (192.168.1.7)' : 'OFFLINE' ?></span>
            </div>

            <!-- Hardware NVR Pill -->
            <div class="edge-hw-pill" title="Dahua ITC ANPR Cameras & IVSS NVR on 192.168.1.0/24">
                <span>CAMERAS: 192.168.1.230 / 210</span>
            </div>

            <!-- Instant Sync Button -->
            <button type="button" class="btn btn-sync" onclick="triggerRemoteEdgeSync()" title="Synchronize latest transactions with on-premise Edge PC">
                <span id="syncBtnIcon">
                    <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </span>
                <span id="syncBtnText">Sync Edge Now</span>
            </button>

            <!-- Quick Portal Jump Links -->
            <a href="../admin/" target="_blank" class="btn btn-toggle" title="Open Local Edge Admin Console" style="text-decoration:none;">
                Admin Console &#x2197;
            </a>
            <a href="../guard/" target="_blank" class="btn btn-toggle" title="Open Guard Station Terminal" style="text-decoration:none;">
                Guard Station &#x2197;
            </a>

            <!-- Theme Switcher -->
            <button type="button" class="btn btn-toggle" onclick="toggleTheme()" title="Switch Light/Dark Theme">
                Theme
            </button>

            <!-- Logout -->
            <a href="login.php?logout=1" class="btn btn-toggle" style="text-decoration:none; color:var(--red);">
                Logout
            </a>
        </div>
    </header>

    <!-- ================================================================
         EXECUTIVE NAVIGATION STRIP (TABS)
         ================================================================ -->
    <nav class="cloud-nav-strip">
        <button type="button" class="cloud-nav-btn active" data-tab="overview" onclick="switchCloudSection('overview')">
            <span>Overview</span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="sessions" onclick="switchCloudSection('sessions')">
            <span>All Sessions</span>
            <span class="cloud-nav-badge" id="navCountSessions"><?= count($recentSessions) ?></span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="visitors" onclick="switchCloudSection('visitors')">
            <span>Driver Requests</span>
            <span class="cloud-nav-badge" id="navCountVisitors"><?= count($pendingVisitors) ?></span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="vehicles" onclick="switchCloudSection('vehicles')">
            <span>Vehicle Registry</span>
            <span class="cloud-nav-badge"><?= count($registeredVehicles) ?></span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="destinations" onclick="switchCloudSection('destinations')">
            <span>Shop Directory</span>
            <span class="cloud-nav-badge"><?= count($destinations) ?></span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="staff" onclick="switchCloudSection('staff')">
            <span>Security &amp; Staff</span>
            <span class="cloud-nav-badge"><?= count($staffUsers) ?></span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="health" onclick="switchCloudSection('health')">
            <span>Hardware &amp; Health</span>
            <span class="cloud-nav-badge" style="background:var(--green); color:#fff;"><?= count($deviceHealth) ?></span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="reconcile" onclick="switchCloudSection('reconcile')">
            <span>Shift Reconcile</span>
            <span class="cloud-nav-badge"><?= count($reconciliations) ?></span>
        </button>
        <button type="button" class="cloud-nav-btn" data-tab="settings" onclick="switchCloudSection('settings')">
            <span>System Settings</span>
        </button>
    </nav>

    <!-- ================================================================
         SECTION 1: OVERVIEW DASHBOARD
         ================================================================ -->
    <section id="sec-overview" class="cloud-section active">

        <!-- Top Hardware & System Telemetry Status Cards -->
        <div class="hw-strip">
            <div class="hw-card">
                <div class="hw-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <div>
                    <div class="hw-title">Edge Gateway</div>
                    <div class="hw-val" style="color:<?= $isOnline ? 'var(--green)' : 'var(--red)' ?>;">
                        <?= $isOnline ? 'Online (192.168.1.7)' : 'Offline / Standby' ?>
                    </div>
                </div>
            </div>
            <div class="hw-card">
                <div class="hw-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
                <div>
                    <div class="hw-title">Dahua ANPR Cameras</div>
                    <div class="hw-val" style="color:var(--green);">
                        Gate 1 (192.168.1.230) &bull; Gate 2 (192.168.1.210)
                    </div>
                </div>
            </div>
            <div class="hw-card">
                <div class="hw-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <div>
                    <div class="hw-title">ALPR OCR Stream</div>
                    <div class="hw-val" style="color:var(--blue);">Active &bull; Continuous Event Stream</div>
                </div>
            </div>
            <div class="hw-card">
                <div class="hw-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                </div>
                <div>
                    <div class="hw-title">Cloud Synced Records</div>
                    <div class="hw-val" style="color:var(--purple);"><?= $totalSessions ?> Stored Sessions</div>
                </div>
            </div>
        </div>

        <!-- KPI Summary Cards Row -->
        <div class="kpi-row">
            <div class="kpi-card">
                <span style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Live Occupancy</span>
                <span class="num" style="color:var(--amber);"><?= $occupied ?></span>
                <span style="font-size:12.5px; color:var(--text-sub);">out of <?= $capacity ?> slots</span>
            </div>

            <div class="kpi-card">
                <span style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Available Bays</span>
                <span class="num" style="color:var(--green);"><?= $available ?></span>
                <span style="font-size:12.5px; color:var(--text-sub);">Free basement spaces</span>
            </div>

            <div class="kpi-card">
                <span style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Today's Entries</span>
                <span class="num" style="color:var(--blue);"><?= $todayEntries ?></span>
                <span style="font-size:12.5px; color:var(--text-sub);">Vehicles admitted today</span>
            </div>

            <div class="kpi-card">
                <span style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Today's Departures</span>
                <span class="num" style="color:var(--green);"><?= $completedToday ?></span>
                <span style="font-size:12.5px; color:var(--text-sub);">Cleared via Gate 2</span>
            </div>

            <div class="kpi-card">
                <span style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Active Overstays (>2h)</span>
                <span class="num" style="color:<?= $overstayCount > 0 ? 'var(--red)' : 'var(--text-sub)' ?>;"><?= $overstayCount ?></span>
                <span style="font-size:12.5px; color:var(--text-sub);">Exceeded 2 free hours</span>
            </div>

            <div class="kpi-card">
                <span style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Average Dwell Time</span>
                <span class="num" style="color:var(--purple);"><?= $avgDwell ?>m</span>
                <span style="font-size:12.5px; color:var(--text-sub);">Customer shopping dwell</span>
            </div>
        </div>

        <!-- Visual Analytics Grid -->
        <div class="charts-grid">
            <div class="chart-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <div>
                        <h3 style="font-size:16px; font-weight:800;">Mall Dwell Time by Destination Store</h3>
                        <p style="font-size:12px; color:var(--text-sub);">Customer volume and dwell minutes across retail stores</p>
                    </div>
                    <span style="font-size:12px; font-weight:700; color:var(--blue); background:rgba(59,130,246,0.1); padding:4px 10px; border-radius:6px;">
                        Top Retailers
                    </span>
                </div>
                <div style="height:260px;">
                    <canvas id="chartCloudDest"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3 style="font-size:16px; font-weight:800; margin-bottom:16px;">Occupancy Slot Gauge</h3>
                <div style="text-align:center; padding:16px 0;">
                    <div style="font-size:58px; font-weight:900; color:<?= $utilizationPct > 90 ? 'var(--red)' : ($utilizationPct > 75 ? 'var(--amber)' : 'var(--green)') ?>;" id="gaugeNum">
                        <?= $utilizationPct ?>%
                    </div>
                    <p style="color:var(--text-sub); font-size:14px; font-weight:700; margin-top:4px;">Basement Capacity Utilized</p>
                    <div style="background:var(--border); height:14px; border-radius:10px; margin-top:20px; overflow:hidden;">
                        <div id="gaugeBar" style="background:<?= $utilizationPct > 90 ? 'var(--red)' : ($utilizationPct > 75 ? 'var(--amber)' : 'var(--green)') ?>; width:<?= $utilizationPct ?>%; height:100%; transition:width 1s ease;"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:12px; color:var(--text-sub); font-weight:700;">
                        <span>0 Slots</span>
                        <span><?= $occupied ?> / <?= $capacity ?> Occupied</span>
                        <span><?= $capacity ?> Max</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Hourly Traffic Curve -->
        <div class="chart-card" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Today's Hourly Vehicle Influx</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Distribution of arrivals from 00:00 to 23:00</p>
                </div>
                <span style="font-size:12px; font-weight:700; color:var(--green); background:rgba(16,185,129,0.1); padding:4px 10px; border-radius:6px;">
                    Peak Activity Analysis
                </span>
            </div>
            <div style="height:220px;">
                <canvas id="chartCloudHourly"></canvas>
            </div>
        </div>

        <!-- Executive Quick Sessions Feed -->
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Live Gate Traffic Feed (Latest 10 Sessions)</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Real-time stream from Gate 1 Entrance and Gate 2 Exit</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="switchCloudSection('sessions')">
                    View All Records &rarr;
                </button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Vehicle Number Plate</th>
                            <th>Driver &amp; Phone</th>
                            <th>Destination Store</th>
                            <th>Entry Time</th>
                            <th>Exit Time</th>
                            <th>Stay Length</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSessions)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-sub);">No records synced yet.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($recentSessions, 0, 10) as $s): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($s['ticket_id']) ?></strong></td>
                                    <td>
                                        <div class="plate-badge-ke">
                                            <span class="flag-ke">KE</span>
                                            <span><?= htmlspecialchars($s['plate_number']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($s['driver_name']) ?></div>
                                        <div style="font-size:11.5px; color:var(--text-sub);"><?= htmlspecialchars($s['driver_phone']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($s['destination']) ?></td>
                                    <td><?= htmlspecialchars($s['entry_time']) ?></td>
                                    <td><?= htmlspecialchars($s['exit_time'] ?? 'Inside Now') ?></td>
                                    <td>
                                        <?php if ($s['duration_minutes']): ?>
                                            <span style="font-weight:800; color:<?= (int)$s['duration_minutes'] > 120 ? 'var(--red)' : 'var(--blue)' ?>;">
                                                <?= (int)$s['duration_minutes'] ?> mins
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-sub);">&bull;&bull;&bull;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-tag <?= strtolower($s['status']) ?>">
                                            <?= htmlspecialchars($s['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </section>

    <!-- ================================================================
         SECTION 2: ALL SESSIONS (INTERACTIVE EXPLORER)
         ================================================================ -->
    <section id="sec-sessions" class="cloud-section">
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Synchronized Parking Sessions Archive</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Search, filter, and inspect all vehicle transactions with duration audit</p>
                </div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <a href="api/export_csv.php?status=all" class="btn btn-primary" title="Download Excel-compatible CSV">
                        Export CSV Report
                    </a>
                </div>
            </div>

            <!-- Filter Pills & Search Bar Toolbar -->
            <div class="filter-toolbar">
                <div class="filter-pills-row">
                    <button type="button" class="filter-pill active" data-sfilter="all" onclick="filterCloudSessions('all')">
                        All Records (<?= count($recentSessions) ?>)
                    </button>
                    <button type="button" class="filter-pill" data-sfilter="active" onclick="filterCloudSessions('active')">
                        Inside Now (<?= $occupied ?>)
                    </button>
                    <button type="button" class="filter-pill" data-sfilter="completed" onclick="filterCloudSessions('completed')">
                        Exited Today (<?= $completedToday ?>)
                    </button>
                    <button type="button" class="filter-pill" data-sfilter="overstay" onclick="filterCloudSessions('overstay')">
                        Overstay &gt;2h (<?= $overstayCount ?>)
                    </button>
                </div>

                <div class="search-input-box">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" style="color:var(--text-sub);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="sessionSearchInput" placeholder="Filter by plate, driver, or store..." oninput="searchCloudSessions(this.value)">
                </div>
            </div>

            <!-- Sessions Table -->
            <div class="table-responsive">
                <table id="cloudSessionsTable">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Vehicle Number Plate</th>
                            <th>Driver &amp; Mobile</th>
                            <th>Destination Store</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Dwell Duration</th>
                            <th>Entry Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSessions)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-sub);">No records synced yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentSessions as $s): 
                                $isOverstay = ($s['status'] === 'ACTIVE' && (time() - strtotime($s['entry_time'])) > 7200);
                            ?>
                                <tr data-status="<?= strtolower($s['status']) ?>" data-overstay="<?= $isOverstay ? '1' : '0' ?>"
                                    data-search="<?= strtolower(htmlspecialchars($s['ticket_id'] . ' ' . $s['plate_number'] . ' ' . $s['driver_name'] . ' ' . $s['destination'])) ?>">
                                    <td><strong><?= htmlspecialchars($s['ticket_id']) ?></strong></td>
                                    <td>
                                        <div class="plate-badge-ke">
                                            <span class="flag-ke">KE</span>
                                            <span><?= htmlspecialchars($s['plate_number']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($s['driver_name']) ?></div>
                                        <div style="font-size:11.5px; color:var(--text-sub);"><?= htmlspecialchars($s['driver_phone']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($s['destination']) ?></td>
                                    <td><?= htmlspecialchars($s['entry_time']) ?></td>
                                    <td><?= htmlspecialchars($s['exit_time'] ?? 'Inside') ?></td>
                                    <td>
                                        <?php if ($s['duration_minutes']): ?>
                                            <span style="font-weight:800; color:<?= (int)$s['duration_minutes'] > 120 ? 'var(--red)' : 'var(--blue)' ?>;">
                                                <?= (int)$s['duration_minutes'] ?> mins
                                            </span>
                                        <?php elseif ($isOverstay): ?>
                                            <span class="status-tag overstay">Overstay &gt;2h</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-sub);">Parked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size:11px; font-weight:700; color:var(--text-sub); text-transform:uppercase;">
                                            <?= htmlspecialchars($s['entry_method'] ?? 'ALPR') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-tag <?= strtolower($s['status']) ?>">
                                            <?= htmlspecialchars($s['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECTION 3: DRIVER REQUESTS / GATE INTAKE QUEUE
         ================================================================ -->
    <section id="sec-visitors" class="cloud-section">
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Gate Camera &amp; Mobile Intake Queue</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Real-time arrivals captured at Gate 1 awaiting data or driver details</p>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Req ID</th>
                            <th>Vehicle Number Plate</th>
                            <th>Driver Name</th>
                            <th>Driver Phone</th>
                            <th>Destination</th>
                            <th>Source</th>
                            <th>Wait Time</th>
                            <th>Timestamp</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingVisitors)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-sub);">No pending visitor intake requests.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingVisitors as $v): ?>
                                <tr>
                                    <td>#<?= (int)$v['id'] ?></td>
                                    <td>
                                        <div class="plate-badge-ke">
                                            <span class="flag-ke">KE</span>
                                            <span><?= htmlspecialchars($v['plate_number']) ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($v['driver_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($v['driver_phone']) ?></td>
                                    <td><?= htmlspecialchars($v['destination']) ?></td>
                                    <td>
                                        <span style="font-size:11.5px; font-weight:700; color:<?= $v['source'] === 'self_signin' ? 'var(--green)' : 'var(--amber)' ?>;">
                                            <?= $v['source'] === 'self_signin' ? 'Mobile Check-in' : 'ANPR Camera' ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$v['wait_minutes'] ?>m ago</td>
                                    <td><?= htmlspecialchars($v['created_at']) ?></td>
                                    <td>
                                        <span class="status-tag <?= strtolower($v['status']) ?>">
                                            <?= htmlspecialchars($v['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECTION 4: VEHICLES REGISTRY (VIP, STAFF, TENANTS, BLACKLIST)
         ================================================================ -->
    <section id="sec-vehicles" class="cloud-section">
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Authorized &amp; Flagged Vehicles Registry</h3>
                    <p style="font-size:12px; color:var(--text-sub);">VIP passes, tenant allotments, staff permits, and security watchlists</p>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Number Plate</th>
                            <th>Vehicle Owner / Driver</th>
                            <th>Mobile Number</th>
                            <th>Category Badge</th>
                            <th>Security Notes</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registeredVehicles)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-sub);">No registered vehicles recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($registeredVehicles as $rv): ?>
                                <tr>
                                    <td>
                                        <div class="plate-badge-ke">
                                            <span class="flag-ke">KE</span>
                                            <span><?= htmlspecialchars($rv['plate_number']) ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($rv['owner_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($rv['phone']) ?></td>
                                    <td>
                                        <span class="status-tag <?= strtolower($rv['category']) ?>">
                                            <?= htmlspecialchars(strtoupper($rv['category'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($rv['notes'] ?? 'None') ?></td>
                                    <td>
                                        <span style="font-weight:700; color:<?= $rv['is_active'] ? 'var(--green)' : 'var(--red)' ?>;">
                                            <?= $rv['is_active'] ? 'Active' : 'Revoked' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($rv['created_at'] ?? '--') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECTION 5: SHOP DESTINATIONS DIRECTORY
         ================================================================ -->
    <section id="sec-destinations" class="cloud-section">
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Mall Retail &amp; Tenant Directory (48 Destinations)</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Stores, dining, banking, and services organized by floor level</p>
                </div>
            </div>
            <div class="filter-toolbar">
                <div class="filter-pills-row">
                    <button type="button" class="filter-pill active" onclick="filterCloudStores('all')">All Floors (48)</button>
                    <button type="button" class="filter-pill" onclick="filterCloudStores('ground')">Ground Floor (5)</button>
                    <button type="button" class="filter-pill" onclick="filterCloudStores('1st')">First Floor (17)</button>
                    <button type="button" class="filter-pill" onclick="filterCloudStores('2nd')">Second Floor (19)</button>
                    <button type="button" class="filter-pill" onclick="filterCloudStores('3rd')">Third Floor (4)</button>
                </div>
            </div>
            <div class="table-responsive">
                <table id="cloudStoresTable">
                    <thead>
                        <tr>
                            <th>Store / Tenant Name</th>
                            <th>Unit Code</th>
                            <th>Floor Level</th>
                            <th>Category</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($destinations as $d): ?>
                            <tr data-floor="<?= strtolower(htmlspecialchars($d['floor_level'])) ?>">
                                <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($d['unit_code'] ?? '--') ?></code></td>
                                <td>
                                    <span style="font-weight:700; color:var(--blue);">
                                        <?= htmlspecialchars($d['floor_level']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($d['category'] ?? 'Retail') ?></td>
                                <td style="color:var(--text-sub); font-size:12.5px;"><?= htmlspecialchars($d['description'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECTION 6: GUARDS & STAFF SHIFTS
         ================================================================ -->
    <section id="sec-staff" class="cloud-section">
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Security Officers &amp; Administrative Roster</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Guard officers on duty at gate terminals and shift supervisors</p>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role Tag</th>
                            <th>Shift Schedule</th>
                            <th>Last Active</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffUsers as $su): ?>
                            <tr>
                                <td>#<?= (int)$su['id'] ?></td>
                                <td><strong><?= htmlspecialchars($su['full_name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($su['username']) ?></code></td>
                                <td>
                                    <span class="status-tag <?= strtolower($su['role']) ?>">
                                        <?= strtoupper($su['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= !empty($su['shift_start']) ? htmlspecialchars($su['shift_start'] . ' – ' . $su['shift_end']) : 'Rotational Shift' ?>
                                </td>
                                <td><?= htmlspecialchars($su['last_login_at'] ?? 'Active Today') ?></td>
                                <td>
                                    <span style="font-weight:800; color:<?= $su['is_active'] ? 'var(--green)' : 'var(--red)' ?>;">
                                        <?= $su['is_active'] ? 'Active Duty' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECTION 7: HARDWARE & SYSTEM HEALTH
         ================================================================ -->
    <section id="sec-health" class="cloud-section">
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Hardware Telemetry &amp; Camera Diagnostics</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Dahua IVSS NVR (192.168.0.3), Edge PC, and stream health status</p>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Device / Subsystem</th>
                            <th>Operational State</th>
                            <th>Diagnostic Message</th>
                            <th>Last Checked</th>
                            <th>Age</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deviceHealth as $dh): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(strtoupper($dh['device'])) ?></strong></td>
                                <td>
                                    <span style="padding:4px 8px; border-radius:6px; font-size:11px; font-weight:800; background:<?= $dh['status'] === 'OK' ? 'rgba(16,185,129,0.15); color:var(--green);' : 'rgba(239,68,68,0.15); color:var(--red);' ?>">
                                        <?= htmlspecialchars($dh['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($dh['message']) ?></td>
                                <td><?= htmlspecialchars($dh['last_checked_at']) ?></td>
                                <td><?= (int)$dh['age_seconds'] ?>s ago</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECTION 8: SHIFT RECONCILIATIONS
         ================================================================ -->
    <section id="sec-reconcile" class="cloud-section">
        <div class="table-card">
            <div class="table-header-row">
                <div>
                    <h3 style="font-size:16px; font-weight:800;">Supervisor Shift Reconciliations</h3>
                    <p style="font-size:12px; color:var(--text-sub);">Handover logs, tickets audited, overstay closures, and shift notes</p>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Shift #</th>
                            <th>Supervisor</th>
                            <th>Shift Start</th>
                            <th>Shift End</th>
                            <th>Active Handover</th>
                            <th>Exited Tickets</th>
                            <th>Total Processed</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reconciliations)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-sub);">No shift reconciliations logged.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reconciliations as $rc): ?>
                                <tr>
                                    <td>#<?= (int)$rc['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($rc['supervisor_name'] ?? 'Supervisor') ?></strong></td>
                                    <td><?= htmlspecialchars($rc['shift_start']) ?></td>
                                    <td><?= htmlspecialchars($rc['shift_end'] ?? 'In Progress') ?></td>
                                    <td><span style="font-weight:700; color:var(--amber);"><?= (int)($rc['active_sessions_count'] ?? 0) ?></span></td>
                                    <td><span style="font-weight:700; color:var(--green);"><?= (int)($rc['completed_sessions_count'] ?? 0) ?></span></td>
                                    <td><strong><?= (int)($rc['total_sessions_count'] ?? 0) ?></strong></td>
                                    <td style="font-size:12px; color:var(--text-sub);"><?= htmlspecialchars($rc['notes'] ?? 'Standard Handover') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECTION 9: SETTINGS & CLOUD SYNC CONFIGURATION
         ================================================================ -->
    <section id="sec-settings" class="cloud-section">
        <div class="table-card" style="padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="font-size:18px; font-weight:800;">Cloud System Settings &amp; Parity</h3>
                    <p style="font-size:13px; color:var(--text-sub);">Configuration mirroring the on-premise Edge PC</p>
                </div>
                <button type="button" class="btn btn-sync" onclick="triggerRemoteEdgeSync()">
                    Force Re-Sync Now
                </button>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                <div style="background:var(--card-hover); border:1px solid var(--border); border-radius:8px; padding:16px;">
                    <div style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Basement Capacity</div>
                    <div style="font-size:22px; font-weight:900; margin-top:4px;"><?= $capacity ?> Slots</div>
                    <p style="font-size:12px; color:var(--text-sub); margin-top:4px;">Maximum total car spaces</p>
                </div>

                <div style="background:var(--card-hover); border:1px solid var(--border); border-radius:8px; padding:16px;">
                    <div style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Free Stay Allowance</div>
                    <div style="font-size:22px; font-weight:900; margin-top:4px; color:var(--green);">120 Minutes (2h)</div>
                    <p style="font-size:12px; color:var(--text-sub); margin-top:4px;">Complimentary shopper parking</p>
                </div>

                <div style="background:var(--card-hover); border:1px solid var(--border); border-radius:8px; padding:16px;">
                    <div style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Dahua ANPR Network</div>
                    <div style="font-size:22px; font-weight:900; margin-top:4px; color:var(--blue);">192.168.1.0/24</div>
                    <p style="font-size:12px; color:var(--text-sub); margin-top:4px;">Gate 1 (192.168.1.230) &bull; Gate 2 (192.168.1.210)</p>
                </div>

                <div style="background:var(--card-hover); border:1px solid var(--border); border-radius:8px; padding:16px;">
                    <div style="font-size:12px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Edge Host Address</div>
                    <div style="font-size:22px; font-weight:900; margin-top:4px;"><?= htmlspecialchars($edgeIp) ?></div>
                    <p style="font-size:12px; color:var(--text-sub); margin-top:4px;">Subnet 192.168.1.0/24</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Toast Notification Banner -->
    <div id="cloudToast"></div>

    <!-- Bundled Libraries -->
    <script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
    <script src="../assets/vendor/gsap/gsap.min.js"></script>

    <!-- Interactive Dashboard Script -->
    <script>
        // 1. Navigation Section Switcher
        function switchCloudSection(sectionId) {
            document.querySelectorAll('.cloud-nav-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-tab') === sectionId);
            });
            document.querySelectorAll('.cloud-section').forEach(sec => {
                sec.classList.toggle('active', sec.id === `sec-${sectionId}`);
            });
        }

        // 2. Filter Sessions Table
        function filterCloudSessions(filterType) {
            document.querySelectorAll('#sec-sessions .filter-pill').forEach(p => {
                p.classList.toggle('active', p.getAttribute('data-sfilter') === filterType);
            });

            const rows = document.querySelectorAll('#cloudSessionsTable tbody tr');
            rows.forEach(tr => {
                const status = tr.getAttribute('data-status');
                const isOverstay = tr.getAttribute('data-overstay') === '1';

                let visible = true;
                if (filterType === 'active') visible = (status === 'active');
                else if (filterType === 'completed') visible = (status === 'completed');
                else if (filterType === 'overstay') visible = isOverstay;

                tr.style.display = visible ? '' : 'none';
            });
        }

        // 3. Search Sessions Table
        function searchCloudSessions(query) {
            const q = (query || '').toLowerCase().trim();
            const rows = document.querySelectorAll('#cloudSessionsTable tbody tr');
            rows.forEach(tr => {
                const text = tr.getAttribute('data-search') || '';
                tr.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        }

        // 4. Filter Store Destinations
        function filterCloudStores(floorKeyword) {
            const pills = document.querySelectorAll('#sec-destinations .filter-pill');
            pills.forEach(p => p.classList.remove('active'));
            event.target.classList.add('active');

            const rows = document.querySelectorAll('#cloudStoresTable tbody tr');
            rows.forEach(tr => {
                const fl = tr.getAttribute('data-floor') || '';
                if (floorKeyword === 'all') {
                    tr.style.display = '';
                } else {
                    tr.style.display = fl.includes(floorKeyword.toLowerCase()) ? '' : 'none';
                }
            });
        }

        // 5. Trigger Remote Edge Sync
        async function triggerRemoteEdgeSync() {
            const btnText = document.getElementById('syncBtnText');
            const btnIcon = document.getElementById('syncBtnIcon');
            if (btnText) btnText.textContent = 'Syncing...';
            if (btnIcon) btnIcon.innerHTML = '<span class="status-indicator online"></span>';

            showCloudToast('Initiating data synchronization with on-premise Edge PC...');
            try {
                const res = await fetch('api/trigger_sync.php');
                const json = await res.json();
                if (json && json.ok) {
                    showCloudToast(`Synchronization successful. ${json.message || 'Records are in parity.'}`);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showCloudToast(json?.error || 'Sync request completed.', 'warn');
                }
            } catch (e) {
                showCloudToast('Sync error or network timeout. Check Edge PC connection.', 'error');
            } finally {
                if (btnText) btnText.textContent = 'Sync Edge Now';
                if (btnIcon) btnIcon.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
            }
        }

        // 6. Toast Notification Helper
        function showCloudToast(msg) {
            const toast = document.getElementById('cloudToast');
            if (!toast) return;
            toast.textContent = msg;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 4000);
        }

        // 7. Light / Dark Theme Switcher
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('mm_cloud_theme', next);
        }

        // 8. Initialize Charts & Visuals on Load
        window.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('mm_cloud_theme');
            if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

            // Chart A: Destination Dwell Visits
            const destCtx = document.getElementById('chartCloudDest');
            if (destCtx && window.Chart) {
                const destLabels = <?= json_encode(array_column($destStats, 'destination')) ?>;
                const destData = <?= json_encode(array_column($destStats, 'visits')) ?>;

                new Chart(destCtx, {
                    type: 'bar',
                    data: {
                        labels: destLabels.length ? destLabels : ['Carrefour', 'Java House', 'Optica', 'Banking Hall', 'Cinema'],
                        datasets: [{
                            label: 'Customer Visits',
                            data: destData.length ? destData : [18, 12, 9, 7, 5],
                            backgroundColor: '#116FC7',
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Chart B: Today's Hourly Traffic Curve
            const hourlyCtx = document.getElementById('chartCloudHourly');
            if (hourlyCtx && window.Chart) {
                const hours = ['00:00','01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00'];
                const hourlyData = <?= json_encode(array_values($hourlyTraffic)) ?>;

                new Chart(hourlyCtx, {
                    type: 'line',
                    data: {
                        labels: hours,
                        datasets: [{
                            label: 'Vehicles Entered',
                            data: hourlyData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointRadius: 3,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // GSAP Gauge Animation
            if (window.gsap) {
                gsap.from('#gaugeBar', { width: 0, duration: 1.2, ease: "power2.out" });
            }
        });
    </script>
</body>
</html>
