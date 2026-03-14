<?php
// src/delivery.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Calculate delivery fee based on zone
 * Uses delivery_zones table for pricing
 * 
 * @param string $address Customer delivery address
 * @param string $delivery_type 'pickup', 'home_delivery'
 * @param float $order_total Total order amount for free delivery checks
 * @return array ['fee' => float, 'zone' => string|null, 'free_reason' => string|null]
 */
function calculate_delivery_fee(string $address, string $delivery_type = 'home_delivery', float $order_total = 0): array {
    // Pickup is always free
    if ($delivery_type === 'pickup') {
        return ['fee' => 0.0, 'zone' => 'Pickup', 'free_reason' => 'Pickup at store'];
    }

    $address = strtolower(trim($address));
    
    // Get active delivery zones ordered by fee (lowest first)
    $zones = fetchAll("SELECT id, name, fee, min_order_for_free FROM delivery_zones WHERE active = 1 ORDER BY fee ASC");
    
    foreach ($zones as $zone) {
        $zoneName = strtolower($zone['name']);
        // Check if address matches zone keywords
        if (str_contains($address, $zoneName)) {
            $fee = (float) $zone['fee'];
            $minOrder = $zone['min_order_for_free'] ?? null;
            
            // Check if free delivery threshold is met
            if ($minOrder && $order_total >= $minOrder) {
                return ['fee' => 0.0, 'zone' => $zone['name'], 'free_reason' => "Free delivery on orders over KSh " . number_format($minOrder)];
            }
            
            return ['fee' => $fee, 'zone' => $zone['name'], 'free_reason' => null];
        }
    }
    
    // Special case for Mlolongo area (default free local delivery)
    if (str_contains($address, 'mlolongo') || str_contains($address, 'syokimau')) {
        if ($order_total >= 5000) {
            return ['fee' => 0.0, 'zone' => 'Mlolongo Local', 'free_reason' => 'Free delivery on orders over KSh 5,000'];
        }
        return ['fee' => 0.0, 'zone' => 'Mlolongo Local', 'free_reason' => null];
    }
    
    // Default: farthest/most expensive zone
    $default_fee = !empty($zones) ? max(array_column($zones, 'fee')) : 300.0;
    return ['fee' => $default_fee, 'zone' => 'Standard', 'free_reason' => null];
}

/**
 * Get all available delivery zones
 */
function get_delivery_zones(): array {
    return fetchAll("SELECT id, name, fee, min_order_for_free FROM delivery_zones WHERE active = 1 ORDER BY sort_order ASC, fee ASC");
}

/**
 * Check if user qualifies for free delivery based on tier
 */
function check_tier_free_delivery(int $user_id, float $order_total): array {
    $tier = get_user_tier($user_id);
    
    if ($tier && $tier['free_delivery']) {
        return ['eligible' => true, 'reason' => "Free delivery as {$tier['name']} member"];
    }
    
    return ['eligible' => false, 'reason' => null];
}