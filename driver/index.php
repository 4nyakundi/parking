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

        /* Success screen */
        .success-panel { display: none; padding: 32px 24px; text-align: center; }
        .success-panel.show { display: block; }
        .success-icon {
            width: 58px; height: 58px; border-radius: 50%;
            border: 2px solid var(--green-lt); background: var(--green-dim);
            color: var(--green-lt); display: inline-flex;
            align-items: center; justify-content: center; margin-bottom: 14px;
        }
        .success-icon svg { width: 28px; height: 28px; stroke: var(--green-lt); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .success-plate {
            font-size: 26px; font-weight: 900; color: var(--blue);
            background: #eef5fc; padding: 4px 18px;
            border-radius: 6px; letter-spacing: 3px;
            border: 2px solid var(--blue-mid); display: inline-block;
            margin: 10px 0 16px; font-family: 'Courier New', monospace;
        }
        .info-block {
            background: #f0f6fc; border: 1px solid var(--border);
            border-left: 3px solid var(--blue); border-radius: 0 6px 6px 0;
            padding: 14px 16px; text-align: left; margin-bottom: 20px;
        }
        .info-block .label { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: var(--text-mut); margin-bottom: 4px; }
        .info-block p { font-size: 13px; color: var(--text); font-weight: 600; line-height: 1.5; }
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
            padding: 10px 20px; text-align: center;
            font-size: 11px; color: var(--text-mut); font-weight: 600;
            flex-shrink: 0;
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
                <div style="font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:var(--green-lt); margin-bottom:6px;">
                    Sign-In Submitted Successfully
                </div>
                <div id="successPlate" class="success-plate">KDA 123A</div>
                <div class="info-block">
                    <div class="label">Next Step</div>
                    <p>Your vehicle is now in the queue for guard approval.
                       Please proceed to the Security Guard booth to collect your printed parking ticket.</p>
                </div>
                <button class="btn-again" onclick="resetForm()">Sign In Another Vehicle</button>
            </div>

        </div>
    </div>

    <footer class="sys-footer">
        Mombasa Mall Basement Parking &mdash; Jomo Kenyatta Avenue, Mombasa &mdash; Kenya Data Protection Act 2019 Compliant
    </footer>

    <script src="../assets/vendor/gsap/gsap.min.js"></script>
    <script src="js/driver.js"></script>
</body>
</html>
