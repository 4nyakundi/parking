<?php
/**
 * Mombasa Mall Basement Parking - Admin & Supervisor Management Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';

// Requires supervisor or admin session
$user = require_role(['supervisor', 'admin'], false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $user['role'] === 'supervisor' ? 'Supervisor Dashboard' : 'Admin Dashboard' ?> - Mombasa Mall Parking</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body data-user-role="<?= htmlspecialchars($user['role']) ?>">

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/img/logo-icon.png" alt="Mombasa Mall" class="sidebar-logo-img">
            <div class="sidebar-title">
                <h2>Mombasa Mall</h2>
                <p>Basement Parking <?= ucfirst($user['role']) ?></p>
            </div>
        </div>

        <div class="sidebar-wordmark">
            <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Operations</div>
            <a class="nav-link active" data-section="overview">
                <svg class="nav-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Overview
            </a>
            <a class="nav-link" data-section="sessions">
                <svg class="nav-icon" viewBox="0 0 24 24"><polyline points="5 12 19 12"/><polyline points="12 5 19 12 12 19"/></svg>
                All Sessions
            </a>
            <a class="nav-link" data-section="visitors">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Driver Requests
            </a>

            <div class="nav-section-label">Registry</div>
            <a class="nav-link" data-section="vehicles">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Vehicles Register
            </a>
            <a class="nav-link" data-section="users">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Users &amp; Guards
            </a>
            <a class="nav-link" data-section="destinations">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Shop Destinations
            </a>

            <div class="nav-section-label">Analysis</div>
            <a class="nav-link" data-section="reports">
                <svg class="nav-icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Reports &amp; Dwell
            </a>
            <a class="nav-link" data-section="audit">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                Audit Log
            </a>
            <a class="nav-link" data-section="reconcile">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                Reconcile Sessions
            </a>
            <a class="nav-link" data-section="cloud">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                Cloud Synchronization
            </a>

            <div class="nav-section-label">System</div>
            <a class="nav-link" data-section="settings">
                <svg class="nav-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                System Settings
            </a>
            <a class="nav-link" data-section="backups">
                <svg class="nav-icon" viewBox="0 0 24 24"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
                Database Backups
            </a>
            <a class="nav-link" data-section="health">
                <svg class="nav-icon" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Hardware &amp; Health
            </a>
            <a class="nav-link" data-section="dpa">
                <svg class="nav-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Data Protection
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-badge">
                <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                <span><?= strtoupper($user['role']) ?></span>
            </div>
            <a href="../api/auth/logout.php" class="btn-logout-sidebar" title="Logout">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <!-- Sidebar Backdrop for Mobile -->
    <div id="sidebarBackdrop" class="sidebar-backdrop" onclick="closeSidebarMobile()"></div>

    <!-- Main Content Stage -->
    <main class="main-content">
        <header class="top-nav">
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" id="btnSidebarToggle" class="sidebar-toggle-btn" onclick="toggleSidebarMobile()" aria-label="Toggle menu">&#9776;</button>
                <h2 id="topNavTitle" style="font-size:18px; font-weight:800;"><?= $user['role'] === 'supervisor' ? 'Supervisor Control Center' : 'Administrative Control Center' ?></h2>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <button type="button" class="btn-primary" onclick="navigateTo('sessions')" style="background:#f0f6fc; color:#116FC7; border:1px solid #b0cfec; padding:7px 12px; font-size:12px; font-weight:700;">
                    All Sessions
                </button>
                <button type="button" class="btn-primary" onclick="navigateTo('reconcile')" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:7px 12px; font-size:12px; font-weight:700;">
                    Shift Reconcile
                </button>
                <a href="../cloud/" target="_blank" class="btn-primary" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; text-decoration:none; padding:7px 12px; font-size:12px; font-weight:700;">
                    Cloud Mirror &#x2197;
                </a>
                <a href="../guard/" target="_blank" class="btn-primary btn-success" style="text-decoration:none; padding:7px 12px; font-size:12px;">
                    <span class="btn-nav-full">Guard Station &#x2197;</span>
                    <span class="btn-nav-short">Guard</span>
                </a>
            </div>
        </header>

        <!-- ============================================================== -->
        <!-- 1. OVERVIEW SECTION -->
        <!-- ============================================================== -->
        <section id="sec-overview" class="section-content active">
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Basement Capacity</div>
                    <div class="kpi-val" id="ovTotal">60</div>
                    <div class="kpi-sub">Total parking slots</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Currently Occupied</div>
                    <div class="kpi-val" id="ovOccupied" style="color:#f59e0b;">--</div>
                    <div class="kpi-sub">Vehicles parked inside</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Available Slots</div>
                    <div class="kpi-val" id="ovAvailable" style="color:#10b981;">--</div>
                    <div class="kpi-sub">Ready for incoming cars</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Today's Entries</div>
                    <div class="kpi-val" id="ovEntries">--</div>
                    <div class="kpi-sub">Cars entered since midnight</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Today's Exits</div>
                    <div class="kpi-val" id="ovExits">--</div>
                    <div class="kpi-sub">Cars safely departed</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Active Overstays</div>
                    <div class="kpi-val" id="ovOverstay" style="color:#ef4444;">--</div>
                    <div class="kpi-sub">Parked > 8 hours</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Average Dwell Time</div>
                    <div class="kpi-val" id="ovAvgStay" style="color:#38bdf8;">--</div>
                    <div class="kpi-sub">Average stay duration today</div>
                </div>
            </div>

            <div class="table-card" style="padding:24px;">
                <h3 style="font-size:18px; margin-bottom:16px;">Today's Hourly Traffic Distribution</h3>
                <div style="height:320px;">
                    <canvas id="chartHourlyTraffic"></canvas>
                </div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 2. ALL SESSIONS SECTION -->
        <!-- ============================================================== -->
        <section id="sec-sessions" class="section-content">
            <div class="filter-pills-row" id="sessionsFilterPills">
                <button type="button" class="filter-pill-btn active" data-sfilter="all" onclick="setSessionQuickFilter('all')">All Records</button>
                <button type="button" class="filter-pill-btn" data-sfilter="active" onclick="setSessionQuickFilter('active')">Inside Now (Active)</button>
                <button type="button" class="filter-pill-btn" data-sfilter="completed" onclick="setSessionQuickFilter('completed')">Completed (Exited)</button>
                <button type="button" class="filter-pill-btn" data-sfilter="today" onclick="setSessionQuickFilter('today')">Today's Traffic</button>
            </div>
            <div class="filter-bar">
                <input type="text" id="filterPlate" class="filter-input" placeholder="Plate number...">
                <input type="text" id="filterTicket" class="filter-input" placeholder="Ticket ID...">
                <select id="filterStatus" class="filter-input">
                    <option value="">All Statuses</option>
                    <option value="ACTIVE">ACTIVE (Inside)</option>
                    <option value="COMPLETED">COMPLETED (Exited)</option>
                </select>
                <input type="date" id="filterDateFrom" class="filter-input">
                <input type="date" id="filterDateTo" class="filter-input">
                <button class="btn-primary" onclick="loadSessions(1)">
                    <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Filter
                </button>
                <button class="btn-primary btn-success" onclick="exportSessionsCsv()">
                    <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Export CSV
                </button>
            </div>

            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Plate</th>
                            <th>Driver</th>
                            <th>Destination</th>
                            <th>Entry Time</th>
                            <th>Exit Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="sessionsTableBody"></tbody>
                </table>
                <div class="table-header-bar" id="sessionsPagination"></div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 3. VISITORS HISTORY -->
        <!-- ============================================================== -->
        <section id="sec-visitors" class="section-content">
            <div class="table-card">
                <div class="table-header-bar">
                    <h3>Driver Self Sign-In Requests</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Plate</th>
                            <th>Driver</th>
                            <th>Phone</th>
                            <th>Destination</th>
                            <th>Timestamp</th>
                            <th>Status</th>
                            <th>Handled By</th>
                        </tr>
                    </thead>
                    <tbody id="visitorsTableBody"></tbody>
                </table>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 4. VEHICLES REGISTER -->
        <!-- ============================================================== -->
        <section id="sec-vehicles" class="section-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <p style="color:var(--text-sub);">Manage VIPs, Mall Staff, Tenants, and Blacklisted security vehicles.</p>
                <button class="btn-primary btn-success" onclick="openVehicleModal()">
                    <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Register Vehicle
                </button>
            </div>

            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Plate</th>
                            <th>Owner / Tenant</th>
                            <th>Phone</th>
                            <th>Category</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vehiclesTableBody"></tbody>
                </table>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 5. USERS & ROLES -->
        <!-- ============================================================== -->
        <section id="sec-users" class="section-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <p style="color:var(--text-sub);">Create and manage security guards (PIN login) and management users.</p>
                <?php if ($user['role'] === 'admin'): ?>
                <button class="btn-primary btn-success" onclick="openUserModal()">
                    <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Security User
                </button>
                <?php else: ?>
                <span class="badge" style="background:#eef5fc; color:#2e5c8a; border:1px solid #b0cfec; padding:6px 12px; font-weight:700;">Supervisor View Only</span>
                <?php endif; ?>
            </div>

            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody"></tbody>
                </table>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 6. DESTINATIONS -->
        <!-- ============================================================== -->
        <section id="sec-destinations" class="section-content">
            <div class="table-card">
                <div class="table-header-bar">
                    <h3>Mall Destination Store Buttons</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Unit / Store</th>
                            <th>Floor Level</th>
                            <th>Category</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="destinationsTableBody"></tbody>
                </table>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 7. REPORTS SECTION -->
        <!-- ============================================================== -->
        <section id="sec-reports" class="section-content">
            <div class="filter-bar">
                <label>Date Range:</label>
                <input type="date" id="repDateFrom" class="filter-input">
                <input type="date" id="repDateTo" class="filter-input">
                <button class="btn-primary" onclick="loadReports()">Generate Report</button>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card"><div class="kpi-title">Total Entries</div><div class="kpi-val" id="repTotalIn">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Total Exits</div><div class="kpi-val" id="repTotalOut">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Avg Dwell Time</div><div class="kpi-val" id="repAvgStay" style="color:#38bdf8;">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Peak Dwell Hour</div><div class="kpi-val" id="repPeak" style="font-size:22px; color:#f59e0b;">--</div></div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
                <div class="table-card" style="padding:20px;">
                    <h3 style="margin-bottom:16px;">Destination Dwell Breakdown</h3>
                    <div style="max-height:260px;">
                        <canvas id="chartDestinations"></canvas>
                    </div>
                </div>

                <div class="table-card">
                    <div class="table-header-bar"><h3>Guard Shift Handover Summary</h3></div>
                    <table class="data-table">
                        <thead><tr><th>Guard Officer</th><th>Entries</th><th>Exits</th><th>Declined</th></tr></thead>
                        <tbody id="shiftTableBody"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 8. AUDIT LOG -->
        <!-- ============================================================== -->
        <section id="sec-audit" class="section-content">
            <div class="table-card">
                <div class="table-header-bar">
                    <h3>Permanent Immutable Audit Trail</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Operator</th>
                            <th>Action</th>
                            <th>Target Entity</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="auditTableBody"></tbody>
                </table>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 9. RECONCILE (DRIFT FIXER) -->
        <!-- ============================================================== -->
                <!-- ============================================================== -->
        <!-- 9. RECONCILE (DRIFT FIXER & ZERO-DRIFT DECK AUDITOR) -->
        <!-- ============================================================== -->
        <section id="sec-reconcile" class="section-content">
            <!-- Supervisor KPI Telemetry Strip -->
            <div class="kpi-grid" style="margin-bottom:18px;">
                <div class="kpi-card accent-blue">
                    <div class="kpi-title">System Active Count</div>
                    <div class="kpi-val" id="recSystemActive">--</div>
                    <div class="kpi-sub">Vehicles currently logged inside</div>
                </div>
                <div class="kpi-card accent-amber">
                    <div class="kpi-title">Extended Dwell (&gt;4h)</div>
                    <div class="kpi-val" id="recExtended4h" style="color:#d97706;">--</div>
                    <div class="kpi-sub">Longer than mall average stay</div>
                </div>
                <div class="kpi-card accent-red">
                    <div class="kpi-title">Overstay Alert (&gt;8h)</div>
                    <div class="kpi-val" id="recOverstay8h" style="color:#dc2626;">--</div>
                    <div class="kpi-sub">Prime suspects for missed exit scan</div>
                </div>
                <div class="kpi-card accent-green">
                    <div class="kpi-title">Supervisor Overrides Today</div>
                    <div class="kpi-val" id="recOverridesToday" style="color:#059669;">--</div>
                    <div class="kpi-sub">Exit gate barrier manual clearances</div>
                </div>
            </div>

            <!-- Zero-Drift Physical Head-Count vs System Active Delta Calculator -->
            <div class="delta-calculator-card">
                <div class="delta-calc-left">
                    <h4>
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="var(--blue)" fill="none" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Zero-Drift Physical Count Auditor
                    </h4>
                    <p>Enter the actual number of vehicles counted during a physical sweep of the basement parking bays. The system calculates occupancy variance and identifies orphaned ghost sessions.</p>
                </div>
                <div class="delta-calc-controls">
                    <div class="delta-input-wrap">
                        <span>Physical Sweep</span>
                        <input type="number" id="deckPhysicalCount" class="delta-input" placeholder="0" min="0" max="60" oninput="calculateDeckDelta()">
                    </div>
                    <button type="button" class="btn-primary" onclick="calculateDeckDelta()" style="height:38px;">Verify Delta</button>
                    <div id="deckDeltaBadge" class="delta-badge balanced" style="display:none;">
                        <span>Balanced (0 Delta)</span>
                    </div>
                </div>
            </div>

            <!-- Table Card with Multi-Threshold Tabs -->
            <div class="table-card">
                <div class="table-header-bar" style="flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3>Basement Active Sessions &amp; Discrepancy Resolution</h3>
                        <p style="font-size:13px; color:var(--text-sub);">Select a duration threshold to inspect active vehicles and force-close ghost records with audit reasons.</p>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <button type="button" class="btn-primary btn-warning" onclick="loadReconcileList(STATE_ADMIN.reconcileThreshold || 8)">
                            <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>Scan Now
                        </button>
                    </div>
                </div>

                <!-- Threshold Filter Pills -->
                <div class="filter-pills-row" style="margin-bottom:14px;">
                    <button type="button" class="filter-pill-btn active" data-rthresh="8" onclick="setReconcileThreshold(8, this)">Overstay &gt; 8h</button>
                    <button type="button" class="filter-pill-btn" data-rthresh="4" onclick="setReconcileThreshold(4, this)">Extended &gt; 4h</button>
                    <button type="button" class="filter-pill-btn" data-rthresh="12" onclick="setReconcileThreshold(12, this)">Overnight &gt; 12h</button>
                    <button type="button" class="filter-pill-btn" data-rthresh="0" onclick="setReconcileThreshold(0, this)">All Active Inside (Deck Sweep)</button>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vehicle Plate</th>
                            <th>Ticket ID</th>
                            <th>Driver &amp; Destination</th>
                            <th>Entry Time</th>
                            <th>Dwell Duration</th>
                            <th>Status Urgency</th>
                            <th>Reconcile Action</th>
                        </tr>
                    </thead>
                    <tbody id="reconcileTableBody">
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">Scanning sessions...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Shift Handover Sign-Off Card -->
            <div class="shift-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h4 style="font-size:15px; font-weight:800; color:var(--text-main); margin-bottom:4px;">Supervisor Shift Handover &amp; Sign-Off</h4>
                        <p style="font-size:12.5px; color:var(--text-sub);">Formal sign-off stamps opening/closing occupancy, physical delta, and override records into the immutable audit trail.</p>
                    </div>
                    <button type="button" class="btn-primary btn-success" onclick="openShiftSignoffModal()">
                        <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                        Sign Off Shift
                    </button>
                </div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 9B. CLOUD SYNCHRONIZATION & MIRROR -->
        <!-- ============================================================== -->
        <section id="sec-cloud" class="section-content">
            <div class="kpi-grid">
                <div class="kpi-card accent-green">
                    <div class="kpi-title">Cloud Mirror Status</div>
                    <div class="kpi-val" id="cloudConnStatus" style="color:#059669;">ONLINE</div>
                    <div class="kpi-sub" id="cloudConnSub">HMAC-SHA256 Encrypted</div>
                </div>
                <div class="kpi-card accent-amber">
                    <div class="kpi-title">Pending Sync Queue</div>
                    <div class="kpi-val" id="cloudPendingCount" style="color:#d97706;">0</div>
                    <div class="kpi-sub">Local records awaiting mirror</div>
                </div>
                <div class="kpi-card accent-blue">
                    <div class="kpi-title">Last Cloud Sync</div>
                    <div class="kpi-val" id="cloudLastSync" style="font-size:18px;">Just now</div>
                    <div class="kpi-sub">Background cycle runs every 60s</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Remote Portal</div>
                    <div class="kpi-val" style="font-size:15px; margin-top:6px;">
                        <a href="../cloud/" target="_blank" class="btn-primary" style="display:inline-block; padding:6px 14px; text-decoration:none; font-size:12px;">Open Cloud Portal &#x2197;</a>
                    </div>
                    <div class="kpi-sub">Executive remote dashboard</div>
                </div>
            </div>

            <!-- Cloud Actions Bar -->
            <div class="table-card" style="margin-top:20px; padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="margin-bottom:4px;">Edge PC Synchronization Control</h3>
                        <p style="font-size:13px; color:var(--text-sub);">Push local basement parking records to the remote executive cloud repository with cryptographic integrity verification.</p>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn-primary btn-success" id="btnTriggerCloudSync" onclick="triggerManualCloudSync()">
                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5" style="vertical-align:-2px; margin-right:6px;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                            Trigger Sync Now
                        </button>
                    </div>
                </div>

                <!-- Sync Console Terminal Output -->
                <div id="cloudSyncConsole" class="cloud-console-box" style="display:none; margin-top:16px;">[Console initialized...]</div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 10. SYSTEM SETTINGS -->
        <!-- ============================================================== -->
        <section id="sec-settings" class="section-content">
            <div class="table-card" style="padding:24px; max-width:800px;">
                <h3 style="margin-bottom:20px;">System & Hardware Settings</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">Basement Slot Capacity</label>
                        <input type="number" id="setCapacity" class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">Overstay Warning Threshold (Hours)</label>
                        <input type="number" id="setOverstay" class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">Mall Display Name</label>
                        <input type="text" id="setMallName" class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">Thermal Printer Share Name</label>
                        <input type="text" id="setPrinterName" class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">Receipt Paper Width (mm)</label>
                        <select id="setPrinterWidth" class="filter-input" style="width:100%;">
                            <option value="80">80 mm Standard</option>
                            <option value="58">58 mm Compact</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">ALPR Confidence Threshold</label>
                        <input type="text" id="setAlprConf" class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">ALPR Debounce Window (Seconds)</label>
                        <input type="number" id="setAlprDebounce" class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:700;">Cloud Sync Ingest Endpoint</label>
                        <input type="text" id="setCloudUrl" class="filter-input" style="width:100%;">
                    </div>
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                <button class="btn-primary btn-success" style="margin-top:24px;" onclick="saveSettings()">Save All Settings</button>
                <?php else: ?>
                <div style="margin-top:20px; padding:12px 16px; background:#eef5fc; border:1px solid #b0cfec; border-radius:6px; color:#2e5c8a; font-size:13px; font-weight:600;">
                    Viewing system configuration in Supervisor Mode. Modification of hardware parameters and core settings requires Administrator privileges.
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 11. DATABASE BACKUPS -->
        <!-- ============================================================== -->
        <section id="sec-backups" class="section-content">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <p style="color:var(--text-sub);">Permanent automated backups. Every backup is preserved permanently with zero auto-deletion.</p>
                <button id="btnBackupNow" class="btn-primary btn-success" onclick="triggerBackupNow()">
                    <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>Backup Database Now
                </button>
            </div>

            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Backup File</th>
                            <th>Size</th>
                            <th>Created Timestamp</th>
                            <th>Initiator</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="backupsTableBody"></tbody>
                </table>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 12. HARDWARE HEALTH & ALPR SIMULATION -->
        <!-- ============================================================== -->
        <section id="sec-health" class="section-content">
            <div class="kpi-grid">
                <div class="kpi-card"><div class="kpi-title">Database</div><div class="kpi-sub" id="healthDb">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Thermal Printer</div><div class="kpi-sub" id="healthPrinter">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Entrance Camera</div><div class="kpi-sub" id="healthCamIn">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Exit Camera</div><div class="kpi-sub" id="healthCamOut">--</div></div>
                <div class="kpi-card"><div class="kpi-title">ALPR Worker</div><div class="kpi-sub" id="healthWorker">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Internet / LAN</div><div class="kpi-sub" id="healthInternet">--</div></div>
                <div class="kpi-card"><div class="kpi-title">Cloud Sync</div><div class="kpi-sub" id="healthCloud">--</div></div>
            </div>

            <div class="table-card" style="padding:24px; max-width:650px;">
                <h3 style="margin-bottom:12px;">Simulate ALPR Camera Detection (Testing Mode)</h3>
                <p style="color:#94a3b8; font-size:14px; margin-bottom:16px;">Test the full pipeline without physical CCTV streams.</p>
                <div style="display:flex; gap:12px;">
                    <select id="simCam" class="filter-input">
                        <option value="entrance">Entrance Camera</option>
                        <option value="exit">Exit Camera</option>
                    </select>
                    <input type="text" id="simPlate" class="filter-input" placeholder="e.g. KDA 456C" value="KDA 456C">
                    <button class="btn-primary" onclick="simulateAlprDetection()">Trigger Event</button>
                </div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- 13. DATA PROTECTION (KENYA DPA 2019) -->
        <!-- ============================================================== -->
        <section id="sec-dpa" class="section-content">
            <div class="table-card" style="padding:24px;">
                <h3 style="margin-bottom:12px;">Kenya Data Protection Act 2019 - Data Subject Lookup</h3>
                <p style="color:#94a3b8; font-size:14px; margin-bottom:16px;">Search by phone number or license plate to export all recorded interactions upon citizen request.</p>
                <div style="display:flex; gap:12px; margin-bottom:20px;">
                    <input type="text" id="dpaSearchQuery" class="filter-input" placeholder="Enter 07XXXXXXXX or KDA 123A..." style="width:360px;">
                    <button class="btn-primary" onclick="searchDpaData()">Search & Export</button>
                </div>
                <pre id="dpaResultsPre" style="background:#090e17; padding:20px; border-radius:8px; border:1px solid #22304f; color:#38bdf8; max-height:400px; overflow:auto;"></pre>
            </div>
        </section>

    </main>

    <!-- Modal: Session Detail & Notes -->
    <div id="sessionDetailModal" class="admin-modal-overlay">
        <div class="admin-modal">
            <div class="modal-header">
                <h3>Session Details</h3>
                <button onclick="closeAdminModal('sessionDetailModal')" style="background:none; border:none; color:#cbd5e1; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; font-size:14px;">
                <div><span style="color:#94a3b8;">Ticket:</span> <strong id="detTicketId"></strong></div>
                <div><span style="color:#94a3b8;">Plate:</span> <strong id="detPlate" style="color:#fbbf24;"></strong></div>
                <div><span style="color:#94a3b8;">Driver:</span> <span id="detDriver"></span></div>
                <div><span style="color:#94a3b8;">Destination:</span> <span id="detDest"></span></div>
                <div><span style="color:#94a3b8;">Entry Time:</span> <span id="detEntry"></span></div>
                <div><span style="color:#94a3b8;">Exit Time:</span> <span id="detExit"></span></div>
                <div><span style="color:#94a3b8;">Status:</span> <span id="detStatus"></span></div>
                <div><span style="color:#94a3b8;">Approved By:</span> <span id="detApprover"></span></div>
            </div>

            <h4 style="margin:16px 0 8px 0; color:#38bdf8;">Session Audit Trail</h4>
            <ul id="detAuditTrail" style="list-style:none; font-size:13px; color:#cbd5e1; background:#090e17; padding:12px; border-radius:8px; max-height:160px; overflow-y:auto;"></ul>

            <h4 style="margin:16px 0 8px 0;">Supervisor Notes</h4>
            <textarea id="detNotes" style="width:100%; height:80px; background:#090e17; border:1px solid #334155; border-radius:8px; color:#fff; padding:10px;"></textarea>
            <div style="margin-top:14px; text-align:right;">
                <button class="btn-primary" id="btnSaveNotes" onclick="saveSessionNotes()">Save Notes</button>
            </div>
        </div>
    </div>

    <!-- Modal: Register / Edit Vehicle -->
    <div id="vehicleModal" class="admin-modal-overlay">
        <div class="admin-modal">
            <div class="modal-header">
                <h3>Register Vehicle</h3>
                <button onclick="closeAdminModal('vehicleModal')" style="background:none; border:none; color:#cbd5e1; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <input type="hidden" id="vehId" value="0">
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div><label>License Plate</label><input type="text" id="vehPlate" class="filter-input" style="width:100%; text-transform:uppercase;"></div>
                <div><label>Owner / Tenant Name</label><input type="text" id="vehOwner" class="filter-input" style="width:100%;"></div>
                <div><label>Phone Number</label><input type="tel" id="vehPhone" class="filter-input" style="width:100%;"></div>
                <div>
                    <label>Vehicle Type</label>
                    <select id="vehType" class="filter-input" style="width:100%;">
                        <option value="car">Car / Sedan / SUV</option>
                        <option value="motorcycle">Motorcycle / Boda</option>
                        <option value="van">Van / Matatu</option>
                        <option value="truck">Truck / Lorry</option>
                        <option value="government">Government (GK/CG)</option>
                    </select>
                </div>
                <div>
                    <label>Category</label>
                    <select id="vehCategory" class="filter-input" style="width:100%;">
                        <option value="regular">Regular</option>
                        <option value="vip">VIP Guest (Priority Slot)</option>
                        <option value="staff">Mall Staff</option>
                        <option value="tenant">Store Tenant</option>
                        <option value="blacklisted">BLACKLISTED (Do Not Admit)</option>
                    </select>
                </div>
                <div><label>Notes / Alerts</label><textarea id="vehNotes" class="filter-input" style="width:100%; height:70px;"></textarea></div>
                <button class="btn-primary btn-success" onclick="saveVehicle()">Save Vehicle</button>
            </div>
        </div>
    </div>

    <!-- Modal: Add / Edit User -->
    <div id="userModal" class="admin-modal-overlay">
        <div class="admin-modal">
            <div class="modal-header">
                <h3>Security User Account</h3>
                <button onclick="closeAdminModal('userModal')" style="background:none; border:none; color:#cbd5e1; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <input type="hidden" id="userId" value="0">
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div><label>Full Name</label><input type="text" id="userFullName" class="filter-input" style="width:100%;"></div>
                <div><label>Username (Supervisors/Admins only)</label><input type="text" id="userUsername" class="filter-input" style="width:100%;"></div>
                <div>
                    <label>Role</label>
                    <select id="userRole" class="filter-input" style="width:100%;">
                        <option value="guard">Security Guard (PIN Keypad)</option>
                        <option value="supervisor">Security Supervisor</option>
                        <option value="admin">System Administrator</option>
                    </select>
                </div>
                <div><label>Phone Number</label><input type="tel" id="userPhone" class="filter-input" style="width:100%;"></div>
                <div><label>4-Digit Guard PIN (For tablet keypad login)</label><input type="password" id="userPin" class="filter-input" style="width:100%;" placeholder="e.g. 1234"></div>
                <div><label>Password (For supervisors & admins)</label><input type="password" id="userPassword" class="filter-input" style="width:100%;" placeholder="Leave blank to keep current"></div>
                <button class="btn-primary btn-success" onclick="saveUser()">Save User Account</button>
            </div>
        </div>
    </div>

        <!-- Modal: Reconcile Justification -->
    <div id="reconcileJustificationModal" class="admin-modal-overlay">
        <div class="admin-modal" style="max-width:520px;">
            <div class="modal-header">
                <h3 style="color:#d97706;">Reconcile &amp; Clear Session</h3>
                <button onclick="closeAdminModal('reconcileJustificationModal')" style="background:none; border:none; color:#cbd5e1; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <p style="font-size:13px; color:var(--text-sub); line-height:1.5; margin-bottom:14px;">
                Force closing session for vehicle <strong id="recPlateNumber" style="font-family:monospace; background:#eef5fc; padding:2px 6px; border-radius:4px; border:1px solid #b0cfec;">KDA 123A</strong> (Ticket: <span id="recTicketId" style="color:var(--blue); font-weight:700;">MM-001</span>). This clears the vehicle from active occupancy.
            </p>
            <input type="hidden" id="recSessionId" value="0">
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:10.5px; font-weight:800; text-transform:uppercase; color:var(--text-sub); margin-bottom:6px;">Select Verification Justification</label>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <button type="button" class="rec-preset-btn" onclick="selectRecPreset(this)">Tailgated through exit barrier behind another vehicle</button>
                    <button type="button" class="rec-preset-btn" onclick="selectRecPreset(this)">Physical deck sweep confirmed parking bay is vacant</button>
                    <button type="button" class="rec-preset-btn" onclick="selectRecPreset(this)">CCTV audit confirmed vehicle exited without ticket scan</button>
                    <button type="button" class="rec-preset-btn" onclick="selectRecPreset(this)">Customer lost ticket and departed via security override</button>
                </div>
            </div>
            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:10.5px; font-weight:800; text-transform:uppercase; color:var(--text-sub); margin-bottom:6px;">Custom Audit Justification</label>
                <input type="text" id="recReasonInput" class="filter-input" style="width:100%;" placeholder="Enter reason or choose preset above...">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-primary" style="background:#fff; color:var(--text-sub); border:1px solid var(--border);" onclick="closeAdminModal('reconcileJustificationModal')">Cancel</button>
                <button type="button" class="btn-primary btn-warning" id="btnConfirmReconcile" onclick="submitReconcileForceClose()">Confirm Reconciliation</button>
            </div>
        </div>
    </div>

    <!-- Modal: Supervisor Shift Sign-Off -->
    <div id="shiftSignoffModal" class="admin-modal-overlay">
        <div class="admin-modal" style="max-width:480px;">
            <div class="modal-header">
                <h3 style="color:#059669;">Complete Shift Sign-Off</h3>
                <button onclick="closeAdminModal('shiftSignoffModal')" style="background:none; border:none; color:#cbd5e1; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <p style="font-size:13px; color:var(--text-sub); line-height:1.5; margin-bottom:14px;">
                Sign off on your shift deck count. This permanently locks the audit timestamp and hands over occupancy records to the incoming supervisor.
            </p>
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:18px;">
                <div>
                    <label style="display:block; font-size:10.5px; font-weight:800; text-transform:uppercase; margin-bottom:4px;">Physical Vehicle Headcount</label>
                    <input type="number" id="signoffPhysicalCount" class="filter-input" style="width:100%; font-size:16px; font-weight:700;" placeholder="Counted vehicles in bays">
                </div>
                <div>
                    <label style="display:block; font-size:10.5px; font-weight:800; text-transform:uppercase; margin-bottom:4px;">Shift Handover Notes</label>
                    <textarea id="signoffNotes" class="filter-input" style="width:100%; height:75px;" placeholder="e.g. All bays swept, Gate 1 camera lens cleaned, 0 pending tickets."></textarea>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-primary" style="background:#fff; color:var(--text-sub); border:1px solid var(--border);" onclick="closeAdminModal('shiftSignoffModal')">Cancel</button>
                <button type="button" class="btn-primary btn-success" id="btnConfirmSignoff" onclick="submitShiftSignoff()">Complete &amp; Record Sign-Off</button>
            </div>
        </div>
    </div>
    <!-- Bundled Libraries (No CDN!) -->
    <script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
    <script src="../assets/vendor/gsap/gsap.min.js"></script>
    
    <!-- Admin Controller -->
    <script src="js/admin.js"></script>
    <script>
        function toggleSidebarMobile() {
            const sb = document.querySelector('.sidebar');
            const bd = document.getElementById('sidebarBackdrop');
            if (sb) sb.classList.toggle('open');
            if (bd) bd.classList.toggle('active');
        }
        function closeSidebarMobile() {
            const sb = document.querySelector('.sidebar');
            const bd = document.getElementById('sidebarBackdrop');
            if (sb) sb.classList.remove('open');
            if (bd) bd.classList.remove('active');
        }
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
            link.addEventListener('click', closeSidebarMobile);
        });
    </script>
    <script type="module" src="../assets/js/firebase-init.js"></script>
</body>
</html>
