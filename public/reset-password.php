<?php
// public/reset-password.php - Reset password page
require_once __DIR__ . '/../src/config.php';

$page_title = 'Reset Password - ' . APP_NAME;
require_once __DIR__ . '/../templates/header.php';

$message = '';
$message_type = '';
$show_form = true;

$token = $_GET['token'] ?? '';

// Validate token
if (empty($token)) {
    $message = 'Invalid reset token';
    $message_type = 'danger';
    $show_form = false;
} else {
    require_once __DIR__ . '/../src/password.php';
    $validation = validate_reset_token($token);
    
    if (!$validation['valid']) {
        $message = $validation['message'];
        $message_type = 'danger';
        $show_form = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || strlen($password) < 6) {
        $message = 'Password must be at least 6 characters';
        $message_type = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'danger';
    } else {
        $result = reset_password($token, $password);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'danger';
        
        if ($result['success']) {
            $show_form = false;
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h4 class="card-title">Reset Password</h4>
                        <p class="text-muted">Enter your new password below.</p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <?php if ($show_form): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       minlength="6" placeholder="At least 6 characters" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" placeholder="Repeat your password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
