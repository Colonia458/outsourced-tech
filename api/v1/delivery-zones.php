<?php
require_once __DIR__ . '/../../src/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT id, name, max_distance_km, fee, min_order_for_free, active, sort_order
            FROM delivery_zones
            WHERE active = 1
            ORDER BY sort_order ASC, fee ASC
        ");
        $zones = $stmt->fetchAll();
        sendResponse($zones);
    } catch (PDOException $e) {
        sendResponse(['success' => false, 'message' => 'Error fetching delivery zones'], 500);
    }
}

// Create delivery zone (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name']) || !isset($data['fee'])) {
        sendResponse(['success' => false, 'message' => 'Name and fee are required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO delivery_zones (name, max_distance_km, fee, min_order_for_free, active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['max_distance_km'] ?? null,
            $data['fee'],
            $data['min_order_for_free'] ?? null,
            $data['active'] ?? 1,
            $data['sort_order'] ?? 0
        ]);
        sendResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        sendResponse(['success' => false, 'message' => 'Error creating delivery zone'], 500);
    }
}
