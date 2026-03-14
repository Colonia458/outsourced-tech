<?php
// admin/login.php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/security.php';
// Session is already started by secure_session_start() inside src/config.php

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_user'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill in both username and password.";
    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        try {
            $stmt = $db->prepare("SELECT id, username, password_hash, role, full_name
                                  FROM admin_users
                                  WHERE username = ? AND active = 1
                                  LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Admin login DB error: ' . $e->getMessage());
            $error = "A system error occurred. Please contact the administrator.";
            $admin = null;
        }

        if ($admin && isset($admin['password_hash']) && password_verify($password, $admin['password_hash'])) {
            // Successful login — regenerate session ID to prevent fixation
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['full_name'] ?? $admin['username'];
            header("Location: dashboard.php");
            exit();
        } elseif (empty($error)) {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .login-container {
            max-width: 420px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .logo { font-size: 2rem; font-weight: bold; color: #0d6efd; text-align: center; margin-bottom: 1.5rem; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo"><i class="fas fa-microchip"></i> <?= APP_NAME ?></div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" 
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        
        <?= csrf_field() ?>

        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="text-center mt-4 text-muted small">
        <a href="<?= BASE_URL ?>">Back to Shop</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>