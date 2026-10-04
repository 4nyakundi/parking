<?php
/**
 * Mombasa Mall Basement Parking - Admin & Supervisor Login
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
    <title>Management Login - Mombasa Mall Parking</title>
    <style>
        :root {
            --bg: #090e17;
            --card: #111a2f;
            --border: #273553;
            --text: #f8fafc;
            --blue: #3b82f6;
            --blue-glow: rgba(59, 130, 246, 0.3);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body {
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-box {
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
        }
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 900;
            margin: 0 auto 16px auto;
            color: #fff;
            box-shadow: 0 4px 16px var(--blue-glow);
        }
        h1 { font-size: 24px; font-weight: 800; text-align: center; margin-bottom: 6px; }
        p { color: #94a3b8; font-size: 14px; text-align: center; margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: 700; color: #cbd5e1; margin-bottom: 8px; }
        input {
            width: 100%;
            padding: 14px 16px;
            background: #090e17;
            border: 2px solid var(--border);
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus { border-color: var(--blue); }
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 16px var(--blue-glow);
            transition: transform 0.15s;
        }
        .btn-submit:active { transform: scale(0.98); }
        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
            display: none;
        }
        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }
    </style>
</head>
<body>

    <div class="login-box">
        <div class="logo">🛡️</div>
        <h1>Management Portal</h1>
        <p>Mombasa Mall Basement Parking Administration</p>

        <div id="errorBox" class="error-msg"></div>

        <form id="loginForm" onsubmit="return false;">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" placeholder="e.g. admin or supervisor" required autofocus>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit" id="btnLogin">Sign In →</button>
        </form>

        <div class="login-footer">
            Default credentials: <strong>admin</strong> / <strong>admin123</strong><br>
            Security guards: Please access via <a href="../guard/" style="color:#38bdf8; text-decoration:none;">Guard Station</a>
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
                btn.textContent = 'Sign In →';

                if (json.ok && json.data) {
                    if (json.data.role === 'guard') {
                        errBox.textContent = 'Guards must login using their PIN at the Guard Station.';
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
                btn.textContent = 'Sign In →';
                errBox.textContent = 'Connection error. Ensure XAMPP Apache & MySQL are running.';
                errBox.style.display = 'block';
            }
        });
    </script>
</body>
</html>
