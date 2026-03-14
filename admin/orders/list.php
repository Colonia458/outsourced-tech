<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$page_title = 'Orders Management';

// Handle status update
if (isset($_POST['update_status']) && is_numeric($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $allowed_statuses = ['pending', 'processing', 'ready_for_delivery', 'shipped', 'delivered', 'cancelled', 'returned'];
    $new_status = $_POST['status'] ?? '';
    $admin_note = trim($_POST['admin_note'] ?? '');

    if (!in_array($new_status, $allowed_statuses)) {
        header('Location: list.php?msg=invalid_status');
        exit();
    }

    query(
        "UPDATE orders SET status = ?, admin_note = ? WHERE id = ?",
        [$new_status, $admin_note, $order_id]
    );
    
    header('Location: list.php?msg=updated');
    exit();
}

// Handle record payment (admin)
if (isset($_POST['action']) && $_POST['action'] === 'record_payment' && is_numeric($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    
    $order = fetchOne("SELECT id, total_amount, payment_status FROM orders WHERE id = ?", [$order_id]);
    if ($order && $order['payment_status'] !== 'paid') {
        // Record payment
        $trans_id = 'PAY_' . strtoupper(uniqid());
        query("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?", [$order_id]);
        
        // Record payment in payments table
        try {
            db_insert('payments', [
                'order_id' => $order_id,
                'amount' => $order['total_amount'],
                'method' => 'payment',
                'transaction_id' => $trans_id,
                'receipt_number' => 'RCP' . date('YmdHis'),
                'status' => 'completed'
            ]);
        } catch (Exception $e) { /* table might not exist */ }
        
        header('Location: list.php?msg=payment_recorded');
        exit();
    }
}

// Build query with filters
$where = "1=1";
$params = [];

if (!empty($_GET['status']) && in_array($_GET['status'], ['pending','processing','ready_for_delivery','shipped','delivered','cancelled','returned'])) {
    $where .= " AND o.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['payment']) && in_array($_GET['payment'], ['pending','paid','failed','refunded'])) {
    $where .= " AND o.payment_status = ?";
    $params[] = $_GET['payment'];
}

if (!empty($_GET['search'])) {
    $where .= " AND (o.order_number LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sql = "SELECT o.*, u.full_name, u.email, u.phone, dz.name as delivery_zone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN delivery_zones dz ON o.delivery_zone_id = dz.id
        WHERE $where
        ORDER BY o.created_at DESC";

$orders = fetchAll($sql, $params);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white mb-4"><i class="fas fa-microchip"></i> <?= APP_NAME ?></h4>
        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="../products/list.php"><i class="fas fa-box"></i> Products</a>
        <a href="../services/list.php"><i class="fas fa-tools"></i> Services</a>
        <a href="list.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="../users/list.php"><i class="fas fa-users"></i> Users</a>
        <a href="../service-bookings/list.php"><i class="fas fa-calendar"></i> Bookings</a>
        <a href="../chatbot/conversations.php"><i class="fas fa-comments"></i> Chatbot</a>
        <a href="../delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a>
        <a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="../coupons/manage.php"><i class="fas fa-tag"></i> Coupons</a>
        <a href="../reviews/manage.php"><i class="fas fa-star"></i> Reviews</a>
        <a href="../system-status.php"><i class="fas fa-server"></i> System</a>
        <a href="../logs.php"><i class="fas fa-file-alt"></i> Logs</a>
        <a href="../delivery-map.php"><i class="fas fa-map"></i> Map</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <h2 class="mb-4">Orders Management</h2>

        <?php if ($msg === 'updated'): ?>
            <div class="alert alert-success">Order status updated!</div>
        <?php elseif ($msg === 'payment_recorded'): ?>
            <div class="alert alert-success">Payment recorded successfully! Order is now marked as paid.</div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search order number, name, phone..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= ($_GET['status'] ?? '') == 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="ready_for_delivery" <?= ($_GET['status'] ?? '') == 'ready_for_delivery' ? 'selected' : '' ?>>Ready for Delivery</option>
                            <option value="shipped" <?= ($_GET['status'] ?? '') == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= ($_GET['status'] ?? '') == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="payment" class="form-select">
                            <option value="">All Payments</option>
                            <option value="pending" <?= ($_GET['payment'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= ($_GET['payment'] ?? '') == 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="failed" <?= ($_GET['payment'] ?? '') == 'failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="7" class="text-center py-4">No orders found</td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($o['order_number']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($o['full_name'] ?? 'Guest') ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($o['phone'] ?? '') ?></small>
                                        </td>
                                        <td>KSh <?= number_format($o['total_amount']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $o['status'] == 'pending' ? 'warning' : 
                                                ($o['status'] == 'processing' ? 'info' : 
                                                ($o['status'] == 'delivered' ? 'success' : 
                                                ($o['status'] == 'cancelled' ? 'danger' : 'secondary'))) 
                                            ?>">
                                                <?= ucfirst(str_replace('_', ' ', $o['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $o['payment_status'] == 'paid' ? 'success' : ($o['payment_status'] == 'failed' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($o['payment_status']) ?>
                                            </span>
                                            <?php if ($o['payment_status'] != 'paid'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                    <input type="hidden" name="action" value="record_payment">
                                                    <button type="submit" class="btn btn-xs btn-outline-success" title="Record Payment" onclick="return confirm('Record payment for this order?')">
                                                        <i class="fas fa-check"></i> Pay
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#orderModal<?= $o['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Order Details Modal -->
                                    <div class="modal fade" id="orderModal<?= $o['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Order #<?= htmlspecialchars($o['order_number']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-4">
                                                        <div class="col-md-6">
                                                            <h6>Customer Info</h6>
                                                            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($o['full_name'] ?? 'N/A') ?></p>
                                                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($o['email'] ?? 'N/A') ?></p>
                                                            <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($o['phone'] ?? 'N/A') ?></p>
                                                            <p class="mb-0"><strong>Address:</strong> <?= htmlspecialchars($o['delivery_address'] ?? 'N/A') ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Order Summary</h6>
                                                            <p class="mb-1"><strong>Subtotal:</strong> KSh <?= number_format($o['subtotal']) ?></p>
                                                            <p class="mb-1"><strong>Delivery:</strong> KSh <?= number_format($o['delivery_fee']) ?></p>
                                                            <p class="mb-0"><strong>Total:</strong> KSh <?= number_format($o['total_amount']) ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <h6>Update Status</h6>
                                                    <form method="POST">
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <select name="status" class="form-select">
                                                                    <option value="pending" <?= $o['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="processing" <?= $o['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                                                    <option value="ready_for_delivery" <?= $o['status'] == 'ready_for_delivery' ? 'selected' : '' ?>>Ready for Delivery</option>
                                                                    <option value="shipped" <?= $o['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                                                    <option value="delivered" <?= $o['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                                    <option value="cancelled" <?= $o['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <input type="text" name="admin_note" class="form-control" placeholder="Admin note" value="<?= htmlspecialchars($o['admin_note'] ?? '') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <button type="submit" name="update_status" class="btn btn-primary w-100">Update</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
