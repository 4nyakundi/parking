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
            background: #111a2f;
            border: 2px solid #273553;
            border-radius: 20px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }
        .logo {
            width: 56px;
            height: 56px;
            background: #3b82f6;
            color: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px auto;
        }
        h1 { font-size: 22px; text-align: center; margin-bottom: 6px; }
        p { font-size: 14px; color: #94a3b8; text-align: center; margin-bottom: 24px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 700; color: #cbd5e1; margin-bottom: 6px; }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #090e17;
            border: 2px solid #273553;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
            outline: none;
        }
        input:focus { border-color: #3b82f6; }
        button {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 10px;
        }
        .err {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
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

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="management" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit">Access Cloud Mirror →</button>
        </form>
        <div style="text-align:center; margin-top:20px; font-size:12px; color:#64748b;">
            Default credentials: <strong>management</strong> / <strong>mall2026</strong>
        </div>
    </div>
</body>
</html>
