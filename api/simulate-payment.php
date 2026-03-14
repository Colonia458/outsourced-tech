<?php
// api/simulate-payment.php - Process payment (simulated for testing)

header('Content-Type: application/json');

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Simulate payment for an order
$input = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($input['order_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0);

// Validate
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'order_id is required']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'amount must be greater than 0']);
    exit;
}

// Verify order belongs to logged-in user (or user is admin)
$order = fetchOne(
    "SELECT o.*, u.full_name, u.phone as user_phone 
     FROM orders o 
     JOIN users u ON o.user_id = u.id 
     WHERE o.id = ?",
    [$order_id]
);

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Check user owns this order (or is admin)
if (!isset($_SESSION['admin_user']) && $order['user_id'] != ($_SESSION['user_id'] ?? 0)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Simulate successful payment
$transaction_id = 'SIM_' . strtoupper(uniqid());
$receipt_number = 'SIM' . date('YmdHis');

// Update order payment status
$updateResult = query(
    "UPDATE orders SET
        payment_status = 'paid',
        status = 'processing'
     WHERE id = ?",
    [$order_id]
);

if (!$updateResult) {
    error_log("Payment simulation failed - update query returned false for order $order_id");
}

// Record payment
try {
    db_insert('payments', [
        'order_id'          => $order_id,
        'amount'            => $amount,
        'method'            => 'payment',
        'transaction_id'   => $transaction_id,
        'receipt_number'    => $receipt_number,
        'status'            => 'completed',
    ]);
} catch (Exception $e) {
    // payments table might not exist, continue anyway
}

// Log the payment
error_log("PAYMENT: Order #{$order['order_number']}, Amount: KSh $amount, Transaction: $transaction_id");

echo json_encode([
    'success' => true,
    'message' => 'Payment successful!',
    'transaction_id' => $transaction_id,
    'receipt_number' => $receipt_number,
    'order' => [
        'id' => $order_id,
        'order_number' => $order['order_number'],
        'status' => 'processing',
        'payment_status' => 'paid'
    ],
    'next_steps' => [
        'user' => 'Your payment was successful. You can now track your delivery.',
        'admin' => 'View the order in admin panel to manage delivery.'
    ]
]);
