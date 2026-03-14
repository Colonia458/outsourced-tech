<?php
// src/database.php - Database helper functions
// Uses the $db connection from config.php

require_once __DIR__ . '/config.php';

// Make sure we have the global $db from config.php
global $db;

if (!isset($db) || !($db instanceof PDO)) {
    // If config.php connection doesn't exist, create it (fallback)
    try {
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        die('Database connection failed. Please try again later.');
    }
}

// ───────────────────────────────────────────────
//  Existing helper functions (improved slightly)
// ───────────────────────────────────────────────

function query($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

function fetchOne($sql, $params = []) {
    $result = query($sql, $params)->fetch();
    return $result ?: null;   // return null instead of false when no row
}

// ───────────────────────────────────────────────
//  NEW: db_insert() – inserts row and returns new ID
// ───────────────────────────────────────────────

/**
 * Insert a new record into a table using named parameters
 * 
 * Usage example:
 * $id = db_insert('users', [
 *     'username'      => 'gordon',
 *     'email'         => 'gordon@example.com',
 *     'password_hash' => $hash,
 *     'full_name'     => 'Gordon K.',
 *     'phone'         => '0712345678'
 * ]);
 * 
 * @param string $table Table name (will be validated against whitelist)
 * @param array  $data  Associative array [column => value]
 * @return int|false    New insert ID on success, false on failure
 */
function db_insert(string $table, array $data) {
    global $db;

    if (empty($data)) {
        return false;
    }

    // Whitelist of allowed table names (add your tables here)
    $allowed_tables = [
        'users', 'orders', 'order_items', 'products', 'categories',
        'coupons', 'delivery_zones', 'loyalty_tiers', 'services',
        'service_bookings', 'reviews', 'activity_logs', 'payments',
        'delivery_tracking', 'product_images'
    ];
    
    // Validate table name against whitelist to prevent SQL injection
    if (!in_array($table, $allowed_tables)) {
        error_log("Invalid table name in db_insert: " . $table);
        return false;
    }

    // Validate column names (alphanumeric and underscore only)
    foreach (array_keys($data) as $column) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            error_log("Invalid column name in db_insert: " . $column);
            return false;
        }
    }

    // Prepare column names and named placeholders
    $columns      = array_keys($data);
    $placeholders = array_map(fn($col) => ":$col", $columns);

    // Use backticks for MySQL/XAMPP, double-quotes for PostgreSQL/Supabase
    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
    $quote  = ($driver === 'pgsql') ? '"' : '`';
    $sql = "INSERT INTO {$quote}{$table}{$quote}
            (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        // In production: log error instead of throwing
        error_log("Insert failed in table $table: " . $e->getMessage());
        return false;
    }
}