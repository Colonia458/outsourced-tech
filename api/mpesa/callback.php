<?php
// api/mpesa/callback.php – webhook (M-Pesa sends POST here)

require_once '../../src/config.php';
require_once '../../src/database.php';

header('Content-Type: application/json');

// Read raw POST data from Safaricom
$callback = json_decode(file_get_contents('php://input'), true);

if (!$callback) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid payload']));
}

// Log raw callback (for debugging)
file_put_contents(__DIR__ . '/mpesa_callback.log', date('c') . " " . json_encode($callback) . "\n", FILE_APPEND);

// Check if transaction was successful
if (isset($callback['Body']['stkCallback']['ResultCode']) && $callback['Body']['stkCallback']['ResultCode'] === 0) {
    $metadata = $callback['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];

    $amount          = $metadata[0]['Value'] ?? 0;
    $receipt         = $metadata[1]['Value'] ?? null;
    $phone           = $metadata[4]['Value'] ?? null;
    $account_ref     = $metadata[3]['Value'] ?? null; // e.g. OUTSOURCED-TECH

    // Extract order_id from AccountReference (adjust pattern as needed)
    $order_id = str_replace('OUTSOURCED-TECH-', '', $account_ref);

    if ($order_id && is_numeric($order_id)) {
        // Update order status
        query(
            "UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?",
            [(int)$order_id]
        );

        // Record payment using correct db_insert() signature
        db_insert('payments', [
            'order_id'       => (int)$order_id,
            'amount'         => $amount,
            'method'         => 'mpesa',
            'transaction_id' => $callback['Body']['stkCallback']['CheckoutRequestID'] ?? '',
            'receipt_number' => $receipt,
            'status'         => 'completed',
        ]);
    }
}

http_response_code(200);
echo json_encode(['success' => true]);