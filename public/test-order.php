<?php
// Test order creation
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

echo "<h1>Testing Order Creation</h1>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "</p>";

// Check if orders table exists
global $db;
try {
    $stmt = $db->query("SHOW TABLES LIKE 'orders'");
    $tables = $stmt->fetchAll();
    echo "<p>Orders table exists: " . (count($tables) > 0 ? 'YES' : 'NO') . "</p>";
} catch (Exception $e) {
    echo "<p>Error checking table: " . $e->getMessage() . "</p>";
}

// Try direct insert
echo "<h2>Testing direct insert</h2>";
try {
    $sql = "INSERT INTO orders (user_id, order_number, status, payment_status, payment_method, subtotal, delivery_fee, total_amount, delivery_type, phone)
            VALUES (1, 'TEST-" . time() . "', 'pending', 'paid', 'payment', 100, 0, 100, 'pickup', '0712345678')";
    $db->exec($sql);
    $lastId = $db->lastInsertId();
    echo "<p style='color:green'>Direct insert successful! Last ID: $lastId</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Direct insert failed: " . $e->getMessage() . "</p>";
}

echo "<p><a href='checkout.php'>Back to checkout</a></p>";
