<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';
require_once '../../src/coupons.php';
require_once '../../src/security.php';

$page_title = 'Manage Coupons';

// Handle coupon actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $code = strtoupper(sanitize($_POST['code'] ?? ''));
        $description = sanitize($_POST['description'] ?? '');
        $discount_type = $_POST['discount_type'] ?? 'percentage';
        $discount_value = (float)$_POST['discount_value'];
        $min_order = (float)$_POST['min_order_amount'];
        $max_uses = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        
        if (empty($code) || empty($discount_value)) {
            $error = 'Please fill in all required fields';
        } else {
            $id = db_insert('coupons', [
                'code' => $code,
                'description' => $description,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'min_order_amount' => $min_order,
                'max_uses' => $max_uses,
                'valid_until' => $valid_until,
                'is_active' => 1
            ]);
            if ($id) {
                $message = 'Coupon created successfully';
            } else {
                $error = 'Failed to create coupon. Code might already exist.';
            }
        }
    } elseif ($action === 'toggle') {
        $coupon_id = (int)$_POST['coupon_id'];
        $coupon = fetchOne("SELECT is_active FROM coupons WHERE id = ?", [$coupon_id]);
        if ($coupon) {
            query("UPDATE coupons SET is_active = ? WHERE id = ?", [$coupon['is_active'] ? 0 : 1, $coupon_id]);
            $message = 'Coupon status updated';
        }
    } elseif ($action === 'delete') {
        $coupon_id = (int)$_POST['coupon_id'];
        query("DELETE FROM coupons WHERE id = ?", [$coupon_id]);
        $message = 'Coupon deleted';
    }
}

// Get all coupons
$coupons = fetchAll("SELECT * FROM coupons ORDER BY created_at DESC");

// Get admin name for welcome message
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
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
        :root { --primary: #0d6efd; --dark: #212529; }
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .sidebar a i { width: 25px; }
        .main-content { margin-left: 250px; padding: 20px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-white mb-4"><i class="fas fa-microchip"></i> <?= APP_NAME ?></h4>
        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="../products/list.php"><i class="fas fa-box"></i> Products</a>
        <a href="../services/list.php"><i class="fas fa-tools"></i> Services</a>
        <a href="../orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="../users/list.php"><i class="fas fa-users"></i> Users</a>
        <a href="../service-bookings/list.php"><i class="fas fa-calendar"></i> Bookings</a>
        <a href="../chatbot/conversations.php"><i class="fas fa-comments"></i> Chatbot</a>
        <a href="../delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a>
        <a href="manage.php" class="active"><i class="fas fa-ticket"></i> Coupons</a>
        <a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="../reviews/manage.php"><i class="fas fa-star"></i> Reviews</a>
        <a href="../system-status.php"><i class="fas fa-server"></i> System</a>
        <a href="../logs.php"><i class="fas fa-file-lines"></i> Logs</a>
        <a href="../delivery-map.php"><i class="fas fa-map"></i> Map</a>
        <a href="../logout.php" style="margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="bg-primary bg-gradient text-white p-4 rounded mb-4">
            <h4 class="mb-1">Welcome back, <?= htmlspecialchars($admin_name) ?>!</h4>
            <p class="mb-0 opacity-75">Manage your coupons and promotional codes here.</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <!-- Create Coupon Card -->
            <div class="col-12 col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Coupon</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="mb-3">
                                <label class="form-label">Coupon Code *</label>
                                <input type="text" name="code" class="form-control" placeholder="e.g., SUMMER20" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Discount Type</label>
                                <select name="discount_type" class="form-select">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (KES)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Discount Value *</label>
                                <input type="number" name="discount_value" class="form-control" step="0.01" min="0" placeholder="e.g., 10 or 500" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Minimum Order Amount (KES)</label>
                                <input type="number" name="min_order_amount" class="form-control" step="0.01" min="0" value="0">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Max Uses</label>
                                <input type="number" name="max_uses" class="form-control" min="1" placeholder="Leave empty for unlimited">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Valid Until</label>
                                <input type="date" name="valid_until" class="form-control">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Create Coupon
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Existing Coupons Card -->
            <div class="col-12 col-lg-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-ticket me-2"></i>Existing Coupons</h5>
                        <span class="badge bg-primary"><?= count($coupons) ?> total</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Code</th>
                                        <th>Discount</th>
                                        <th>Min Order</th>
                                        <th>Uses</th>
                                        <th>Valid Until</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($coupons)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-ticket-alt fa-2x mb-2 d-block opacity-50"></i>
                                                No coupons yet
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($coupons as $coupon): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($coupon['code']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                                        <span class="text-success fw-bold"><?= $coupon['discount_value'] ?>%</span>
                                                    <?php else: ?>
                                                        <span class="text-success fw-bold">KES <?= number_format($coupon['discount_value']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>KES <?= number_format($coupon['min_order_amount']) ?></td>
                                                <td>
                                                    <?= $coupon['used_count'] ?? 0 ?><?= $coupon['max_uses'] ? ' / ' . $coupon['max_uses'] : '' ?>
                                                </td>
                                                <td>
                                                    <?php if ($coupon['valid_until']): ?>
                                                        <?= date('M d, Y', strtotime($coupon['valid_until'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No expiry</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($coupon['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                                        <button type="submit" class="btn btn-sm <?= $coupon['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                                            <i class="fas <?= $coupon['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this coupon?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
