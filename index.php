<?php
/**
 * Mombasa Mall Basement Parking — Portal Router
 * LIGHT THEME v4.0  |  Palette: #116FC7 / #609FDA / #B0CFEC / #FFFFFF
 */
$ua       = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isMobile = str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mombasa Mall — Basement Parking Management System</title>
    <style>
        :root {
            --bg:        #eef5fc;
            --panel:     #ffffff;
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
            --amber:     #d97706;
            --purple:    #6d28d9;
            --text:      #0d2b4e;
            --text-sub:  #2e5c8a;
            --text-mut:  #6b92b8;
            --font:      system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --shadow:    0 1px 4px rgba(17,111,199,0.10), 0 0 0 1px var(--border);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font); }
        html, body { height: 100%; }
        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Top system bar */
        .sys-bar {
            background: var(--blue);
            box-shadow: 0 2px 8px rgba(17,111,199,0.25);
            padding: 0 32px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sys-bar-brand { display: flex; align-items: center; gap: 14px; }
        .sys-bar-brand img.icon { height: 32px; width: auto; object-fit: contain; filter: brightness(0) invert(1); }
        .sys-bar-brand img.wm   { height: 24px; width: auto; object-fit: contain; filter: brightness(0) invert(1); opacity: 0.85; }
        .divider-v { width: 1px; height: 24px; background: rgba(255,255,255,0.25); }
        .sys-bar-title {
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.12em; color: rgba(255,255,255,0.92);
        }
        .sys-bar-right {
            display: flex; align-items: center; gap: 16px;
            font-size: 11px; color: rgba(255,255,255,0.75); font-weight: 600;
        }
        .sys-badge {
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.30);
            border-radius: 4px; padding: 3px 10px;
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.08em; color: #fff;
        }

        /* Main body */
        .portal-body {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 36px 20px;
        }
        .portal-wrapper { width: 100%; max-width: 700px; }

        /* Hero card */
        .portal-hero {
            margin-bottom: 28px;
            display: flex; align-items: center; gap: 24px;
            padding: 24px 28px;
            background: #fff;
            border: 1px solid var(--border);
            border-top: 4px solid var(--blue);
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(17,111,199,0.10);
        }
        .portal-hero img.hero-logo { height: 60px; width: auto; object-fit: contain; flex-shrink: 0; }
        .portal-hero-text h1 {
            font-size: 20px; font-weight: 900; color: var(--blue);
            letter-spacing: -0.01em; line-height: 1.2;
        }
        .portal-hero-text p {
            font-size: 12px; color: var(--text-sub); margin-top: 4px;
            text-transform: uppercase; letter-spacing: 0.07em; font-weight: 600;
        }
        .sys-status-strip { display: flex; gap: 16px; margin-top: 12px; }
        .status-chip { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-sub); font-weight: 600; }
        .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-lt); }
        .dot.blue { background: var(--blue); }

        /* Group */
        .portal-group { margin-bottom: 22px; }
        .portal-group-label {
            font-size: 9px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.16em; color: var(--text-mut);
            margin-bottom: 8px; padding-bottom: 5px;
            border-bottom: 1px solid var(--border);
        }
        .portal-links { display: flex; flex-direction: column; gap: 8px; }

        .portal-link {
            background: #fff;
            border: 1px solid var(--border);
            border-left-width: 4px;
            border-radius: 8px;
            padding: 15px 20px;
            text-decoration: none;
            color: var(--text);
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(17,111,199,0.06);
            transition: all 0.15s;
        }
        .portal-link:hover {
            box-shadow: 0 3px 12px rgba(17,111,199,0.14);
            border-color: var(--blue-mid);
            transform: translateY(-1px);
        }

        .portal-link.guard  { border-left-color: var(--blue); }
        .portal-link.driver { border-left-color: var(--blue-mid); }
        .portal-link.admin  { border-left-color: var(--green-lt); }
        .portal-link.cloud  { border-left-color: var(--purple); }
        .portal-link.util   { border-left-color: var(--border); }

        .portal-link-inner { flex: 1; }
        .portal-link-title {
            display: block; font-size: 14px; font-weight: 800;
            letter-spacing: 0.01em; margin-bottom: 2px;
        }
        .portal-link.guard  .portal-link-title { color: var(--blue); }
        .portal-link.driver .portal-link-title { color: var(--blue-mid); }
        .portal-link.admin  .portal-link-title { color: var(--green-lt); }
        .portal-link.cloud  .portal-link-title { color: var(--purple); }
        .portal-link.util   .portal-link-title { color: var(--text-sub); }

        .portal-link-sub { font-size: 11px; color: var(--text-mut); font-weight: 500; }
        .portal-arrow {
            width: 18px; height: 18px; stroke: var(--blue-light); fill: none;
            stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
            flex-shrink: 0; transition: stroke 0.15s;
        }
        .portal-link:hover .portal-arrow { stroke: var(--blue); }

        /* Footer */
        .sys-footer {
            background: #fff;
            border-top: 1px solid var(--border);
            padding: 12px 32px;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 11px; color: var(--text-mut);
            font-weight: 600;
        }
        .sys-footer img { height: 18px; width: auto; object-fit: contain; opacity: 0.5; }

        @media (max-width: 640px) {
            .sys-bar {
                padding: 0 14px;
                height: 50px;
            }
            .sys-bar-brand img.wm { display: none; }
            .divider-v { display: none; }
            .sys-bar-title { font-size: 10px; }
            .sys-bar-right span:first-child { display: none; }
            .sys-badge { font-size: 9px; padding: 2px 7px; }

            .portal-body { padding: 18px 12px; }
            .portal-hero {
                flex-direction: column;
                text-align: center;
                padding: 18px 16px;
                gap: 12px;
                margin-bottom: 20px;
            }
            .portal-hero img.hero-logo { height: 48px; }
            .portal-hero-text h1 { font-size: 17px; }
            .portal-hero-text p { font-size: 11px; }
            .sys-status-strip {
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
            }
            .portal-group { margin-bottom: 18px; }
            .portal-link {
                padding: 12px 14px;
                gap: 10px;
            }
            .portal-link-title { font-size: 13px; }
            .portal-link-sub { font-size: 10.5px; }

            .sys-footer {
                padding: 10px 14px;
                flex-direction: column;
                gap: 6px;
                text-align: center;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>

    <header class="sys-bar">
        <div class="sys-bar-brand">
            <img src="assets/img/logo-icon.png" alt="Mombasa Mall" class="icon">
            <div class="divider-v"></div>
            <img src="assets/img/logo-wordmark.png" alt="Mombasa Mall" class="wm">
            <div class="divider-v"></div>
            <span class="sys-bar-title">Basement Parking Management System</span>
        </div>
        <div class="sys-bar-right">
            <span>Mombasa, Kenya</span>
            <span class="sys-badge">60 Slots</span>
            <span id="sysTime" style="font-family:'Courier New',monospace; font-size:13px; color:rgba(255,255,255,0.9);"></span>
        </div>
    </header>

    <main class="portal-body">
        <div class="portal-wrapper">

            <div class="portal-hero">
                <img src="assets/img/logo-wordmark.png" alt="Mombasa Mall" class="hero-logo">
                <div class="portal-hero-text">
                    <h1>Mombasa Mall Basement Parking</h1>
                    <p>Automated Parking Management System &mdash; Select Access Portal</p>
                    <div class="sys-status-strip">
                        <div class="status-chip"><div class="dot"></div> System Online</div>
                        <div class="status-chip"><div class="dot blue"></div> Database Connected</div>
                        <div class="status-chip"><div class="dot"></div> Camera Feed Ready</div>
                    </div>
                </div>
            </div>

            <div class="portal-group">
                <div class="portal-group-label">Operational Portals</div>
                <div class="portal-links">
                    <a href="guard/" class="portal-link guard">
                        <div class="portal-link-inner">
                            <span class="portal-link-title">Guard Control Station</span>
                            <span class="portal-link-sub">Tablet interface &mdash; entrance approval, exit clearing, ticket printing</span>
                        </div>
                        <svg class="portal-arrow" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                    <a href="driver/" class="portal-link driver">
                        <div class="portal-link-inner">
                            <span class="portal-link-title">Driver Self Sign-In</span>
                            <span class="portal-link-sub">Mobile page accessed via basement entrance QR code</span>
                        </div>
                        <svg class="portal-arrow" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>

            <div class="portal-group">
                <div class="portal-group-label">Management Portals</div>
                <div class="portal-links">
                    <a href="admin/" class="portal-link admin">
                        <div class="portal-link-inner">
                            <span class="portal-link-title">Supervisor &amp; Admin Panel</span>
                            <span class="portal-link-sub">Sessions, audit logs, reports, backups, user management, settings</span>
                        </div>
                        <svg class="portal-arrow" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                    <a href="cloud/" class="portal-link cloud">
                        <div class="portal-link-inner">
                            <span class="portal-link-title">Remote Management Dashboard</span>
                            <span class="portal-link-sub">Cloud-mirrored view &mdash; accessible from any location</span>
                        </div>
                        <svg class="portal-arrow" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>

            <div class="portal-group">
                <div class="portal-group-label">Utilities</div>
                <div class="portal-links">
                    <a href="driver/poster.html" class="portal-link util" target="_blank">
                        <div class="portal-link-inner">
                            <span class="portal-link-title">Printable QR Billboard Poster</span>
                            <span class="portal-link-sub">A3/A4 printable sign for basement ramp display</span>
                        </div>
                        <svg class="portal-arrow" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>

        </div>
    </main>

    <footer class="sys-footer">
        <span>Mombasa Mall Basement Parking v4.0 &mdash; Jomo Kenyatta Avenue, Mombasa, Kenya</span>
        <img src="assets/img/logo-icon.png" alt="Mombasa Mall">
    </footer>

    <script>
        (function clock() {
            const el = document.getElementById('sysTime');
            if (el) el.textContent = new Date().toLocaleTimeString('en-GB', { hour12: false });
            setTimeout(clock, 1000);
        })();
    </script>
</body>
</html>
