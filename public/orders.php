<?php
// public/orders.php - User order history and tracking

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

$user_id = $_SESSION['user_id'];

// Get all user orders with product images
$orders = fetchAll(
    "SELECT o.*, 
            (SELECT pi.filename FROM product_images pi 
             JOIN order_items oi ON oi.product_id = pi.product_id 
             WHERE oi.order_id = o.id AND pi.is_main = 1 LIMIT 1) as product_image
     FROM orders o 
     WHERE o.user_id = ? 
     ORDER BY o.created_at DESC", 
    [$user_id]
);

// Get order items with product images
function getOrderItems($order_id) {
    return fetchAll(
        "SELECT oi.*, p.image as product_image 
         FROM order_items oi 
         LEFT JOIN products p ON oi.product_id = p.id 
         WHERE oi.order_id = ?",
        [$order_id]
    );
}

$page_title = 'My Orders';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <h1 class="mb-4 fw-bold text-center">My Orders</h1>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <?php if (empty($orders)): ?>
        <div class="alert alert-info text-center py-5 rounded-4 shadow-sm">
            <i class="fas fa-shopping-bag fa-4x text-primary mb-4 d-block"></i>
            <h4 class="mb-3">No orders yet</h4>
            <p class="text-muted mb-4">When you place orders, they'll appear here.</p>
            <a href="products.php" class="btn btn-primary btn-lg px-5 py-3">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12">
                <?php foreach ($orders as $order): ?>
                    <div class="card mb-4 shadow-sm border-0 rounded-4">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h5 class="mb-0 fw-bold">Order #<?= htmlspecialchars($order['order_number']) ?></h5>
                                <small class="text-muted"><?= date('F d, Y \a\t h:i A', strtotime($order['created_at'])) ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fs-5 fw-bold text-primary mb-1">KSh <?= number_format($order['total_amount']) ?></div>
                                <?php 
$status_class = 'secondary';
if ($order['status'] == 'pending') $status_class = 'warning';
elseif ($order['status'] == 'processing') $status_class = 'info';
elseif ($order['status'] == 'ready_for_delivery') $status_class = 'primary';
elseif ($order['status'] == 'delivered') $status_class = 'success';
elseif ($order['status'] == 'cancelled') $status_class = 'danger';
?>
                                    <span class="badge bg-<?= $status_class ?> fs-6">
                                    <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Order Details</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Subtotal:</strong></td>
                                            <td>KSh <?= number_format($order['subtotal']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Delivery:</strong></td>
                                            <td>KSh <?= number_format($order['delivery_fee']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Total:</strong></td>
                                            <td><strong>KSh <?= number_format($order['total_amount']) ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Payment:</strong></td>
                                            <td>
                                                <span class="badge bg-<?= $order['payment_status'] == 'paid' ? 'success' : ($order['payment_status'] == 'failed' ? 'danger' : 'warning') ?>">
                                                    <?= ucfirst($order['payment_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>Delivery Info</h6>
                                    <p><strong>Type:</strong> <?= ucfirst($order['delivery_type']) ?></p>
                                    <?php if ($order['delivery_type'] == 'home_delivery' && !empty($order['delivery_address'])): ?>
                                        <p><strong>Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($order['phone'])): ?>
                                        <p><strong>Phone:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                                    <?php endif; ?>

                                    <?php if ($order['delivery_type'] === 'home_delivery' && !in_array($order['status'], ['delivered', 'cancelled'])): ?>
                                        <a href="track-order.php?order_id=<?= $order['id'] ?>"
                                           class="btn btn-sm btn-primary mt-1">
                                            <i class="fas fa-map-marker-alt me-1"></i> Track Order
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Order Status Timeline -->
                            <?php
                            $steps = ['pending', 'processing', 'ready_for_delivery', 'shipped', 'delivered'];
                            $current_step = array_search($order['status'], $steps) !== false ? array_search($order['status'], $steps) : 0;
                            $step_labels = ['Order Placed', 'Processing', 'Ready', 'On the Way', 'Delivered'];
                            ?>
                            <div class="mt-3 p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <?php for ($i = 0; $i < count($steps); $i++): ?>
                                        <?php if ($i > $current_step && $i !== count($steps)-1) break; ?>
                                        <div class="text-center flex-fill">
                                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1
                                                <?= $i <= $current_step ? 'bg-primary text-white' : 'bg-secondary text-white' ?>"
                                                style="width:28px;height:28px;font-size:12px;">
                                                <?php if ($i < $current_step): ?>
                                                    <i class="fas fa-check"></i>
                                                <?php else: ?>
                                                    <?= $i + 1 ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small <?= $i <= $current_step ? 'text-dark' : 'text-muted' ?>">
                                                <?= $step_labels[$i] ?>
                                            </div>
                                        </div>
                                        <?php if ($i < count($steps) - 1): ?>
                                            <div class="flex-fill border-top <?= $i < $current_step ? 'border-primary' : 'border-secondary' ?>" style="height: 2px; margin-top: 14px;"></div>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <!-- Track on Map Section -->
                            <?php if ($order['delivery_type'] === 'home_delivery' && !in_array($order['status'], ['delivered', 'cancelled'])): ?>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#map_<?= $order['id'] ?>">
                                    <i class="fas fa-map me-1"></i> Show Map Tracking
                                </button>
                                <div class="collapse mt-2" id="map_<?= $order['id'] ?>">
                                    <div class="card card-body p-0" style="height: 250px; border-radius: 8px; overflow: hidden;">
                                        <div id="mini_map_<?= $order['id'] ?>" style="height: 100%; width: 100%;"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle me-1"></i>Live tracking available when driver is assigned</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Order Items -->
                            <?php 
                            $items = getOrderItems($order['id']);
                            if (!empty($items)):
                            ?>
                            <hr>
                            <h6>Items Ordered</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($item['product_image'])): ?>
                                                            <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($item['product_image']) ?>" 
                                                                 class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($item['product_name']) ?>
                                                    </div>
                                                </td>
                                                <td>KSh <?= number_format($item['price']) ?></td>
                                                <td><?= $item['quantity'] ?></td>
                                                <td>KSh <?= number_format($item['price'] * $item['quantity']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center py-3">
                            <a href="order-confirmation.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-receipt me-1"></i> View Details
                            </a>
                            <?php if ($order['status'] === 'delivered'): ?>
                                <a href="products.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-redo me-1"></i> Reorder
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="text-center mt-4">
        <a href="products.php" class="btn btn-outline-primary">Continue Shopping</a>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize maps for each order with tracking
    <?php foreach ($orders as $order): ?>
        <?php if ($order['delivery_type'] === 'home_delivery' && !in_array($order['status'], ['delivered', 'cancelled'])): ?>
        (function() {
            const mapDiv = document.getElementById('mini_map_<?= $order['id'] ?>');
            if (!mapDiv._leaflet_id) {
                // Default to Mlolongo location
                const defaultLat = -1.4225;
                const defaultLng = 36.9811;
                
                const map = L.map('mini_map_<?= $order['id'] ?>').setView([defaultLat, defaultLng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);
                
                // Add store marker
                const storeIcon = L.divIcon({
                    html: '<div style="background:#0d6efd;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:12px;">🏪</div>',
                    iconSize: [24, 24], iconAnchor: [12, 12]
                });
                L.marker([defaultLat, defaultLng], {icon: storeIcon}).addTo(map).bindPopup('Store Location');
                
                // Add delivery marker if available
                <?php if (!empty($order['delivery_lat']) && !empty($order['delivery_lng'])): ?>
                const deliveryIcon = L.divIcon({
                    html: '<div style="background:#28a745;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:12px;">📍</div>',
                    iconSize: [24, 24], iconAnchor: [12, 12]
                });
                L.marker([<?= $order['delivery_lat'] ?>, <?= $order['delivery_lng'] ?>], {icon: deliveryIcon}).addTo(map).bindPopup('Delivery Location');
                map.setView([<?= $order['delivery_lat'] ?>, <?= $order['delivery_lng'] ?>], 14);
                <?php endif; ?>
            }
        })();
        <?php endif; ?>
    <?php endforeach; ?>
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
