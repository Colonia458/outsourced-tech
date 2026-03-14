<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$page_title = 'Service Bookings';

// Handle status update
if (isset($_POST['update_booking']) && is_numeric($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    query(
        "UPDATE service_bookings SET status = ?, admin_notes = ? WHERE id = ?",
        [$status, $admin_notes, $booking_id]
    );
    
    header('Location: list.php?msg=updated');
    exit();
}

// Get bookings
$bookings = fetchAll("
    SELECT sb.*, u.full_name, u.email, u.phone, s.name as service_name, s.price as service_price
    FROM service_bookings sb
    LEFT JOIN users u ON sb.user_id = u.id
    LEFT JOIN services s ON sb.service_id = s.id
    ORDER BY sb.booking_date DESC, sb.booking_time DESC
");

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
        <a href="../orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="../users/list.php"><i class="fas fa-users"></i> Users</a>
        <a href="list.php" class="active"><i class="fas fa-calendar"></i> Bookings</a>
        <a href="../chatbot/conversations.php"><i class="fas fa-comments"></i> Chatbot</a>
        <a href="../coupons/manage.php"><i class="fas fa-ticket"></i> Coupons</a>
        <a href="../reviews/manage.php"><i class="fas fa-star"></i> Reviews</a>
        <a href="../delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a>
        <a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="../system-status.php"><i class="fas fa-server"></i> System</a>
        <a href="../logs.php"><i class="fas fa-file-lines"></i> Logs</a>
        <a href="../delivery-map.php"><i class="fas fa-map"></i> Map</a>
        <a href="../logout.php" style="margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <h2 class="mb-4">Service Bookings</h2>

        <?php if ($msg === 'updated'): ?>
            <div class="alert alert-success">Booking updated!</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Service</th>
                                <th>Customer</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr><td colspan="7" class="text-center py-4">No bookings found</td></tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td><?= $b['id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($b['service_name']) ?><br>
                                            <small class="text-muted">KSh <?= number_format($b['service_price']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($b['full_name'] ?? 'Guest') ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($b['phone'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($b['booking_date'])) ?><br>
                                            <small class="text-muted"><?= $b['booking_time'] ? date('h:i A', strtotime($b['booking_time'])) : 'Anytime' ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $b['status'] == 'pending' ? 'warning' : 
                                                ($b['status'] == 'confirmed' ? 'info' : 
                                                ($b['status'] == 'completed' ? 'success' : 
                                                ($b['status'] == 'cancelled' ? 'danger' : 'secondary'))) 
                                            ?>">
                                                <?= ucfirst($b['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($b['notes'] ?? '-') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal<?= $b['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Booking Modal -->
                                    <div class="modal fade" id="bookingModal<?= $b['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Booking #<?= $b['id'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Service:</strong> <?= htmlspecialchars($b['service_name']) ?> (KSh <?= number_format($b['service_price']) ?>)</p>
                                                    <p><strong>Customer:</strong> <?= htmlspecialchars($b['full_name'] ?? 'Guest') ?></p>
                                                    <p><strong>Phone:</strong> <?= htmlspecialchars($b['phone'] ?? 'N/A') ?></p>
                                                    <p><strong>Date:</strong> <?= date('F d, Y', strtotime($b['booking_date'])) ?></p>
                                                    <p><strong>Time:</strong> <?= $b['booking_time'] ? date('h:i A', strtotime($b['booking_time'])) : 'Anytime' ?></p>
                                                    <p><strong>Customer Notes:</strong> <?= htmlspecialchars($b['notes'] ?? 'None') ?></p>
                                                    
                                                    <hr>
                                                    <h6>Update Status</h6>
                                                    <form method="POST">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <select name="status" class="form-select">
                                                                    <option value="pending" <?= $b['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="confirmed" <?= $b['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                                    <option value="in_progress" <?= $b['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                                    <option value="completed" <?= $b['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                    <option value="cancelled" <?= $b['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <input type="text" name="admin_notes" class="form-control" placeholder="Admin notes" value="<?= htmlspecialchars($b['admin_notes'] ?? '') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                            <button type="submit" name="update_booking" class="btn btn-primary w-100">Update Booking</button>
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
