<?php
/**
 * Product Comparison API Endpoints
 * Outsourced Technologies E-Commerce Platform
 */

// Disable all error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Wrap everything in try-catch to ensure JSON output
try {
    require_once __DIR__ . '/../../src/config.php';
    require_once __DIR__ . '/../../src/compare.php';
    
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get':
        case 'list':
            $products = get_comparison_products();
            $formatted = array_map(function($p) {
                return [
                    'id' => (int)$p['id'],
                    'name' => $p['name'],
                    'price' => (float)$p['price'],
                    'image' => $p['image'],
                    'short_description' => $p['short_description'] ?? '',
                    'description' => $p['description'] ?? '',
                    'stock' => (int)$p['stock'],
                    'category_name' => $p['category_name'] ?? '',
                    'brand' => $p['brand'] ?? '',
                    'avg_rating' => (float)($p['avg_rating'] ?? 0),
                    'review_count' => (int)($p['review_count'] ?? 0)
                ];
            }, $products);
            echo json_encode(['success' => true, 'message' => 'Comparison retrieved', 'data' => ['products' => $formatted, 'count' => count($formatted)]]);
            break;
            
        case 'add':
            $input = json_decode(file_get_contents('php://input'), true);
            $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
            
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required', 'data' => null, 'status' => 400]);
                exit;
            }
            
            $result = add_to_comparison($product_id);
            
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => $result['message'], 'data' => ['count' => $result['count']]]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message'], 'data' => ['count' => $result['count']], 'status' => 400]);
            }
            break;
            
        case 'remove':
            $input = json_decode(file_get_contents('php://input'), true);
            $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
            
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required', 'data' => null, 'status' => 400]);
                exit;
            }
            
            remove_from_comparison($product_id);
            echo json_encode(['success' => true, 'message' => 'Product removed from comparison', 'data' => ['count' => get_comparison_count()]]);
            break;
            
        case 'toggle':
            $input = json_decode(file_get_contents('php://input'), true);
            $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
            
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required', 'data' => null, 'status' => 400]);
                exit;
            }
            
            $result = toggle_comparison($product_id);
            echo json_encode(['success' => true, 'message' => 'Comparison updated', 'data' => ['action' => $result['action'], 'count' => $result['count']]]);
            break;
            
        case 'clear':
            clear_comparison();
            echo json_encode(['success' => true, 'message' => 'Comparison cleared', 'data' => ['count' => 0]]);
            break;
            
        case 'check':
            $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
            
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required', 'data' => null, 'status' => 400]);
                exit;
            }
            
            echo json_encode(['success' => true, 'message' => 'Status retrieved', 'data' => ['in_comparison' => is_in_comparison($product_id), 'count' => get_comparison_count()]]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null, 'status' => 400]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'data' => null, 'status' => 500]);
}
