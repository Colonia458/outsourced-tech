<?php
// public/verify-otp.php - OTP Verification page for password reset
require_once __DIR__ . '/../src/config.php';

// DEBUG: Log session state on page load
error_log('[DEBUG verify-otp.php] Session ID: ' . session_id());
error_log('[DEBUG verify-otp.php] Session otp_email: ' . ($_SESSION['otp_email'] ?? 'NOT SET'));
error_log('[DEBUG verify-otp.php] Session debug_otp: ' . ($_SESSION['debug_otp'] ?? 'NOT SET'));

$page_title = 'Verify OTP - ' . APP_NAME;
require_once __DIR__ . '/../templates/header.php';

// Check if email is in session
if (!isset($_SESSION['otp_email'])) {
    // Redirect to forgot password if no email in session
    header('Location: forgot-password.php');
    exit;
}

$email = $_SESSION['otp_email'];
$debug_otp = $_SESSION['debug_otp'] ?? null;
$message = '';
$message_type = '';

// Show debug OTP on initial page load (development mode only)
if ($debug_otp) {
    $message = 'Development Mode - OTP: <strong class="text-danger">' . $debug_otp . '</strong>';
    $message_type = 'warning';
}

// Check for resend request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    require_once __DIR__ . '/../src/password.php';
    $result = request_password_reset_otp($email);
    $message = 'A new verification code has been sent to your email.';
    $message_type = 'success';
    
    // Show debug OTP in development and update session
    if (isset($result['debug_otp'])) {
        $message .= '<br><small class="text-danger">Dev OTP: ' . $result['debug_otp'] . '</small>';
        $_SESSION['debug_otp'] = $result['debug_otp']; // Update session with new OTP
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = sanitize($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($otp)) {
        $message = 'Please enter the verification code';
        $message_type = 'danger';
    } elseif (empty($new_password)) {
        $message = 'Please enter a new password';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'danger';
    } else {
        require_once __DIR__ . '/../src/password.php';
        
        // First verify the OTP
        $verify_result = verify_password_reset_otp($email, $otp);
        
        if (!$verify_result['success']) {
            $message = $verify_result['message'];
            $message_type = 'danger';
        } else {
            // OTP verified, now reset the password
            $reset_result = reset_password_with_otp($email, $new_password);
            
            if ($reset_result['success']) {
                // Clear session
                unset($_SESSION['otp_email']);
                unset($_SESSION['debug_otp']);
                
                // Show success and redirect to login
                $message = $reset_result['message'];
                $message_type = 'success';
                
                // Redirect after short delay
                header('refresh:2;url=login.php');
            } else {
                $message = $reset_result['message'];
                $message_type = 'danger';
            }
        }
    }
}

// Get masked email for display
require_once __DIR__ . '/../src/password.php';
$masked_email = mask_email($email);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h4 class="card-title">Verify Your Identity</h4>
                        <p class="text-muted">Enter the 6-digit code sent to <strong><?= htmlspecialchars($masked_email) ?></strong></p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <?php if (!isset($reset_result['success']) || !$reset_result['success']): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="otp" class="form-label">Verification Code</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" 
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                                   required autocomplete="one-time-code" style="letter-spacing: 8px; font-size: 24px;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   placeholder="Enter new password (min 6 characters)" required minlength="6">
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-2">Didn't receive the code?</p>
                        <a href="?resend=1" class="btn btn-outline-secondary btn-sm">Resend Code</a>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="forgot-password.php">Use a different email</a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="login.php">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-focus on OTP input and handle input formatting
document.addEventListener('DOMContentLoaded', function() {
    const otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.focus();
        
        // Only allow numbers
        otpInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
