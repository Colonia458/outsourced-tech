<?php
// api/mpesa/stk-push.php

require_once '../../src/config.php';
require_once '../../src/mpesa.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$phone     = trim($data['phone'] ?? '');
$amount    = floatval($data['amount'] ?? 0);
$order_id  = $data['order_id'] ?? 'TEST';

if (empty($phone) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid phone or amount']);
    exit;
}

// Format phone to 2547xxxxxxxx
if (str_starts_with($phone, '0')) {
    $phone = '254' . substr($phone, 1);
}

$result = mpesa_stk_push($phone, $amount, 'OUTSOURCED-TECH', $order_id);

echo json_encode($result);