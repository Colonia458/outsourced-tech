<?php
// src/activity.php - Activity Logging System for Analytics

/**
 * Log an activity event
 * 
 * @param string $action The action performed (e.g., 'view_product', 'add_to_cart', 'place_order')
 * @param array $details Additional details about the action
 * @param int|null $user_id User ID if logged in, null for guest
 */
function log_activity($action, $details = [], $user_id = null) {
    global $pdo;
    
    // Determine if using Supabase or local database
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return true;
        } catch (Exception $e) {
            // Table might not exist, log to file instead
            log_to_file($action, $details);
        }
    }
    
    // Fallback to file logging
    log_to_file($action, $details);
    return false;
}

/**
 * Fallback file-based logging
 */
function log_to_file($action, $details) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/activity.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND);
}

/**
 * Get activity summary for dashboard
 */
function get_activity_summary($days = 7) {
    global $pdo;
    
    $summary = [
        'total_visits' => 0,
        'unique_visitors' => 0,
        'page_views' => 0,
        'add_to_cart' => 0,
        'checkouts' => 0,
        'orders' => 0
    ];
    
    try {
        // Total visits (all activity logs in period)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM activity_logs 
            WHERE created_at > NOW() - INTERVAL '" . (int)$days . " days'
        ");
        $stmt->execute();
        $summary['total_visits'] = (int)$stmt->fetchColumn();
        
        // Unique visitors
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) FROM activity_logs 
            WHERE created_at > NOW() - INTERVAL '" . (int)$days . " days'
        ");
        $stmt->execute();
        $summary['unique_visitors'] = (int)$stmt->fetchColumn();
        
        // Page views (view_product actions)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM activity_logs 
            WHERE action = 'view_product' AND created_at > NOW() - INTERVAL '" . (int)$days . " days'
        ");
        $stmt->execute();
        $summary['page_views'] = (int)$stmt->fetchColumn();
        
        // Add to cart
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM activity_logs 
            WHERE action = 'add_to_cart' AND created_at > NOW() - INTERVAL '" . (int)$days . " days'
        ");
        $stmt->execute();
        $summary['add_to_cart'] = (int)$stmt->fetchColumn();
        
        // Checkouts initiated
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM activity_logs 
            WHERE action = 'checkout_started' AND created_at > NOW() - INTERVAL '" . (int)$days . " days'
        ");
        $stmt->execute();
        $summary['checkouts'] = (int)$stmt->fetchColumn();
        
        // Orders placed
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM activity_logs 
            WHERE action = 'place_order' AND created_at > NOW() - INTERVAL '" . (int)$days . " days'
        ");
        $stmt->execute();
        $summary['orders'] = (int)$stmt->fetchColumn();
        
    } catch (Exception $e) {
        // Return default summary if table doesn't exist
    }
    
    return $summary;
}

/**
 * Get top products by views
 */
function get_top_products($days = 7, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                details->>'product_id' as product_id,
                details->>'product_name' as product_name,
                COUNT(*) as view_count
            FROM activity_logs
            WHERE action = 'view_product' 
            AND created_at > NOW() - INTERVAL '" . (int)$days . " days'
            GROUP BY details->>'product_id', details->>'product_name'
            ORDER BY view_count DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get daily sales statistics
 */
function get_daily_stats($days = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as order_count,
                SUM(total_amount) as total_sales
            FROM orders
            WHERE created_at > NOW() - INTERVAL '" . (int)$days . " days'
            AND payment_status = 'paid'
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Track page view
 */
function track_page_view($page, $user_id = null) {
    log_activity('view_page', [
        'page' => $page,
        'url' => $_SERVER['REQUEST_URI'] ?? ''
    ], $user_id);
}

/**
 * Track product view
 */
function track_product_view($product_id, $product_name, $user_id = null) {
    log_activity('view_product', [
        'product_id' => $product_id,
        'product_name' => $product_name
    ], $user_id);
}

/**
 * Track add to cart
 */
function track_add_to_cart($product_id, $product_name, $quantity, $price, $user_id = null) {
    log_activity('add_to_cart', [
        'product_id' => $product_id,
        'product_name' => $product_name,
        'quantity' => $quantity,
        'price' => $price
    ], $user_id);
}

/**
 * Track checkout started
 */
function track_checkout_started($order_id, $total, $user_id = null) {
    log_activity('checkout_started', [
        'order_id' => $order_id,
        'total' => $total
    ], $user_id);
}

/**
 * Track order placed
 */
function track_order_placed($order_id, $order_number, $total, $user_id = null) {
    log_activity('place_order', [
        'order_id' => $order_id,
        'order_number' => $order_number,
        'total' => $total
    ], $user_id);
}

/**
 * Track user registration
 */
function track_registration($user_id, $username) {
    log_activity('user_register', [
        'user_id' => $user_id,
        'username' => $username
    ], $user_id);
}

/**
 * Track user login
 */
function track_login($user_id, $username) {
    log_activity('user_login', [
        'user_id' => $user_id,
        'username' => $username
    ], $user_id);
}

// Activity constants for consistent tracking
define('ACTIVITY_VIEW_PAGE', 'view_page');
define('ACTIVITY_VIEW_PRODUCT', 'view_product');
define('ACTIVITY_ADD_TO_CART', 'add_to_cart');
define('ACTIVITY_REMOVE_FROM_CART', 'remove_from_cart');
define('ACTIVITY_CHECKOUT_STARTED', 'checkout_started');
define('ACTIVITY_CHECKOUT_COMPLETED', 'checkout_completed');
define('ACTIVITY_PLACE_ORDER', 'place_order');
define('ACTIVITY_USER_REGISTER', 'user_register');
define('ACTIVITY_USER_LOGIN', 'user_login');
define('ACTIVITY_USER_LOGOUT', 'user_logout');
define('ACTIVITY_BOOK_SERVICE', 'book_service');
define('ACTIVITY_USE_COUPON', 'use_coupon');
define('ACTIVITY_SEARCH', 'search');
