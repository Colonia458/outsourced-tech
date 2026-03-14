<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';
require_once '../../src/security.php';

$page_title = 'Users Management';

// Handle block/unblock — now POST with CSRF to prevent CSRF via GET link
if (isset($_POST['toggle_user']) && is_numeric($_POST['user_id'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $user_id = (int)$_POST['user_id'];
    $user = fetchOne("SELECT active FROM users WHERE id = ?", [$user_id]);
    if ($user) {
        $new_active = $user['active'] ? 0 : 1;
        query("UPDATE users SET active = ? WHERE id = ?", [$new_active, $user_id]);
    }
    header('Location: list.php');
    exit();
}

// Handle adjust loyalty points
if (isset($_POST['adjust_points']) && is_numeric($_POST['user_id'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $user_id = (int)$_POST['user_id'];
    $points = (int)$_POST['points'];
    $action = $_POST['action']; // 'add' or 'deduct'
    
    if ($action === 'add') {
        query("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?", [$points, $user_id]);
    } else {
        query("UPDATE users SET loyalty_points = GREATEST(0, loyalty_points - ?) WHERE id = ?", [$points, $user_id]);
    }
    
    header('Location: list.php?msg=points_adjusted');
    exit();
}

// Get users
$users = fetchAll("SELECT * FROM users ORDER BY created_at DESC");

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
        <a href="list.php" class="active"><i class="fas fa-users"></i> Users</a>
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
        <h2 class="mb-4">Users / Customers Management</h2>

        <?php if ($msg === 'points_adjusted'): ?>
            <div class="alert alert-success">Loyalty points adjusted!</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Loyalty Points</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="8" class="text-center py-4">No users found</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= $u['id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($u['full_name'] ?? $u['username']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?= number_format($u['loyalty_points']) ?> pts</span>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pointsModal<?= $u['id'] ?>">
                                                <i class="fas fa-plus-minus"></i>
                                            </button>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <td>
                                            <?php if ($u['active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Blocked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $u['active'] ? 'Block' : 'Unblock' ?> this user?')">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" name="toggle_user" class="btn btn-sm btn-<?= $u['active'] ? 'warning' : 'success' ?>">
                                                    <i class="fas fa-<?= $u['active'] ? 'ban' : 'check' ?>"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    
                                    <!-- Points Modal -->
                                    <div class="modal fade" id="pointsModal<?= $u['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Adjust Points - <?= htmlspecialchars($u['full_name'] ?? $u['username']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <div class="modal-body">
                                                        <p>Current Points: <strong><?= number_format($u['loyalty_points']) ?></strong></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Points</label>
                                                            <input type="number" name="points" class="form-control" min="1" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Action</label>
                                                            <select name="action" class="form-select">
                                                                <option value="add">Add Points</option>
                                                                <option value="deduct">Deduct Points</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <button type="submit" name="adjust_points" class="btn btn-primary">Update Points</button>
                                                    </div>
                                                </form>
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

        <div class="mt-3 text-muted">
            Total users: <?= count($users) ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
