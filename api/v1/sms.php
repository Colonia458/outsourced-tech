<?php
// api/v1/sms.php - SMS Notifications API

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/database.php';
require_once __DIR__ . '/../../src/security.php';
require_once __DIR__ . '/../../src/sms.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get user ID from token (optional)
$userId = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader && strpos($authHeader, 'Bearer') === 0) {
    $token = trim(str_replace('Bearer', '', $authHeader));
    $userId = verify_token($token);
}

switch ($method) {
    case 'GET':
        // Get SMS subscription status
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            break;
        }
        
        $subscription = get_sms_subscription($userId);
        
        echo json_encode([
            'success' => true,
            'data' => $subscription,
        ]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'subscribe':
                // Subscribe to SMS notifications
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    break;
                }
                
                if (!isset($data['phone_number'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Phone number required']);
                    break;
                }
                
                // Check if subscription exists
                $existing = get_sms_subscription($userId);
                
                if ($existing) {
                    // Update
                    update_sms_subscription($userId, [
                        'phone_number' => $data['phone_number'],
                    ]);
                } else {
                    // Insert new
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("
                        INSERT INTO sms_subscriptions (user_id, phone_number)
                        VALUES (?, ?)
                    ");
                    $stmt->bind_param('is', $userId, $data['phone_number']);
                    $stmt->execute();
                }
                
                // Send verification code
                $code = rand(100000, 999999);
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    UPDATE sms_subscriptions 
                    SET verification_code = ?, verification_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                    WHERE user_id = ?
                ");
                $stmt->bind_param('si', $code, $userId);
                $stmt->execute();
                
                // Send verification SMS
                send_sms($data['phone_number'], "Your verification code is: $code", 'verification');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Verification code sent',
                ]);
                break;
                
            case 'verify':
                // Verify phone number
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    break;
                }
                
                if (!isset($data['code'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Verification code required']);
                    break;
                }
                
                $result = verify_phone_otp($userId, $data['code']);
                
                echo json_encode($result);
                break;
                
            case 'unsubscribe':
                // Unsubscribe from SMS
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    break;
                }
                
                update_sms_subscription($userId, [
                    'notifications_order_updates' => false,
                    'notifications_promotions' => false,
                    'notifications_delivery_updates' => false,
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Unsubscribed from SMS notifications',
                ]);
                break;
                
            case 'update_preferences':
                // Update notification preferences
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    break;
                }
                
                $preferences = [];
                if (isset($data['order_updates'])) {
                    $preferences['notifications_order_updates'] = $data['order_updates'];
                }
                if (isset($data['promotions'])) {
                    $preferences['notifications_promotions'] = $data['promotions'];
                }
                if (isset($data['delivery_updates'])) {
                    $preferences['notifications_delivery_updates'] = $data['delivery_updates'];
                }
                
                update_sms_subscription($userId, $preferences);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Preferences updated',
                ]);
                break;
                
            case 'send_promotional':
                // Send promotional SMS (admin only)
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    break;
                }
                
                // Check if admin
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                
                if (!$user || !$user['is_admin']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Admin access required']);
                    break;
                }
                
                if (!isset($data['message'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Message required']);
                    break;
                }
                
                // Get all subscribed users
                $stmt = $db->prepare("
                    SELECT phone_number FROM sms_subscriptions 
                    WHERE is_verified = TRUE AND notifications_promotions = TRUE
                ");
                $stmt->execute();
                $subscribers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $count = 0;
                foreach ($subscribers as $subscriber) {
                    send_promotional_sms($subscriber['phone_number'], $data['message']);
                    $count++;
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "Promotional SMS sent to $count subscribers",
                ]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
