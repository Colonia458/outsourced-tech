<?php
// controllers/OrderController.php - MVC Controller for Orders

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/security.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/cart.php';

class OrderController {
    
    /**
     * Create new order
     */
    public function store() {
        // Verify user is logged in
        require_login();
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Verify CSRF
        $token = $input['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        
        $delivery_type = sanitize($input['delivery_type'] ?? 'pickup');
        $address = sanitize($input['address'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        $subtotal = (float)($input['subtotal'] ?? 0);
        $delivery_fee = (float)($input['delivery_fee'] ?? 0);
        $total = (float)($input['total'] ?? 0);
        $coupon_code = sanitize($input['coupon_code'] ?? '');
        
        // Validate input
        $errors = [];
        
        if (!in_array($delivery_type, ['pickup', 'delivery'])) {
            $errors[] = "Invalid delivery type";
        }
        
        if ($delivery_type === 'delivery' && empty($address)) {
            $errors[] = "Delivery address is required";
        }
        
        if (!validate_phone($phone)) {
            $errors[] = "Invalid phone number format";
        }
        
        if ($subtotal <= 0) {
            $errors[] = "Invalid order amount";
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
        
        // Check rate limiting
        if (!check_rate_limit('order_' . $_SESSION['user_id'], 5, 300)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
            exit;
        }
        
        // Apply coupon if provided
        $discount = 0;
        if ($coupon_code) {
            require_once __DIR__ . '/../src/coupons.php';
            $coupon = validate_coupon($coupon_code, $subtotal);
            if ($coupon['valid']) {
                $discount = $coupon['discount'];
            }
        }
        
        // Generate order number
        $order_number = 'ORD-' . strtoupper(uniqid());
        
        // Get cart items
        $cart_items = get_cart();
        
        if (empty($cart_items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cart is empty']);
            exit;
        }
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert order
            $order_id = db_insert('orders', [
                'user_id' => $_SESSION['user_id'],
                'order_number' => $order_number,
                'subtotal' => $subtotal,
                'delivery_fee' => $delivery_fee,
                'discount' => $discount,
                'total_amount' => $total,
                'delivery_type' => $delivery_type,
                'delivery_address' => $address,
                'phone' => $phone,
                'payment_status' => 'pending',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Insert order items
            foreach ($cart_items as $item) {
                db_insert('order_items', [
                    'order_id' => $order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);
                
                // Update product stock
                query(
                    "UPDATE products SET stock = stock - ? WHERE id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }
            
            // Update coupon usage
            if ($coupon_code && isset($coupon)) {
                query(
                    "UPDATE coupons SET used_count = used_count + 1 WHERE code = ?",
                    [$coupon_code]
                );
            }
            
            // Clear cart
            cart_clear();
            
            // Commit transaction
            $pdo->commit();
            
            // Log security event
            log_security_event('order_created', [
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total' => $total
            ]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total' => $total
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            
            log_security_event('order_error', [
                'error' => $e->getMessage()
            ]);
            
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create order']);
        }
    }
    
    /**
     * Get user's orders
     */
    public function index() {
        require_login();
        
        $orders = fetchAll(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
            [$_SESSION['user_id']]
        );
        
        // Get order items for each order
        foreach ($orders as &$order) {
            $order['items'] = fetchAll(
                "SELECT oi.*, p.name, p.image
                 FROM order_items oi
                 JOIN products p ON oi.product_id = p.id
                 WHERE oi.order_id = ?",
                [$order['id']]
            );
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
    }
    
    /**
     * Get single order
     */
    public function show($id) {
        require_login();
        
        $order = fetchOne(
            "SELECT * FROM orders WHERE id = ? AND user_id = ?",
            [$id, $_SESSION['user_id']]
        );
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            exit;
        }
        
        $order['items'] = fetchAll(
            "SELECT oi.*, p.name, p.image 
             FROM order_items oi 
             JOIN products p ON oi.product_id = p.id 
             WHERE oi.order_id = ?",
            [$id]
        );
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
    }
    
    /**
     * Update order status (admin)
     */
    public function updateStatus($id) {
        if (!isset($_SESSION['admin_user'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $token = $input['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        
        $status = sanitize($input['status'] ?? '');
        $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
        
        if (!in_array($status, $allowed_statuses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status']);
            exit;
        }
        
        query("UPDATE orders SET status = ? WHERE id = ?", [$status, $id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
}
