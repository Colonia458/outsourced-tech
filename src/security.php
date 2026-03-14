<?php
// src/security.php - Security helpers for CSRF, XSS, Input Validation

/**
 * Start session securely
 */
function secure_session_start() {
    // Do nothing if a session is already active
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $session_options = [
        'cookie_httponly' => 1,
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => 1,
        'use_only_cookies' => 1,
    ];

    session_start($session_options);
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token field for forms
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Sanitize output (XSS prevention)
 */
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    
    // Use HTMLPurifier or similar for rich content in production
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Kenyan format)
 */
function validate_phone($phone) {
    // Remove spaces and dashes
    $phone = preg_replace('/[\s\-]/', '', $phone);
    // Check for valid Kenyan format
    return preg_match('/^0[17]\d{8}$/', $phone) === 1;
}

/**
 * Validate URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate integer range
 */
function validate_int_range($value, $min = null, $max = null) {
    $options = [
        'options' => []
    ];
    
    if ($min !== null) {
        $options['options']['min_range'] = $min;
    }
    if ($max !== null) {
        $options['options']['max_range'] = $max;
    }
    
    return filter_var($value, FILTER_VALIDATE_INT, $options) !== false;
}

/**
 * Password strength validation
 */
function validate_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

/**
 * Rate limiting check
 */
function check_rate_limit($identifier, $max_attempts = 5, $time_window = 300) {
    $key = 'rate_limit_' . $identifier;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }
    
    $session = $_SESSION[$key];
    
    // Reset if time window expired
    if (time() - $session['first_attempt'] > $time_window) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
        return true;
    }
    
    // Check if exceeded limit
    if ($session['attempts'] >= $max_attempts) {
        return false;
    }
    
    // Increment attempts
    $_SESSION[$key]['attempts']++;
    return true;
}

/**
 * Get client IP address safely
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (take first)
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            // Validate IP
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Log security events
 */
function log_security_event($event_type, $details = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => get_client_ip(),
        'event' => $event_type,
        'details' => $details,
        'user_id' => $_SESSION['user_id'] ?? $_SESSION['admin_user'] ?? null
    ];
    
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/security.log';
    $log_content = json_encode($log_entry) . "\n";
    
    file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
}

/**
 * Content Security Policy header
 */
function set_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // CSP for production - adjust as needed
    // header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;");
}
