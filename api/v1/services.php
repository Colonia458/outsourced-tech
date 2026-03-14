<?php
// api/v1/services.php

require_once '../../src/config.php';
require_once '../../src/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $services = fetchAll("SELECT id, name, slug, price, description FROM services WHERE visible = 1 ORDER BY name");
    echo json_encode(['success' => true, 'data' => $services]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    $data = json_decode(file_get_contents('php://input'), true);

    $service_id   = (int)($data['service_id'] ?? 0);
    $booking_date = $data['date'] ?? null;

    if (!$service_id || !$booking_date) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $inserted = db_insert(
        "INSERT INTO service_bookings (user_id, service_id, booking_date, status)
         VALUES (?, ?, ?, 'pending')",
        [current_user_id(), $service_id, $booking_date]
    );

    echo json_encode([
        'success' => $inserted > 0,
        'booking_id' => $inserted
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);