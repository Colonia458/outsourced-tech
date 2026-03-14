<?php
// api/v1/recommendations.php - Recommendations API

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/database.php';
require_once __DIR__ . '/../../src/security.php';
require_once __DIR__ . '/../../src/recommendations.php';

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
        // Get recommendations
        $type = $_GET['type'] ?? 'personalized';
        $limit = min(intval($_GET['limit'] ?? 10), 50);
        $productId = $_GET['product_id'] ?? null;
        
        if (!$userId && $type === 'personalized') {
            // Return popular for non-authenticated users
            $type = 'popular';
        }
        
        $recommendations = get_recommendations_api($userId, $type, $limit);
        
        // Format response
        $response = [
            'success' => true,
            'type' => $type,
            'count' => count($recommendations),
            'data' => array_map(function($item) {
                return [
                    'id' => $item['id'] ?? $item['product_id'],
                    'name' => $item['name'],
                    'price' => floatval($item['price']),
                    'image' => $item['image'],
                    'score' => isset($item['score']) ? floatval($item['score']) : null,
                ];
            }, $recommendations),
        ];
        
        echo json_encode($response);
        break;
        
    case 'POST':
        // Log product interaction
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['product_id']) || !isset($data['interaction_type'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            break;
        }
        
        // Validate interaction type
        $validTypes = ['view', 'add_to_cart', 'purchase', 'wishlist', 'compare', 'review'];
        if (!in_array($data['interaction_type'], $validTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid interaction type']);
            break;
        }
        
        // Get session ID
        $sessionId = $_COOKIE['PHPSESSID'] ?? null;
        
        // Get product price if not provided
        $priceAtTime = $data['price'] ?? null;
        if (!$priceAtTime) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->bind_param('i', $data['product_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $priceAtTime = $row['price'];
            }
        }
        
        $interactionId = log_product_interaction([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'product_id' => $data['product_id'],
            'interaction_type' => $data['interaction_type'],
            'rating' => $data['rating'] ?? null,
            'price_at_time' => $priceAtTime,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
        ]);
        
        echo json_encode([
            'success' => true,
            'interaction_id' => $interactionId,
        ]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
