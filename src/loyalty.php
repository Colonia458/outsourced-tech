<?php
// src/loyalty.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Award points after order (example: 1 point per 100 KSh spent)
 */
function award_loyalty_points(int $user_id, float $order_total): void {
    if ($user_id <= 0 || $order_total <= 0) return;

    $points = (int) floor($order_total / 100);

    query(
        "UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?",
        [$points, $user_id]
    );
}

/**
 * Get current loyalty tier
 */
function get_user_tier(int $user_id): ?array {
    $points = fetchOne("SELECT loyalty_points FROM users WHERE id = ?", [$user_id])['loyalty_points'] ?? 0;

    return fetchOne(
        "SELECT * FROM loyalty_tiers 
         WHERE min_points <= ? 
         ORDER BY min_points DESC 
         LIMIT 1",
        [$points]
    );
}