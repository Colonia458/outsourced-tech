<?php
// api/test-invoice.php - Test Invoice Generation
// Usage: Visit this URL to test invoice generation

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';

echo "<h1>🧪 Invoice Generation Test</h1>";

$results = [];

// Test 1: Check if Dompdf is installed
echo "<h2>Test 1: Dompdf Installation</h2>";
try {
    $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
        if (class_exists('Dompdf\Dompdf')) {
            echo "<p style='color: green;'>✅ Dompdf is installed!</p>";
            $results['dompdf'] = 'OK';
        } else {
            echo "<p style='color: red;'>❌ Dompdf class not found</p>";
            $results['dompdf'] = 'FAIL';
        }
    } else {
        echo "<p style='color: red;'>❌ Vendor autoload not found. Run <code>composer install</code></p>";
        $results['dompdf'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    $results['dompdf'] = 'ERROR';
}

// Test 2: Check database connection
echo "<h2>Test 2: Database Connection</h2>";
try {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $order_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p style='color: green;'>✅ Database connected! Found $order_count orders.</p>";
    $results['database'] = 'OK';
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
    $results['database'] = 'FAIL';
}

// Test 3: Get a test order
echo "<h2>Test 3: Get Test Order</h2>";
try {
    global $pdo;
    $stmt = $pdo->query("
        SELECT o.*, u.username, u.email, u.full_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.payment_status = 'paid'
        LIMIT 1
    ");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "<p style='color: green;'>✅ Found order: {$order['order_number']}</p>";
        echo "<p>Email: {$order['email']}</p>";
        $results['order'] = $order['id'];
    } else {
        echo "<p style='color: orange;'>⚠️ No paid orders found. Creating test order...</p>";
        
        // Create a test order
        $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $order_number = 'TEST-' . date('YmdHis');
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, order_number, status, payment_status, subtotal, delivery_fee, total_amount)
                VALUES (?, ?, 'pending', 'pending', 5000, 0, 5000)
            ");
            $stmt->execute([$user['id'], $order_number]);
            $order_id = $pdo->lastInsertId();
            
            // Add test item
            $stmt = $pdo->query("SELECT id, name, price FROM products LIMIT 1");
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, name, price, quantity, subtotal)
                    VALUES (?, ?, ?, ?, 1, ?)
                ");
                $stmt->execute([$order_id, $product['id'], $product['name'], $product['price'], $product['price']]);
            }
            
            echo "<p style='color: green;'>✅ Created test order: $order_number (ID: $order_id)</p>";
            $results['order'] = $order_id;
        } else {
            echo "<p style='color: red;'>❌ No users found. Please create a user first.</p>";
            $results['order'] = 'NO_USER';
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    $results['order'] = 'ERROR';
}

// Test 4: Generate Invoice
echo "<h2>Test 4: Generate Invoice</h2>";
if (!empty($results['order']) && is_numeric($results['order'])) {
    require_once __DIR__ . '/../src/invoice.php';
    
    $invoice_path = generate_invoice($results['order']);
    
    if ($invoice_path && file_exists($invoice_path)) {
        echo "<p style='color: green;'>✅ Invoice generated successfully!</p>";
        echo "<p>File: $invoice_path</p>";
        echo "<p>Size: " . filesize($invoice_path) . " bytes</p>";
        
        // Provide download link
        $download_url = str_replace(__DIR__ . '/..', '', $invoice_path);
        echo "<p><a href='$download_url' target='_blank'>📥 Download Invoice PDF</a></p>";
        
        $results['invoice'] = 'OK';
    } else {
        echo "<p style='color: red;'>❌ Invoice generation failed.</p>";
        $results['invoice'] = 'FAIL';
    }
} else {
    echo "<p style='color: orange;'>⚠️ Skipped - no valid order</p>";
    $results['invoice'] = 'SKIPPED';
}

// Test 5: Send Invoice Email
echo "<h2>Test 5: Send Invoice Email</h2>";
if (!empty($results['order']) && is_numeric($results['order']) && $results['invoice'] === 'OK') {
    require_once __DIR__ . '/../src/invoice.php';
    
    $email_sent = send_invoice_email($results['order']);
    
    if ($email_sent) {
        echo "<p style='color: green;'>✅ Test email sent! Check your inbox.</p>";
        $results['email'] = 'OK';
    } else {
        echo "<p style='color: orange;'>⚠️ Email may have failed. Check logs/email_errors.log</p>";
        $results['email'] = 'CHECK_LOGS';
    }
} else {
    echo "<p style='color: orange;'>⚠️ Skipped - no invoice generated</p>";
    $results['email'] = 'SKIPPED';
}

// Summary
echo "<h2>📊 Test Summary</h2>";
echo "<pre>";
print_r($results);
echo "</pre>";

$all_passed = !in_array('FAIL', $results) && !in_array('ERROR', $results);
if ($all_passed) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px;'>";
    echo "<h3 style='color: #155724; margin: 0;'>🎉 All tests passed!</h3>";
    echo "<p>Invoice generation is working correctly.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24; margin: 0;'>⚠️ Some tests failed</h3>";
    echo "<p>Please check the errors above.</p>";
    echo "</div>";
}

echo "<h2>📝 Next Steps</h2>";
echo "<ul>";
echo "<li>Set up cron jobs (see SETUP_CRON.md)</li>";
echo "<li>Configure your email settings in .env</li>";
echo "<li>Test the M-Pesa callback to auto-generate invoices</li>";
echo "</ul>";

echo "<p><a href='../admin/dashboard.php'>← Back to Admin Dashboard</a></p>";
