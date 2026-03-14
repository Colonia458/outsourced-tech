<?php
// public/track-order.php - Live order tracking with map

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    header("Location: orders.php");
    exit;
}

// Fetch order (must belong to this user)
$order = fetchOne(
    "SELECT o.*, u.full_name, u.phone as user_phone
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.id = ? AND o.user_id = ?",
    [$order_id, $_SESSION['user_id']]
);

if (!$order) {
    header("Location: orders.php");
    exit;
}

$page_title = 'Track Order #' . htmlspecialchars($order['order_number']);
require_once __DIR__ . '/../templates/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="orders.php" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
        <h2 class="mb-0">
            <i class="fas fa-map-marker-alt text-danger me-2"></i>
            Track Order <span class="text-primary"><?= htmlspecialchars($order['order_number']) ?></span>
        </h2>
    </div>

    <!-- Status Bar -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <strong>Order Status:</strong>
                    <span class="badge ms-2 fs-6
                        <?= match($order['status']) {
                            'pending'              => 'bg-warning text-dark',
                            'processing'           => 'bg-info text-dark',
                            'ready_for_delivery'   => 'bg-primary',
                            'shipped'              => 'bg-primary',
                            'delivered'            => 'bg-success',
                            'cancelled'            => 'bg-danger',
                            default                => 'bg-secondary'
                        } ?>">
                        <?= ucwords(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                </div>
                <div class="col-md-4">
                    <strong>Payment:</strong>
                    <span class="badge ms-2 <?= $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= ucfirst($order['payment_status']) ?>
                    </span>
                </div>
                <div class="col-md-4 text-md-end">
                    <strong>Total:</strong>
                    <span class="text-success fw-bold ms-2">KSh <?= number_format($order['total_amount']) ?></span>
                </div>
            </div>

            <!-- Progress Steps -->
            <div class="mt-4">
                <?php
                $steps = ['pending' => 0, 'processing' => 1, 'ready_for_delivery' => 2, 'shipped' => 3, 'delivered' => 4];
                $current_step = $steps[$order['status']] ?? 0;
                $step_labels = ['Order Placed', 'Processing', 'Ready', 'On the Way', 'Delivered'];
                ?>
                <div class="d-flex justify-content-between position-relative" style="padding: 0 10px;">
                    <div class="position-absolute top-50 start-0 end-0 translate-middle-y" style="height:4px;background:#dee2e6;z-index:0;margin:0 30px;"></div>
                    <div class="position-absolute top-50 start-0 translate-middle-y" style="height:4px;background:#0d6efd;z-index:1;margin:0 30px;width:<?= min(100, $current_step * 25) ?>%;"></div>
                    <?php foreach ($step_labels as $i => $label): ?>
                        <div class="text-center" style="z-index:2;width:60px;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1
                                <?= $i <= $current_step ? 'bg-primary text-white' : 'bg-white border border-2 border-secondary text-secondary' ?>"
                                style="width:36px;height:36px;font-size:14px;">
                                <?= $i < $current_step ? '✓' : ($i + 1) ?>
                            </div>
                            <small class="<?= $i <= $current_step ? 'text-primary fw-bold' : 'text-muted' ?>" style="font-size:11px;">
                                <?= $label ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-map me-2"></i>Delivery Map</h5>
            <span class="badge bg-secondary" id="last_update">Loading...</span>
        </div>
        <div class="card-body p-0">
            <div id="tracking_map" style="height: 420px; border-radius: 0 0 8px 8px;"></div>
        </div>
        <div class="card-footer text-muted small">
            <i class="fas fa-sync-alt me-1"></i> Map updates every 15 seconds while order is in transit.
            <span id="driver_status" class="ms-3"></span>
        </div>
    </div>

    <!-- Order Details -->
    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Order Details</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Delivery Address:</strong><br>
                    <?= htmlspecialchars($order['delivery_address'] ?: 'Pickup at store') ?></p>
                    <p><strong>Delivery Type:</strong> <?= ucfirst($order['delivery_type']) ?></p>
                    <p><strong>Order Date:</strong> <?= date('d M Y, H:i', strtotime($order['created_at'])) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Order Number:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
                    <p><strong>Payment Method:</strong> <?= ucfirst($order['payment_method'] ?? 'M-Pesa') ?></p>
                    <?php if ($order['admin_note'] ?? ''): ?>
                        <p><strong>Note from us:</strong> <?= htmlspecialchars($order['admin_note']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const ORDER_ID   = <?= $order_id ?>;
const ORDER_LAT  = <?= $order['delivery_lat'] ?? 'null' ?>;
const ORDER_LNG  = <?= $order['delivery_lng'] ?? 'null' ?>;
const ORDER_STATUS = '<?= $order['status'] ?>';

// Default center: Mlolongo, Kenya
const defaultCenter = [-1.3667, 36.9833];

const map = L.map('tracking_map').setView(
    ORDER_LAT && ORDER_LNG ? [ORDER_LAT, ORDER_LNG] : defaultCenter,
    ORDER_LAT ? 15 : 13
);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
}).addTo(map);

// Customer delivery pin (red)
let customerMarker = null;
if (ORDER_LAT && ORDER_LNG) {
    customerMarker = L.marker([ORDER_LAT, ORDER_LNG], {
        icon: L.divIcon({
            html: '<div style="background:#dc3545;width:22px;height:22px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,.4);"></div>',
            iconSize: [22, 22], iconAnchor: [11, 11], className: ''
        })
    }).addTo(map).bindPopup('<b>📍 Your Delivery Location</b>');
}

// Driver marker (blue truck)
let driverMarker = null;
let routeLine    = null;

function updateDriverLocation(lat, lng, updatedAt) {
    if (!lat || !lng) {
        document.getElementById('driver_status').innerHTML = '<i class="fas fa-truck text-muted me-1"></i> Driver location not yet available';
        return;
    }

    document.getElementById('driver_status').innerHTML =
        `<i class="fas fa-truck text-primary me-1"></i> Driver last seen: ${new Date(updatedAt).toLocaleTimeString()}`;

    if (driverMarker) {
        driverMarker.setLatLng([lat, lng]);
    } else {
        driverMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                html: '<div style="background:#0d6efd;width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:14px;">🚚</div>',
                iconSize: [28, 28], iconAnchor: [14, 14], className: ''
            })
        }).addTo(map).bindPopup('<b>🚚 Your Delivery Driver</b>');
    }

    // Draw line between driver and customer
    if (customerMarker) {
        if (routeLine) map.removeLayer(routeLine);
        routeLine = L.polyline([[lat, lng], [ORDER_LAT, ORDER_LNG]], {
            color: '#0d6efd', weight: 3, dashArray: '8 6', opacity: 0.7
        }).addTo(map);

        // Fit map to show both markers
        map.fitBounds([[lat, lng], [ORDER_LAT, ORDER_LNG]], { padding: [40, 40] });
    }
}

// Poll for updates every 15 seconds
async function pollTracking() {
    try {
        const res  = await fetch(`<?= BASE_URL ?>../api/v1/tracking.php?order_id=${ORDER_ID}`, { credentials: 'same-origin' });
        const data = await res.json();

        if (data.success) {
            const o = data.order;
            document.getElementById('last_update').textContent = 'Updated: ' + new Date().toLocaleTimeString();
            updateDriverLocation(o.driver_lat, o.driver_lng, o.driver_updated_at);
        }
    } catch (e) {
        console.error('Tracking poll error:', e);
    }
}

// Initial load
pollTracking();

// Only keep polling if order is in transit
if (['processing', 'ready_for_delivery', 'shipped'].includes(ORDER_STATUS)) {
    setInterval(pollTracking, 15000);
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
