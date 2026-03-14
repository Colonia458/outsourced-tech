<?php
// public/checkout.php - Simplified checkout

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/cart.php';
require_once __DIR__ . '/../src/delivery.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

if (cart_count() === 0) {
    header("Location: cart.php");
    exit;
}

$subtotal = cart_total();
$delivery_fee = 0;
$total = $subtotal + $delivery_fee;
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_type = $_POST['delivery_type'] ?? 'pickup';
    if ($delivery_type === 'delivery') {
        $delivery_type = 'home_delivery';
    }
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate
    if (empty($phone) || !preg_match('/^0[17]\d{8}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number (07XX or 01XX followed by 8 digits)';
    }
    if ($delivery_type === 'home_delivery' && empty($address)) {
        $errors[] = 'Please provide delivery address';
    }
    
    if (empty($errors)) {
        // Calculate delivery fee
        $delivery_fee = 0;
        if ($delivery_type === 'home_delivery') {
            try {
                $fee_result = calculate_delivery_fee($address, $delivery_type);
                $delivery_fee = is_array($fee_result) ? ($fee_result['fee'] ?? 0) : 0;
            } catch (Exception $e) {
                $delivery_fee = 0;
            }
        }
        $total = $subtotal + $delivery_fee;
        
        // Create order
        $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(time() % 1000000, 6, '0', STR_PAD_LEFT);
        
        try {
            $order_id = db_insert('orders', [
                'user_id' => $_SESSION['user_id'],
                'order_number' => $order_number,
                'status' => 'pending',
                'payment_status' => 'pending', // Payment pending until M-Pesa confirms
                'payment_method' => 'payment',
                'subtotal' => $subtotal,
                'delivery_fee' => $delivery_fee,
                'total_amount' => $total,
                'delivery_type' => $delivery_type,
                'delivery_address' => $address,
                'phone' => $phone,
                'customer_note' => $notes,
            ]);
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            $order_id = false;
        }
        
        if ($order_id && $order_id > 0) {
            // Add order items
            $cart = get_cart();
            foreach ($cart as $item) {
                db_insert('order_items', [
                    'order_id' => $order_id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity']
                ]);
            }
            
            // Record payment
            $trans_id = 'PAY_' . strtoupper(uniqid());
            try {
                db_insert('payments', [
                    'order_id' => $order_id,
                    'amount' => $total,
                    'method' => 'payment',
                    'transaction_id' => $trans_id,
                    'receipt_number' => 'RCP' . date('YmdHis'),
                    'status' => 'pending' // Payment pending until confirmed
                ]);
            } catch (Exception $e) {
                // Continue even if payment record fails
            }
            
            // Clear cart and redirect
            cart_clear();
            header("Location: order-confirmation.php?order_id=" . $order_id);
            exit;
        } else {
            $errors[] = 'Failed to create order. Please try again.';
        }
    }
}

$page_title = 'Checkout';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <h1 class="mb-4 fw-bold text-center">Checkout</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p class="mb-1"><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="checkout.php">
        <div class="row g-4">
            <!-- Delivery Type -->
            <div class="col-md-6">
                <label class="form-label fw-bold">Delivery Type</label>
                <select name="delivery_type" id="delivery_type" class="form-select" required onchange="toggleDelivery()">
                    <option value="pickup">Pickup at Shop (Free)</option>
                    <option value="delivery">Home Delivery</option>
                </select>
            </div>
            
            <!-- Phone -->
            <div class="col-md-6">
                <label class="form-label fw-bold">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" required 
                       placeholder="07XX XXX XXX" pattern="0[17]\d{8}" 
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            
            <!-- Delivery Address -->
            <div class="col-12" id="address_group" style="display:none;">
                <label class="form-label fw-bold">Delivery Address *</label>
                <textarea name="address" class="form-control" rows="3" 
                          placeholder="Enter your full delivery address"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            
            <!-- Notes -->
            <div class="col-12">
                <label class="form-label">Order Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="2" 
                          placeholder="Any special instructions..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Order Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span>KSh <?= number_format($subtotal) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Delivery:</span>
                    <span>KSh <?= number_format($delivery_fee) ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total:</span>
                    <span>KSh <?= number_format($total) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Submit -->
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-success btn-lg px-5 rounded-pill">
                <i class="fas fa-check-circle me-2"></i> Complete Order
            </button>
            <a href="cart.php" class="btn btn-outline-secondary btn-lg px-5 rounded-pill ms-2">Back to Cart</a>
        </div>
    </form>
</div>

<script>
function toggleDelivery() {
    const type = document.getElementById('delivery_type').value;
    const addressGroup = document.getElementById('address_group');
    
    if (type === 'delivery') {
        addressGroup.style.display = 'block';
    } else {
        addressGroup.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
