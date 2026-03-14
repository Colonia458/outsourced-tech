<?php
// src/otp.php - OTP (One-Time Password) functionality

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Generate a numeric OTP code
 * @param int $length Number of digits (default: 6)
 * @return string Generated OTP
 */
function generate_otp($length = 6) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

/**
 * Create OTP table if it doesn't exist
 */
function ensure_otp_table_exists() {
    global $db;
    
    $sql = "CREATE TABLE IF NOT EXISTS otp_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp VARCHAR(10) NOT NULL,
        purpose ENUM('password_reset', 'email_verification', 'login_verification') DEFAULT 'password_reset',
        attempts INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        verified_at TIMESTAMP NULL,
        INDEX idx_email (email),
        INDEX idx_otp (otp),
        INDEX idx_expires (expires_at)
    )";
    
    try {
        $db->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log("Failed to create OTP table: " . $e->getMessage());
        return false;
    }
}

/**
 * Store OTP in database
 * @param string $email User email address
 * @param string $otp The OTP code
 * @param int $expiry_minutes Minutes until OTP expires (default: 10)
 * @param string $purpose Purpose of OTP (default: password_reset)
 * @return bool Success status
 */
function store_otp($email, $otp, $expiry_minutes = 10, $purpose = 'password_reset') {
    // Ensure table exists
    ensure_otp_table_exists();
    
    // Delete any existing OTPs for this email and purpose
    query(
        "DELETE FROM otp_verifications WHERE email = ? AND purpose = ? AND verified_at IS NULL",
        [$email, $purpose]
    );
    
    // Calculate expiry time
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));
    
    // Insert new OTP using query (bypass whitelist for otp_verifications)
    global $db;
    $stmt = $db->prepare("INSERT INTO otp_verifications (email, otp, purpose, expires_at, attempts) VALUES (?, ?, ?, ?, 0)");
    $result = $stmt->execute([$email, $otp, $purpose, $expires_at]);
    
    return $result !== false;
}

/**
 * Verify OTP and increment attempt counter
 * @param string $email User email address
 * @param string $otp The OTP code to verify
 * @param string $purpose Purpose of OTP (default: password_reset)
 * @param int $max_attempts Maximum allowed attempts (default: 3)
 * @return array Result with success status and message
 */
function verify_otp($email, $otp, $purpose = 'password_reset', $max_attempts = 3) {
    // Find the OTP record
    $record = fetchOne(
        "SELECT * FROM otp_verifications 
         WHERE email = ? AND purpose = ? AND verified_at IS NULL 
         ORDER BY created_at DESC LIMIT 1",
        [$email, $purpose]
    );
    
    if (!$record) {
        return [
            'success' => false, 
            'message' => 'No OTP found. Please request a new code.',
            'code' => 'NO_OTP'
        ];
    }
    
    // Check if OTP has expired
    if (strtotime($record['expires_at']) < time()) {
        return [
            'success' => false, 
            'message' => 'OTP has expired. Please request a new code.',
            'code' => 'EXPIRED'
        ];
    }
    
    // Check attempts
    if ($record['attempts'] >= $max_attempts) {
        // Delete the expired OTP
        query("DELETE FROM otp_verifications WHERE id = ?", [$record['id']]);
        return [
            'success' => false, 
            'message' => 'Too many failed attempts. Please request a new code.',
            'code' => 'MAX_ATTEMPTS'
        ];
    }
    
    // Verify OTP using timing-safe comparison
    if (!hash_equals($record['otp'], $otp)) {
        // Increment attempts
        query(
            "UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?",
            [$record['id']]
        );
        
        $remaining = $max_attempts - $record['attempts'] - 1;
        return [
            'success' => false, 
            'message' => "Invalid OTP. You have {$remaining} attempt(s) remaining.",
            'code' => 'INVALID_OTP',
            'remaining_attempts' => $remaining
        ];
    }
    
    // Mark as verified
    query(
        "UPDATE otp_verifications SET verified_at = NOW() WHERE id = ?",
        [$record['id']]
    );
    
    return [
        'success' => true, 
        'message' => 'OTP verified successfully',
        'code' => 'VERIFIED'
    ];
}

/**
 * Check if OTP exists and is still valid (not expired, not verified)
 * @param string $email User email address
 * @param string $purpose Purpose of OTP
 * @return bool True if valid OTP exists
 */
function has_valid_otp($email, $purpose = 'password_reset') {
    $record = fetchOne(
        "SELECT * FROM otp_verifications 
         WHERE email = ? AND purpose = ? AND verified_at IS NULL 
         AND expires_at > NOW() 
         ORDER BY created_at DESC LIMIT 1",
        [$email, $purpose]
    );
    
    return $record !== false;
}

/**
 * Get remaining time before OTP expires
 * @param string $email User email address
 * @param string $purpose Purpose of OTP
 * @return int Seconds remaining, or 0 if no valid OTP
 */
function get_otp_remaining_time($email, $purpose = 'password_reset') {
    $record = fetchOne(
        "SELECT expires_at FROM otp_verifications 
         WHERE email = ? AND purpose = ? AND verified_at IS NULL 
         ORDER BY created_at DESC LIMIT 1",
        [$email, $purpose]
    );
    
    if (!$record) {
        return 0;
    }
    
    $remaining = strtotime($record['expires_at']) - time();
    return max(0, $remaining);
}

/**
 * Delete all OTPs for an email and purpose
 * @param string $email User email address
 * @param string $purpose Purpose of OTP
 * @return bool Success status
 */
function delete_otp($email, $purpose = 'password_reset') {
    $result = query(
        "DELETE FROM otp_verifications WHERE email = ? AND purpose = ?",
        [$email, $purpose]
    );
    
    return $result !== false;
}

/**
 * Clean up expired OTPs (can be called via cron)
 * @return int Number of records deleted
 */
function cleanup_expired_otps() {
    $result = query("DELETE FROM otp_verifications WHERE expires_at < NOW()");
    return $result ? 1 : 0;
}
