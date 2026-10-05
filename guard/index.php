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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Guard Station — Mombasa Mall Basement Parking</title>
    <link rel="stylesheet" href="css/guard.css">
</head>
<body>

<!-- Offline Banner -->
<div id="offlineBanner" class="offline-banner">
    NETWORK DISCONNECTED — OFFLINE SAFE MODE ACTIVE — DO NOT CLOSE THIS WINDOW
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

        <button id="btnSoundToggle" class="icon-btn">
            <svg class="i-icon" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
            Sound
        </button>
        <button id="btnLangToggle" class="icon-btn">
            <svg class="i-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            SW
        </button>
        <button class="icon-btn" onclick="openHelpModal()">
            <svg class="i-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Help
        </button>
        <button class="icon-btn danger" onclick="location.href='../api/auth/logout.php'" title="Logout">
            <svg class="i-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </button>
    </div>
</header>

<!-- ================================================================
     OCCUPANCY DASHBOARD STRIP
     ================================================================ -->
<section class="occupancy-dashboard">
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
</section>

<!-- ================================================================
     MODE TABS
     ================================================================ -->
<nav class="mode-tabs-container">
    <button class="mode-tab entering active" data-tab="entering">
        <svg class="i-icon" viewBox="0 0 24 24"><polyline points="5 12 19 12"/><polyline points="12 5 19 12 12 19"/></svg>
        Vehicles Entering
        <span id="pendingCountBadge" class="tab-badge" style="display:none;">0</span>
    </button>
    <button class="mode-tab leaving" data-tab="leaving">
        <svg class="i-icon" viewBox="0 0 24 24"><polyline points="19 12 5 12"/><polyline points="12 19 5 12 12 5"/></svg>
        Vehicles Exiting
    </button>
    <button class="mode-tab inside" data-tab="inside">
        <svg class="i-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Current Roster
    </button>
</nav>

<!-- ================================================================
     MAIN LAYOUT: LEFT PANEL + RIGHT CONTENT
     ================================================================ -->
<div class="guard-two-col">

    <!-- LEFT COMMAND PANEL -->
    <aside class="left-panel">

        <!-- Logo block -->
        <div style="text-align:center; padding:10px 0 4px; border-bottom:1px solid var(--border);">
            <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall" style="height:30px; width:auto; object-fit:contain; opacity:0.6;">
        </div>

        <!-- Live Stats -->
        <div class="panel-section">
            <div class="panel-section-title">Live Session Stats</div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Today Entries</span>
                <span class="panel-stat-value amber" id="pnlTodayIn">--</span>
            </div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Today Exits</span>
                <span class="panel-stat-value" id="pnlTodayOut">--</span>
            </div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Overstays</span>
                <span class="panel-stat-value red" id="pnlOverstay">--</span>
            </div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Avg. Dwell Time</span>
                <span class="panel-stat-value" id="pnlAvgDwell">-- min</span>
            </div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Pending Approval</span>
                <span class="panel-stat-value amber" id="pnlPending">--</span>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="panel-section">
            <div class="panel-section-title">Quick Actions</div>
            <button class="panel-quick-btn amber-action" id="btnOpenManualAdd2">
                <svg class="i-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Register Vehicle Manually
            </button>
            <button class="panel-quick-btn" onclick="switchTab('leaving'); document.getElementById('exitSearchInput').focus();">
                <svg class="i-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Search for Exiting Vehicle
            </button>
            <button class="panel-quick-btn" onclick="switchTab('inside'); loadActiveSessions();">
                <svg class="i-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                View Full Roster
            </button>
            <button class="panel-quick-btn" onclick="window.open('../admin/', '_blank')">
                <svg class="i-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Admin Panel
            </button>
        </div>

        <!-- Device Status -->
        <div class="panel-section">
            <div class="panel-section-title">Device Status</div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Thermal Printer</span>
                <span class="panel-stat-value" id="pnlPrinterStatus">Unknown</span>
            </div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Entry Camera</span>
                <span class="panel-stat-value" id="pnlCam1Status">Unknown</span>
            </div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">Exit Camera</span>
                <span class="panel-stat-value" id="pnlCam2Status">Unknown</span>
            </div>
            <div class="panel-stat-row">
                <span class="panel-stat-label">ALPR Worker</span>
                <span class="panel-stat-value" id="pnlAlprStatus">Unknown</span>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="panel-section" style="flex:1; min-height:0;">
            <div class="panel-section-title">Activity Log</div>
            <div class="panel-log" id="activityLog">
                <div class="panel-log-entry log-ok">
                    <span class="log-time">--:--:--</span> System ready
                </div>
            </div>
        </div>

        <!-- Danger zone -->
        <div class="panel-section">
            <div class="panel-section-title">Supervisor Actions</div>
            <button class="panel-quick-btn danger" onclick="openSupervisorOverrideModal('')">
                <svg class="i-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Override Exit (No Ticket)
            </button>
        </div>

    </aside>

    <!-- RIGHT MAIN CONTENT -->
    <div class="right-content-area" id="mainStage">

        <!-- VIEW 1: VEHICLES ENTERING -->
        <section id="viewEntering">
            <!-- Action Bar: Fetch Live Camera Plate -->
            <div class="entering-action-bar">
                <button class="btn-fetch-camera" id="btnFetchCameraPlate" onclick="triggerCameraPlateFetch()">
                    <svg class="i-icon" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span id="btnFetchCamText">Fetch Camera Plate Now</span>
                </button>
                <div class="cam-status-pill">
                    <span class="pulse-dot"></span>
                    <span id="camEntranceStatusLabel">Entrance Camera (192.168.1.230): Online</span>
                </div>
            </div>

            <!-- ALPR Live Detection Banner -->
            <div id="alprEntranceBanner" class="alpr-live-banner" style="display:none;">
                <div class="alpr-banner-left">
                    <div class="cam-live-indicator">
                        <span class="pulse-dot"></span>
                        CAMERA LIVE
                    </div>
                    <span style="font-weight:700; font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.06em;">Entry Camera Detected:</span>
                    <span id="alprBannerPlate" class="alpr-plate-display">KDA 123A</span>
                </div>
                <div id="alprBannerMeta" class="alpr-meta">Awaiting match</div>
            </div>

            <!-- Pending Request Cards -->
            <div id="pendingCardsGrid" class="cards-grid">
                <!-- Populated dynamically -->
            </div>
        </section>

        <!-- VIEW 2: VEHICLES EXITING -->
        <section id="viewLeaving" style="display:none;">
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

        <!-- VIEW 3: CURRENT ROSTER -->
        <section id="viewInside" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h2 class="section-heading" style="margin:0; border:none; padding:0;">Basement Parking Roster</h2>
                <button class="btn-search-clear" onclick="loadActiveSessions()">
                    <svg class="i-icon" style="display:inline; vertical-align:middle;" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    Refresh
                </button>
            </div>
            <div class="table-responsive">
                <table class="cars-table">
                    <thead>
                        <tr>
                            <th>Number Plate</th>
                            <th>Driver Name</th>
                            <th>Destination</th>
                            <th>Entry Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="insideTableBody">
                    </tbody>
                </table>
            </div>
        </section>

    </div><!-- /right-content-area -->

</div><!-- /guard-two-col -->

<!-- ================================================================
     MODAL 1: GUARD PIN LOGIN
     ================================================================ -->
<div id="pinLoginModal" class="modal-overlay">
    <div class="modal-box" style="max-width:380px;">
        <div class="pin-modal-brand">
            <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall">
            <p>Basement Parking — Guard Login</p>
        </div>
        <p style="text-align:center; color:var(--text-muted); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em;">
            Enter Your 4-Digit Security PIN
        </p>
        <div class="pin-display">
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
                    <button type="button" class="floor-pill active" data-floor="ALL">All (48)</button>
                    <button type="button" class="floor-pill" data-floor="Ground Floor">Level G</button>
                    <button type="button" class="floor-pill" data-floor="1st Floor">Level 1</button>
                    <button type="button" class="floor-pill" data-floor="2nd Floor">Level 2</button>
                    <button type="button" class="floor-pill" data-floor="3rd Floor">Level 3</button>
                    <button type="button" class="floor-pill" data-floor="Basement">Basement</button>
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
                        <span id="intakeVerifiedBadge" class="badge-verified">✓ VERIFIED</span>
                        <span id="intakeTimeAgo" class="intake-time">Just now</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeVehicleIntakeModal()" class="modal-close-btn" title="Close">&#x2715;</button>
        </div>

        <div id="intakeAlertBanner" style="display:none;" class="badge-alert"></div>

        <!-- Form fields: Name, Phone, Destination -->
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

            <div class="form-field">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <label style="margin:0;">Destination Store / Floor</label>
                    <span style="font-size:10px; color:var(--text-muted); font-weight:700;">Tap quick pill:</span>
                </div>
                <!-- 1-Tap Quick Pills -->
                <div class="intake-pills-row" id="intakePillsContainer">
                    <button type="button" class="dest-pill-btn" onclick="selectIntakePill('Naivas Supermarket', this)">🛒 Naivas</button>
                    <button type="button" class="dest-pill-btn" onclick="selectIntakePill('NCBA Bank', this)">🏦 Bank / ATM</button>
                    <button type="button" class="dest-pill-btn" onclick="selectIntakePill('Food Court', this)">🍔 Food Court</button>
                    <button type="button" class="dest-pill-btn" onclick="selectIntakePill('Java House', this)">☕ Java House</button>
                    <button type="button" class="dest-pill-btn" onclick="selectIntakePill('Level 1 Retail', this)">Level 1</button>
                    <button type="button" class="dest-pill-btn" onclick="selectIntakePill('Level 2 Retail', this)">Level 2</button>
                    <button type="button" class="dest-pill-btn" onclick="selectIntakePill('Mombasa Mall', this)">Other</button>
                </div>
                <!-- Dropdown for all mall stores -->
                <select id="intakeDestSelect" class="card-dest-select" style="margin-top:8px;">
                    <!-- Populated dynamically -->
                </select>
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
     MODAL 5: OPERATION GUIDE
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

<!-- Toast Notification -->
<div id="toastMsg" class="toast-msg">
    <svg class="i-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastText">Action completed</span>
    <button id="btnToastReprint" class="btn-search-clear" style="display:none;">Reprint</button>
</div>

<!-- Bundled Local Libraries (NO CDN) -->
<script src="../assets/vendor/gsap/gsap.min.js"></script>
<script src="../assets/vendor/gsap/Flip.min.js"></script>
<script src="../assets/vendor/confetti/confetti.browser.min.js"></script>

<!-- Application Scripts -->
<script src="js/guard_i18n.js"></script>
<script src="js/guard.js"></script>

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
