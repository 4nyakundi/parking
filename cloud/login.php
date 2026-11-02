<?php
/**
 * Mombasa Mall Basement Parking - Remote Cloud Management Login
 * Industrial High-Security Gateway
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
    if (file_exists(__DIR__ . '/config.cloud.local.php')) {
        $localCfg = require __DIR__ . '/config.cloud.local.php';
        if (is_array($localCfg)) {
            $cfg = array_replace_recursive($cfg, $localCfg);
        }
    }
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
            $error = 'Invalid credentials. Access denied.';
        }
    } catch (Throwable $e) {
        $error = 'Database connection error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Executive Access - Mombasa Mall</title>
    <style>
        :root {
            --bg-base: #0b0f17;
            --panel: #111827;
            --panel-border: #1f293d;
            --border-highlight: #38bdf8;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #0284c7;
            --accent-hover: #0369a1;
            --input-bg: #090e17;
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
            color: var(--border-highlight);
            text-transform: uppercase;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .tls-badge {
            font-size: 10px;
            font-weight: 700;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 2px 7px;
            border-radius: 2px;
            letter-spacing: 0.05em;
        }
        .brand-block {
            text-align: center;
            margin-bottom: 28px;
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
        .err-banner {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px 12px;
            border-radius: 3px;
            margin-bottom: 20px;
            font-size: 12.5px;
            font-weight: 600;
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
    </style>
</head>
<body>
    <div class="login-card">
        <div class="header-meta">
            <span class="system-code">MOMBASA-MALL // CLOUD-GW</span>
            <span class="tls-badge">TLS ENCRYPTED</span>
        </div>

        <div class="brand-block">
            <h1 class="brand-title">Remote Cloud Control</h1>
            <p class="brand-subtitle">Executive Command &amp; Property Mirror</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="err-banner"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="cloudLoginForm">
            <div class="form-group">
                <label for="cloudUser">Management Operator</label>
                <input type="text" id="cloudUser" name="username" class="input-control" placeholder="Enter username" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="cloudPass">Security Passphrase</label>
                <input type="password" id="cloudPass" name="password" class="input-control" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-submit">Authenticate Cloud Access</button>
        </form>

        <div class="card-footer">
            Restricted Property Management Access.<br>
            All remote queries and synchronization actions are audited.
        </div>
    </div>
</body>
</html>
