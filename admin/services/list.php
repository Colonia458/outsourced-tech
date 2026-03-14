<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$page_title = 'Services Management';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    query("DELETE FROM services WHERE id = ?", [$delete_id]);
    header('Location: list.php?msg=deleted');
    exit();
}

// Get services
$services = fetchAll("SELECT * FROM services ORDER BY name");

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
        <a href="list.php" class="active"><i class="fas fa-tools"></i> Services</a>
        <a href="../orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Services Management</h2>
            <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Service</a>
        </div>

        <?php if ($msg === 'deleted'): ?>
            <div class="alert alert-success">Service deleted successfully!</div>
        <?php elseif ($msg === 'saved'): ?>
            <div class="alert alert-success">Service saved successfully!</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Service</th>
                                <th>Price</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($services)): ?>
                                <tr><td colspan="6" class="text-center py-4">No services found</td></tr>
                            <?php else: ?>
                                <?php foreach ($services as $s): ?>
                                    <tr>
                                        <td><?= $s['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($s['description'] ?? '', 0, 60)) ?></small>
                                        </td>
                                        <td>KSh <?= number_format($s['price']) ?></td>
                                        <td><?= $s['duration_minutes'] ? $s['duration_minutes'] . ' min' : '-' ?></td>
                                        <td>
                                            <?php if ($s['visible']): ?>
                                                <span class="badge bg-primary">Visible</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                            <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this service?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 text-muted">
            Total services: <?= count($services) ?>
        </div>
    </div>
</body>
</html>
