<?php
// public/cart.php

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/cart.php'; // must define get_cart(), cart_total(), cart_remove(), cart_update()

$page_title = 'Shopping Cart';

$cart_items = get_cart();
$subtotal = cart_total();
?>

<?php require_once __DIR__ . '/../templates/header.php'; ?>

<div class="container py-5">
    <h1 class="mb-4 fw-bold text-center">Your Shopping Cart</h1>

    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info text-center py-5 rounded-4 shadow-sm">
            <i class="fas fa-shopping-cart fa-4x text-primary mb-4 d-block"></i>
            <h4 class="mb-3">Your cart is empty</h4>
            <p class="text-muted mb-4">Looks like you haven't added anything yet.</p>
            <a href="products.php" class="btn btn-primary btn-lg px-5 py-3">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50%;">Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $key => $item): 
                                        $item_total = $item['price'] * $item['quantity'];
                                        $product_id = $item['product_id'] ?? 0;
                                        // Get product image
                                        $product_img = '';
                                        if (!empty($product_id)) {
                                            $product = fetchOne("SELECT (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image FROM products p WHERE p.id = ?", [$product_id]);
                                            $product_img = $product['image'] ?? '';
                                        }
                                    ?>
                                        <tr data-key="<?= $product_id ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($product_img)): ?>
                                                        <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($product_img) ?>" 
                                                             class="me-3" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                                    <?php else: ?>
                                                        <div class="bg-light me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 8px;">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <a href="product.php?id=<?= $product_id ?>" class="text-decoration-none text-dark fw-medium">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                        </a>
                                                        <div class="small text-muted">SKU: <?= htmlspecialchars($item['sku'] ?? 'N/A') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>KSh <?= number_format($item['price']) ?></td>
                                            <td>
                                                <div class="input-group input-group-sm" style="width: 120px;">
                                                    <button class="btn btn-outline-secondary qty-decrease" type="button">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number" class="form-control text-center qty-input" 
                                                           value="<?= $item['quantity'] ?>" min="1" 
                                                           data-key="<?= $product_id ?>">
                                                    <button class="btn btn-outline-secondary qty-increase" type="button">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="fw-bold text-success">KSh <span class="item-total"><?= number_format($item_total) ?></span></td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <button class="btn btn-sm btn-outline-danger btn-remove" data-key="<?= $product_id ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <a href="wishlist.php?add=<?= $product_id ?>" class="btn btn-sm btn-outline-secondary" title="Save for later">
                                                        <i class="fas fa-heart"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Subtotal:</h5>
                            <h4 class="mb-0 fw-bold text-success">KSh <span id="cart-subtotal"><?= number_format($subtotal) ?></span></h4>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="products.php" class="btn btn-outline-secondary btn-lg rounded-pill">
                        <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                    </a>
                    <a href="checkout.php" class="btn btn-success btn-lg px-5 rounded-pill">
                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                    </a>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card shadow-sm rounded-4">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Order Summary</h5>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (<?= count($cart_items) ?> items)</span>
                            <span>KSh <span id="summary-subtotal"><?= number_format($subtotal) ?></span></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Delivery Fee</span>
                            <span id="summary-delivery" class="text-muted">Calculated at checkout</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total</span>
                            <span>KSh <span id="summary-total"><?= number_format($subtotal) ?></span></span>
                        </div>
                        
                        <!-- Delivery Info -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-truck text-primary me-2"></i>
                                <small class="fw-medium">Delivery within Mlolongo</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-clock text-primary me-2"></i>
                                <small class="text-muted">Est. 1-2 business days</small>
                            </div>
                        </div>
                        
                        </div>
                        
                        <!-- Trust Badges -->
                        <div class="text-center mt-4">
                            <div class="d-flex justify-content-center gap-3 small text-muted">
                                <span><i class="fas fa-shield-alt me-1"></i>Secure</span>
                                <span><i class="fas fa-undo me-1"></i>Returns</span>
                                <span><i class="fas fa-headset me-1"></i>Support</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Cart interactivity
document.addEventListener('DOMContentLoaded', () => {
    const updateCart = (key, qty) => {
        fetch(BASE_URL + '../api/v1/cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update', item_id: key, quantity: qty })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    };

    // Quantity change
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', () => {
            const key = input.dataset.key;
            const qty = parseInt(input.value) || 1;
            if (qty < 1) input.value = 1;
            updateCart(key, qty);
        });
    });

    // Increase / Decrease buttons
    document.querySelectorAll('.qty-increase').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            input.value = parseInt(input.value) + 1;
            input.dispatchEvent(new Event('change'));
        });
    });

    document.querySelectorAll('.qty-decrease').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.nextElementSibling;
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    // Remove item
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            if (confirm('Remove this item?')) {
                const key = btn.dataset.key;
                fetch(BASE_URL + '../api/v1/cart.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'remove', item_id: key })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                });
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>