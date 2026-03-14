<?php
// api/enhanced-cron.php - Enhanced Cron Job for All Automation Tasks
// This file handles: Queues, Notifications, Reports, Loyalty, Inventory
// Usage: */15 * * * * curl -s https://yourdomain.com/api/enhanced-cron.php?key=YOUR_KEY

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';

header('Content-Type: application/json');

// Security check
$secret_key = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';
$expected_key = getenv('CRON_SECRET_KEY') ?: 'test123'; // Default for testing

// Allow empty key for localhost testing
if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']) && empty($secret_key)) {
    // Allow localhost without key for testing
} elseif ($secret_key !== $expected_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tasks' => []
];

// Task 1: Process notification queue (every 15 minutes)
$results['tasks']['process_queue'] = process_notification_queue();

// Task 2: Send booking reminders (every hour)
$results['tasks']['booking_reminders'] = send_booking_reminders();

// Task 3: Check abandoned carts (every hour)
$results['tasks']['abandoned_carts'] = check_abandoned_carts_cron();

// Task 4: Generate and send scheduled reports (daily at 6 AM)
if (date('H') == '06') {
    $results['tasks']['scheduled_reports'] = process_scheduled_reports_cron();
}

// Task 5: Expire loyalty points (daily at midnight)
if (date('H') == '00') {
    $results['tasks']['loyalty_expiry'] = expire_loyalty_points_cron();
}

// Task 6: Auto-hide out-of-stock products (hourly)
$results['tasks']['inventory_visibility'] = update_inventory_visibility();

// Task 7: Cancel unpaid orders after 24 hours
$results['tasks']['cancel_unpaid_orders'] = cancel_unpaid_orders_cron();

// Task 8: Generate daily sales snapshot (daily at midnight)
if (date('H') == '00') {
    $results['tasks']['daily_snapshot'] = generate_daily_snapshot();
}

// Task 9: Low stock alerts (every 6 hours)
if (in_array(date('H'), ['06', '12', '18', '00'])) {
    $results['tasks']['low_stock_alerts'] = check_low_stock_alerts();
}

// Log results
$log_entry = "[" . date('Y-m-d H:i:s') . "] Cron executed: " . json_encode($results) . "\n";
@file_put_contents(__DIR__ . '/../logs/cron.log', $log_entry, FILE_APPEND);

echo json_encode($results, JSON_PRETTY_PRINT);

/**
 * Process notification queue
 */
function process_notification_queue() {
    global $pdo;
    
    try {
        // Get pending notifications
        $stmt = $pdo->prepare("
            SELECT * FROM notification_queue 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT 50
        ");
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processed = 0;
        $success = 0;
        
        foreach ($notifications as $notif) {
            $processed++;
            
            // Mark as processing
            $update = $pdo->prepare("UPDATE notification_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?");
            $update->execute([$notif['id']]);
            
            $data = json_decode($notif['data'], true);
            $notif_success = false;
            
            try {
                if ($notif['type'] === 'email') {
                    $notif_success = send_notification_email($notif['event'], $data);
                } elseif ($notif['type'] === 'sms') {
                    $notif_success = send_notification_sms($notif['event'], $data);
                }
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
            }
            
            $status = $notif_success ? 'sent' : 'failed';
            if (!$notif_success && $notif['attempts'] < $notif['max_attempts']) {
                $status = 'pending';
            }
            
            $update = $pdo->prepare("UPDATE notification_queue SET status = ?, processed_at = NOW() WHERE id = ?");
            $update->execute([$status, $notif['id']]);
            
            if ($notif_success) $success++;
        }
        
        return ['processed' => $processed, 'success' => $success];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Send booking reminders
 */
function send_booking_reminders() {
    global $pdo;
    
    try {
        // Get bookings in next 24 hours
        $stmt = $pdo->query("
            SELECT sb.*, s.name as service_name, u.email, u.phone, u.full_name
            FROM service_bookings sb
            JOIN services s ON sb.service_id = s.id
            LEFT JOIN users u ON sb.user_id = u.id
            WHERE sb.status IN ('pending', 'confirmed')
            AND sb.booking_date = CURDATE()
            AND sb.reminder_sent = 0
        ");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($bookings as $booking) {
            // Add to queue
            add_to_queue_cron('email', 'booking_reminder', [
                'booking_id' => $booking['id'],
                'service_name' => $booking['service_name'],
                'email' => $booking['email']
            ]);
            
            // Mark as reminded
            $update = $pdo->prepare("UPDATE service_bookings SET reminder_sent = 1 WHERE id = ?");
            $update->execute([$booking['id']]);
            
            $count++;
        }
        
        return ['reminders_queued' => $count];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Check abandoned carts
 */
function check_abandoned_carts_cron() {
    global $pdo;
    
    try {
        // Check if cart table exists
        $table_exists = $pdo->query("SHOW TABLES LIKE 'cart'")->rowCount() > 0;
        if (!$table_exists) {
            return ['message' => 'Cart table not found - skipping'];
        }
        
        // Find users with carts older than 24 hours who haven't ordered
        $stmt = $pdo->query("
            SELECT DISTINCT c.user_id, u.email, u.full_name
            FROM cart c
            JOIN users u ON c.user_id = u.id
            WHERE c.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND u.email IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM orders o 
                WHERE o.user_id = c.user_id 
                AND o.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            )
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($users as $user) {
            // Check if already sent
            $check = $pdo->prepare("
                SELECT COUNT(*) FROM notification_queue 
                WHERE event = 'abandoned_cart' AND user_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $check->execute([$user['user_id']]);
            
            if ($check->fetchColumn() == 0) {
                add_to_queue_cron('email', 'abandoned_cart', [
                    'user_id' => $user['user_id'],
                    'email' => $user['email']
                ]);
                $count++;
            }
        }
        
        return ['emails_queued' => $count];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Process scheduled reports
 */
function process_scheduled_reports_cron() {
    require_once __DIR__ . '/../src/reports.php';
    return process_scheduled_reports();
}

/**
 * Expire loyalty points
 */
function expire_loyalty_points_cron() {
    global $pdo;
    
    try {
        // Find expired points
        $stmt = $pdo->query("
            SELECT user_id, SUM(points) as total_points
            FROM loyalty_transactions
            WHERE expires_at < CURDATE() AND type = 'earn' AND points > 0
            GROUP BY user_id
        ");
        $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expired as $item) {
            // Deduct from user
            $update = $pdo->prepare("UPDATE users SET loyalty_points = GREATEST(0, loyalty_points - ?) WHERE id = ?");
            $update->execute([$item['total_points'], $item['user_id']]);
            
            // Log expiration
            $insert = $pdo->prepare("
                INSERT INTO loyalty_transactions (user_id, points, type, description)
                VALUES (?, ?, 'expire', 'Points expired')
            ");
            $insert->execute([$item['user_id'], -$item['total_points']]);
            
            // Mark as expired
            $update = $pdo->prepare("
                UPDATE loyalty_transactions 
                SET points = 0 
                WHERE expires_at < CURDATE() AND type = 'earn' AND user_id = ?
            ");
            $update->execute([$item['user_id']]);
        }
        
        return ['users_processed' => count($expired)];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Update inventory visibility
 */
function update_inventory_visibility() {
    global $pdo;
    
    try {
        // Hide out of stock products
        $stmt = $pdo->query("
            UPDATE products 
            SET visible = 0 
            WHERE stock = 0 AND visible = 1
        ");
        $hidden = $stmt->rowCount();
        
        // Show products back in stock
        $stmt = $pdo->query("
            UPDATE products 
            SET visible = 1 
            WHERE stock > 0 AND visible = 0
        ");
        $shown = $stmt->rowCount();
        
        return ['hidden' => $hidden, 'shown' => $shown];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Cancel unpaid orders
 */
function cancel_unpaid_orders_cron() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            UPDATE orders 
            SET status = 'cancelled', 
                admin_note = 'Auto-cancelled: Payment not received within 24 hours'
            WHERE status = 'pending' 
            AND payment_status = 'pending'
            AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $cancelled = $stmt->rowCount();
        
        return ['cancelled' => $cancelled];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Generate daily snapshot
 */
function generate_daily_snapshot() {
    global $pdo;
    
    try {
        // Get yesterday's stats
        $stmt = $pdo->query("
            INSERT INTO daily_snapshots (date, total_orders, paid_orders, revenue, created_at)
            SELECT 
                CURDATE() - INTERVAL 1 DAY,
                COUNT(*),
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END),
                SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END),
                NOW()
            FROM orders 
            WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
        ");
        
        return ['snapshot_created' => true];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Check low stock alerts
 */
function check_low_stock_alerts() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.visible = 1 AND p.stock <= p.reorder_level
            AND p.id NOT IN (
                SELECT product_id FROM inventory_alerts 
                WHERE alert_type IN ('low_stock', 'out_of_stock') 
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            )
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($products as $product) {
            $alert_type = $product['stock'] == 0 ? 'out_of_stock' : 'low_stock';
            
            // Log alert
            $insert = $pdo->prepare("
                INSERT INTO inventory_alerts (product_id, alert_type, previous_stock, current_stock)
                VALUES (?, ?, ?, ?)
            ");
            $insert->execute([$product['id'], $alert_type, $product['stock'] + 1, $product['stock']]);
            
            // Queue email to admin
            add_to_queue_cron('email', 'low_stock_alert', [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'stock' => $product['stock'],
                'reorder_level' => $product['reorder_level']
            ]);
            
            $count++;
        }
        
        return ['alerts_created' => $count];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Add to notification queue
 */
function add_to_queue_cron($type, $event, $data) {
    global $pdo;
    
    try {
        $user_id = $data['user_id'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO notification_queue (type, event, data, user_id, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$type, $event, json_encode($data), $user_id]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send notification email
 */
function send_notification_email($event, $data) {
    require_once __DIR__ . '/../src/email.php';
    
    $admin_email = getenv('ADMIN_EMAIL') ?: 'admin@outsourcedtechnologies.co.ke';
    
    switch ($event) {
        case 'booking_reminder':
            // Would send reminder email
            return true;
            
        case 'abandoned_cart':
            // Would send cart recovery email
            return true;
            
        case 'low_stock_alert':
            $subject = "Low Stock Alert: " . $data['product_name'];
            $body = "Product: {$data['product_name']}\nCurrent Stock: {$data['stock']}\nReorder Level: {$data['reorder_level']}";
            return send_email($admin_email, $subject, $body);
            
        default:
            return false;
    }
}

/**
 * Send notification SMS
 */
function send_notification_sms($event, $data) {
    // Would send SMS notification
    return true;
}
