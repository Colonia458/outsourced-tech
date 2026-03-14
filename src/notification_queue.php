<?php
// src/notification_queue.php - Background Queue & Notification System

/**
 * Add a task to the notification queue
 * @param string $type Notification type (email, sms, push)
 * @param string $event Event type (order_confirmed, booking_reminder, etc.)
 * @param array $data Data for the notification
 * @return int|false Queue ID or false on failure
 */
function add_to_queue($type, $event, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notification_queue (type, event, data, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$type, $event, json_encode($data)]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Queue add error: " . $e->getMessage());
        return false;
    }
}

/**
 * Process the notification queue
 * @param int $limit Maximum notifications to process
 * @return array Processing results
 */
function process_queue($limit = 50) {
    global $pdo;
    
    $results = ['processed' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
    
    try {
        // Get pending notifications
        $stmt = $pdo->prepare("
            SELECT * FROM notification_queue 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($notifications as $notification) {
            $results['processed']++;
            
            try {
                $data = json_decode($notification['data'], true);
                $success = false;
                
                switch ($notification['type']) {
                    case 'email':
                        $success = process_email_notification($notification['event'], $data);
                        break;
                    case 'sms':
                        $success = process_sms_notification($notification['event'], $data);
                        break;
                    case 'push':
                        $success = process_push_notification($notification['event'], $data);
                        break;
                }
                
                if ($success) {
                    $results['success']++;
                    $status = 'sent';
                } else {
                    $results['failed']++;
                    $status = 'failed';
                }
                
                // Update notification status
                $stmt = $pdo->prepare("
                    UPDATE notification_queue 
                    SET status = ?, processed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status, $notification['id']]);
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
                
                $stmt = $pdo->prepare("
                    UPDATE notification_queue 
                    SET status = 'failed', error_message = ?, processed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$e->getMessage(), $notification['id']]);
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Queue processing error: " . $e->getMessage();
    }
    
    return $results;
}

/**
 * Process email notification
 */
function process_email_notification($event, $data) {
    require_once __DIR__ . '/email.php';
    
    switch ($event) {
        case 'order_confirmed':
            // Send order confirmation - already handled in checkout
            return true;
            
        case 'booking_reminder':
            return send_booking_reminder_email($data['booking_id']);
            
        case 'abandoned_cart':
            return send_abandoned_cart_email($data['user_id']);
            
        case 'low_stock_alert':
            return send_low_stock_email($data['product_id']);
            
        case 'payment_received':
            return send_payment_confirmation_email($data['order_id']);
            
        default:
            error_log("Unknown email event: $event");
            return false;
    }
}

/**
 * Process SMS notification
 */
function process_sms_notification($event, $data) {
    require_once __DIR__ . '/sms.php';
    
    switch ($event) {
        case 'booking_reminder':
            return send_booking_reminder_sms($data['booking_id']);
            
        case 'order_update':
            return send_order_update_sms($data['order_id']);
            
        default:
            return false;
    }
}

/**
 * Process push notification
 */
function process_push_notification($event, $data) {
    // Implementation for web push notifications
    return true;
}

/**
 * Send booking reminder email
 */
function send_booking_reminder_email($booking_id) {
    require_once __DIR__ . '/booking.php';
    require_once __DIR__ . '/email.php';
    
    $booking = get_booking_details($booking_id);
    
    if (!$booking || empty($booking['email'])) {
        return false;
    }
    
    $hours_until = round((strtotime($booking['booking_date'] . ' ' . $booking['booking_time']) - time()) / 3600, 1);
    
    $subject = "Reminder: Your {$booking['service_name']} is in {$hours_until} hours";
    $html = "
    <html>
    <body>
        <h2>Booking Reminder</h2>
        <p>Dear " . htmlspecialchars($booking['full_name'] ?? $booking['username']) . ",</p>
        <p>This is a friendly reminder about your upcoming service booking:</p>
        <ul>
            <li><strong>Service:</strong> {$booking['service_name']}</li>
            <li><strong>Date:</strong> " . date('d M Y', strtotime($booking['booking_date'])) . "</li>
            <li><strong>Time:</strong> " . date('h:i A', strtotime($booking['booking_time'])) . "</li>
        </ul>
        <p>See you soon!</p>
    </body>
    </html>";
    
    return send_email($booking['email'], $subject, $html);
}

/**
 * Send booking reminder SMS
 */
function send_booking_reminder_sms($booking_id) {
    require_once __DIR__ . '/booking.php';
    require_once __DIR__ . '/sms.php';
    
    $booking = get_booking_details($booking_id);
    
    if (!$booking || empty($booking['phone'])) {
        return false;
    }
    
    $message = "Reminder: Your {$booking['service_name']} is " . 
               date('d M at h:i A', strtotime($booking['booking_date'] . ' ' . $booking['booking_time'])) . 
               ". See you soon! - Outsourced Technologies";
    
    return send_sms($booking['phone'], $message, 'booking_reminder');
}

/**
 * Send abandoned cart email
 */
function send_abandoned_cart_email($user_id) {
    global $pdo;
    
    require_once __DIR__ . '/email.php';
    
    // Get user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    // Get cart items
    $stmt = $pdo->prepare("
        SELECT c.*, p.name, p.price, p.image
        FROM cart c
        LEFT JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cart_items)) {
        return false;
    }
    
    $total = array_sum(array_map(function($item) { 
        return $item['price'] * $item['quantity']; 
    }, $cart_items));
    
    $subject = "You left something behind! Complete your order";
    $html = "
    <html>
    <body>
        <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
            <div style='background: #0d6efd; color: white; padding: 20px; text-align: center;'>
                <h2>You left items in your cart!</h2>
            </div>
            <div style='padding: 20px; background: #f9f9f9;'>
                <p>Dear " . htmlspecialchars($user['full_name'] ?? $user['username']) . ",</p>
                <p>You left these items in your cart:</p>
                
                <div style='background: white; padding: 15px; border-radius: 5px;'>
                    <table style='width: 100%;'>";
    
    foreach ($cart_items as $item) {
        $html .= "<tr>
            <td style='padding: 10px;'>" . htmlspecialchars($item['name']) . "</td>
            <td style='padding: 10px; text-align: center;'>x{$item['quantity']}</td>
            <td style='padding: 10px; text-align: right;'>KSh " . number_format($item['price'] * $item['quantity']) . "</td>
        </tr>";
    }
    
    $html .= "</table>
                    <hr>
                    <p style='text-align: right;'><strong>Total: KSh " . number_format($total) . "</strong></p>
                </div>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <a href='https://outsourcedtechnologies.co.ke/cart.php' style='display: inline-block; padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Complete Your Order</a>
                </div>
                
                <p style='margin-top: 20px; color: #666;'>Need help? Contact us at info@outsourcedtechnologies.co.ke</p>
            </div>
        </div>
    </body>
    </html>";
    
    return send_email($user['email'], $subject, $html);
}

/**
 * Send low stock alert email to admin
 */
function send_low_stock_email($product_id) {
    global $pdo;
    
    require_once __DIR__ . '/email.php';
    
    // Get product details
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        return false;
    }
    
    $admin_email = getenv('ADMIN_EMAIL') ?: 'admin@outsourcedtechnologies.co.ke';
    
    $subject = "⚠️ Low Stock Alert: " . $product['name'];
    $html = "
    <html>
    <body>
        <h2 style='color: #dc3545;'>Low Stock Alert</h2>
        <p>The following product is running low on stock:</p>
        
        <table style='width: 100%; border-collapse: collapse;'>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Product</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($product['name']) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>SKU</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($product['sku']) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Category</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($product['category_name']) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Current Stock</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd; color: " . ($product['stock'] <= 5 ? '#dc3545' : '#ffc107') . "; font-weight: bold;'>" . $product['stock'] . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Reorder Level</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . $product['reorder_level'] . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Price</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>KSh " . number_format($product['price']) . "</td>
            </tr>
        </table>
        
        <p style='margin-top: 20px;'>
            <a href='https://outsourcedtechnologies.co.ke/admin/products/edit.php?id={$product['id']}' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>Update Stock</a>
        </p>
    </body>
    </html>";
    
    return send_email($admin_email, $subject, $html);
}

/**
 * Send payment confirmation email
 */
function send_payment_confirmation_email($order_id) {
    global $pdo;
    
    require_once __DIR__ . '/email.php';
    
    $stmt = $pdo->prepare("
        SELECT o.*, u.email, u.full_name, u.username
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order || empty($order['email'])) {
        return false;
    }
    
    $subject = "Payment Received - Order " . $order['order_number'];
    $html = "
    <html>
    <body>
        <h2 style='color: #28a745;'>Payment Received!</h2>
        <p>Dear " . htmlspecialchars($order['full_name'] ?? $order['username']) . ",</p>
        <p>We've received your payment of <strong>KSh " . number_format($order['total_amount']) . "</strong></p>
        <p>Your order <strong>{$order['order_number']}</strong> is now being processed.</p>
        <p>Thank you for shopping with us!</p>
    </body>
    </html>";
    
    return send_email($order['email'], $subject, $html);
}

/**
 * Send order update SMS
 */
function send_order_update_sms($order_id) {
    global $pdo;
    
    require_once __DIR__ . '/sms.php';
    
    $stmt = $pdo->prepare("
        SELECT o.*, u.phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order || empty($order['phone'])) {
        return false;
    }
    
    $status_messages = [
        'processing' => 'Your order is being prepared',
        'ready_for_delivery' => 'Your order is ready for delivery',
        'shipped' => 'Your order has been shipped',
        'delivered' => 'Your order has been delivered'
    ];
    
    $message = $status_messages[$order['status']] ?? 'Your order status has been updated';
    $message .= " - Order {$order['order_number']}. Track: https://outsourcedtechnologies.co.ke/orders.php";
    
    return send_sms($order['phone'], $message, 'order_update');
}

/**
 * Schedule booking reminders (called by cron)
 */
function schedule_booking_reminders() {
    global $pdo;
    
    // Get bookings in next 24 hours that haven't been reminded
    $stmt = $pdo->query("
        SELECT sb.*, s.name as service_name
        FROM service_bookings sb
        LEFT JOIN services s ON sb.service_id = s.id
        WHERE sb.status IN ('pending', 'confirmed')
        AND sb.booking_date >= CURDATE()
        AND sb.booking_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
        AND (sb.reminder_sent IS NULL OR sb.reminder_sent = 0)
    ");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($bookings as $booking) {
        // Calculate hours until booking
        $booking_datetime = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']);
        $hours_until = ($booking_datetime - time()) / 3600;
        
        // Send reminder if within 24 hours or 1 hour
        if ($hours_until <= 24 || $hours_until <= 1) {
            // Add to queue
            add_to_queue('email', 'booking_reminder', ['booking_id' => $booking['id']]);
            add_to_queue('sms', 'booking_reminder', ['booking_id' => $booking['id']]);
            
            // Mark as reminded
            $stmt = $pdo->prepare("UPDATE service_bookings SET reminder_sent = 1 WHERE id = ?");
            $stmt->execute([$booking['id']]);
            
            $count++;
        }
    }
    
    return $count;
}

/**
 * Check for abandoned carts (called by cron)
 */
function check_abandoned_carts() {
    global $pdo;
    
    // Find users with cart items who haven't ordered in 24 hours
    $stmt = $pdo->query("
        SELECT DISTINCT c.user_id, u.email, u.full_name, u.username
        FROM cart c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND u.id IS NOT NULL
        AND u.email IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM orders o 
            WHERE o.user_id = c.user_id 
            AND o.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        )
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($users as $user) {
        // Check if already sent today
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notification_queue 
            WHERE event = 'abandoned_cart' 
            AND user_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$user['user_id']]);
        
        if ($stmt->fetchColumn() == 0) {
            add_to_queue('email', 'abandoned_cart', ['user_id' => $user['user_id']]);
            $count++;
        }
    }
    
    return $count;
}
