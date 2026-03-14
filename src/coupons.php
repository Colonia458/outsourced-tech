<?php
// src/coupons.php - Coupon/Promo code system

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Validate a coupon code
 * @return array ['valid' => bool, 'discount' => float, 'message' => string]
 */
function validate_coupon($code, $order_total = 0) {
    $code = strtoupper(trim($code));
    
    // Find coupon
    $coupon = fetchOne(
        "SELECT * FROM coupons WHERE code = ? AND is_active = 1",
        [$code]
    );
    
    if (!$coupon) {
        return ['valid' => false, 'discount' => 0, 'message' => 'Invalid coupon code'];
    }
    
    // Check expiration
    if (!empty($coupon['valid_until']) && strtotime($coupon['valid_until']) < time()) {
        return ['valid' => false, 'discount' => 0, 'message' => 'This coupon has expired'];
    }
    
    // Check if coupon has started
    if (strtotime($coupon['valid_from']) > time()) {
        return ['valid' => false, 'discount' => 0, 'message' => 'This coupon is not yet valid'];
    }
    
    // Check max uses
    if ($coupon['max_uses'] !== null && $coupon['used_count'] >= $coupon['max_uses']) {
        return ['valid' => false, 'discount' => 0, 'message' => 'This coupon has reached its maximum uses'];
    }
    
    // Check minimum order amount
    if ($coupon['min_order_amount'] > 0 && $order_total < $coupon['min_order_amount']) {
        return [
            'valid' => false, 
            'discount' => 0, 
            'message' => 'Minimum order of KSh ' . number_format($coupon['min_order_amount']) . ' required'
        ];
    }
    
    // Calculate discount
    if ($coupon['discount_type'] === 'percentage') {
        $discount = ($order_total * $coupon['discount_value']) / 100;
    } else {
        $discount = $coupon['discount_value'];
    }
    
    return [
        'valid' => true,
        'discount' => $discount,
        'coupon_id' => $coupon['id'],
        'code' => $coupon['code'],
        'message' => 'Coupon applied!'
    ];
}

/**
 * Apply a coupon to an order
 */
function apply_coupon($coupon_id) {
    // Increment usage count
    query(
        "UPDATE coupons SET used_count = used_count + 1 WHERE id = ?",
        [$coupon_id]
    );
}

/**
 * Get all active coupons (for admin)
 */
function get_all_coupons() {
    return fetchAll("SELECT * FROM coupons ORDER BY created_at DESC");
}

/**
 * Create a new coupon
 */
function create_coupon($data) {
    $data['code'] = strtoupper($data['code']);
    return db_insert('coupons', $data);
}

/**
 * Delete a coupon
 */
function delete_coupon($id) {
    query("DELETE FROM coupons WHERE id = ?", [$id]);
}
