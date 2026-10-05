<?php
/**
 * Mombasa Mall Basement Parking — Driver Mobile Self Sign-In
 * LIGHT THEME v4.0  |  Palette: #116FC7 / #609FDA / #B0CFEC / #FFFFFF
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vehicle Sign-In — Mombasa Mall Basement Parking</title>
    <style>
        :root {
            --bg:        #eef5fc;
            --card:      #ffffff;
            --card-alt:  #f5f9fe;
            --border:    #b0cfec;
            --border-l:  #d0e5f5;
            --blue:      #116FC7;
            --blue-mid:  #609FDA;
            --blue-light:#B0CFEC;
            --blue-dim:  rgba(17,111,199,0.08);
            --blue-hover:#0d5ca8;
            --green:     #0a6b43;
            --green-lt:  #0d8b58;
            --green-dim: rgba(10,107,67,0.10);
            --red:       #dc2626;
            --text:      #0d2b4e;
            --text-sub:  #2e5c8a;
            --text-mut:  #6b92b8;
            --font:      system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --shadow:    0 2px 16px rgba(17,111,199,0.12), 0 0 0 1px var(--border);
        }
        *, *::before, *::after {
            box-sizing: border-box; margin: 0; padding: 0;
            -webkit-tap-highlight-color: transparent;
        }
        html, body { height: 100%; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font);
            font-size: 14px;
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* System header */
        .sys-header {
            background: var(--blue);
            box-shadow: 0 2px 8px rgba(17,111,199,0.25);
            padding: 0 20px;
            height: 52px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .sys-header img.icon { height: 28px; width: auto; object-fit: contain; filter: brightness(0) invert(1); }
        .sys-header img.wm   { height: 20px; width: auto; object-fit: contain; filter: brightness(0) invert(1); opacity: 0.85; }
        .divider-v { width: 1px; height: 20px; background: rgba(255,255,255,0.25); }
        .sys-header-label {
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.12em; color: rgba(255,255,255,0.9);
        }

        /* Main */
        .main {
            flex: 1; display: flex; align-items: flex-start; justify-content: center;
            padding: 24px 16px 36px; overflow-y: auto;
        }

        .form-card {
            width: 100%; max-width: 480px;
            background: #fff;
            border: 1px solid var(--border);
            border-top: 4px solid var(--blue);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* Card header */
        .form-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 14px;
            background: var(--card-alt);
        }
        .form-card-header img { height: 36px; width: auto; object-fit: contain; }
        .form-card-header-text h2 { font-size: 16px; font-weight: 800; color: var(--blue); line-height: 1.2; }
        .form-card-header-text p {
            font-size: 11px; color: var(--text-sub); margin-top: 2px;
            text-transform: uppercase; letter-spacing: 0.07em; font-weight: 600;
        }

        .form-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }

        .form-section-label {
            font-size: 9px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.16em; color: var(--text-mut);
            padding-bottom: 6px; border-bottom: 1px solid var(--border);
        }

        .field { display: flex; flex-direction: column; gap: 5px; }
        .field label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.10em; color: var(--text-mut); }
        .field input {
            background: #f0f6fc;
            border: 1px solid var(--border);
            border-radius: 6px; padding: 11px 14px;
            font-size: 15px; font-weight: 600; color: var(--text);
            font-family: var(--font); outline: none;
            transition: border-color 0.15s, box-shadow 0.15s; width: 100%;
        }
        .field input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(17,111,199,0.10); }
        .field input.plate {
            text-transform: uppercase; letter-spacing: 4px; text-align: center;
            font-size: 26px; font-weight: 900; color: var(--blue);
            border-color: var(--blue-mid);
            background: #eef5fc;
            font-family: 'Courier New', 'Consolas', monospace;
        }
        .field input::placeholder { color: var(--text-mut); font-weight: 400; font-size: 13px; letter-spacing: normal; }
        .field .err { font-size: 11px; color: var(--red); display: none; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }

        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* Destination search & floor filter */
        .dest-filter-bar { display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px; }
        .dest-search-input {
            width: 100%; background: #f0f6fc; border: 1px solid var(--border);
            border-radius: 6px; padding: 9px 12px; font-size: 13px; color: var(--text);
            font-family: var(--font); outline: none; transition: border-color 0.15s, box-shadow 0.15s;
        }
        .dest-search-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(17,111,199,0.10); }
        .floor-tabs { display: flex; gap: 4px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none; }
        .floor-tabs::-webkit-scrollbar { display: none; }
        .floor-tab {
            background: #f0f6fc; border: 1px solid var(--border); border-radius: 4px;
            padding: 5px 10px; font-size: 11px; font-weight: 700; color: var(--text-secondary);
            cursor: pointer; white-space: nowrap; transition: all 0.15s; font-family: var(--font);
        }
        .floor-tab:hover { border-color: var(--blue-mid); color: var(--blue); }
        .floor-tab.active { background: var(--blue); border-color: var(--blue); color: #fff; }
        .selected-dest-banner {
            display: none; background: var(--blue-dim); border: 1px solid var(--blue-mid);
            border-radius: 6px; padding: 8px 12px; font-size: 12px; font-weight: 700; color: var(--blue); margin-bottom: 8px;
        }
        .dest-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 6px;
            max-height: 250px; overflow-y: auto; padding: 2px;
        }
        .dest-card {
            background: #f0f6fc; border: 1px solid var(--border); border-radius: 6px;
            padding: 8px 10px; text-align: left; font-weight: 700; font-size: 12px;
            color: var(--text-sub); cursor: pointer; transition: all 0.15s; line-height: 1.3;
            display: flex; flex-direction: column; gap: 3px;
        }
        .dest-card:hover { border-color: var(--blue-mid); color: var(--blue); background: var(--blue-dim); }
        .dest-card.selected {
            border-color: var(--blue); background: var(--blue); color: #fff;
            box-shadow: 0 2px 6px rgba(17,111,199,0.25);
        }
        .dest-card .unit-badge {
            font-size: 9px; font-weight: 800; letter-spacing: 0.05em; color: var(--blue);
            background: rgba(17,111,199,0.12); border-radius: 3px; padding: 1px 5px; width: fit-content;
        }
        .dest-card.selected .unit-badge { background: rgba(255,255,255,0.25); color: #fff; }
        .dest-card .dest-category { font-size: 10px; color: var(--text-mut); font-weight: 500; }
        .dest-card.selected .dest-category { color: rgba(255,255,255,0.85); }
        .dest-error { font-size: 11px; color: var(--red); display: none; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }

        .consent-row { display: flex; align-items: flex-start; gap: 10px; font-size: 12px; color: var(--text-sub); line-height: 1.5; }
        .consent-row input[type=checkbox] { width: 16px; height: 16px; accent-color: var(--blue); margin-top: 2px; flex-shrink: 0; }

        .btn-submit {
            width: 100%; background: var(--blue); color: #fff;
            border: none; border-radius: 6px; height: 50px;
            font-size: 13px; font-weight: 800; letter-spacing: 0.07em;
            text-transform: uppercase; cursor: pointer;
            transition: background 0.15s; font-family: var(--font);
            box-shadow: 0 2px 8px rgba(17,111,199,0.25);
        }
        .btn-submit:hover    { background: var(--blue-hover); }
        .btn-submit:disabled { opacity: 0.45; cursor: not-allowed; }

        /* Parking Policy Banner on Form */
        .parking-policy-banner {
            margin: 14px 20px 0;
            background: #fffdf5;
            border: 1px solid #fed7aa;
            border-left: 4px solid #ea580c;
            border-radius: 6px;
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
        }
        .policy-badge-icon {
            color: #ea580c;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .policy-badge-icon svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2.2; }
        .policy-text-area { flex: 1; min-width: 0; }
        .policy-text-area .policy-title {
            font-size: 12px;
            font-weight: 800;
            color: #9a3412;
            letter-spacing: 0.02em;
            margin-bottom: 2px;
        }
        .policy-text-area .policy-desc {
            font-size: 11.5px;
            color: #7c2d12;
            line-height: 1.45;
            margin: 0;
        }

        /* Success screen */
        .success-panel { display: none; padding: 28px 22px; text-align: center; }
        .success-panel.show { display: block; }
        .success-icon {
            width: 56px; height: 56px; border-radius: 50%;
            border: 2px solid var(--green-lt); background: var(--green-dim);
            color: var(--green-lt); display: inline-flex;
            align-items: center; justify-content: center; margin-bottom: 12px;
        }
        .success-icon svg { width: 28px; height: 28px; stroke: var(--green-lt); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .success-title {
            font-size: 13px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.1em; color: var(--green-lt); margin-bottom: 6px;
        }
        .success-plate {
            font-size: 26px; font-weight: 900; color: var(--blue);
            background: #eef5fc; padding: 4px 18px;
            border-radius: 6px; letter-spacing: 3px;
            border: 2px solid var(--blue-mid); display: inline-block;
            margin: 8px 0 16px; font-family: 'Courier New', monospace;
        }
        .info-block {
            background: #f0f6fc; border: 1px solid var(--border);
            border-left: 3px solid var(--blue); border-radius: 0 6px 6px 0;
            padding: 12px 14px; text-align: left; margin-bottom: 14px;
        }
        .info-block .label { font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: var(--blue); margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .info-block p { font-size: 12.5px; color: var(--text); font-weight: 600; line-height: 1.5; margin: 0; }

        /* Policy & Penalty Warning Card */
        .policy-card {
            background: #fffdf5;
            border: 1px solid #fed7aa;
            border-left: 4px solid #ea580c;
            border-radius: 6px;
            padding: 12px 14px;
            text-align: left;
            margin-bottom: 14px;
        }
        .policy-card-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #c2410c;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }
        .policy-card-title svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2.2; flex-shrink: 0; }
        .policy-rules-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .policy-rules-list li {
            font-size: 12px;
            color: #431407;
            line-height: 1.45;
            position: relative;
            padding-left: 16px;
        }
        .policy-rules-list li::before {
            content: "•";
            position: absolute;
            left: 2px;
            top: -1px;
            font-size: 16px;
            color: #ea580c;
            font-weight: 900;
        }
        .policy-rules-list li strong {
            color: #9a3412;
            font-weight: 700;
        }

        /* Facility / Additional Info Card */
        .facility-info-card {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 14px;
            text-align: left;
            margin-bottom: 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .fac-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-sub);
            font-weight: 600;
        }
        .fac-item svg {
            width: 14px; height: 14px; stroke: var(--blue); fill: none; stroke-width: 2; flex-shrink: 0;
        }

        /* Powered by badge */
        .powered-by-box {
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 9px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 11.5px;
            color: var(--text-sub);
        }
        .powered-by-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--blue);
            font-weight: 800;
            text-decoration: none;
            transition: color 0.15s;
        }
        .powered-by-link:hover {
            color: #0c4a6e;
            text-decoration: underline;
        }
        .powered-by-link svg {
            width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.2;
        }

        .btn-again {
            width: 100%; background: transparent; border: 1px solid var(--border);
            color: var(--text-sub); border-radius: 6px; height: 44px;
            font-size: 12px; font-weight: 700; cursor: pointer;
            font-family: var(--font); text-transform: uppercase; letter-spacing: 0.06em;
            transition: all 0.15s;
        }
        .btn-again:hover { background: var(--blue-dim); color: var(--blue); border-color: var(--blue-mid); }

        .hp-field { display: none !important; }

        .sys-footer {
            background: #fff; border-top: 1px solid var(--border);
            padding: 12px 20px; text-align: center;
            font-size: 11px; color: var(--text-mut); font-weight: 600;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .sys-footer a { color: var(--blue); font-weight: 700; text-decoration: none; }
        .sys-footer a:hover { text-decoration: underline; }

        @media (max-width: 540px) {
            .sys-header { padding: 0 12px; height: 48px; gap: 8px; }
            .sys-header img.icon { height: 24px; }
            .sys-header img.wm { display: none; }
            .divider-v { display: none; }
            .sys-header-label { font-size: 9.5px; }

            .main { padding: 12px 10px 24px; }
            .form-card { border-radius: 8px; }
            .form-card-header { padding: 14px 16px; gap: 10px; }
            .form-card-header img { height: 28px; }
            .form-card-header-text h2 { font-size: 14.5px; }
            .form-card-header-text p { font-size: 9.5px; }
            .parking-policy-banner { margin: 10px 14px 0; }
            .form-body { padding: 16px 14px; gap: 12px; }

            .field-row { grid-template-columns: 1fr; gap: 12px; }
            .field input { font-size: 16px; padding: 10px 12px; }
            .field input.plate {
                font-size: 22px;
                letter-spacing: 2px;
                padding: 8px 10px;
            }
            .dest-grid {
                grid-template-columns: 1fr;
                gap: 5px;
                max-height: 220px;
            }
            .btn-submit { height: 48px; font-size: 12.5px; }
            .success-plate { font-size: 22px; letter-spacing: 2px; padding: 4px 14px; }
            .facility-info-card { grid-template-columns: 1fr; gap: 6px; }
        }
    </style>
</head>
<body>

    <header class="sys-header">
        <img src="../assets/img/logo-icon.png" alt="Mombasa Mall" class="icon">
        <div class="divider-v"></div>
        <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall" class="wm">
        <div class="divider-v"></div>
        <span class="sys-header-label">Basement Parking &mdash; Vehicle Self Sign-In</span>
    </header>

    <div class="main">
        <div class="form-card">

            <div class="form-card-header">
                <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall">
                <div class="form-card-header-text">
                    <h2>Vehicle Sign-In</h2>
                    <p>Basement Parking &mdash; Free &mdash; 60 Slots</p>
                </div>
            </div>

            <input type="text" id="hpWebsite" name="hp_website" class="hp-field" tabindex="-1" autocomplete="off">

            <!-- Parking Duration & Penalty Policy Notice -->
            <div class="parking-policy-banner">
                <div class="policy-badge-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="policy-text-area">
                    <div class="policy-title">Free Customer Parking &mdash; Limited to 2.5 Hours</div>
                    <p class="policy-desc">
                        Parking is complimentary for up to <strong>2.5 hours (150 minutes)</strong>.
                        <strong>Extension beyond 2.5 hours may lead to penalty fees.</strong>
                        Please collect your printed ticket from the guard booth and keep it safe for exit.
                    </p>
                </div>
            </div>

            <form id="driverForm" onsubmit="return false;">
                <div class="form-body">

                    <div class="form-section-label">Vehicle Information</div>

                    <div class="field">
                        <label for="inputPlate">Number Plate</label>
                        <input type="text" id="inputPlate" class="plate"
                               placeholder="KDA 123A" maxlength="12"
                               autofocus autocomplete="off" spellcheck="false">
                        <span id="plateError" class="err">Invalid plate number</span>
                    </div>

                    <div class="form-section-label">Driver Information</div>

                    <div class="field-row">
                        <div class="field">
                            <label for="inputName">Full Name</label>
                            <input type="text" id="inputName" placeholder="e.g. Samuel Mwangi" autocomplete="name">
                        </div>
                        <div class="field">
                            <label for="inputPhone">Mobile Number</label>
                            <input type="tel" id="inputPhone" placeholder="0712 345 678" autocomplete="tel">
                            <span id="phoneError" class="err">Invalid phone number</span>
                        </div>
                    </div>

                    <div class="form-section-label">Destination / Store (48 Stores)</div>

                    <!-- Selected store confirmation badge -->
                    <div id="selectedDestBanner" class="selected-dest-banner">
                        Selected: <strong id="selectedDestText">None</strong>
                    </div>

                    <!-- Search and Floor filters -->
                    <div class="dest-filter-bar">
                        <input type="text" id="destSearchInput" class="dest-search-input"
                               placeholder="Search store (e.g. Naivas, NCBA, Lovisa, Gym, Pizza)...">
                        
                        <div class="floor-tabs" id="driverFloorTabs">
                            <button type="button" class="floor-tab active" data-floor="ALL">All (48)</button>
                            <button type="button" class="floor-tab" data-floor="Ground Floor">Level G (5)</button>
                            <button type="button" class="floor-tab" data-floor="1st Floor">Level 1 (17)</button>
                            <button type="button" class="floor-tab" data-floor="2nd Floor">Level 2 (19)</button>
                            <button type="button" class="floor-tab" data-floor="3rd Floor">Level 3 (4)</button>
                            <button type="button" class="floor-tab" data-floor="Basement">Basement (3)</button>
                        </div>
                    </div>

                    <div class="dest-grid" id="driverDestGrid">
                        <!-- Populated dynamically with all 48 shops by driver.js -->
                    </div>
                    <span id="destError" class="dest-error">Please select your destination store</span>

                    <div class="consent-row">
                        <input type="checkbox" id="chkConsent" checked>
                        <label for="chkConsent">
                            I confirm this information is accurate and consent to vehicle check-in
                            under the Kenya Data Protection Act 2019.
                        </label>
                    </div>

                    <button type="button" class="btn-submit" id="btnSubmitSignIn" onclick="submitDriverSignIn()">
                        Submit Sign-In Request
                    </button>

                </div>
            </form>

            <div class="success-panel" id="successBox">
                <div class="success-icon">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="success-title">
                    Sign-In Submitted Successfully
                </div>
                <div id="successPlate" class="success-plate">KDA 123A</div>

                <!-- Next Step -->
                <div class="info-block">
                    <div class="label">
                        <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        Next Step &mdash; Gate Entry
                    </div>
                    <p>Your vehicle is now queued for security verification. Please proceed to the <strong>Security Guard booth</strong> at the entrance boom barrier to collect your printed parking ticket.</p>
                </div>

                <!-- Critical Parking Regulations & Penalty Warning -->
                <div class="policy-card">
                    <div class="policy-card-title">
                        <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Important Parking Regulations &amp; Time Limit
                    </div>
                    <ul class="policy-rules-list">
                        <li>
                            <strong>Free Parking Limit:</strong> Customer parking is strictly limited to <strong>2.5 Hours (150 Minutes)</strong> from time of entry.
                        </li>
                        <li>
                            <strong>Overstay Penalty:</strong> <strong>Extension beyond the 2.5-hour limit may lead to penalty fees</strong> or vehicle wheel clamping as per Mombasa Mall regulations.
                        </li>
                        <li>
                            <strong>Ticket Retention:</strong> Retain your printed ticket at all times. Lost tickets are subject to a standard lost-ticket penalty fee plus verification of vehicle ownership.
                        </li>
                        <li>
                            <strong>Exit Boom Gate:</strong> Present your printed ticket to the security officer at the exit gate for verification before departing.
                        </li>
                    </ul>
                </div>

                <!-- Facility & Additional Info -->
                <div class="facility-info-card">
                    <div class="fac-item">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>24/7 CCTV Monitored Facility</span>
                    </div>
                    <div class="fac-item">
                        <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span>Security Desk at Basement Level</span>
                    </div>
                    <div class="fac-item">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Mall Hours: 08:00 &ndash; 22:00 Daily</span>
                    </div>
                    <div class="fac-item">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>Kenya DPA 2019 Compliant</span>
                    </div>
                </div>

                <!-- Powered by DataPort.inc -->
                <div class="powered-by-box">
                    <span>System Powered by</span>
                    <a href="https://dpinc.top" target="_blank" rel="noopener" class="powered-by-link">
                        DataPort.inc (dpinc.top)
                        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                </div>

                <button class="btn-again" onclick="resetForm()">Sign In Another Vehicle</button>
            </div>

        </div>
    </div>

    <footer class="sys-footer">
        <div>Mombasa Mall Basement Parking &mdash; Jomo Kenyatta Avenue, Mombasa &mdash; Kenya Data Protection Act 2019 Compliant</div>
        <div>System Powered by <a href="https://dpinc.top" target="_blank" rel="noopener">DataPort.inc (dpinc.top)</a></div>
    </footer>

    <script src="../assets/vendor/gsap/gsap.min.js"></script>
    <script src="js/driver.js"></script>
    <script type="module" src="../assets/js/firebase-init.js"></script>
</body>
</html>
