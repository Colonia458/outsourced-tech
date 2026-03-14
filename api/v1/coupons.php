<?php
// api/v1/coupons.php - Coupon validation API
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../src/database.php';

$code = strtoupper($_GET['code'] ?? '');
$subtotal = (float)($_GET['subtotal'] ?? 0);

if (empty($code)) {
    echo json_encode(['valid' => false, 'message' => 'Please provide a coupon code']);
    exit;
}

$coupon = fetchOne(
    "SELECT * FROM coupons WHERE code = ? AND is_active = 1",
    [$code]
);

if (!$coupon) {
    echo json_encode(['valid' => false, 'message' => 'Invalid coupon code']);
    exit;
}

// Check expiration
if ($coupon['valid_until'] && strtotime($coupon['valid_until']) < time()) {
    echo json_encode(['valid' => false, 'message' => 'This coupon has expired']);
    exit;
}

// Check usage limit
if ($coupon['max_uses'] && $coupon['used_count'] >= $coupon['max_uses']) {
    echo json_encode(['valid' => false, 'message' => 'This coupon has reached its usage limit']);
    exit;
}

// Check minimum order
if ($coupon['min_order_amount'] && $subtotal < $coupon['min_order_amount']) {
    echo json_encode(['valid' => false, 'message' => 'Minimum order of KSh ' . number_format($coupon['min_order_amount']) . ' required']);
    exit;
}

// Calculate discount
$discount = 0;
if ($coupon['discount_type'] === 'percentage') {
    $discount = ($subtotal * $coupon['discount_value']) / 100;
} else {
    $discount = $coupon['discount_value'];
    // Don't exceed subtotal
    if ($discount > $subtotal) {
        $discount = $subtotal;
    }
}

echo json_encode([
    'valid' => true,
    'description' => $coupon['description'] ?: "{$coupon['discount_value']} " . ($coupon['discount_type'] === 'percentage' ? '%' : 'KES') . ' off',
    'discount_type' => $coupon['discount_type'],
    'discount_value' => $coupon['discount_value'],
    'discount' => $discount
]);
