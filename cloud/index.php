<?php
/**
 * Mombasa Mall Basement Parking - Remote Executive Management Dashboard
 * Synchronized live mirror of the on-premise Edge PC.
 */

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
$pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// 1. Fetch Edge Heartbeat & Check Online Status
$hbStmt = $pdo->query('
    SELECT *, TIMESTAMPDIFF(SECOND, last_synced_at, NOW()) AS age_seconds 
    FROM cloud_edge_heartbeats 
    ORDER BY id DESC LIMIT 1
');
$heartbeat = $hbStmt->fetch(PDO::FETCH_ASSOC);

$isOnline = false;
$lastSyncText = 'Never synced';
if ($heartbeat) {
    $age = (int)$heartbeat['age_seconds'];
    $isOnline = $age < 180; // Online if synced in last 3 minutes
    $lastSyncText = $heartbeat['last_synced_at'] . " ({$age}s ago)";
}

// 2. Compute Live Cloud Stats
$capacity = (int)($cfg['mall']['capacity'] ?? 60);

$occStmt = $pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions WHERE status = "ACTIVE"');
$occupied = (int)$occStmt->fetchColumn();
$available = max(0, $capacity - $occupied);

$todayEntries = (int)$pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions WHERE DATE(entry_time) = CURDATE()')->fetchColumn();
$todayExits   = (int)$pdo->query('SELECT COUNT(*) FROM cloud_parking_sessions WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()')->fetchColumn();
$avgDwell     = round((float)$pdo->query('SELECT AVG(duration_minutes) FROM cloud_parking_sessions WHERE status = "COMPLETED" AND DATE(exit_time) = CURDATE()')->fetchColumn());

// 3. Recent Sessions
$sessStmt = $pdo->query('SELECT * FROM cloud_parking_sessions ORDER BY id DESC LIMIT 15');
$recentSessions = $sessStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Destination Popularity
$destStmt = $pdo->query('SELECT destination, COUNT(*) AS cnt FROM cloud_parking_sessions GROUP BY destination ORDER BY cnt DESC LIMIT 6');
$destCounts = $destStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Management - Mombasa Mall Parking Cloud Mirror</title>
    <link rel="stylesheet" href="css/cloud.css">
</head>
<body>

    <header class="top-bar">
        <div class="brand-badge">
            <div class="icon">☁️</div>
            <div>
                <h1 style="font-size:20px; font-weight:900;">Mombasa Mall Executive Cloud Mirror</h1>
                <p style="font-size:13px; color:var(--text-sub);">Remote Management Center • Edge PC Sync</p>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:16px;">
            <div class="edge-status-pill <?= $isOnline ? '' : 'offline' ?>">
                <span style="font-size:16px;"><?= $isOnline ? '🟢' : '🔴' ?></span>
                <span>Edge PC <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?> (Last sync: <?= htmlspecialchars($lastSyncText) ?>)</span>
            </div>

            <button class="btn btn-toggle" onclick="toggleTheme()">🌓 Theme</button>
            <a href="login.php?logout=1" class="btn btn-toggle" style="text-decoration:none; color:#ef4444;">Logout</a>
        </div>
    </header>

    <!-- Top KPI Grid -->
    <section class="kpi-row">
        <div class="kpi-card">
            <span style="font-size:13px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Live Occupancy</span>
            <span class="num" style="color:var(--amber);" id="statOccupied"><?= $occupied ?></span>
            <span style="font-size:13px; color:var(--text-sub);">out of <?= $capacity ?> total slots</span>
        </div>

        <div class="kpi-card">
            <span style="font-size:13px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Available Spaces</span>
            <span class="num" style="color:var(--green);" id="statAvailable"><?= $available ?></span>
            <span style="font-size:13px; color:var(--text-sub);">Free bays in basement</span>
        </div>

        <div class="kpi-card">
            <span style="font-size:13px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Today's Entries</span>
            <span class="num" style="color:var(--blue);"><?= $todayEntries ?></span>
            <span style="font-size:13px; color:var(--text-sub);">Vehicles admitted today</span>
        </div>

        <div class="kpi-card">
            <span style="font-size:13px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Today's Departures</span>
            <span class="num" style="color:var(--green);"><?= $todayExits ?></span>
            <span style="font-size:13px; color:var(--text-sub);">Cleared exits</span>
        </div>

        <div class="kpi-card">
            <span style="font-size:13px; font-weight:800; color:var(--text-sub); text-transform:uppercase;">Avg Dwell Time</span>
            <span class="num" style="color:#a855f7;"><?= $avgDwell ?>m</span>
            <span style="font-size:13px; color:var(--text-sub);">Customer shopping dwell</span>
        </div>
    </section>

    <!-- Visual Charts Grid -->
    <section class="charts-grid">
        <div class="chart-card">
            <h3 style="font-size:18px; margin-bottom:16px;">Mall Dwell Time by Destination Store</h3>
            <div style="height:280px;">
                <canvas id="chartCloudDest"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h3 style="font-size:18px; margin-bottom:16px;">Occupancy Slot Gauge</h3>
            <div style="text-align:center; padding:20px 0;">
                <div style="font-size:64px; font-weight:900; color:var(--green);" id="gaugeNum">
                    <?= round(($occupied / max(1, $capacity)) * 100) ?>%
                </div>
                <p style="color:var(--text-sub); font-size:16px; font-weight:700;">Basement Capacity Utilized</p>
                <div style="background:var(--border); height:16px; border-radius:10px; margin-top:20px; overflow:hidden;">
                    <div id="gaugeBar" style="background:var(--green); width:<?= round(($occupied / max(1, $capacity)) * 100) ?>%; height:100%;"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sessions Cloud Mirror Table -->
    <section class="table-card">
        <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:18px;">Recently Synced Parking Sessions</h3>
            <button class="btn btn-primary" onclick="alert('Exporting Cloud CSV...')">📥 Export Remote CSV</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Plate Number</th>
                    <th>Driver Name</th>
                    <th>Destination</th>
                    <th>Entry Time</th>
                    <th>Exit Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentSessions)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-sub);">No records synced yet. Run sync worker on Edge PC.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentSessions as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['ticket_id']) ?></strong></td>
                            <td style="font-weight:900; color:#fbbf24;"><?= htmlspecialchars($s['plate_number']) ?></td>
                            <td><?= htmlspecialchars($s['driver_name']) ?></td>
                            <td><?= htmlspecialchars($s['destination']) ?></td>
                            <td><?= htmlspecialchars($s['entry_time']) ?></td>
                            <td><?= htmlspecialchars($s['exit_time'] ?? 'Inside') ?></td>
                            <td><?= $s['duration_minutes'] ? htmlspecialchars((string)$s['duration_minutes']) . 'm' : '-' ?></td>
                            <td>
                                <span style="padding:4px 8px; border-radius:6px; font-size:12px; font-weight:800; background:<?= $s['status'] === 'ACTIVE' ? 'rgba(16, 185, 129, 0.2); color:#10b981;' : 'rgba(59, 130, 246, 0.2); color:#3b82f6;' ?>">
                                    <?= htmlspecialchars($s['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- Bundled Libraries -->
    <script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
    <script src="../assets/vendor/gsap/gsap.min.js"></script>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('mm_cloud_theme', next);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('mm_cloud_theme');
            if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

            // Chart.js Destination Breakdown
            const destCtx = document.getElementById('chartCloudDest');
            if (destCtx && window.Chart) {
                const labels = <?= json_encode(array_column($destCounts, 'destination')) ?>;
                const data = <?= json_encode(array_column($destCounts, 'cnt')) ?>;

                new Chart(destCtx, {
                    type: 'bar',
                    data: {
                        labels: labels.length ? labels : ['Naivas', 'Banking Hall', 'Food Court', 'Gym'],
                        datasets: [{
                            label: 'Customer Dwell Visits',
                            data: data.length ? data : [12, 8, 5, 4],
                            backgroundColor: '#3b82f6',
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // Animate gauge with GSAP
            if (window.gsap) {
                gsap.from('#gaugeBar', { width: 0, duration: 1.2, ease: "power2.out" });
            }
        });
    </script>
</body>
</html>
