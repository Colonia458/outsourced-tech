<?php
// admin/delivery-map.php - Admin delivery map overview

session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config.php';
require_once '../src/database.php';

$page_title = 'Delivery Map';

// Fetch all active orders that have coordinates
$active_orders = fetchAll(
    "SELECT o.id, o.order_number, o.status, o.delivery_address,
            o.delivery_lat, o.delivery_lng, o.driver_lat, o.driver_lng,
            o.driver_updated_at, o.total_amount, o.created_at,
            u.full_name, u.phone
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.status NOT IN ('delivered', 'cancelled')
       AND o.delivery_type = 'delivery'
     ORDER BY o.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        #admin_map { height: calc(100vh - 180px); border-radius: 12px; }
        .order-popup { min-width: 220px; }
        .order-popup .badge { font-size: 12px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white mb-4"><i class="fas fa-microchip"></i> <?= APP_NAME ?></h4>
        <a href="index.php"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
        <a href="products/list.php"><i class="fas fa-box me-2"></i> Products</a>
        <a href="services/list.php"><i class="fas fa-tools me-2"></i> Services</a>
        <a href="orders/list.php"><i class="fas fa-shopping-cart me-2"></i> Orders</a>
        <a href="users/list.php"><i class="fas fa-users me-2"></i> Users</a>
        <a href="service-bookings/list.php"><i class="fas fa-calendar-check me-2"></i> Bookings</a>
        <a href="chatbot/conversations.php"><i class="fas fa-robot me-2"></i> Chatbot</a>
        <a href="delivery-zones/manage.php"><i class="fas fa-truck me-2"></i> Delivery</a>
        <a href="coupons/manage.php"><i class="fas fa-ticket me-2"></i> Coupons</a>
        <a href="loyalty-tiers/manage.php"><i class="fas fa-award me-2"></i> Loyalty</a>
        <a href="reviews/manage.php"><i class="fas fa-star me-2"></i> Reviews</a>
        <a href="system-status.php"><i class="fas fa-server me-2"></i> System</a>
        <a href="logs.php"><i class="fas fa-file-lines me-2"></i> Logs</a>
        <a href="delivery-map.php" class="active"><i class="fas fa-map-location-dot me-2"></i> Map</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fas fa-map-marked-alt text-primary me-2"></i>Live Delivery Map</h2>
            <div>
                <span class="badge bg-danger me-2">🔴 Customer Pin</span>
                <span class="badge bg-primary me-2">🚚 Driver</span>
                <span class="badge bg-secondary" id="order_count">
                    <?= count($active_orders) ?> active order<?= count($active_orders) !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <!-- Update Driver Location Panel -->
        <div class="card mb-3 shadow-sm">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Order</label>
                        <select id="driver_order_select" class="form-select form-select-sm">
                            <option value="">-- Select Order --</option>
                            <?php foreach ($active_orders as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['order_number']) ?> — <?= htmlspecialchars($o['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Driver Lat</label>
                        <input type="number" id="driver_lat_input" class="form-control form-control-sm" step="0.00001" placeholder="-1.3667">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Driver Lng</label>
                        <input type="number" id="driver_lng_input" class="form-control form-control-sm" step="0.00001" placeholder="36.9833">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100" onclick="updateDriverFromForm()">
                            <i class="fas fa-location-arrow me-1"></i> Update Driver
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary btn-sm" onclick="useMapClick()">
                            <i class="fas fa-mouse-pointer me-1"></i> Click map to set driver location
                        </button>
                    </div>
                </div>
                <div id="driver_update_msg" class="mt-1 small"></div>
            </div>
        </div>

        <div id="admin_map"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const ORDERS = <?= json_encode($active_orders) ?>;
    const BASE   = '<?= BASE_URL ?>';

    const map = L.map('admin_map').setView([-1.3667, 36.9833], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    const driverMarkers = {};
    const customerMarkers = {};

    // Plot all orders
    ORDERS.forEach(order => {
        // Customer pin (red)
        if (order.delivery_lat && order.delivery_lng) {
            const m = L.marker([order.delivery_lat, order.delivery_lng], {
                icon: L.divIcon({
                    html: `<div style="background:#dc3545;width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,.4);"></div>`,
                    iconSize: [20, 20], iconAnchor: [10, 10], className: ''
                })
            }).addTo(map);

            m.bindPopup(`
                <div class="order-popup">
                    <strong>${order.order_number}</strong><br>
                    <span class="badge bg-secondary">${order.status.replace(/_/g,' ')}</span><br>
                    <small>${order.full_name} · ${order.phone}</small><br>
                    <small>${order.delivery_address || ''}</small><br>
                    <strong>KSh ${parseInt(order.total_amount).toLocaleString()}</strong><br>
                    <a href="orders/list.php" class="btn btn-xs btn-outline-primary mt-1" style="font-size:11px;padding:2px 8px;">View Order</a>
                </div>
            `);
            customerMarkers[order.id] = m;
        }

        // Driver marker (blue)
        if (order.driver_lat && order.driver_lng) {
            const dm = L.marker([order.driver_lat, order.driver_lng], {
                icon: L.divIcon({
                    html: `<div style="background:#0d6efd;width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:14px;">🚚</div>`,
                    iconSize: [28, 28], iconAnchor: [14, 14], className: ''
                })
            }).addTo(map).bindPopup(`<strong>Driver for ${order.order_number}</strong>`);
            driverMarkers[order.id] = dm;
        }
    });

    // ── Click-to-set-driver-location mode ──────────────────────
    let clickMode = false;
    function useMapClick() {
        clickMode = true;
        document.getElementById('driver_update_msg').innerHTML =
            '<span class="text-info"><i class="fas fa-mouse-pointer me-1"></i> Click on the map to set driver location for the selected order</span>';
        map.getContainer().style.cursor = 'crosshair';
    }

    map.on('click', function(e) {
        if (!clickMode) return;
        clickMode = false;
        map.getContainer().style.cursor = '';
        document.getElementById('driver_lat_input').value = e.latlng.lat.toFixed(6);
        document.getElementById('driver_lng_input').value = e.latlng.lng.toFixed(6);
        document.getElementById('driver_update_msg').innerHTML =
            '<span class="text-success"><i class="fas fa-check me-1"></i> Coordinates set — click "Update Driver" to save</span>';
    });

    // ── Update driver location ──────────────────────────────────
    async function updateDriverFromForm() {
        const orderId = document.getElementById('driver_order_select').value;
        const lat     = parseFloat(document.getElementById('driver_lat_input').value);
        const lng     = parseFloat(document.getElementById('driver_lng_input').value);
        const msgEl   = document.getElementById('driver_update_msg');

        if (!orderId) { msgEl.innerHTML = '<span class="text-danger">Please select an order</span>'; return; }
        if (!lat || !lng) { msgEl.innerHTML = '<span class="text-danger">Please enter coordinates or click the map</span>'; return; }

        try {
            const res  = await fetch(BASE + '../api/v1/tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_driver', order_id: parseInt(orderId), lat, lng }),
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.success) {
                msgEl.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i> Driver location updated!</span>';

                // Update or add driver marker on map
                if (driverMarkers[orderId]) {
                    driverMarkers[orderId].setLatLng([lat, lng]);
                } else {
                    driverMarkers[orderId] = L.marker([lat, lng], {
                        icon: L.divIcon({
                            html: `<div style="background:#0d6efd;width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:14px;">🚚</div>`,
                            iconSize: [28, 28], iconAnchor: [14, 14], className: ''
                        })
                    }).addTo(map).bindPopup(`<strong>Driver for Order #${orderId}</strong>`);
                }

                map.setView([lat, lng], 15);
            } else {
                msgEl.innerHTML = `<span class="text-danger">${data.message}</span>`;
            }
        } catch (e) {
            msgEl.innerHTML = '<span class="text-danger">Error updating location</span>';
        }
    }

    // Auto-refresh every 20 seconds
    setInterval(async () => {
        try {
            for (const order of ORDERS) {
                const res  = await fetch(BASE + `../api/v1/tracking.php?order_id=${order.id}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (data.success && data.order.driver_lat && data.order.driver_lng) {
                    const lat = parseFloat(data.order.driver_lat);
                    const lng = parseFloat(data.order.driver_lng);
                    if (driverMarkers[order.id]) {
                        driverMarkers[order.id].setLatLng([lat, lng]);
                    }
                }
            }
        } catch (e) { /* silent */ }
    }, 20000);
    </script>
</body>
</html>
