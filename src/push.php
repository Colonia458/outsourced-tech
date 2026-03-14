<?php
// src/push.php - Web Push Notification Service

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Save push subscription
 */
function save_push_subscription($data) {
    $db = Database::getInstance()->getConnection();
    
    $userId = $data['user_id'] ?? null;
    $endpoint = $data['endpoint'];
    $p256dh = $data['p256dh'];
    $auth = $data['auth'];
    $browser = $data['browser'] ?? 'unknown';
    
    // Check if subscription already exists
    $stmt = $db->prepare("
        SELECT id FROM push_subscriptions 
        WHERE endpoint = ? AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))
    ");
    $stmt->bind_param('sii', $endpoint, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing
        $row = $result->fetch_assoc();
        $stmt = $db->prepare("
            UPDATE push_subscriptions 
            SET p256dh = ?, auth = ?, browser = ?, is_active = TRUE
            WHERE id = ?
        ");
        $stmt->bind_param('sssi', $p256dh, $auth, $browser, $row['id']);
        $stmt->execute();
        return $row['id'];
    }
    
    // Insert new
    $stmt = $db->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, browser)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issss', $userId, $endpoint, $p256dh, $auth, $browser);
    $stmt->execute();
    
    return $db->insert_id;
}

/**
 * Remove push subscription
 */
function remove_push_subscription($endpoint) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("UPDATE push_subscriptions SET is_active = FALSE WHERE endpoint = ?");
    $stmt->bind_param('s', $endpoint);
    $stmt->execute();
    
    return ['success' => true];
}

/**
 * Get active push subscriptions
 */
function get_active_subscriptions($userId = null) {
    $db = Database::getInstance()->getConnection();
    
    if ($userId) {
        $stmt = $db->prepare("
            SELECT * FROM push_subscriptions 
            WHERE is_active = TRUE AND user_id = ?
        ");
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE is_active = TRUE");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subscriptions = [];
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
    
    return $subscriptions;
}

/**
 * Send push notification
 */
function send_push_notification($subscription, $title, $body, $options = []) {
    $config = get_push_vapid_config();
    
    if (empty($config['public_key'])) {
        return ['success' => false, 'message' => 'Push notifications not configured'];
    }
    
    // Prepare notification payload
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => $options['icon'] ?? '/assets/images/logo.png',
        'badge' => $options['badge'] ?? '/assets/images/logo.png',
        'image' => $options['image'] ?? null,
        'tag' => $options['tag'] ?? null,
        'url' => $options['url'] ?? null,
        'data' => $options['data'] ?? null,
        'requireInteraction' => $options['require_interaction'] ?? false,
        'vibrate' => $options['vibrate'] ?? [200, 100, 200],
        'actions' => $options['actions'] ?? [],
    ]);
    
    // For demo/simulation, just log it
    if (empty($config['private_key'])) {
        return [
            'success' => true,
            'message' => 'Push notification simulated',
            'payload' => json_decode($payload, true),
        ];
    }
    
    // In production, use web-push library
    // This is a placeholder for actual implementation
    
    return [
        'success' => true,
        'message' => 'Notification queued',
        'payload' => json_decode($payload, true),
    ];
}

/**
 * Get VAPID configuration
 */
function get_push_vapid_config() {
    return [
        'public_key' => getenv('VAPID_PUBLIC_KEY') ?: '',
        'private_key' => getenv('VAPID_PRIVATE_KEY') ?: '',
        'subject' => getenv('VAPID_SUBJECT') ?: 'mailto:admin@outsourcedtechnologies.co.ke',
    ];
}

/**
 * Create push notification record
 */
function create_push_notification($title, $body, $options = []) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        INSERT INTO push_notifications (title, body, icon, url, image, badge, tag, requires_interaction)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $icon = $options['icon'] ?? null;
    $url = $options['url'] ?? null;
    $image = $options['image'] ?? null;
    $badge = $options['badge'] ?? null;
    $tag = $options['tag'] ?? null;
    $requiresInteraction = $options['require_interaction'] ?? false;
    
    $stmt->bind_param('sssssssi', $title, $body, $icon, $url, $image, $badge, $tag, $requiresInteraction);
    $stmt->execute();
    
    return $db->insert_id;
}

/**
 * Send push notification to user
 */
function notify_user($userId, $title, $body, $options = []) {
    $subscriptions = get_active_subscriptions($userId);
    
    if (empty($subscriptions)) {
        return ['success' => false, 'message' => 'No active subscriptions'];
    }
    
    $notificationId = create_push_notification($title, $body, $options);
    $results = [];
    
    foreach ($subscriptions as $subscription) {
        $result = send_push_notification($subscription, $title, $body, $options);
        
        // Log recipient
        log_push_recipient($notificationId, $subscription['id'], $result['success'] ? 'sent' : 'failed');
        
        $results[] = $result;
    }
    
    return [
        'success' => true,
        'notification_id' => $notificationId,
        'recipients' => count($results),
        'results' => $results,
    ];
}

/**
 * Log push notification recipient
 */
function log_push_recipient($notificationId, $subscriptionId, $status, $errorMessage = null) {
    $db = Database::getInstance()->getConnection();
    
    $sentAt = ($status === 'sent') ? 'CURRENT_TIMESTAMP' : 'NULL';
    
    $sql = "INSERT INTO push_notification_recipients (notification_id, subscription_id, status, error_message)";
    if ($status === 'sent') {
        $sql .= " VALUES (?, ?, ?, ?)";
    } else {
        $sql .= " VALUES (?, ?, ?, ?)";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iiss', $notificationId, $subscriptionId, $status, $errorMessage);
    $stmt->execute();
}

/**
 * Send order notification to user
 */
function send_order_push_notification($userId, $orderId, $status, $message) {
    $titles = [
        'confirmed' => 'Order Confirmed!',
        'shipped' => 'Order Shipped!',
        'delivered' => 'Order Delivered!',
        'cancelled' => 'Order Cancelled',
    ];
    
    $title = $titles[$status] ?? 'Order Update';
    $url = '/outsourced/public/track-order.php?order_id=' . $orderId;
    
    return notify_user($userId, $title, $message, [
        'url' => $url,
        'tag' => 'order_' . $orderId,
    ]);
}

/**
 * Get push notification history
 */
function get_push_notification_history($limit = 50) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT * FROM push_notifications 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        // Get recipient count
        $stmt2 = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered
            FROM push_notification_recipients 
            WHERE notification_id = ?
        ");
        $stmt2->bind_param('i', $row['id']);
        $stmt2->execute();
        $stats = $stmt2->get_result()->fetch_assoc();
        
        $row['stats'] = $stats;
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Generate VAPID keys (run once in CLI)
 */
function generate_vapid_keys() {
    // This would generate actual VAPID keys in production
    // For now, return placeholder keys
    
    return [
        'public_key' => 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U',
        'private_key' => 'UUxI4O8-FbRouAf7-7OTt9GH4o-15VnH7e1W8r3qB2k',
    ];
}
