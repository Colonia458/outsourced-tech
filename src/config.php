<?php
// Load composer autoloader first
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

define('APP_NAME', 'Outsourced Technologies');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/outsourced/');
define('BASE_PATH', __DIR__ . '/../');

// Database Configuration - Supports both local MySQL and Supabase PostgreSQL
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql'); // 'mysql' for local, 'pgsql' for Supabase
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: (DB_DRIVER === 'pgsql' ? '5432' : '3306'));
define('DB_NAME', getenv('DB_NAME') ?: 'outsourced_tech');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// M-Pesa Configuration - MUST be set via environment variables in production
define('MPESA_ENV', getenv('MPESA_ENV') ?: 'sandbox');
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: '');
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: '');
define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE') ?: '');
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: '');
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: 'http://localhost/outsourced/api/mpesa/callback.php');

// Start session securely
require_once __DIR__ . '/security.php';
secure_session_start();

// Environment-based error display (disable in production)
$is_production = getenv('APP_ENV') === 'production';
ini_set('display_errors', $is_production ? '0' : '1');
error_reporting($is_production ? 0 : E_ALL);

// ──────────────────────────────────────────────
//           CREATE DATABASE CONNECTION
// ──────────────────────────────────────────────

try {
    if (DB_DRIVER === 'pgsql') {
        // PostgreSQL connection (for Supabase)
        $db = new PDO(
            "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } else {
        // MySQL connection (for local development)
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    
    // Set timezone (syntax differs between MySQL and PostgreSQL)
    if (DB_DRIVER === 'pgsql') {
        $db->exec("SET TIME ZONE 'Africa/Nairobi'");
    } else {
        $db->exec("SET time_zone = '+03:00'");
    }

} catch (PDOException $e) {
    // Log error details in production, show generic message to users
    error_log('Database connection failed: ' . $e->getMessage());
    if ($is_production) {
        die('Database connection failed. Please try again later.');
    } else {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
}

// Compatibility alias for $pdo
$pdo = $db;
