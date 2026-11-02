<?php
/**
 * Mombasa Mall Basement Parking - Admin & Supervisor Login
 * Industrial High-Security Gateway
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already authenticated as supervisor or admin, proceed directly to dashboard
if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['supervisor', 'admin'], true)) {
    header('Location: index.php');
    exit;
}

$notice = $_GET['notice'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Control Login — Mombasa Mall Parking</title>
    <style>
        :root {
            --bg-base: #0b0f17;
            --panel: #111827;
            --panel-border: #1f293d;
            --border-highlight: #0284c7;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #0284c7;
            --accent-hover: #0369a1;
            --input-bg: #090e17;
            --red: #ef4444;
            --green: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body {
            background: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-top: 3px solid var(--border-highlight);
            border-radius: 4px;
            padding: 38px 32px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 16px 36px rgba(0,0,0,0.45);
        }
        .header-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid #1e293b;
        }
        .system-code {
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.12em;
            color: #38bdf8;
            text-transform: uppercase;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .sec-badge {
            font-size: 10px;
            font-weight: 700;
            color: var(--green);
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 2px 7px;
            border-radius: 2px;
            letter-spacing: 0.05em;
        }
        .brand-block {
            text-align: center;
            margin-bottom: 26px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .brand-subtitle {
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 600;
        }
        .notice-banner {
            background: rgba(2, 132, 199, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #7dd3fc;
            padding: 9px 12px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notice-tag {
            font-size: 9.5px;
            font-weight: 800;
            background: #0284c7;
            color: #fff;
            padding: 2px 5px;
            border-radius: 2px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-control {
            width: 100%;
            padding: 11px 14px;
            background: var(--input-bg);
            border: 1px solid #334155;
            border-radius: 3px;
            color: #ffffff;
            font-size: 13.5px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .input-control:focus {
            border-color: var(--border-highlight);
            box-shadow: 0 0 0 1px var(--border-highlight);
        }
        .btn-toggle-pw {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            padding: 4px 6px;
            letter-spacing: 0.05em;
        }
        .btn-toggle-pw:hover {
            color: #ffffff;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: #ffffff;
            border: none;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.15s, transform 0.1s;
        }
        .btn-submit:hover {
            background: var(--accent-hover);
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--red);
            color: #fca5a5;
            padding: 10px 12px;
            border-radius: 3px;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 20px;
            display: none;
            line-height: 1.4;
        }
        .card-footer {
            margin-top: 26px;
            padding-top: 16px;
            border-top: 1px solid #1e293b;
            text-align: center;
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
        }
        .card-footer a {
            color: #38bdf8;
            text-decoration: none;
            font-weight: 600;
        }
        .card-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="header-meta">
            <span class="system-code">MOMBASA-MALL // OPERATIONAL-GATEWAY</span>
            <span class="sec-badge">RESTRICTED ACCESS</span>
        </div>

        <div class="brand-block">
            <h1 class="brand-title">Control Center Login</h1>
            <p class="brand-subtitle">Supervisor &amp; Administrator Access</p>
        </div>

        <?php if ($notice === 'switch_role'): ?>
            <div class="notice-banner">
                <span class="notice-tag">NOTICE</span>
                <span>Switched from Guard session. Authenticate as Supervisor or Admin.</span>
            </div>
        <?php elseif ($notice === 'session_expired'): ?>
            <div class="notice-banner">
                <span class="notice-tag">NOTICE</span>
                <span>Session expired. Please re-authenticate to continue.</span>
            </div>
        <?php elseif ($notice === 'logged_out'): ?>
            <div class="notice-banner" style="background:rgba(16,185,129,0.1); border-color:rgba(16,185,129,0.3); color:#86efac;">
                <span class="notice-tag" style="background:#10b981;">SUCCESS</span>
                <span>Logged out successfully.</span>
            </div>
        <?php endif; ?>

        <div id="errorBox" class="error-msg"></div>

        <form id="loginForm" onsubmit="return false;">
            <div class="form-group">
                <label for="username">Operator Identifier</label>
                <input type="text" id="username" class="input-control" placeholder="Enter username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Security Passphrase</label>
                <div class="input-wrap">
                    <input type="password" id="password" class="input-control" placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility()">SHOW</button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="btnLogin">Authenticate Credentials</button>
        </form>

        <div class="card-footer">
            Security &amp; Facility Operations Personnel Only.<br>
            Guard Tablet station available at <a href="../guard/">Guard Portal</a>.
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const pw = document.getElementById('password');
            const btn = document.querySelector('.btn-toggle-pw');
            if (pw.type === 'password') {
                pw.type = 'text';
                btn.textContent = 'HIDE';
            } else {
                pw.type = 'password';
                btn.textContent = 'SHOW';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const user = document.getElementById('username').value.trim();
            const pass = document.getElementById('password').value;
            submitLogin(user, pass);
        });

        async function submitLogin(user, pass) {
            const btn = document.getElementById('btnLogin');
            const errBox = document.getElementById('errorBox');
            errBox.style.display = 'none';

            if (!user || !pass) {
                errBox.textContent = 'Please enter both username and password.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Authenticating...';

            try {
                const res = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'password', username: user, password: pass })
                });
                const json = await res.json();

                btn.disabled = false;
                btn.textContent = 'Authenticate Credentials';

                if (json.ok && json.data) {
                    if (json.data.role === 'guard') {
                        errBox.textContent = 'Security Guards must login using PIN at the Guard Tablet Station.';
                        errBox.style.display = 'block';
                        return;
                    }
                    window.location.href = 'index.php';
                } else {
                    errBox.textContent = json.error || 'Invalid credentials.';
                    errBox.style.display = 'block';
                }
            } catch (err) {
                btn.disabled = false;
                btn.textContent = 'Authenticate Credentials';
                errBox.textContent = 'Connection error. Ensure XAMPP Apache & MySQL are running.';
                errBox.style.display = 'block';
            }
        }
    </script>
</body>
</html>
