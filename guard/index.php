<?php
/**
 * Mombasa Mall Basement Parking — Guard Tablet Station
 * Industrial Control System UI v3.0
 * Left-panel command sidebar + right content area. No emojis.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guard Station — Mombasa Mall Basement Parking</title>
    <link rel="stylesheet" href="css/guard.css">
    <link rel="stylesheet" href="css/guard-dashboard.css?v=20261103d">
</head>
<body>

<!-- Offline Banner -->
<div id="offlineBanner" class="offline-banner">
    Local server unavailable — showing last known data. Reconnect to record arrivals and exits.
</div>

<!-- ================================================================
     TOP NAVIGATION BAR
     ================================================================ -->
<header class="top-bar">
    <div class="brand-section">
        <img src="../assets/img/logo-icon.png" alt="Mombasa Mall" class="brand-logo-img">
        <div class="brand-divider"></div>
        <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall" class="brand-wordmark-img">
        <div class="brand-divider"></div>
        <div class="brand-text">
            <h1>Basement Parking</h1>
            <p>Guard Control Station &mdash; 60 Slots</p>
        </div>
    </div>

    <!-- System Health -->
    <div class="health-status-bar">
        <div class="status-item">
            <span id="dotNet" class="status-dot unknown"></span>
            <span id="txtNet">LAN</span>
        </div>
        <div class="status-item">
            <span id="dotPrinter" class="status-dot unknown"></span>
            <span id="txtPrinter">Printer</span>
        </div>
        <div class="status-item">
            <span id="dotCamIn" class="status-dot unknown"></span>
            <span id="txtCamIn">Entry Cam</span>
        </div>
        <div class="status-item">
            <span id="dotCamOut" class="status-dot unknown"></span>
            <span id="txtCamOut">Exit Cam</span>
        </div>
        <div class="status-item">
            <span id="dotCloud" class="status-dot unknown"></span>
            <span id="txtCloud">Cloud Sync</span>
        </div>
    </div>

    <!-- Right Controls -->
    <div class="user-clock-section">
        <div id="liveClock" class="live-clock">--:--:--</div>

        <div class="guard-pill">
            <svg class="i-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span id="guardNameDisplay">Gate Officer</span>
            <span class="guard-role-tag" id="guardRoleTag">Guard</span>
        </div>

        <button id="btnSoundToggle" class="icon-btn" title="Toggle Sound">
            <svg class="i-icon" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
            <span class="btn-lbl">Sound</span>
        </button>
        <button id="btnLangToggle" class="icon-btn" title="Toggle Language">
            <svg class="i-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <span class="btn-lbl">SW</span>
        </button>
        <button class="icon-btn" onclick="openHelpModal()" title="Help Manual">
            <svg class="i-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span class="btn-lbl">Help</span>
        </button>
        <button type="button" class="icon-btn danger" onclick="openLogoutConfirmModal()" title="Sign Out of Guard Terminal">
            <svg class="i-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="btn-lbl">Logout</span>
        </button>
    </div>
</header>
<!-- ================================================================
     OCCUPANCY DASHBOARD STRIP
     ================================================================ -->
<section class="occupancy-dashboard" id="occupancyOverview">
    <div class="metric-card total">
        <div class="metric-label">Total Capacity</div>
        <div class="metric-number" id="statTotal">60</div>
    </div>
    <div class="metric-card occupied">
        <div class="metric-label">Occupied</div>
        <div class="metric-number" id="statOccupied">--</div>
    </div>
    <div class="metric-card available" id="cardAvailable">
        <div class="metric-label">Available</div>
        <div class="metric-number" id="statAvailable">--</div>
        <div id="parkingFullBadge" class="full-parking-badge">CAPACITY FULL</div>
    </div>
    <div class="occupancy-actions">
        <button id="btnOpenManualAdd" class="btn-manual-add">
            <svg class="i-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Register Vehicle
        </button>
    </div>
    <div class="daily-overview" aria-label="Today's recorded vehicle movement">
        <div class="daily-overview-heading">
            <span>Today's Overview <span id="todayOverviewDate"></span></span>
            <span id="todayOverviewFreshness">Waiting for live totals</span>
        </div>
        <div class="daily-overview-cards">
            <div class="metric-card total">
                <div class="metric-label">Recorded Arrivals Today</div>
                <div class="metric-number" id="statTodayArrivals">--</div>
                <div class="daily-metric-note">Registered + unregistered</div>
            </div>
            <div class="metric-card occupied">
                <div class="metric-label">Registered Today</div>
                <div class="metric-number" id="statTodayRegistered">--</div>
                <div class="daily-metric-note">Visits with captured details</div>
            </div>
            <div class="metric-card unregistered">
                <div class="metric-label">Unregistered Today</div>
                <div class="metric-number" id="statTodayUnregistered">--</div>
                <div class="daily-metric-note" id="todayUnregisteredBreakdown">Awaiting registration or left without details</div>
            </div>
            <div class="metric-card available">
                <div class="metric-label">Exited Today</div>
                <div class="metric-number" id="statTodayExited">--</div>
                <div class="daily-metric-note">All recorded departures</div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================
     MOBILE / TABLET VIEW SWITCHER TABS (Hidden on Desktop)
     ================================================================ -->
<nav class="mode-tabs-container">
    <button class="mode-tab inside active" data-tab="inside" onclick="switchMainTab('inside')">
        <svg class="i-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        <span>Basement Roster</span>
        <span id="rosterCountBadge" class="tab-badge inside-badge">0</span>
    </button>
    <button class="mode-tab entering" data-tab="entering" onclick="switchMainTab('entering')">
        <svg class="i-icon" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span>Gate Camera Queue</span>
        <span id="pendingCountBadge" class="tab-badge" style="display:none;">0</span>
    </button>
    <button class="mode-tab leaving" data-tab="leaving" onclick="switchMainTab('leaving')">
        <svg class="i-icon" viewBox="0 0 24 24"><polyline points="19 12 5 12"/><polyline points="12 19 5 12 12 5"/></svg>
        <span>Vehicles Exiting</span>
    </button>
</nav>

<!-- ================================================================
     MAIN WORKSPACE LAYOUT: 3-COLUMN UNIFIED COMMAND CENTER
     Left Panel (Stats & Actions) | Center (Basement Roster) | Right (Camera Queue)
     ================================================================ -->
<div class="guard-main-layout" id="guardMainLayout">

    <!-- 1. LEFT COMMAND PANEL -->
    <aside class="left-panel">

        <!-- Station Brand Block -->
        <div class="panel-brand-header">
            <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall" class="panel-brand-logo">
            <div class="panel-station-tag">Guard Control Station</div>
        </div>

        <!-- Live Session Stats (2x2 Matrix) -->
        <div class="panel-section">
            <div class="panel-section-title">Live Session Stats</div>
            <div class="panel-stats-grid">
                <div class="panel-stat-card in">
                    <span class="panel-stat-label">In Today</span>
                    <span class="panel-stat-value blue" id="pnlTodayIn">--</span>
                </div>
                <div class="panel-stat-card out">
                    <span class="panel-stat-label">Out Today</span>
                    <span class="panel-stat-value green" id="pnlTodayOut">--</span>
                </div>
                <div class="panel-stat-card overstay">
                    <span class="panel-stat-label">Overstay</span>
                    <span class="panel-stat-value red" id="pnlOverstay">0</span>
                </div>
                <div class="panel-stat-card dwell">
                    <span class="panel-stat-label">Avg Stay</span>
                    <span class="panel-stat-value" id="pnlAvgDwell">--</span>
                </div>
            </div>
            <div class="panel-queue-strip">
                <span class="panel-queue-label">Gate Intake Queue:</span>
                <span class="panel-queue-pill" id="pnlPending">0</span>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="panel-section">
            <div class="panel-section-title">Quick Actions</div>
            <div class="panel-actions-list">
                <button class="panel-quick-btn amber-action" id="btnOpenManualAdd2">
                    <svg class="i-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Register Vehicle</span>
                </button>
                <button class="panel-quick-btn" onclick="switchCenterView('exit'); document.getElementById('exitSearchInput')?.focus();">
                    <svg class="i-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span>Find Exiting Car</span>
                </button>
                <button class="panel-quick-btn" onclick="switchCenterView('roster'); loadActiveSessions(true);">
                    <svg class="i-icon" viewBox="0 0 24 24"><path d="M23 4v6h-6M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    <span>Refresh Roster</span>
                </button>
                <button class="panel-quick-btn" onclick="window.open('../admin/', '_blank')">
                    <svg class="i-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Admin Portal</span>
                </button>
            </div>
        </div>

        <!-- Connection details stay in the side panel rather than above the roster. -->
        <section id="dashboardLiveStatus" class="dashboard-live-status sidebar-live-status" data-state="connecting" aria-label="Dashboard connection">
            <div class="live-connection"><span class="live-indicator"></span><strong id="edgeConnectionState">Connecting</strong></div>
            <span id="dashboardFreshness">Waiting for first update</span>
            <div class="sidebar-cloud-status">Cloud queue: <strong id="cloudQueueState">Connecting</strong></div>
            <button type="button" class="live-retry" onclick="GuardLive.snapshot()">Refresh data</button>
            <p id="dashboardResourceNotice" role="status"></p>
        </section>

        <!-- Device Telemetry -->
        <div class="panel-section">
            <div class="panel-section-title">Device Telemetry</div>
            <div class="panel-device-grid">
                <div class="device-chip" title="Thermal Ticket Printer">
                    <span class="device-dot online"></span>
                    <span class="device-name">Printer:</span>
                    <span class="device-val" id="pnlPrinterStatus">Ready</span>
                </div>
                <div class="device-chip" title="Dahua Gate 1 Entrance Camera (192.168.1.230)">
                    <span class="device-dot online"></span>
                    <span class="device-name">Entry:</span>
                    <span class="device-val" id="pnlCam1Status">Online</span>
                </div>
                <div class="device-chip" title="Dahua Gate 2 Exit Camera (192.168.1.210)">
                    <span class="device-dot online"></span>
                    <span class="device-name">Exit:</span>
                    <span class="device-val" id="pnlCam2Status">Online</span>
                </div>
                <div class="device-chip" title="ALPR OCR Camera Worker">
                    <span class="device-dot online"></span>
                    <span class="device-name">ALPR:</span>
                    <span class="device-val" id="pnlAlprStatus">Active</span>
                </div>
            </div>
        </div>

        <details id="printIssuesPanel" class="print-issues-panel sidebar-print-issues" hidden>
            <summary>Print issues (<span id="printIssueCount">0</span>)</summary>
            <p>Saved entries needing a ticket reprint.</p>
            <div id="printIssuesList"></div>
        </details>

        <!-- Terminal Activity Log -->
        <div class="panel-section panel-section-log">
            <div class="panel-section-title">Terminal Log</div>
            <div class="panel-log" id="activityLog">
                <div class="panel-log-entry log-ok">
                    <span class="log-time">--:--:--</span> Station ready
                </div>
            </div>
        </div>

        <!-- Supervisor Override Action -->
        <div class="panel-section panel-section-sup">
            <button class="panel-quick-btn danger" onclick="openSupervisorOverrideModal('')">
                <svg class="i-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Supervisor Override</span>
            </button>
        </div>

    </aside>

    <!-- 2. CENTER MAIN PAGE: BASEMENT ACTIVE PARKING ROSTER -->
    <main class="center-main-area" id="mainStage">

        <!-- Center Stage Header & Filters -->
        <div class="roster-header-bar">
            <div class="roster-title-group">
                <div class="roster-badge-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                </div>
                <div>
                    <h2 class="roster-title" id="centerStageTitle">Basement Parking — Active Vehicles Inside</h2>
                    <p class="roster-subtitle" id="centerStageSubtitle">Newest arrivals first &bull; Longest parked last &bull; Free up to 2 hours</p>
                </div>
            </div>

            <div class="roster-actions-row">
                <!-- Search input -->
                <div class="roster-search-wrapper">
                    <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="rosterSearchInput" class="roster-search-input"
                           placeholder="Filter by plate, driver name, or store..." autocomplete="off">
                    <button class="btn-clear-search" id="btnClearRosterSearch" onclick="clearRosterSearch()">&#x2715;</button>
                </div>

                <!-- Roster tabs keep all parked vehicles and overdue vehicles easy to find. -->
                <div class="roster-filter-pills roster-status-tabs" id="rosterFilterPills" role="tablist" aria-label="Parked vehicle records">
                    <button type="button" class="roster-pill active" id="tabActiveCars" role="tab" aria-selected="true" aria-controls="viewBasementRoster" data-filter="all">Active Cars <span id="countPillAll">0</span></button>
                    <button type="button" class="roster-pill danger" id="tabOverstayedCars" role="tab" aria-selected="false" aria-controls="viewBasementRoster" tabindex="-1" data-filter="critical">Overstayed Cars <span id="countPillCrit">0</span></button>
                    <button type="button" class="roster-pill" id="tabExitedCars" role="tab" aria-selected="false" aria-controls="viewTrafficHistory" tabindex="-1" data-filter="exited">Exited Cars</button>
                    <button type="button" class="roster-pill" id="tabDailyTraffic" role="tab" aria-selected="false" aria-controls="viewTrafficHistory" tabindex="-1" data-filter="day">Daily Traffic</button>
                </div>

                <!-- View Switcher Tabs inside Center Area -->
                <div class="view-switch-btns">
                    <button class="btn-view-toggle active" id="btnToggleRoster" onclick="switchCenterView('roster')">
                        <svg class="i-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                        Basement Roster
                    </button>
                    <button class="btn-view-toggle" id="btnToggleExit" onclick="switchCenterView('exit')">
                        <svg class="i-icon" viewBox="0 0 24 24"><polyline points="19 12 5 12"/><polyline points="12 19 5 12 12 5"/></svg>
                        Exit Clearance
                    </button>
                    <button class="btn-roster-refresh" id="btnRefreshRoster" onclick="GuardHistory.invalidate(); loadActiveSessions(true)" title="Refresh vehicle records">
                        <svg class="i-icon refresh-icon" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- VIEW A: BASEMENT ACTIVE VEHICLES ROSTER -->
        <section id="viewBasementRoster" class="center-content-section active" role="tabpanel" aria-labelledby="tabActiveCars">
            <div class="table-responsive roster-table-wrapper">
                <table class="roster-cars-table">
                    <thead>
                        <tr>
                            <th style="min-width:140px;">Vehicle Number Plate</th>
                            <th>Driver &amp; Info</th>
                            <th>Destination Store</th>
                            <th>Time Entered</th>
                            <th>Expected Leave Time</th>
                            <th style="min-width:145px;">Stay Length (Live Timer)</th>
                            <th>Parking Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="insideTableBody">
                        <!-- Populated dynamically with live ticking duration timers by guard.js -->
                    </tbody>
                </table>
            </div>
        </section>

        <section id="viewTrafficHistory" class="center-content-section traffic-history" role="tabpanel" aria-labelledby="tabExitedCars" style="display:none;">
            <div class="traffic-toolbar">
                <label for="trafficDate">Date <input type="date" id="trafficDate"></label>
                <button type="button" class="btn-roster-action" id="trafficToday">Today</button>
                <button type="button" class="btn-roster-action" id="trafficAllDates">All dates</button>
                <span id="trafficHistoryStatus" role="status" aria-live="polite">Choose a tab to load recorded visits.</span>
            </div>
            <div id="trafficSummary" class="traffic-summary" hidden></div>
            <p class="traffic-note" id="trafficHistoryNote"></p>
            <div class="table-responsive roster-table-wrapper">
                <table class="roster-cars-table">
                    <thead><tr><th>Vehicle / Ticket</th><th>Driver &amp; Info</th><th>Destination Store</th><th>Time Entered</th><th>Time Exited</th><th>Total Stay</th><th>Record Status</th><th>Cleared By</th></tr></thead>
                    <tbody id="trafficHistoryBody"></tbody>
                </table>
            </div>
            <div class="traffic-pagination">
                <button type="button" class="btn-roster-action" id="trafficPrev" disabled>Previous</button>
                <span id="trafficPageInfo"></span>
                <button type="button" class="btn-roster-action" id="trafficNext" disabled>Next</button>
            </div>
        </section>

        <!-- VIEW B: EXIT CLEARANCE SEARCH & CAMERA -->
        <section id="viewExitClearance" class="center-content-section" style="display:none;">
            <div class="entering-action-bar">
                <button class="btn-fetch-camera" id="btnFetchExitCameraPlate" onclick="triggerCameraPlateFetch('exit')">
                    <svg class="i-icon" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span id="btnFetchExitCamText">Fetch Exit Camera Plate Now</span>
                </button>
                <div class="cam-status-pill">
                    <span class="pulse-dot"></span>
                    <span id="camExitStatusLabel">Dahua NVR Exit (192.168.0.3 Ch 2): Online</span>
                </div>
            </div>

            <div id="exitCandidateArea"></div>

            <div class="search-bar-large">
                <svg class="i-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="exitSearchInput" class="search-input-large"
                       placeholder="Search by plate number, phone, or ticket ID...">
                <button class="btn-search-clear"
                        onclick="document.getElementById('exitSearchInput').value=''; searchExitSessions('');">
                    Clear
                </button>
            </div>
            <div id="exitSearchResults" class="cards-grid"></div>
        </section>

    </main>

    <!-- 3. RIGHT SIDEBAR: INCOMING CAMERA CAPTURES & CARS NEEDING DATA -->
    <aside class="right-queue-sidebar" id="rightQueueSidebar">

        <!-- Sidebar Header -->
        <div class="queue-sidebar-header">
            <div class="queue-header-top">
                <div class="queue-title-badge">
                    <span class="radar-dot"></span>
                    <h3 class="queue-title">Gate Camera Entries</h3>
                </div>
                <span id="pendingCountBadge" class="queue-counter-badge">0 Waiting</span>
            </div>
            <p class="queue-subtitle">Captured cars entered awaiting data or driver details</p>

            <!-- Hardware Telemetry Status Strip -->
            <div class="queue-cam-telemetry-row">
                <div class="queue-cam-status-pill online" title="Dahua Entrance ANPR Camera: 192.168.1.230">
                    <span class="pulse-dot"></span>
                    <span id="camEntranceStatusLabel">Gate 1 (192.168.1.230): Online</span>
                </div>
                <span class="cam-feed-badge live-streaming" title="Continuous Real-Time Event Stream Active">⚡ AUTO-STREAM</span>
            </div>

            <!-- Action Buttons: Clean 2-Button Row (Release Stale + Fast Queue Sync) -->
            <div class="queue-cam-actions">
                <button type="button" class="btn-queue-release-stale" id="btnReleaseStaleQueue" onclick="releaseStaleQueue()" title="Release and clear unhandled records older than 15 mins to ease camera intake">
                    <svg class="i-icon" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    <span id="btnReleaseStaleText">Release Stale</span>
                </button>
                <button type="button" class="btn-queue-fast-refresh" id="btnFastRefreshQueue" onclick="fastRefreshEntryQueue(true)" title="Instant live sync with entrance camera & pending intake queue (Hotkey: R)">
                    <svg class="i-icon-refresh" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    <span class="refresh-lbl">Sync Queue</span>
                    <span class="refresh-hotkey">R</span>
                </button>
            </div>
        </div>

        <!-- ALPR Live Detection Banner (Collapsible) -->
        <div id="alprEntranceBanner" class="alpr-live-banner" style="display:none;">
            <div class="alpr-banner-top">
                <div class="alpr-cam-pill">
                    <span class="pulse-dot"></span>
                    <span>LIVE GATE CAPTURE</span>
                </div>
                <div id="alprBannerMeta" class="alpr-meta-tag">Confidence: 95% at 14:56:19</div>
            </div>

            <div class="alpr-banner-body">
                <div class="alpr-plate-kenya-wrap">
                    <span class="plate-flag-ke">KE</span>
                    <span id="alprBannerPlate" class="alpr-plate-text">KDA 123A</span>
                </div>
            </div>

            <div class="alpr-banner-actions">
                <button type="button" class="btn-alpr-quick-admit" id="btnAlprAdmit" onclick="admitCurrentAlprBanner()">
                    <svg class="i-icon" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    <span>Enter Details &amp; Admit</span>
                </button>
                <button type="button" class="btn-alpr-close" onclick="dismissAlprBanner()" title="Dismiss banner">
                    &#x2715;
                </button>
            </div>
        </div>

        <!-- Queue category filters and view toggle. -->
        <div class="queue-control-strip">
            <div class="queue-view-row">
                <div class="queue-view-toggle">
                    <button type="button" class="btn-view-toggle" id="btnViewCompact" onclick="setQueueViewMode('compact')" title="Compact High-Density Rows (View 15+ cars at once)">
                        <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        <span>Rows</span>
                    </button>
                    <button type="button" class="btn-view-toggle active" id="btnViewCards" onclick="setQueueViewMode('cards')" title="Detailed Cards View with Snapshots">
                        <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>Cards</span>
                    </button>
                </div>
            </div>

            <!-- Queue Category Filter Pills -->
            <div class="queue-filter-row">
                <div class="queue-filter-pills">
                    <button type="button" class="q-filter-pill active" data-qfilter="all" onclick="setQueueFilter('all')">
                        All (<span id="qCountAll">0</span>)
                    </button>
                    <button type="button" class="q-filter-pill needs" data-qfilter="needs-data" onclick="setQueueFilter('needs-data')">
                        Needs Data (<span id="qCountNeeds">0</span>)
                    </button>
                    <button type="button" class="q-filter-pill prefilled" data-qfilter="self-checkin" onclick="setQueueFilter('self-checkin')">
                        Pre-Filled (<span id="qCountPre">0</span>)
                    </button>
                </div>
                <button type="button" class="btn-batch-approve" id="btnBatchApprove" onclick="batchApprovePreFilled()" style="display:none;" title="Approve all waiting pre-filled driver sign-ins at once">
                    ⚡ Approve All (<span id="qBatchCount">0</span>)
                </button>
            </div>
        </div>

        <!-- Scrollable list of pending / captured vehicle cards needing data -->
        <div id="pendingCardsGrid" class="queue-cards-list view-cards">
            <!-- Dynamically populated with cars entered needing data by guard.js -->
        </div>

    </aside>

</div><!-- /guard-main-layout -->

<!-- ================================================================
     MODAL 1: GUARD PIN LOGIN
     ================================================================ -->
<div id="pinLoginModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Guard sign in">
    <div class="modal-box" style="max-width:380px;">
        <div class="pin-modal-brand">
            <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall">
            <p>Basement Parking — Guard Login</p>
        </div>
        <p style="text-align:center; color:var(--text-muted); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em;">
            <span id="pinSessionNotice">Enter your 4–6 digit PIN, then press Enter.</span>
        </p>
        <div class="pin-display">
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
            <div class="pin-dot"></div>
        </div>
        <div class="keypad-grid">
            <button class="key-btn" data-val="1">1</button>
            <button class="key-btn" data-val="2">2</button>
            <button class="key-btn" data-val="3">3</button>
            <button class="key-btn" data-val="4">4</button>
            <button class="key-btn" data-val="5">5</button>
            <button class="key-btn" data-val="6">6</button>
            <button class="key-btn" data-val="7">7</button>
            <button class="key-btn" data-val="8">8</button>
            <button class="key-btn" data-val="9">9</button>
            <button class="key-btn action-key" data-val="clear">CLR</button>
            <button class="key-btn" data-val="0">0</button>
            <button class="key-btn enter-key" data-val="enter">ENTER</button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL 2: MANUAL VEHICLE REGISTRATION — FLAT SINGLE POPUP
     ================================================================ -->
<div id="manualWizardModal" class="modal-overlay">
    <div class="modal-box" style="max-width:660px;">
        <div class="modal-header-row">
            <h2 class="modal-title">Register Vehicle Manually</h2>
            <button onclick="closeWizard()" class="modal-close-btn">&#x2715;</button>
        </div>

        <div id="wizCamSuggestion" style="display:none;">
            <button id="btnUseCamPlate" class="cam-suggestion-btn">
                <svg class="i-icon" style="display:inline-block;vertical-align:middle;" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                Use camera-detected plate: <strong id="wizCamPlateVal"></strong>
            </button>
        </div>

        <div id="wizFrequentBanner" style="display:none; background:#eff6ff; border:1px solid #3b82f6; border-radius:6px; padding:10px 14px; margin-bottom:12px; align-items:center; gap:10px;">
            <div style="flex:1;">
                <div style="font-size:12px; font-weight:900; color:#1e40af; letter-spacing:0.04em; text-transform:uppercase;">
                    FREQUENT CLIENT RECOGNIZED &bull; VISIT #<span id="wizVisitCount">3</span>
                </div>
                <div style="font-size:11px; color:#1e3a8a; margin-top:2px;">
                    Previous details are suggestions. <strong>Confirm today’s driver</strong> and destination before admission.
                </div>
            </div>
            <span style="background:#2563eb; color:#fff; font-size:10px; font-weight:800; padding:3px 7px; border-radius:4px; text-transform:uppercase; letter-spacing:0.05em; flex-shrink:0;">Confirm Driver</span>
        </div>

        <section id="wizReturningProfile" class="returning-profile" hidden aria-label="Returning visitor details">
    <strong class="returning-summary"></strong><p class="returning-details"></p><p class="returning-destination"></p>
    <div class="returning-actions"><button type="button" onclick="GuardProfiles.choose('wiz', 'use')">Use These Details</button><button type="button" onclick="GuardProfiles.choose('wiz', 'edit')">Edit Details</button><button type="button" onclick="GuardProfiles.choose('wiz', 'different')">Different Driver</button></div>
    <p class="returning-choice" role="status">Confirm who is driving today. Previous details are suggestions.</p>
</section>
<label class="remember-profile"><input type="checkbox" id="wizRememberDetails" checked> Remember these confirmed details for next visit</label>
        <div class="manual-form-grid">

            <div class="form-field field-full">
                <label for="wizPlateInput">Vehicle Number Plate</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="wizPlateInput" class="plate-style" style="flex:1;"
                           placeholder="KDA 123A" maxlength="12" autocomplete="off" spellcheck="false">
                    <button type="button" class="btn-cam-scan-inline" onclick="scanCameraIntoWizard()">
                        <svg class="i-icon" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        Scan Camera
                    </button>
                </div>
            </div>

            <div class="form-field">
                <label for="wizNameInput">Driver Full Name</label>
                <input type="text" id="wizNameInput" placeholder="e.g. John Mwangi" autocomplete="name">
            </div>

            <div class="form-field">
                <label for="wizPhoneInput">Mobile Number</label>
                <input type="tel" id="wizPhoneInput" placeholder="0712 345 678" autocomplete="tel">
            </div>

            <div class="form-field field-full">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <label style="margin:0;">Destination Store (<span id="wizSelectedDestLabel" style="color:var(--blue); font-weight:800;">None</span>)</label>
                    <span style="font-size:10px; color:var(--text-muted); font-weight:700;">48 Stores</span>
                </div>
                <div style="display:flex; gap:6px; margin-bottom:6px;">
                    <input type="text" id="wizDestSearch" placeholder="Filter store (e.g. Naivas, NCBA, Gym, Pizza)..."
                           style="flex:1; padding:7px 10px; font-size:12px; border:1px solid var(--border); border-radius:4px; outline:none; background:var(--bg-input);">
                </div>
                <div class="floor-pill-tabs" id="wizFloorTabs" style="display:flex; gap:4px; flex-wrap:wrap; margin-bottom:6px;">
                    <button type="button" class="floor-pill active" data-floor="ALL">All (45)</button>
                    <button type="button" class="floor-pill" data-floor="Ground Floor">Ground Floor</button>
                    <button type="button" class="floor-pill" data-floor="1st Floor">First Floor</button>
                    <button type="button" class="floor-pill" data-floor="2nd Floor">Second Floor</button>
                    <button type="button" class="floor-pill" data-floor="3rd Floor">Third Floor</button>
                </div>
                <div id="wizDestinationsGrid" class="destinations-grid" style="max-height:180px; overflow-y:auto; padding:2px;">
                    <!-- Populated by guard.js loadDestinations() -->
                </div>
            </div>

        </div>

        <div class="modal-footer">
            <button class="btn-cancel-modal" onclick="closeWizard()">Cancel</button>
            <button class="btn-submit-checkin" onclick="submitManualWizard()">
                Register &amp; Print Ticket
            </button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL 2b: VEHICLE INTAKE POPUP (TAP-TO-FILL FOR GUARDS)
     ================================================================ -->
<div id="vehicleIntakeModal" class="modal-overlay">
    <div class="modal-box intake-modal-box">
        <!-- Header with Plate & Snapshot -->
        <div class="intake-header">
            <div class="intake-header-left">
                <div id="intakeSnapWrapper" class="intake-snap-wrapper">
                    <img id="intakeSnapImg" src="" alt="Snapshot" class="intake-snap-thumb" onclick="window.open(this.src, '_blank')">
                </div>
                <div class="intake-plate-meta">
                    <div class="intake-plate-badge" id="intakePlateDisplay">KDA 123A</div>
                    <div class="intake-meta-row">
                        <span id="intakeSourceBadge" class="cam-source-badge">📷 CAMERA</span>
                        <span id="intakeTypeBadge" class="cam-source-badge" style="display:none;">🚗 CAR</span>
                        <span id="intakeFrequentBadge" class="cam-source-badge" style="display:none; background:#2563eb; color:#fff; font-weight:800;">⚡ FREQUENT CLIENT</span>
                        <span id="intakeVerifiedBadge" class="badge-verified">✓ VERIFIED</span>
                        <span id="intakeEntryTime" class="intake-time" style="background:#e0f2fe; color:#0369a1; font-weight:800; padding:2px 8px; border-radius:4px; display:none;">In: --</span>
                        <span id="intakeTimeAgo" class="intake-time">Just now</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeVehicleIntakeModal()" class="modal-close-btn" title="Close">&#x2715;</button>
        </div>

        <div id="intakeAlertBanner" style="display:none;" class="badge-alert"></div>

        <!-- Frequent Client Recognition Banner -->
        <div id="intakeFrequentBanner" style="display:none; background:#eff6ff; border:1px solid #3b82f6; border-radius:6px; padding:10px 14px; margin-bottom:12px; align-items:center; gap:10px;">
            <div style="flex:1;">
                <div style="font-size:12px; font-weight:900; color:#1e40af; letter-spacing:0.04em; text-transform:uppercase;">
                    FREQUENT CLIENT RECOGNIZED &bull; VISIT #<span id="intakeVisitCount">3</span>
                </div>
                <div style="font-size:11px; color:#1e3a8a; margin-top:2px;">
                    Previous details are suggestions. <strong>Confirm today’s driver</strong> and destination before admission.
                </div>
            </div>
            <span style="background:#2563eb; color:#fff; font-size:10px; font-weight:800; padding:3px 7px; border-radius:4px; text-transform:uppercase; letter-spacing:0.05em; flex-shrink:0;">Confirm Driver</span>
        </div>

        <!-- Driver Self-Check-in Notification Banner -->
        <div id="intakeSelfCheckinBanner" style="display:none; background:#ecfdf5; border:1px solid #10b981; border-radius:8px; padding:10px 14px; margin-bottom:12px; align-items:center; gap:10px;">
            <span style="font-size:22px; flex-shrink:0;">📱</span>
            <div style="flex:1;">
                <div style="font-size:12.5px; font-weight:900; color:#065f46; letter-spacing:0.02em;">
                    DRIVER COMPLETED SELF CHECK-IN
                </div>
                <div style="font-size:11px; color:#047857; margin-top:2px;">
                    Driver has pre-filled vehicle &amp; visit details on mobile. Tap <strong>APPROVE &amp; PRINT TICKET</strong> below so driver can proceed to shop.
                </div>
            </div>
            <span style="background:#10b981; color:#fff; font-size:10px; font-weight:800; padding:4px 8px; border-radius:12px; text-transform:uppercase; letter-spacing:0.05em; flex-shrink:0;">Mobile Verified</span>
        </div>

        <!-- Form fields: Name, Phone, Destination -->
        <section id="intakeReturningProfile" class="returning-profile" hidden aria-label="Returning visitor details">
    <strong class="returning-summary"></strong><p class="returning-details"></p><p class="returning-destination"></p>
    <div class="returning-actions"><button type="button" onclick="GuardProfiles.choose('intake', 'use')">Use These Details</button><button type="button" onclick="GuardProfiles.choose('intake', 'edit')">Edit Details</button><button type="button" onclick="GuardProfiles.choose('intake', 'different')">Different Driver</button></div>
    <p class="returning-choice" role="status">Confirm who is driving today. Previous details are suggestions.</p>
</section>
<label class="remember-profile"><input type="checkbox" id="intakeRememberDetails" checked> Remember these confirmed details for next visit</label>
        <div class="intake-form-body">
            <div class="intake-grid-2">
                <div class="form-field">
                    <label for="intakeDriverName">Driver Full Name</label>
                    <input type="text" id="intakeDriverName" class="intake-input" 
                           placeholder="Driver Name (e.g. John Mwangi)" autocomplete="name">
                </div>
                <div class="form-field">
                    <label for="intakeDriverPhone">Mobile Number</label>
                    <input type="tel" id="intakeDriverPhone" class="intake-input" 
                           placeholder="07XX XXX XXX (Optional)" autocomplete="tel">
                </div>
            </div>

            <div class="form-field field-full">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="margin:0; font-size:11px; font-weight:800; color:var(--text-primary);">
                        Destination Store (<span id="intakeSelectedDestLabel" style="color:var(--blue); font-weight:900;">Choose destination</span>)
                    </label>
                    <input type="hidden" id="intakeDestinationValue" value="">
                    <span style="font-size:10px; color:var(--text-muted); font-weight:700;">Tap floor then shop:</span>
                </div>

                <!-- Floor Level Tabs: Ground Floor, First Floor, Second Floor, Third Floor -->
                <div class="intake-floor-tabs" id="intakeFloorTabs">
                    <button type="button" class="intake-floor-tab active" data-floor="Ground Floor" onclick="switchIntakeFloor('Ground Floor', this)">
                        Ground Floor
                    </button>
                    <button type="button" class="intake-floor-tab" data-floor="1st Floor" onclick="switchIntakeFloor('1st Floor', this)">
                        First Floor
                    </button>
                    <button type="button" class="intake-floor-tab" data-floor="2nd Floor" onclick="switchIntakeFloor('2nd Floor', this)">
                        Second Floor
                    </button>
                    <button type="button" class="intake-floor-tab" data-floor="3rd Floor" onclick="switchIntakeFloor('3rd Floor', this)">
                        Third Floor
                    </button>
                </div>

                <!-- Outlined Shops Available on Clicked Floor -->
                <div class="intake-shops-grid" id="intakeShopsGrid">
                    <!-- Populated dynamically upon clicking floor -->
                </div>
            </div>
        </div>

        <!-- Action Footer -->
        <div class="intake-actions-footer">
            <button type="button" class="btn-intake-accept" id="btnIntakeAccept" onclick="submitIntakeFromModal()">
                <svg class="i-icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span id="btnIntakeAcceptText">ACCEPT &amp; PRINT TICKET</span>
            </button>
            <button type="button" class="btn-intake-decline" onclick="declineCurrentIntake()">
                ✕ Decline
            </button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL 3: DECLINE REASON
     ================================================================ -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header-row">
            <h2 class="modal-title" style="color:var(--red-lt);">Decline Vehicle Entry</h2>
            <button onclick="closeRejectDialog()" class="modal-close-btn">&#x2715;</button>
        </div>
        <p style="color:var(--text-muted); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em;">
            Select reason &mdash; recorded in audit log
        </p>
        <div class="reject-reasons">
            <button class="reject-reason-btn" onclick="confirmReject('Driver decided not to enter')">
                Driver decided not to enter
            </button>
            <button class="reject-reason-btn" onclick="confirmReject('Refused security luggage inspection')">
                Refused security inspection
            </button>
            <button class="reject-reason-btn" onclick="confirmReject('Vehicle over capacity / oversized')">
                Oversized or over-height vehicle
            </button>
            <button class="reject-reason-btn danger" onclick="confirmReject('Blacklisted security risk')">
                BLACKLISTED / SECURITY RISK
            </button>
        </div>
        <button class="btn-cancel-modal" style="width:100%;" onclick="closeRejectDialog()">Cancel</button>
    </div>
</div>

<!-- ================================================================
     MODAL 4: SUPERVISOR EXIT OVERRIDE
     ================================================================ -->
<div id="supervisorOverrideModal" class="modal-overlay">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header-row">
            <h2 class="modal-title" style="color:var(--amber-bright);">Supervisor Exit Override</h2>
            <button onclick="closeSupervisorOverrideModal()" class="modal-close-btn">&#x2715;</button>
        </div>
        <p style="color:var(--text-muted); font-size:12px; line-height:1.6;">
            Supervisor authorization required to clear departure without a valid session record. This action will be permanently logged.
        </p>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <input type="password" id="supOverridePin"
                   class="override-input amber-border"
                   placeholder="Supervisor PIN">
            <input type="text" id="supOverrideReason"
                   class="override-input"
                   placeholder="Reason (e.g. Lost ticket / Vehicle breakdown)">
        </div>
        <div class="modal-footer">
            <button class="btn-cancel-modal" onclick="closeSupervisorOverrideModal()">Cancel</button>
            <button class="btn-submit-checkin" style="background:var(--amber); color:#000;" onclick="submitSupervisorOverride()">
                Authorize Exit
            </button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL 5: THERMAL PARKING RECEIPT PREVIEW & PRINT
     ================================================================ -->
<div id="receiptPreviewModal" class="modal-overlay">
    <div class="receipt-dialog-box">
        <button type="button" onclick="closeReceiptModal()" class="receipt-close-btn" title="Close">&#x2715;</button>

        <!-- Printable Thermal Slip -->
        <div class="thermal-slip" id="printableReceipt">
            <div class="slip-header">
                <div class="slip-brand">MOMBASA MALL</div>
                <div class="slip-sub-brand">BASEMENT PARKING &bull; 60 SLOTS</div>
                <div class="slip-contact">Tel: +254 700 000 000 | 24/7 Security</div>
                <div class="slip-dash-line">================================</div>
            </div>

            <div class="slip-ticket-section">
                <div class="slip-title">ENTRY PARKING TICKET</div>
                <div class="slip-ticket-id" id="rcptTicketId">MM-20261005-0001</div>
                <div class="slip-barcode">
                    <span class="barcode-graphic">|||| | ||| |||| | |||||| || | |||| ||</span>
                    <span class="barcode-caption" id="rcptBarcodeNum">MM-20261005-0001</span>
                </div>
            </div>

            <!-- Big Kenyan License Plate -->
            <div class="slip-plate-wrap">
                <div class="slip-plate" id="rcptPlate">KDM 687V</div>
            </div>

            <div class="slip-dash-line">--------------------------------</div>

            <div class="slip-details">
                <div class="slip-row">
                    <span class="slip-label">DRIVER:</span>
                    <span class="slip-val" id="rcptDriver">Visitor</span>
                </div>
                <div class="slip-row" id="rcptPhoneRow">
                    <span class="slip-label">MOBILE:</span>
                    <span class="slip-val" id="rcptPhone">--</span>
                </div>
                <div class="slip-row">
                    <span class="slip-label">STORE:</span>
                    <span class="slip-val" id="rcptDestination">G-01 Naivas Supermarket</span>
                </div>
                <div class="slip-row">
                    <span class="slip-label">GATE GUARD:</span>
                    <span class="slip-val" id="rcptGuard">John Omondi (Gate 1)</span>
                </div>
            </div>

            <div class="slip-dash-line">--------------------------------</div>

            <!-- 2 HOURS HIGHLIGHT BOX -->
            <div class="slip-timing-box">
                <div class="timing-callout-row time-in">
                    <span class="t-label">TIME IN:</span>
                    <span class="t-value" id="rcptTimeIn">13:08:05 &bull; 05/10/2026</span>
                </div>
                <div class="timing-callout-row stay-limit">
                    <span class="t-label">PERMITTED STAY:</span>
                    <span class="t-value">2 Hours (120 Mins)</span>
                </div>
                <div class="timing-callout-row time-out">
                    <span class="t-label">EXPECTED OUT:</span>
                    <span class="t-value" id="rcptTimeOut">15:08:05 &bull; 05/10/2026</span>
                </div>
                <div class="timing-notice">
                    ★ FREE customer parking within 2 hours limit.<br>
                    <strong>Extension beyond 2 hours is subject to penalty fees.</strong>
                </div>
            </div>

            <div class="slip-dash-line">================================</div>

            <div class="slip-footer">
                <div class="slip-warning">KEEP THIS TICKET SAFE</div>
                <div>Customer permitted 2 hrs within mall.</div>
                <div style="font-weight:700; color:#b91c1c; margin:2px 0;">Extension / overstay is subject to penalty.</div>
                <div>Present ticket to security guard upon exit.</div>
                <div class="slip-thanks">Asante kwa Kutembelea Mombasa Mall!</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="receipt-dialog-actions">
            <button type="button" class="btn-print-slip" onclick="printReceiptDirect()">
                <svg class="i-icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Receipt
            </button>
            <button type="button" class="btn-dismiss-slip" onclick="closeReceiptModal()">
                Done (Close)
            </button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL 6: OPERATION GUIDE
     ================================================================ -->
<div id="helpModal" class="modal-overlay">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header-row">
            <h2 class="modal-title">Guard Operation Manual</h2>
            <button onclick="closeHelpModal()" class="modal-close-btn">&#x2715;</button>
        </div>
        <div class="help-steps">
            <div class="help-step">
                <div class="help-step-title">Procedure 1 — Incoming Vehicle (Driver Used QR Code)</div>
                <p>Driver scanned the entrance QR code. Their request appears as a card in <strong>Vehicles Entering</strong>.
                   Verify plate number, then click <strong>Accept &amp; Print Ticket</strong>.</p>
            </div>
            <div class="help-step">
                <div class="help-step-title">Procedure 2 — Incoming Vehicle (No QR Scan)</div>
                <p>Click <strong>Register Vehicle</strong> in the top bar or the left panel. Enter plate number, driver name,
                   phone and destination in the form, then click <strong>Register &amp; Print</strong>.</p>
            </div>
            <div class="help-step">
                <div class="help-step-title">Procedure 3 — Outgoing Vehicle</div>
                <p>Camera auto-detects departure. If not automatic, switch to <strong>Vehicles Exiting</strong> and type the
                   plate number in the search box. Verify details then click <strong>Clear Exit</strong>.</p>
            </div>
            <div class="help-step">
                <div class="help-step-title">Procedure 4 — Printer Out of Paper</div>
                <p>Replace thermal paper roll then use the <strong>Reprint</strong> button in the <strong>Current Roster</strong>
                   table row for the affected vehicle.</p>
            </div>
            <div class="help-step">
                <div class="help-step-title">Procedure 5 — Supervisor Override</div>
                <p>If a vehicle cannot be matched (lost ticket), use <strong>Override Exit (No Ticket)</strong> in the left
                   panel. Requires Supervisor PIN. All overrides are permanently audited.</p>
            </div>
        </div>
        <button class="btn-submit-checkin" style="width:100%;" onclick="closeHelpModal()">
            Acknowledged — Close Guide
        </button>
    </div>
</div>

<!-- ================================================================
     SOFT LOGOUT CONFIRMATION MODAL
     ================================================================ -->
<div id="logoutConfirmModal" class="modal-overlay" onclick="if(event.target===this) closeLogoutConfirmModal()">
    <div class="modal-box logout-modal-box" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
        <div class="logout-modal-header">
            <div class="logout-icon-bubble">
                <svg viewBox="0 0 24 24" class="logout-icon"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </div>
            <div class="logout-modal-title-wrap">
                <h2 id="logoutModalTitle" class="modal-title" style="color:var(--text-primary); font-size:17px; margin:0;">End Guard Shift &amp; Sign Out?</h2>
                <div class="logout-modal-meta">Gate 1 Terminal &bull; Active Shift: <strong><?= htmlspecialchars($guardName ?? 'Gate Officer') ?></strong></div>
            </div>
            <button type="button" onclick="closeLogoutConfirmModal()" class="modal-close-btn" title="Cancel">&#x2715;</button>
        </div>
        <div class="logout-modal-body">
            <p>You are about to sign out of the guard terminal. Any vehicles currently waiting in the gate intake queue will remain preserved for the relieving officer.</p>
        </div>
        <div class="modal-footer logout-modal-footer">
            <button type="button" class="btn-cancel-modal" onclick="closeLogoutConfirmModal()">
                Stay Signed In
            </button>
            <button type="button" id="btnConfirmLogout" class="btn-confirm-logout" onclick="executeLogout()">
                <svg class="i-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span id="logoutBtnLabel">Confirm Sign Out</span>
            </button>
        </div>
    </div>
</div>

<!-- ================================================================
     SOFT VEHICLE EXIT CLEARANCE CONFIRMATION MODAL
     ================================================================ -->
<div id="exitConfirmModal" class="modal-overlay" onclick="if(event.target===this) closeExitConfirmModal()">
    <div class="modal-box exit-confirm-modal-box" role="dialog" aria-modal="true" aria-labelledby="exitModalTitle">
        <div class="exit-modal-header">
            <div class="exit-icon-bubble">
                <svg viewBox="0 0 24 24" class="exit-icon"><polyline points="9 18 15 12 9 6"/><line x1="19" y1="12" x2="5" y2="12"/></svg>
            </div>
            <div class="exit-modal-title-wrap">
                <h2 id="exitModalTitle" class="modal-title" style="color:var(--blue); font-size:17px; margin:0;">Confirm Vehicle Departure</h2>
                <div class="exit-modal-subtitle">Basement Parking Departure Clearance</div>
            </div>
            <button type="button" onclick="closeExitConfirmModal()" class="modal-close-btn" title="Cancel">&#x2715;</button>
        </div>
        <div class="exit-modal-body">
            <div class="exit-plate-card">
                <div class="alpr-plate-kenya-wrap">
                    <span class="plate-flag-ke">KE</span>
                    <span id="exitConfirmPlate" class="alpr-plate-text">KDA 123A</span>
                </div>
                <div class="exit-meta-grid">
                    <div class="exit-meta-item">
                        <span class="lbl">Assigned Bay</span>
                        <strong id="exitConfirmBay">--</strong>
                    </div>
                    <div class="exit-meta-item">
                        <span class="lbl">Stay Duration</span>
                        <strong id="exitConfirmDwell">--</strong>
                    </div>
                    <div class="exit-meta-item">
                        <span class="lbl">Entry Time</span>
                        <strong id="exitConfirmEntryTime">--</strong>
                    </div>
                </div>
            </div>
            <p class="exit-confirm-notice">This will record the departure time in the audit log, and release 1 parking bay back to total capacity. Operate the physical barrier using the station procedure.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel-modal" onclick="closeExitConfirmModal()">Cancel / Keep Parked</button>
            <button type="button" id="btnConfirmClearExit" class="btn-confirm-exit" onclick="commitClearExit()">
                <svg class="i-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <span id="exitBtnLabel">Record Exit</span>
            </button>
        </div>
    </div>
</div>

<!-- ================================================================
     SOFT BATCH APPROVE CONFIRMATION MODAL
     ================================================================ -->
<div id="batchApproveModal" class="modal-overlay" onclick="if(event.target===this) closeBatchApproveModal()">
    <div class="modal-box" style="max-width:460px;" role="dialog" aria-modal="true">
        <div class="modal-header-row">
            <h2 class="modal-title" style="color:var(--blue); font-size:16px;">⚡ Batch Approve Pre-Filled Vehicles</h2>
            <button onclick="closeBatchApproveModal()" class="modal-close-btn">&#x2715;</button>
        </div>
        <div style="padding:10px 0; font-size:13px; color:var(--text-secondary); line-height:1.5;">
            <p id="batchModalPrompt">Approve all pre-filled vehicles in the queue?</p>
            <p style="margin-top:8px; font-size:12px; color:var(--text-muted);">This will auto-assign parking bays and send gate entry tickets to the thermal printer in sequence.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel-modal" onclick="closeBatchApproveModal()">Cancel</button>
            <button type="button" id="btnExecuteBatchApprove" class="btn-submit-checkin" onclick="executeBatchApproveConfirmed()">
                Confirm &amp; Print Tickets
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toastMsg" class="toast-msg">
    <svg class="i-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastText" role="status" aria-live="polite">Action completed</span>
    <button id="btnToastReprint" class="btn-search-clear" style="display:none;">Reprint</button>
</div>

<!-- Bundled Local Libraries (NO CDN) -->
<script src="../assets/vendor/gsap/gsap.min.js"></script>
<script src="../assets/vendor/gsap/Flip.min.js"></script>

<!-- Application Scripts -->
<script src="js/guard_i18n.js"></script>
<script src="js/guard-live-model.js?v=20261103d"></script>
<script src="js/guard.js?v=20261103d"></script>
<script src="js/guard-profiles.js?v=20261103"></script>
<script src="js/guard-history.js?v=20261103a"></script>
<script src="js/guard-live.js?v=20261103d"></script>
<script type="module" src="../assets/js/firebase-init.js"></script>

<script>
    function openHelpModal()  { document.getElementById('helpModal').classList.add('open'); }
    function closeHelpModal() { document.getElementById('helpModal').classList.remove('open'); }

    // Wire the duplicate "Register Vehicle" button in the left panel
    document.addEventListener('DOMContentLoaded', () => {
        const btn2 = document.getElementById('btnOpenManualAdd2');
        if (btn2) btn2.addEventListener('click', () => {
            document.getElementById('btnOpenManualAdd').click();
        });
    });
</script>
</body>
</html>
