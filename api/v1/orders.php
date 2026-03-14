<?php
// api/v1/orders.php

require_once '../../src/config.php';
require_once '../../src/cart.php';
require_once '../../src/delivery.php';
require_once '../../src/auth.php';

header('Content-Type: application/json');

// Allow both POST and GET for order retrieval
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
    require_login();
    $user_id = $_SESSION['user_id'];
    $orders = fetchAll("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC", [$user_id]);
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'view') {
    require_login();
    $order_id = (int)($_GET['order_id'] ?? 0);
    $order = fetchOne("SELECT * FROM orders WHERE id = ? AND user_id = ?", [$order_id, $_SESSION['user_id']]);
    if ($order) {
        $items = fetchAll("SELECT oi.*, p.name, p.image 
                          FROM order_items oi 
                          LEFT JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?", [$order_id]);
        echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
    exit;
}

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? 'create_order';

if ($action === 'create_order') {
    $delivery_type = $data['delivery_type'] ?? 'pickup';
    // Normalize delivery_type value
    if ($delivery_type === 'delivery') {
        $delivery_type = 'home_delivery';
    }
    $address       = trim($data['address'] ?? '');
    $phone         = trim($data['phone'] ?? '');
    $delivery_lat  = isset($data['delivery_lat']) && $data['delivery_lat'] !== '' ? (float)$data['delivery_lat'] : null;
    $delivery_lng  = isset($data['delivery_lng']) && $data['delivery_lng'] !== '' ? (float)$data['delivery_lng'] : null;

    if (cart_count() === 0) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    if ($delivery_type === 'delivery' && empty($address)) {
        echo json_encode(['success' => false, 'message' => 'Delivery address required']);
        exit;
    }

    if (empty($phone) || !preg_match('/^0[17]\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Valid M-Pesa phone required']);
        exit;
    }

    // Always recalculate totals server-side — never trust client-supplied amounts
    $subtotal = cart_total();
    $delivery_fee_array = calculate_delivery_fee($address, $delivery_type);
    $delivery_fee = is_array($delivery_fee_array) ? ($delivery_fee_array['fee'] ?? 0) : floatval($delivery_fee_array);
    $total = round($subtotal + $delivery_fee, 2);
    $order_number = 'OUT-' . date('Ymd') . '-' . str_pad(time(), 6, '0', STR_PAD_LEFT);

    // Create order
    $order_data = [
        'user_id'          => $_SESSION['user_id'],
        'order_number'     => $order_number,
        'status'           => 'pending',
        'payment_status'   => 'pending',
        'payment_method'   => 'mpesa',
        'subtotal'         => $subtotal,
        'delivery_fee'     => $delivery_fee,
        'total_amount'     => $total,
        'delivery_type'    => $delivery_type,
        'delivery_address' => $address,
        'phone'            => $phone,
    ];
    if ($delivery_lat !== null) $order_data['delivery_lat'] = $delivery_lat;
    if ($delivery_lng !== null) $order_data['delivery_lng'] = $delivery_lng;

    $order_id = db_insert('orders', $order_data);

    // Fallback: if order_id is 0/false, get it from database
    if (!$order_id) {
        $existing = fetchOne("SELECT id FROM orders WHERE order_number = ?", [$order_number]);
        $order_id = $existing['id'] ?? false;
    }

    // Add cart items to order_items
    $cart = get_cart();
    foreach ($cart as $item) {
        db_insert('order_items', [
            'order_id' => $order_id,
            'product_id' => $item['product_id'],
            'product_name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity']
        ]);
    }

    cart_clear();

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'total' => $total,
        'message' => 'Order created successfully'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);