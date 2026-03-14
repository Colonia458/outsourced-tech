<?php
// api/index.php - API Router Entry Point

// Set security headers
require_once __DIR__ . '/../src/security.php';
set_security_headers();

// Start session securely
secure_session_start();

// Handle CORS (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include router
require_once __DIR__ . '/../src/router.php';
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/OrderController.php';

// Create router
$router = create_router('/api');

// Product routes
$router->get('/products', 'ProductController@index');
$router->get('/products/{id}', 'ProductController@show');
$router->post('/products', 'ProductController@store');
$router->put('/products/{id}', 'ProductController@update');
$router->delete('/products/{id}', 'ProductController@destroy');

// Order routes
$router->post('/orders', 'OrderController@store');
$router->get('/orders', 'OrderController@index');
$router->get('/orders/{id}', 'OrderController@show');
$router->put('/orders/{id}/status', 'OrderController@updateStatus');

// Dispatch request
$router->dispatch();
