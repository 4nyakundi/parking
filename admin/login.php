<?php
/**
 * Mombasa Mall Basement Parking - Admin & Supervisor Login
 * LIGHT THEME v4.0 | Palette: #116FC7 / #609FDA / #B0CFEC / #FFFFFF
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
    <title>Management Portal Login — Mombasa Mall Parking</title>
    <style>
        :root {
            --bg:        #eef5fc;
            --card:      #ffffff;
            --card-alt:  #f8fafc;
            --border:    #b0cfec;
            --border-l:  #d0e5f5;
            --blue:      #116FC7;
            --blue-mid:  #609FDA;
            --blue-light:#B0CFEC;
            --blue-dim:  rgba(17,111,199,0.08);
            --blue-hover:#0d5ca8;
            --text:      #0d2b4e;
            --text-sub:  #2e5c8a;
            --text-mut:  #64748b;
            --red:       #dc2626;
            --green:     #059669;
            --font:      system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --shadow:    0 12px 36px rgba(17,111,199,0.14), 0 0 0 1px var(--border);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font); }
        body {
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-box {
            background: var(--card);
            border: 1px solid var(--border);
            border-top: 4px solid var(--blue);
            border-radius: 14px;
            padding: 36px 32px;
            max-width: 440px;
            width: 100%;
            box-shadow: var(--shadow);
            position: relative;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 22px;
        }
        .brand-header img.logo-icon {
            height: 48px;
            width: auto;
            object-fit: contain;
            margin-bottom: 10px;
        }
        .brand-header img.logo-wm {
            height: 22px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto 8px auto;
        }
        .brand-header h1 {
            font-size: 19px;
            font-weight: 900;
            color: var(--blue);
            letter-spacing: -0.01em;
        }
        .brand-header p {
            color: var(--text-sub);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 4px;
        }

        /* Notice Banner */
        .notice-banner {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Quick 1-Click Access Buttons */
        .quick-access-box {
            background: #f8fafc;
            border: 1px solid var(--border-l);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 20px;
        }
        .quick-access-label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-mut);
            margin-bottom: 10px;
            text-align: center;
        }
        .quick-access-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .btn-quick-role {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: left;
        }
        .btn-quick-role:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(17,111,199,0.12);
        }
        .btn-quick-role.admin:hover {
            border-color: var(--blue);
            background: #f0f7ff;
        }
        .btn-quick-role.super:hover {
            border-color: #059669;
            background: #f0fdf4;
        }
        .role-icon {
            font-size: 20px;
            line-height: 1;
        }
        .role-meta {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .role-meta strong {
            font-size: 12px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
        }
        .role-meta small {
            font-size: 10px;
            color: var(--text-mut);
            line-height: 1.2;
        }

        .or-divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-mut);
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin: 16px 0;
        }
        .or-divider::before, .or-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-l);
        }
        .or-divider:not(:empty)::before { margin-right: .8em; }
        .or-divider:not(:empty)::after { margin-left: .8em; }

        .form-group { margin-bottom: 16px; }
        label {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-sub);
            margin-bottom: 6px;
        }
        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }
        input:focus {
            background: #ffffff;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(17,111,199,0.12);
        }
        .btn-toggle-pw {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-mut);
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            padding: 4px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(17,111,199,0.25);
            transition: background 0.15s, transform 0.1s;
        }
        .btn-submit:hover { background: var(--blue-hover); transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

        .error-msg {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--red);
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
            display: none;
            line-height: 1.4;
        }
        .login-footer {
            margin-top: 22px;
            text-align: center;
            font-size: 12px;
            color: var(--text-mut);
            line-height: 1.6;
            padding-top: 16px;
            border-top: 1px solid var(--border-l);
        }
        .login-footer a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 700;
        }
        .login-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="login-box">
        <div class="brand-header">
            <img src="../assets/img/logo-icon.png" alt="Mombasa Mall" class="logo-icon">
            <img src="../assets/img/logo-wordmark.png" alt="Mombasa Mall" class="logo-wm">
            <h1>Management Portal</h1>
            <p>Supervisor &amp; Administrator Access</p>
        </div>

        <?php if ($notice === 'switch_role'): ?>
            <div class="notice-banner">
                <span>🔄</span>
                <span>Switched from Guard session. Please sign in as Supervisor or Admin.</span>
            </div>
        <?php elseif ($notice === 'session_expired'): ?>
            <div class="notice-banner">
                <span>⏱️</span>
                <span>Your session has expired. Please sign in again.</span>
            </div>
        <?php elseif ($notice === 'logged_out'): ?>
            <div class="notice-banner" style="background:#f0fdf4; border-color:#bbf7d0; color:#15803d;">
                <span>✓</span>
                <span>Logged out successfully.</span>
            </div>
        <?php endif; ?>

        <div id="errorBox" class="error-msg"></div>

        <!-- 1-Click Quick Role Switchers -->
        <div class="quick-access-box">
            <span class="quick-access-label">Instant 1-Click Sign-In</span>
            <div class="quick-access-grid">
                <button type="button" class="btn-quick-role admin" onclick="quickLogin('admin', 'admin123')">
                    <span class="role-icon">👑</span>
                    <span class="role-meta">
                        <strong>Administrator</strong>
                        <small>Full System Access</small>
                    </span>
                </button>
                <button type="button" class="btn-quick-role super" onclick="quickLogin('supervisor', 'super123')">
                    <span class="role-icon">🛡️</span>
                    <span class="role-meta">
                        <strong>Supervisor</strong>
                        <small>Shift &amp; Reconcile</small>
                    </span>
                </button>
            </div>
        </div>

        <div class="or-divider">Or enter custom credentials</div>

        <form id="loginForm" onsubmit="return false;">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" placeholder="e.g. admin or supervisor" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility()">SHOW</button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="btnLogin">Sign In</button>
        </form>

        <div class="login-footer">
            Admin: <strong>admin</strong> / <strong>admin123</strong> &bull; Supervisor: <strong>supervisor</strong> / <strong>super123</strong><br>
            Security guards: Access via <a href="../guard/">Guard Tablet Station</a>
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

        async function quickLogin(user, pass) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = pass;
            submitLogin(user, pass);
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
            btn.textContent = 'Signing In...';

            try {
                const res = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'password', username: user, password: pass })
                });
                const json = await res.json();

                btn.disabled = false;
                btn.textContent = 'Sign In';

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
                btn.textContent = 'Sign In';
                errBox.textContent = 'Connection error. Ensure XAMPP Apache & MySQL are running.';
                errBox.style.display = 'block';
            }
        }
    </script>
</body>
</html>
