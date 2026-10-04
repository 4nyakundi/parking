<?php
/**
 * Mombasa Mall Basement Parking - Admin & Supervisor Login
 * LIGHT THEME v4.0 | Palette: #116FC7 / #609FDA / #B0CFEC / #FFFFFF
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['supervisor', 'admin'], true)) {
    header('Location: index.php');
    exit;
}
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
            --card-alt:  #f5f9fe;
            --border:    #b0cfec;
            --border-l:  #d0e5f5;
            --blue:      #116FC7;
            --blue-mid:  #609FDA;
            --blue-light:#B0CFEC;
            --blue-dim:  rgba(17,111,199,0.08);
            --blue-hover:#0d5ca8;
            --text:      #0d2b4e;
            --text-sub:  #2e5c8a;
            --text-mut:  #6b92b8;
            --red:       #dc2626;
            --font:      system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --shadow:    0 8px 30px rgba(17,111,199,0.12), 0 0 0 1px var(--border);
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
            border-radius: 12px;
            padding: 36px 32px;
            max-width: 420px;
            width: 100%;
            box-shadow: var(--shadow);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .brand-header img.logo-icon {
            height: 48px;
            width: auto;
            object-fit: contain;
            margin-bottom: 12px;
        }
        .brand-header img.logo-wm {
            height: 24px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto 10px auto;
        }
        .brand-header h1 {
            font-size: 18px;
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
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.10em;
            color: var(--text-mut);
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #f0f6fc;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(17,111,199,0.10);
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
            box-shadow: 0 2px 8px rgba(17,111,199,0.25);
            transition: background 0.15s;
        }
        .btn-submit:hover { background: var(--blue-hover); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .error-msg {
            background: rgba(220, 38, 38, 0.08);
            border: 1px solid var(--red);
            color: var(--red);
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
            display: none;
        }
        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: var(--text-mut);
            line-height: 1.5;
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

        <div id="errorBox" class="error-msg"></div>

        <form id="loginForm" onsubmit="return false;">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" placeholder="e.g. admin or supervisor" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit" id="btnLogin">Sign In</button>
        </form>

        <div class="login-footer">
            Admin: <strong>admin</strong> / <strong>admin123</strong> &bull; Supervisor: <strong>supervisor</strong> / <strong>super123</strong><br>
            Security guards: Please access via <a href="../guard/">Guard Tablet Station</a>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnLogin');
            const errBox = document.getElementById('errorBox');
            errBox.style.display = 'none';

            const user = document.getElementById('username').value.trim();
            const pass = document.getElementById('password').value;

            btn.disabled = true;
            btn.textContent = 'Verifying...';

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
        });
    </script>
</body>
</html>
