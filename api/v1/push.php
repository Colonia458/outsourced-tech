<?php
// api/v1/push.php - Push Notifications API

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/database.php';
require_once __DIR__ . '/../../src/security.php';
require_once __DIR__ . '/../../src/push.php';

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
        // Get VAPID public key
        $config = get_push_vapid_config();
        
        echo json_encode([
            'success' => true,
            'public_key' => $config['public_key'],
        ]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'subscribe':
                // Save push subscription
                if (!isset($data['endpoint']) || !isset($data['keys'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing subscription data']);
                    break;
                }
                
                $subscriptionId = save_push_subscription([
                    'user_id' => $userId,
                    'endpoint' => $data['endpoint'],
                    'p256dh' => $data['keys']['p256dh'],
                    'auth' => $data['keys']['auth'],
                    'browser' => $data['browser'] ?? 'unknown',
                ]);
                
                echo json_encode([
                    'success' => true,
                    'subscription_id' => $subscriptionId,
                ]);
                break;
                
            case 'unsubscribe':
                // Remove push subscription
                if (!isset($data['endpoint'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing endpoint']);
                    break;
                }
                
                remove_push_subscription($data['endpoint']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Unsubscribed successfully',
                ]);
                break;
                
            case 'test':
                // Send test notification
                if (!$userId) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Authentication required']);
                    break;
                }
                
                $result = notify_user($userId, 'Test Notification', 'This is a test push notification from Outsourced Technologies!');
                
                echo json_encode($result);
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
