<?php
/**
 * One-time admin account setup script.
 * Visit http://localhost/outsourced/setup-admin.php in Chrome.
 * DELETE THIS FILE immediately after use.
 */

// Only allow running from localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Access denied.');
}

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/security.php';

$message = '';
$done    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? 'admin');
    $password  = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? 'Administrator');

    if (strlen($password) < 8) {
        $message = '❌ Password must be at least 8 characters.';
    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = '❌ Invalid request. Please try again.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Create admin_users table if it doesn't exist
            $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                username      VARCHAR(50)  NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role          VARCHAR(20)  NOT NULL DEFAULT 'admin',
                full_name     VARCHAR(100) NOT NULL,
                active        TINYINT(1)   NOT NULL DEFAULT 1,
                created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");

            // Insert or update admin
            $existing = fetchOne("SELECT id FROM admin_users WHERE username = ?", [$username]);
            if ($existing) {
                query("UPDATE admin_users SET password_hash = ?, full_name = ?, active = 1 WHERE username = ?",
                      [$hash, $full_name, $username]);
                $message = "✅ Admin account <strong>$username</strong> updated successfully.";
            } else {
                db_insert('admin_users', [
                    'username'      => $username,
                    'password_hash' => $hash,
                    'role'          => 'admin',
                    'full_name'     => $full_name,
                    'active'        => 1,
                ]);
                $message = "✅ Admin account <strong>$username</strong> created successfully.";
            }
            $done = true;
        } catch (Exception $e) {
            $message = '❌ Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Setup — Outsourced Technologies</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 480px; margin: 80px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 32px; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
        h2 { margin-top: 0; color: #0d6efd; }
        label { display: block; margin-top: 14px; font-weight: bold; font-size: 14px; }
        input { width: 100%; padding: 8px 10px; margin-top: 4px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 15px; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #0d6efd; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        button:hover { background: #0b5ed7; }
        .msg { margin-top: 16px; padding: 12px; border-radius: 5px; background: #e8f5e9; border: 1px solid #a5d6a7; }
        .warn { background: #fff3e0; border-color: #ffcc80; color: #e65100; margin-top: 16px; padding: 12px; border-radius: 5px; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h2>🔧 Admin Account Setup</h2>

    <?php if ($message): ?>
        <div class="msg"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($done): ?>
        <div class="warn">
            ⚠️ <strong>Security:</strong> Delete this file immediately!<br>
            <code>c:/xampp/htdocs/outsourced/setup-admin.php</code><br><br>
            <a href="admin/">→ Go to Admin Login</a>
        </div>
    <?php else: ?>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" value="admin" required>

            <label>Full Name</label>
            <input type="text" name="full_name" value="Administrator" required>

            <label>Password (min 8 characters)</label>
            <input type="password" name="password" required minlength="8" placeholder="Choose a strong password">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <button type="submit">Create Admin Account</button>
        </form>
        <div class="warn">⚠️ Delete this file after use — it must not remain on a live server.</div>
    <?php endif; ?>
</div>
</body>
</html>
