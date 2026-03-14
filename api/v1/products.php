<?php
// api/v1/products.php

require_once '../../src/config.php';
require_once '../../src/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $cat_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;

    $sql = "SELECT p.id, p.name, p.slug, p.price, p.short_description,
                   (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image,
                   c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.visible = 1";

    $params = [];

    if ($cat_id) {
        $sql .= " AND p.category_id = ?";
        $params[] = $cat_id;
    }

    $sql .= " ORDER BY p.featured DESC, p.id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $products = fetchAll($sql, $params);

    echo json_encode([
        'success' => true,
        'data'    => $products,
        'count'   => count($products)
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);