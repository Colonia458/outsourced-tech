<?php
/**
 * JWT Authentication Helpers
 * Outsourced Technologies E-Commerce Platform
 * 
 * Requires: composer require firebase/php-jwt
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// JWT Configuration
define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400 * 7); // 7 days in seconds

/**
 * Generate JWT token for user
 * 
 * @param int $user_id User ID
 * @param string $email User email
 * @param array $extra_claims Additional claims to include
 * @return string JWT token
 */
function generate_jwt_token(int $user_id, string $email, array $extra_claims = []): string {
    $issued_at = time();
    $expiration = $issued_at + JWT_EXPIRY;
    
    $payload = array_merge([
        'iss' => $_SERVER['HTTP_HOST'] ?? 'outsourcedtechnologies.co.ke',
        'aud' => $_SERVER['HTTP_HOST'] ?? 'outsourcedtechnologies.co.ke',
        'iat' => $issued_at,
        'exp' => $expiration,
        'nbf' => $issued_at,
        'jti' => bin2hex(random_bytes(16)),
        'user_id' => $user_id,
        'email' => $email,
        'type' => 'access'
    ], $extra_claims);
    
    return JWT::encode($payload, JWT_SECRET_KEY, JWT_ALGORITHM);
}

/**
 * Verify and decode JWT token
 * 
 * @param string $token JWT token
 * @return object|false Decoded token or false on failure
 */
function verify_jwt_token(string $token): object|false {
    try {
        return JWT::decode($token, new Key(JWT_SECRET_KEY, JWT_ALGORITHM));
    } catch (ExpiredException $e) {
        error_log('JWT Token expired: ' . $e->getMessage());
        return false;
    } catch (SignatureInvalidException $e) {
        error_log('JWT Signature invalid: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('JWT Verification failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user ID from JWT token
 * 
 * @param string $token JWT token
 * @return int|false User ID or false
 */
function get_user_id_from_token(string $token): int|false {
    $decoded = verify_jwt_token($token);
    return $decoded ? (int)$decoded->user_id : false;
}

/**
 * Generate refresh token
 * 
 * @param int $user_id User ID
 * @return string Refresh token
 */
function generate_refresh_token(int $user_id): string {
    $payload = [
        'user_id' => $user_id,
        'type' => 'refresh',
        'jti' => bin2hex(random_bytes(16)),
        'iat' => time(),
        'exp' => time() + (86400 * 30) // 30 days
    ];
    
    return JWT::encode($payload, JWT_SECRET_KEY, JWT_ALGORITHM);
}

/**
 * Generate password reset JWT token
 * 
 * @param int $user_id User ID
 * @param string $email User email
 * @return string JWT token
 */
function generate_password_reset_token(int $user_id, string $email): string {
    $payload = [
        'user_id' => $user_id,
        'email' => $email,
        'type' => 'password_reset',
        'jti' => bin2hex(random_bytes(16)),
        'iat' => time(),
        'exp' => time() + 3600 // 1 hour
    ];
    
    return JWT::encode($payload, JWT_SECRET_KEY, JWT_ALGORITHM);
}

/**
 * Verify password reset token
 * 
 * @param string $token Reset token
 * @return object|false Decoded token or false
 */
function verify_password_reset_token(string $token): object|false {
    $decoded = verify_jwt_token($token);
    
    if (!$decoded || ($decoded->type ?? '') !== 'password_reset') {
        return false;
    }
    
    return $decoded;
}

/**
 * Generate email verification token
 * 
 * @param int $user_id User ID
 * @param string $email User email
 * @return string Verification token
 */
function generate_email_verification_token(int $user_id, string $email): string {
    $payload = [
        'user_id' => $user_id,
        'email' => $email,
        'type' => 'email_verification',
        'jti' => bin2hex(random_bytes(16)),
        'iat' => time(),
        'exp' => time() + (86400 * 7) // 7 days
    ];
    
    return JWT::encode($payload, JWT_SECRET_KEY, JWT_ALGORITHM);
}

/**
 * Verify email verification token
 * 
 * @param string $token Verification token
 * @return object|false Decoded token or false
 */
function verify_email_verification_token(string $token): object|false {
    $decoded = verify_jwt_token($token);
    
    if (!$decoded || ($decoded->type ?? '') !== 'email_verification') {
        return false;
    }
    
    return $decoded;
}

/**
 * Get JWT from Authorization header
 * 
 * @return string|false JWT token or false
 */
function get_jwt_from_header(): string|false {
    $headers = getallheaders();
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    
    return false;
}

/**
 * Require JWT authentication (API middleware)
 * 
 * @return object|false Decoded token or exit with 401
 */
function require_jwt_auth(): object {
    $token = get_jwt_from_header();
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization token required'
        ]);
        exit;
    }
    
    $decoded = verify_jwt_token($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit;
    }
    
    return $decoded;
}

/**
 * Check if token type is valid access token
 * 
 * @param object $decoded Decoded JWT
 * @return bool
 */
function is_access_token(object $decoded): bool {
    return ($decoded->type ?? '') === 'access';
}

/**
 * Create JWT auth response
 * 
 * @param int $user_id User ID
 * @param string $email User email
 * @return array Auth response
 */
function create_auth_response(int $user_id, string $email): array {
    return [
        'access_token' => generate_jwt_token($user_id, $email),
        'refresh_token' => generate_refresh_token($user_id),
        'token_type' => 'Bearer',
        'expires_in' => JWT_EXPIRY
    ];
}
