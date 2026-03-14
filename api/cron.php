<?php
// api/cron.php - Cron Job Handler for Scheduled Tasks
// This file should be called via cron (e.g., every hour)
// Usage: */5 * * * * curl -s https://yourdomain.com/api/cron.php?key=YOUR_SECRET_KEY

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../src/email.php';

header('Content-Type: application/json');

// Security check - require secret key
$secret_key = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';
$expected_key = getenv('CRON_SECRET_KEY') ?: 'your-secret-cron-key-change-me';

if ($secret_key !== $expected_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tasks' => []
];

// Task 1: Clean old sessions (if using file sessions)
$results['tasks']['cleanup_sessions'] = cleanupOldSessions();

// Task 2: Clean old chatbot conversations (older than 30 days)
$results['tasks']['cleanup_chatbot'] = cleanupOldChatbotConversations();

// Task 3: Check and notify low stock products
$results['tasks']['low_stock_notification'] = checkLowStock();

// Task 4: Cancel unpaid orders after 24 hours
$results['tasks']['cancel_unpaid_orders'] = cancelUnpaidOrders();

// Task 5: Clean old password reset tokens
$results['tasks']['cleanup_password_resets'] = cleanupPasswordResets();

// Task 6: Update product view counts analytics
$results['tasks']['cleanup_old_logs'] = cleanupOldLogs();

// Task 7: Check pending payments timeout
$results['tasks']['check_pending_payments'] = checkPendingPayments();

echo json_encode($results, JSON_PRETTY_PRINT);

/**
 * Clean old PHP sessions
 */
function cleanupOldSessions() {
    $result = ['status' => 'ok', 'message' => 'No session cleanup needed (using database)'];
    return $result;
}

/**
 * Clean old chatbot conversations
 */
function cleanupOldChatbotConversations() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM chatbot_conversations 
            WHERE created_at < NOW() - INTERVAL '30 days'
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        return [
            'status' => 'ok',
            'message' => "Deleted $deleted old chatbot conversations"
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Check low stock and notify admin
 */
function checkLowStock() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT id, name, stock, low_stock_threshold 
            FROM products 
            WHERE stock <= low_stock_threshold 
            AND visible = 1
            LIMIT 10
        ");
        $low_stock_products = $stmt->fetchAll();
        
        if (count($low_stock_products) > 0) {
            // Log for admin review
            $message = "Low stock alert: " . count($low_stock_products) . " products below threshold\n";
            foreach ($low_stock_products as $product) {
                $message .= "- {$product['name']}: {$product['stock']} remaining\n";
            }
            
            // Log to file
            @file_put_contents(
                __DIR__ . '/../logs/low_stock.log',
                "[" . date('Y-m-d H:i:s') . "] " . $message . "\n",
                FILE_APPEND
            );
            
            return [
                'status' => 'warning',
                'count' => count($low_stock_products),
                'message' => 'Low stock products found and logged'
            ];
        }
        
        return [
            'status' => 'ok',
            'message' => 'All products sufficiently stocked'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Cancel unpaid orders after 24 hours
 */
function cancelUnpaidOrders() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'cancelled', 
                admin_note = 'Auto-cancelled: Payment not received within 24 hours'
            WHERE status = 'pending' 
            AND payment_status = 'pending'
            AND created_at < NOW() - INTERVAL '24 hours'
        ");
        $stmt->execute();
        $cancelled = $stmt->rowCount();
        
        return [
            'status' => 'ok',
            'message' => "Cancelled $cancelled unpaid orders"
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Clean expired password reset tokens
 */
function cleanupPasswordResets() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM password_resets 
            WHERE expires_at < NOW() 
            OR used_at IS NOT NULL
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        return [
            'status' => 'ok',
            'message' => "Deleted $deleted expired/used password reset tokens"
        ];
    } catch (Exception $e) {
        // Table might not exist yet
        return [
            'status' => 'ok',
            'message' => 'No password resets to clean (table may not exist)'
        ];
    }
}

/**
 * Clean old log files
 */
function cleanupOldLogs() {
    $log_dir = __DIR__ . '/../logs';
    $deleted = 0;
    
    if (is_dir($log_dir)) {
        $files = glob($log_dir . '/*.log');
        foreach ($files as $file) {
            if (filemtime($file) < time() - (30 * 24 * 60 * 60)) { // 30 days
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
    }
    
    return [
        'status' => 'ok',
        'message' => "Deleted $deleted old log files"
    ];
}

/**
 * Check and flag stale pending payments
 */
function checkPendingPayments() {
    global $pdo;
    
    try {
        // Find payments pending for more than 1 hour
        $stmt = $pdo->prepare("
            SELECT p.id, p.order_id, p.created_at, o.order_number
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE p.status = 'pending'
            AND p.created_at < NOW() - INTERVAL '1 hour'
        ");
        $stmt->execute();
        $stale_payments = $stmt->fetchAll();
        
        if (count($stale_payments) > 0) {
            @file_put_contents(
                __DIR__ . '/../logs/stale_payments.log',
                "[" . date('Y-m-d H:i:s') . "] " . count($stale_payments) . " stale pending payments found\n",
                FILE_APPEND
            );
        }
        
        return [
            'status' => 'ok',
            'message' => count($stale_payments) . ' stale pending payments found'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
