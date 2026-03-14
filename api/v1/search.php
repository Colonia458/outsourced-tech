<?php
/**
 * Search API Endpoints
 * Outsourced Technologies E-Commerce Platform
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/security.php';
require_once __DIR__ . '/../../src/search.php';

// Rate limiting
apply_rate_limit('api_search', 60, 60);

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

$action = $_GET['action'] ?? 'search';

switch ($action) {
    case 'search':
        // Get search/filter parameters
        $params = [
            'search' => $_GET['search'] ?? '',
            'category' => isset($_GET['category']) ? explode(',', $_GET['category']) : null,
            'min_price' => isset($_GET['min_price']) ? (float)$_GET['min_price'] : null,
            'max_price' => isset($_GET['max_price']) ? (float)$_GET['max_price'] : null,
            'brand' => isset($_GET['brand']) ? explode(',', $_GET['brand']) : null,
            'min_rating' => isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : null,
            'in_stock' => isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : null,
            'sort' => $_GET['sort'] ?? 'newest',
            'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
            'per_page' => isset($_GET['per_page']) ? (int)$_GET['per_page'] : 12
        ];
        
        // Save search to history
        if (!empty($params['search'])) {
            save_search_history($params['search']);
        }
        
        $results = search_products($params);
        
        // Format products for response
        $products = array_map(function($p) {
            return [
                'id' => (int)$p['id'],
                'name' => $p['name'],
                'price' => (float)$p['price'],
                'image' => $p['image'],
                'short_description' => $p['short_description'],
                'stock' => (int)$p['stock'],
                'category_name' => $p['category_name'],
                'avg_rating' => (float)$p['avg_rating'],
                'review_count' => (int)$p['review_count']
            ];
        }, $results['products']);
        
        api_response(true, 'Search results retrieved', [
            'products' => $products,
            'pagination' => [
                'page' => $results['page'],
                'per_page' => $results['per_page'],
                'total' => $results['total'],
                'total_pages' => $results['total_pages']
            ]
        ]);
        break;
        
    case 'suggestions':
        // Get autocomplete suggestions
        $term = $_GET['term'] ?? '';
        
        if (strlen($term) < 2) {
            api_response(true, 'Term too short', ['products' => [], 'categories' => []]);
        }
        
        $suggestions = get_search_suggestions($term);
        
        api_response(true, 'Suggestions retrieved', $suggestions);
        break;
        
    case 'filters':
        // Get available filter options
        $current_filters = [
            'category' => isset($_GET['category']) ? explode(',', $_GET['category']) : [],
            'brand' => isset($_GET['brand']) ? explode(',', $_GET['brand']) : []
        ];
        
        $options = get_filter_options($current_filters);
        
        api_response(true, 'Filter options retrieved', $options);
        break;
        
    case 'history':
        // Get search history
        $history = get_popular_searches();
        
        api_response(true, 'Search history retrieved', [
            'history' => $history
        ]);
        break;
        
    default:
        api_response(false, 'Invalid action', null, 400);
}
