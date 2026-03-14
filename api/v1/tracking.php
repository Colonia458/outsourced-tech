<?php
// api/v1/tracking.php - Delivery location tracking API

header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/database.php';
require_once __DIR__ . '/../../src/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ─────────────────────────────────────────────────────────────
//  GET /api/v1/tracking.php?order_id=X
//  Returns order delivery pin + current driver location
// ─────────────────────────────────────────────────────────────
if ($method === 'GET') {
    require_login();

    $order_id = (int)($_GET['order_id'] ?? 0);
    if ($order_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'order_id required']);
        exit;
    }

    // Users can only see their own orders; admins can see all
    $is_admin = isset($_SESSION['admin_user']);
    if ($is_admin) {
        $order = fetchOne(
            "SELECT id, order_number, status, delivery_lat, delivery_lng,
                    driver_lat, driver_lng, driver_updated_at, delivery_address
             FROM orders WHERE id = ?",
            [$order_id]
        );
    } else {
        $order = fetchOne(
            "SELECT id, order_number, status, delivery_lat, delivery_lng,
                    driver_lat, driver_lng, driver_updated_at, delivery_address
             FROM orders WHERE id = ? AND user_id = ?",
            [$order_id, $_SESSION['user_id']]
        );
    }

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Get last 20 driver location history points
    $history = fetchAll(
        "SELECT lat, lng, recorded_at FROM delivery_tracking
         WHERE order_id = ? ORDER BY recorded_at DESC LIMIT 20",
        [$order_id]
    );

    echo json_encode([
        'success'  => true,
        'order'    => $order,
        'history'  => array_reverse($history),
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────
//  POST /api/v1/tracking.php
//  action = 'save_pin'   — customer saves delivery pin
//  action = 'update_driver' — driver/admin updates driver location
// ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    // ── Customer saves delivery pin ──────────────────────────
    if ($action === 'save_pin') {
        require_login();

        $order_id = (int)($data['order_id'] ?? 0);
        $lat      = (float)($data['lat'] ?? 0);
        $lng      = (float)($data['lng'] ?? 0);

        if ($order_id <= 0 || $lat === 0.0 || $lng === 0.0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'order_id, lat and lng required']);
            exit;
        }

        // Validate lat/lng ranges
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
            exit;
        }

        // Verify order belongs to user
        $order = fetchOne(
            "SELECT id FROM orders WHERE id = ? AND user_id = ?",
            [$order_id, $_SESSION['user_id']]
        );
        if (!$order) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        query(
            "UPDATE orders SET delivery_lat = ?, delivery_lng = ? WHERE id = ?",
            [$lat, $lng, $order_id]
        );

        echo json_encode(['success' => true, 'message' => 'Delivery pin saved']);
        exit;
    }

    // ── Driver / Admin updates driver location ───────────────
    if ($action === 'update_driver') {
        // Must be admin or have a driver token
        if (!isset($_SESSION['admin_user'])) {
            // Check for driver token in Authorization header
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $driver_token = getenv('DRIVER_API_TOKEN') ?: '';
            if (empty($driver_token) || $auth_header !== 'Bearer ' . $driver_token) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
        }

        $order_id = (int)($data['order_id'] ?? 0);
        $lat      = (float)($data['lat'] ?? 0);
        $lng      = (float)($data['lng'] ?? 0);

        if ($order_id <= 0 || $lat === 0.0 || $lng === 0.0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'order_id, lat and lng required']);
            exit;
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
            exit;
        }

        // Update driver location on order
        query(
            "UPDATE orders SET driver_lat = ?, driver_lng = ?, driver_updated_at = NOW() WHERE id = ?",
            [$lat, $lng, $order_id]
        );

        // Log to history
        db_insert('delivery_tracking', [
            'order_id'    => $order_id,
            'lat'         => $lat,
            'lng'         => $lng,
            'source'      => isset($_SESSION['admin_user']) ? 'admin' : 'driver',
        ]);

        echo json_encode(['success' => true, 'message' => 'Driver location updated']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
