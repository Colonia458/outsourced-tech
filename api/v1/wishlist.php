<?php
/**
 * Wishlist API Endpoints
 * Outsourced Technologies E-Commerce Platform
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/security.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/wishlist.php';

// Rate limiting
apply_rate_limit('api_wishlist', 60, 60);

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// API Response helper
function api_response($success, $message, $data = null, $status = 200) {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Handle request based on method
switch ($method) {
    case 'GET':
        // Get wishlist
        if (!is_logged_in()) {
            api_response(false, 'Authentication required', null, 401);
        }
        
        $user_id = current_user_id();
        $items = get_wishlist($user_id);
        
        api_response(true, 'Wishlist retrieved successfully', [
            'items' => $items,
            'count' => count($items)
        ]);
        break;
        
    case 'POST':
        // Add to wishlist
        if (!is_logged_in()) {
            api_response(false, 'Authentication required', null, 401);
        }
        
        $user_id = current_user_id();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['product_id'])) {
            api_response(false, 'Product ID is required', null, 400);
        }
        
        $product_id = (int)$input['product_id'];
        
        if ($product_id <= 0) {
            api_response(false, 'Invalid product ID', null, 400);
        }
        
        $result = add_to_wishlist($user_id, $product_id);
        
        if ($result['success']) {
            $count = get_wishlist_count($user_id);
            api_response(true, $result['message'], ['count' => $count]);
        } else {
            api_response(false, $result['message'], null, 400);
        }
        break;
        
    case 'DELETE':
        // Remove from wishlist
        if (!is_logged_in()) {
            api_response(false, 'Authentication required', null, 401);
        }
        
        $user_id = current_user_id();
        
        // Get product_id from query string or JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 
                      (isset($input['product_id']) ? (int)$input['product_id'] : 0);
        
        if ($product_id <= 0) {
            api_response(false, 'Product ID is required', null, 400);
        }
        
        $removed = remove_from_wishlist($user_id, $product_id);
        
        if ($removed) {
            $count = get_wishlist_count($user_id);
            api_response(true, 'Product removed from wishlist', ['count' => $count]);
        } else {
            api_response(false, 'Product not found in wishlist', null, 404);
        }
        break;
        
    case 'PUT':
        // Move to cart
        if (!is_logged_in()) {
            api_response(false, 'Authentication required', null, 401);
        }
        
        $user_id = current_user_id();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['product_id'])) {
            api_response(false, 'Product ID is required', null, 400);
        }
        
        $product_id = (int)$input['product_id'];
        $result = move_to_cart($user_id, $product_id);
        
        if ($result['success']) {
            $count = get_wishlist_count($user_id);
            api_response(true, $result['message'], ['count' => $count]);
        } else {
            api_response(false, $result['message'], null, 400);
        }
        break;
        
    default:
        api_response(false, 'Method not allowed', null, 405);
}
