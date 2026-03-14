<?php
// api/v1/loyalty.php

require_once '../../src/config.php';
require_once '../../src/loyalty.php';
require_once '../../src/auth.php';

header('Content-Type: application/json');

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tier = get_user_tier(current_user_id());

    echo json_encode([
        'success' => true,
        'points'  => fetchOne("SELECT loyalty_points FROM users WHERE id = ?", [current_user_id()])['loyalty_points'] ?? 0,
        'tier'    => $tier ?: ['name' => 'None', 'min_points' => 0]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);