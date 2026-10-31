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
            transition: all 0.15s ease; font-family: var(--font);
            box-shadow: 0 2px 8px rgba(17,111,199,0.25);
        }
        .btn-submit:hover    { background: var(--blue-hover); }
        .btn-submit:disabled { opacity: 0.45; cursor: not-allowed; }

        /* Button Tactile Micro-Interactions */
        button, .btn-submit, .btn-confirm-cam-plate, .btn-diff-plate, .btn-cam-refresh, .btn-again, .floor-tab {
            user-select: none;
            -webkit-user-select: none;
            transition: transform 0.12s cubic-bezier(0.16, 1, 0.3, 1),
                        background 0.15s ease,
                        border-color 0.15s ease,
                        box-shadow 0.15s ease;
        }
        button:active:not(:disabled),
        .btn-submit:active:not(:disabled),
        .btn-confirm-cam-plate:active:not(:disabled),
        .btn-diff-plate:active:not(:disabled),
        .btn-cam-refresh:active:not(:disabled),
        .btn-again:active:not(:disabled) {
            transform: translateY(1px) scale(0.975);
        }
        button:focus-visible {
            outline: 2px solid var(--blue-mid);
            outline-offset: 2px;
        }

        /* Soft Driver Floating Toast */
        .driver-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(120%);
            background: #ffffff;
            border: 1px solid var(--border);
            border-left: 4px solid var(--blue);
            color: var(--text);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(17, 111, 199, 0.18);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            pointer-events: none;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 90vw;
            line-height: 1.4;
        }
        .driver-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
            pointer-events: auto;
        }
        .driver-toast.warn {
            border-left-color: #f59e0b;
        }
        .driver-toast.error {
            border-left-color: var(--red);
        }

        @keyframes softShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-4px); }
            40%, 80% { transform: translateX(4px); }
        }
        .input-shake {
            animation: softShake 0.4s ease-in-out;
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
        }

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

        /* Multi-Car Camera Detection Live Section on Driver Self Sign-In */
        .cam-detected-section {
            margin: 14px 20px 0;
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f1fc 100%);
            border: 1.5px solid var(--blue-mid);
            border-left: 4px solid var(--blue);
            border-radius: 8px;
            padding: 12px 14px;
            box-shadow: 0 4px 14px rgba(17,111,199,0.12);
            display: flex;
            flex-direction: column;
            gap: 9px;
        }
        .cam-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .cam-header-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 800;
            color: var(--blue);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            flex-wrap: wrap;
        }
        .live-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.25);
            animation: pulse-ring 1.6s infinite;
        }
        @keyframes pulse-ring {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.6; }
        }
        .cam-count-chip {
            background: #dbeafe;
            color: var(--blue);
            border: 1px solid var(--blue-mid);
            font-size: 9.5px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 10px;
            letter-spacing: 0;
        }
        .btn-cam-refresh {
            height: 28px;
            padding: 0 10px;
            border-radius: 4px;
            background: #fff;
            border: 1px solid var(--border);
            color: var(--blue);
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-cam-refresh:hover {
            background: #eff6ff;
            border-color: var(--blue-mid);
        }
        .btn-cam-refresh.rotating svg {
            animation: refreshSpin 0.7s linear infinite;
        }
        .cam-section-hint {
            font-size: 11.5px;
            color: var(--text-sub);
            font-weight: 600;
            margin: 0;
            line-height: 1.4;
        }
        
        /* Carousel / Grid of Cars */
        .cam-cars-carousel {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 4px 2px 10px;
            margin: 0 -2px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .cam-cars-carousel::-webkit-scrollbar {
            height: 5px;
        }
        .cam-cars-carousel::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 3px;
        }
        .cam-cars-carousel::-webkit-scrollbar-thumb {
            background: #93c5fd;
            border-radius: 3px;
        }

        /* Individual Vehicle Card */
        .cam-car-card {
            flex: 0 0 172px;
            max-width: 172px;
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(17,111,199,0.06);
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            user-select: none;
        }
        .cam-car-card:hover {
            border-color: var(--blue-mid);
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(17,111,199,0.15);
        }
        .cam-car-card.selected {
            border-color: #059669;
            background: #f0fdf4;
            box-shadow: 0 0 0 2px #059669, 0 6px 16px rgba(5,150,105,0.18);
            transform: translateY(-2px);
        }
        .cam-car-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 9.5px;
            color: var(--text-mut);
            font-weight: 700;
        }
        .cam-car-time {
            font-size: 9.5px;
            color: var(--text-mut);
        }
        .cam-car-thumb {
            width: 100%;
            height: 54px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid var(--border);
            background: #f1f5f9;
        }
        .cam-car-placeholder-thumb {
            width: 100%;
            height: 54px;
            border-radius: 4px;
            border: 1px solid var(--border-l);
            background: #f1f7fc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .cam-car-plate-wrap {
            display: flex;
            justify-content: center;
            margin: 2px 0;
        }
        .cam-car-plate {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fef08a;
            border: 1.5px solid #000;
            color: #000;
            font-family: 'Courier New', 'Consolas', monospace;
            font-weight: 900;
            font-size: 14.5px;
            letter-spacing: 1.5px;
            padding: 2px 7px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .cam-car-plate .plate-flag {
            background: #116FC7;
            color: #fff;
            font-size: 8px;
            font-weight: 900;
            padding: 1px 3px;
            border-radius: 2px;
            letter-spacing: 0;
        }
        .cam-car-status-pill {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 6px;
            border-radius: 3px;
            text-align: center;
        }
        .cam-car-status-pill.needs {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .cam-car-status-pill.prefilled {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .btn-select-car {
            height: 28px;
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.12s ease;
            margin-top: 2px;
        }
        .btn-select-car:hover {
            background: var(--blue-hover);
        }
        .cam-car-card.selected .btn-select-car {
            background: #059669;
        }
        .cam-section-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 6px;
            border-top: 1px dashed var(--border);
            font-size: 11px;
        }
        .cam-footer-note {
            color: var(--text-mut);
            font-weight: 600;
        }
        .btn-manual-toggle {
            background: none;
            border: none;
            color: var(--blue);
            font-size: 11px;
            font-weight: 700;
            text-decoration: underline;
            cursor: pointer;
            padding: 2px 4px;
        }
        .btn-manual-toggle:hover {
            color: var(--blue-hover);
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

        /* Success Screen Details & Timing Cards */
        .success-details-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            text-align: left;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .success-card-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-mut);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 5px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 12px;
            border-bottom: 1px dashed #f8fafc;
        }
        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .detail-label {
            color: var(--text-mut);
            font-weight: 600;
            font-size: 11.5px;
        }
        .detail-value {
            color: var(--text);
            font-weight: 700;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }

        /* Timing highlight card */
        .timing-highlight-card {
            background: #fffdf5;
            border: 1px solid #fed7aa;
            border-left: 4px solid #ea580c;
            border-radius: 8px;
            padding: 12px 14px;
            text-align: left;
            margin-bottom: 14px;
        }
        .timing-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 12px;
        }
        .timing-row.highlight {
            background: #ffedd5;
            padding: 6px 8px;
            border-radius: 6px;
            margin: 4px 0;
        }
        .timing-label {
            color: #9a3412;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .timing-val {
            font-weight: 800;
            color: #7c2d12;
            font-family: 'Courier New', monospace;
            font-size: 12.5px;
        }
        .timing-val.out {
            color: #c2410c;
            font-size: 13.5px;
        }
        .penalty-warning-note {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #fdba74;
            font-size: 11px;
            color: #991b1b;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.4;
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
        }

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
            <div class="parking-policy-banner" id="parkingPolicyBanner">
                <div class="policy-badge-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="policy-text-area">
                    <div class="policy-title">Free Customer Parking &mdash; Limited to 2 Hours</div>
                    <p class="policy-desc">
                        Parking is complimentary for up to <strong>2 hours (120 minutes)</strong>.
                        <strong>Extension beyond 2 hours may lead to penalty fees.</strong>
                        Please collect your printed ticket from the guard booth and keep it safe for exit.
                    </p>
                </div>
            </div>

            <!-- Camera Live Detection & Vehicle Selection Strip for Arriving Drivers -->
            <div id="camDetectedSection" class="cam-detected-section" style="display:none;">
                <div class="cam-section-header">
                    <div class="cam-header-title">
                        <span class="live-pulse-dot"></span>
                        <span>GATE 1 LIVE ARRIVALS &bull; TAP YOUR CAR</span>
                        <span class="cam-count-chip" id="camCarsCountBadge">0 Cars</span>
                    </div>
                    <button type="button" class="btn-cam-refresh" id="btnCamRefresh" onclick="fetchCameraDetectedPlate(true)" title="Check entrance camera again for latest captures">
                        <svg class="i-icon-cam-sync" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        <span>Refresh</span>
                    </button>
                </div>
                <p class="cam-section-hint">Select your vehicle captured by the entrance camera to auto-fill your plate number:</p>

                <!-- Horizontal scrollable list of detected vehicles -->
                <div class="cam-cars-carousel" id="camCarsCarousel">
                    <!-- Populated dynamically by driver.js with detected cars -->
                </div>

                <!-- Footer helper with manual entry fallback link -->
                <div class="cam-section-footer">
                    <span class="cam-footer-note">Don't see your car in the camera list?</span>
                    <button type="button" class="btn-manual-toggle" onclick="focusManualPlateInput()">Enter Plate Manually &darr;</button>
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

                    <div class="form-section-label">Destination / Store (45 Stores)</div>

                    <!-- Selected store confirmation badge -->
                    <div id="selectedDestBanner" class="selected-dest-banner">
                        Selected: <strong id="selectedDestText">None</strong>
                    </div>

                    <!-- Search and Floor filters -->
                    <div class="dest-filter-bar">
                        <input type="text" id="destSearchInput" class="dest-search-input"
                               placeholder="Search store (e.g. Naivas, NCBA, Lovisa, Gym, Pizza)...">
                        
                        <div class="floor-tabs" id="driverFloorTabs">
                            <button type="button" class="floor-tab active" data-floor="ALL">All (45)</button>
                            <button type="button" class="floor-tab" data-floor="Ground Floor">Ground Floor (5)</button>
                            <button type="button" class="floor-tab" data-floor="1st Floor">First Floor (17)</button>
                            <button type="button" class="floor-tab" data-floor="2nd Floor">Second Floor (19)</button>
                            <button type="button" class="floor-tab" data-floor="3rd Floor">Third Floor (4)</button>
                        </div>
                    </div>

                    <div class="dest-grid" id="driverDestGrid">
                        <!-- Populated dynamically with all 45 shops by driver.js -->
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

                <!-- Driver & Destination Details -->
                <div class="success-details-card">
                    <div class="success-card-title">
                        <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Driver &amp; Visit Details
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Driver Name</span>
                        <span class="detail-value" id="successDriverName">--</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Mobile Number</span>
                        <span class="detail-value" id="successDriverPhone">--</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Destination</span>
                        <span class="detail-value" id="successDestination">--</span>
                    </div>
                </div>

                <!-- Timing & Stay Duration Card -->
                <div class="timing-highlight-card">
                    <div class="success-card-title" style="color:#9a3412; border-bottom:1px solid #fed7aa;">
                        <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Parking Time &amp; Stay Limit
                    </div>
                    <div class="timing-row">
                        <span class="timing-label">Time In:</span>
                        <span class="timing-val" id="successTimeIn">--:--:--</span>
                    </div>
                    <div class="timing-row">
                        <span class="timing-label">Parking Limit:</span>
                        <span class="timing-val" style="color:#15803d;">2 Hours (120 Mins) &mdash; Free</span>
                    </div>
                    <div class="timing-row highlight">
                        <span class="timing-label" style="color:#c2410c;">Expected Time Out:</span>
                        <span class="timing-val out" id="successTimeOut">--:--:--</span>
                    </div>
                    <div class="timing-row" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:6px 10px; margin:5px 0;">
                        <span class="timing-label" style="color:#166534; font-weight:800;">Remaining Free Time:</span>
                        <span class="timing-val" id="successCountdownTimer" style="color:#15803d; font-size:13.5px; font-weight:900;">02:00:00</span>
                    </div>
                    <div class="penalty-warning-note">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Parking is limited to 2 hours. Extension beyond 2 hours may lead to penalty fees.</span>
                    </div>
                </div>

                <!-- Next Step -->
                <div class="info-block">
                    <div class="label">
                        <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        Next Step &mdash; Gate Entry
                    </div>
                    <p>Your vehicle is queued for security approval. Please proceed to the <strong>Security Guard booth</strong> to collect your printed parking ticket.</p>
                </div>

                <button class="btn-again" onclick="resetForm()">Sign In Another Vehicle</button>
            </div>

        </div>
    </div>

    <footer class="sys-footer">
        <div>Mombasa Mall Basement Parking &mdash; Jomo Kenyatta Avenue, Mombasa &mdash; Kenya Data Protection Act 2019 Compliant</div>
    </footer>

    <!-- Soft Driver Toast Alert -->
    <div id="driverToast" class="driver-toast">
        <span id="driverToastIcon">ℹ️</span>
        <span id="driverToastText">Notification</span>
    </div>

    <script src="../assets/vendor/gsap/gsap.min.js"></script>
    <script src="js/driver.js?v=20261005_1722"></script>
    <script type="module" src="../assets/js/firebase-init.js?v=20261005_1722"></script>
</body>
</html>
