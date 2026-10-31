<?php
/**
 * Mombasa Mall Basement Parking - Remote Cloud Management Login
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['cloud_user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $cfg = require __DIR__ . '/config.cloud.php';
    $dbCfg = $cfg['db'];

    try {
        $dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        require_once __DIR__ . '/schema_init.php';
        ensure_cloud_schema($pdo);

        $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, role FROM cloud_users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['cloud_user_id'] = $user['id'];
            $_SESSION['cloud_name']    = $user['full_name'];
            $_SESSION['cloud_role']    = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid management credentials.';
        }
    } catch (Throwable $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Management Login - Mombasa Mall</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body {
            background: #090e17;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .box {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-top: 4px solid #38bdf8;
            border-radius: 12px;
            padding: 36px 30px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .logo { font-size: 38px; text-align: center; margin-bottom: 12px; }
        h1 { font-size: 20px; font-weight: 900; text-align: center; margin-bottom: 6px; }
        p { font-size: 13px; color: #94a3b8; text-align: center; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 6px; }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #fff;
            font-size: 14px;
            outline: none;
        }
        input:focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #0284c7;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            margin-top: 10px;
        }
        button[type="submit"]:hover { background: #0369a1; }
        .err {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .btn-quick {
            width: 100%;
            padding: 10px;
            background: #1e293b;
            color: #38bdf8;
            border: 1px solid #334155;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 16px;
            transition: all 0.15s ease;
        }
        .btn-quick:hover {
            background: #334155;
            border-color: #38bdf8;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="logo">☁️</div>
        <h1>Remote Cloud Portal</h1>
        <p>Mombasa Mall Executive Management</p>

        <?php if (!empty($error)): ?>
            <div class="err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <button type="button" class="btn-quick" onclick="fillCloudDemo()">
            ⚡ 1-Click Sign-In (management / mall2026)
        </button>

        <form method="POST" id="cloudLoginForm">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="cloudUser" name="username" placeholder="management" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="cloudPass" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit">Access Cloud Mirror →</button>
        </form>
        <div style="text-align:center; margin-top:20px; font-size:12px; color:#64748b;">
            Default credentials: <strong>management</strong> / <strong>mall2026</strong>
        </div>
    </div>
    <script>
        function fillCloudDemo() {
            document.getElementById('cloudUser').value = 'management';
            document.getElementById('cloudPass').value = 'mall2026';
            document.getElementById('cloudLoginForm').submit();
        }
    </script>
</body>
</html>
