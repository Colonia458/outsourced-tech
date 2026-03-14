<?php
// api/v1/categories.php

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/database.php';

// Set proper JSON response header
header('Content-Type: application/json; charset=utf-8');

// Optional: allow CORS only from your frontend domain in production
// header('Access-Control-Allow-Origin: http://localhost:3000'); // or your actual frontend URL
header('Access-Control-Allow-Origin: *'); // fine for local development

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Fetch all categories (sorted)
        $categories = fetchAll(
            "SELECT id, name, slug, description, display_order 
             FROM categories 
             ORDER BY display_order ASC, name ASC"
        );

        echo json_encode([
            'success' => true,
            'count'   => count($categories),
            'data'    => $categories
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed. Use GET.'
]);