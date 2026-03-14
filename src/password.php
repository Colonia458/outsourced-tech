<?php
// src/password.php - Password reset functionality

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/email.php';

/**
 * Get environment variable with $_ENV fallback
 * @param string $name Variable name
 * @param mixed $default Default value if not found
 * @return mixed
 */
function get_env($name, $default = null) {
    $value = getenv($name);
    if ($value === false) {
        $value = $_ENV[$name] ?? $default;
    }
    return $value;
}

/**
 * Generate a secure random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Request OTP for password reset
 * @param string $email_or_phone User's email or phone
 * @return array Result with success status and message
 */
function request_password_reset_otp($email_or_phone) {
    // DEBUG: Log function call
    error_log('[DEBUG request_password_reset_otp] Called with: ' . $email_or_phone);
    error_log('[DEBUG request_password_reset_otp] APP_ENV: ' . get_env('APP_ENV'));
    
    // Find user by email or phone
    $user = fetchOne(
        "SELECT id, email, phone, full_name FROM users WHERE email = ? OR phone = ?",
        [$email_or_phone, $email_or_phone]
    );
    
    // DEBUG: Log user lookup result
    error_log('[DEBUG request_password_reset_otp] User lookup result: ' . ($user ? 'Found user: ' . $user['email'] : 'No user found'));
    
    if (!$user || empty($user['email'])) {
        // Don't reveal if user exists
        return ['success' => true, 'message' => 'If an account exists, a verification code will be sent to your email'];
    }
    
    // Check if there's a recent valid OTP (skip in development mode for testing)
    if (get_env('APP_ENV') !== 'development' && has_valid_otp($user['email'], 'password_reset')) {
        $remaining = get_otp_remaining_time($user['email'], 'password_reset');
        $minutes = ceil($remaining / 60);
        return [
            'success' => true, 
            'message' => "A code was already sent. Please check your email or wait {$minutes} minutes for a new code.",
            'email' => mask_email($user['email'])
        ];
    }
    
    // Generate 6-digit OTP
    $otp = generate_otp(6);
    error_log('[DEBUG request_password_reset_otp] Generated OTP: ' . $otp);
    
    // Store OTP in database (expires in 10 minutes)
    if (!store_otp($user['email'], $otp, 10, 'password_reset')) {
        return ['success' => false, 'message' => 'Failed to generate verification code. Please try again.'];
    }
    
    // Send OTP via email
    $email_result = send_otp_email($user['email'], $otp, 'password_reset');
    
    // In development mode, always include the OTP for testing
    $debug_otp = (get_env('APP_ENV') === 'development' && isset($otp)) ? $otp : '123456';
    error_log('[DEBUG request_password_reset_otp] debug_otp set to: ' . ($debug_otp ?? 'NULL (APP_ENV not development)'));
    
    // Log email result for debugging
    if (get_env('APP_ENV') === 'development') {
        @file_put_contents(
            __DIR__ . '/../logs/email_debug.log',
            "[" . date('Y-m-d H:i:s') . "] Email send result: " . ($email_result['success'] ? 'success' : 'failed') . "\n",
            FILE_APPEND
        );
    }
    
    return [
        'success' => true, 
        'message' => 'If an account exists, a verification code has been sent to your email',
        'debug_otp' => $debug_otp,
        'email' => mask_email($user['email'])
    ];
}

/**
 * Verify OTP and return user for password reset
 * @param string $email User's email
 * @param string $otp One-time password
 * @return array Result with success status and user data
 */
function verify_password_reset_otp($email, $otp) {
    // Find user by email
    $user = fetchOne(
        "SELECT id, email, phone, full_name FROM users WHERE email = ?",
        [$email]
    );
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid verification code'];
    }
    
    // Verify OTP
    $result = verify_otp($email, $otp, 'password_reset', 3);
    
    if (!$result['success']) {
        return $result;
    }
    
    return [
        'success' => true,
        'message' => 'Verification successful',
        'user_id' => $user['id'],
        'email' => $user['email']
    ];
}

/**
 * Reset password using verified OTP
 * @param string $email User's email
 * @param string $new_password New password
 * @return array Result with success status and message
 */
function reset_password_with_otp($email, $new_password) {
    // Find user by email
    $user = fetchOne(
        "SELECT id, email, phone, full_name FROM users WHERE email = ?",
        [$email]
    );
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Hash password
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update user password
    query("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $user['id']]);
    
    // Delete any remaining OTPs for this user
    delete_otp($email, 'password_reset');
    
    return ['success' => true, 'message' => 'Password reset successfully! You can now login with your new password.'];
}

/**
 * Mask email for display (e.g., g***@example.com)
 */
function mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $name = $parts[0];
    $domain = $parts[1];
    
    if (strlen($name) <= 2) {
        $masked = str_repeat('*', strlen($name));
    } else {
        $masked = $name[0] . str_repeat('*', strlen($name) - 2) . $name[strlen($name) - 1];
    }
    
    return $masked . '@' . $domain;
}

/**
 * Request password reset
 */
function request_password_reset($email_or_phone) {
    // Find user by email or phone
    $user = fetchOne(
        "SELECT id, email, phone, full_name FROM users WHERE email = ? OR phone = ?",
        [$email_or_phone, $email_or_phone]
    );
    
    if (!$user) {
        // Don't reveal if user exists
        return ['success' => true, 'message' => 'If an account exists, a reset link will be sent'];
    }
    
    // Delete any existing tokens for this user
    query("DELETE FROM password_resets WHERE user_id = ?", [$user['id']]);
    
    // Generate new token (valid for 1 hour)
    $token = generate_token();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    db_insert('password_resets', [
        'user_id' => $user['id'],
        'token' => $token,
        'expires_at' => $expires
    ]);
    
    // Build reset link and send via email
    $reset_link = rtrim(get_env('BASE_URL', 'http://localhost/outsourced/public/'), '/') . "/reset-password.php?token=$token";

    // Log for debugging (never expose token in logs in production)
    // Note: Remove this line in production - tokens should never be logged
    // error_log("Password reset link for {$user['email']}: $reset_link");

    // TODO: send email — require_once __DIR__ . '/email.php'; send_email($user['email'], 'Password Reset', ...);

    return [
        'success' => true,
        'message' => 'If an account exists, a reset link will be sent',
    ];
}

/**
 * Validate password reset token
 */
function validate_reset_token($token) {
    $reset = fetchOne(
        "SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL",
        [$token]
    );
    
    if (!$reset) {
        return ['valid' => false, 'message' => 'Invalid or expired token'];
    }
    
    // Use timing-safe comparison for token
    if (!hash_equals($reset['token'], $token)) {
        return ['valid' => false, 'message' => 'Invalid token'];
    }
    
    if (strtotime($reset['expires_at']) < time()) {
        return ['valid' => false, 'message' => 'Token has expired'];
    }
    
    return [
        'valid' => true,
        'user_id' => $reset['user_id']
    ];
}

/**
 * Reset password
 */
function reset_password($token, $new_password) {
    $validation = validate_reset_token($token);
    
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }
    
    // Hash password
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update user password
    query("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $validation['user_id']]);
    
    // Mark token as used
    query(
        "UPDATE password_resets SET used_at = NOW() WHERE token = ?",
        [$token]
    );
    
    return ['success' => true, 'message' => 'Password reset successfully!'];
}
