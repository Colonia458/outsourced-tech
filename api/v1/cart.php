<?php
// api/v1/cart.php

require_once '../../src/config.php';
require_once '../../src/cart.php';

header('Content-Type: application/json');

// Get action from query string, form data, or JSON body
$action = $_GET['action'] ?? '';

// Parse JSON body if present
$json_data = json_decode(file_get_contents('php://input'), true);
$data = $json_data ?? $_POST;

// If action not in query string, check JSON or POST
if (empty($action) && isset($data['action'])) {
    $action = $data['action'];
}

if ($action === 'count') {
    echo json_encode(['success' => true, 'count' => cart_count()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($json_data)) {
    switch ($action) {
        case 'add':
            $success = cart_add((int)($data['product_id'] ?? 0), (int)($data['quantity'] ?? 1));
            echo json_encode(['success' => $success]);
            break;

        case 'update':
            $success = cart_update((int)($data['item_id'] ?? 0), (int)($data['quantity'] ?? 1));
            echo json_encode(['success' => $success]);
            break;

        case 'remove':
            $success = cart_remove((int)($data['item_id'] ?? 0));
            echo json_encode(['success' => $success]);
            break;

        default:
            // Debug: log what we received
            error_log("Cart API - action: $action, data: " . json_encode($data));
            echo json_encode(['success' => false, 'message' => 'Invalid action: "' . $action . '"']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'items'   => get_cart(),
        'total'   => cart_total(),
        'count'   => cart_count()
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);