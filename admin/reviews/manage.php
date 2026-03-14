<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/reviews.php';
require_once '../../src/security.php';

$page_title = 'Manage Reviews';

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $action = $_POST['action'] ?? '';
    $review_id = (int)($_POST['review_id'] ?? 0);
    
    if ($action === 'approve') {
        moderate_review($review_id, true);
        $message = 'Review approved successfully';
    } elseif ($action === 'reject') {
        moderate_review($review_id, false);
        $message = 'Review rejected';
    } elseif ($action === 'delete') {
        delete_review($review_id);
        $message = 'Review deleted';
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'pending';
$reviews = get_all_reviews($filter === 'approved' ? 1 : ($filter === 'rejected' ? 0 : null));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-container { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 250px;
            background: var(--card-bg);
            border-right: 1px solid var(--border-color);
            padding: 24px;
            overflow-y: auto;
            flex-shrink: 0;
        }
        .admin-logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 32px;
        }
        .admin-nav { list-style: none; }
        .admin-nav li { margin-bottom: 8px; }
        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
        }
        .admin-nav a:hover, .admin-nav a.active {
            background: var(--dark-bg);
            color: var(--primary-color);
        }
        .admin-content { flex: 1; padding: 32px; }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .filters { display: flex; gap: 8px; }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-secondary { background: var(--secondary-color); color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .reviews-grid {
            display: grid;
            gap: 16px;
        }
        .review-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .review-product {
            font-weight: 600;
            color: var(--primary-color);
        }
        .review-user {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .review-rating {
            color: #f59e0b;
            margin-bottom: 8px;
        }
        .review-text {
            background: var(--dark-bg);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .review-actions {
            display: flex;
            gap: 8px;
        }
        .status-pending { color: #f59e0b; }
        .status-approved { color: #10b981; }
        .status-rejected { color: #ef4444; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <div class="admin-logo"><?= APP_NAME ?></div>
            <ul class="admin-nav">
                <li><a href="../index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="../products/list.php"><i class="fas fa-box"></i> Products</a></li>
                <li><a href="../services/list.php"><i class="fas fa-tools"></i> Services</a></li>
                <li><a href="../orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="../users/list.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="../service-bookings/list.php"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                <li><a href="../chatbot/conversations.php"><i class="fas fa-robot"></i> Chatbot</a></li>
                <li><a href="../delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a></li>
                <li><a href="../coupons/manage.php"><i class="fas fa-ticket"></i> Coupons</a></li>
                <li><a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a></li>
                <li><a href="manage.php" class="active"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="../system-status.php"><i class="fas fa-server"></i> System</a></li>
                <li><a href="../logs.php"><i class="fas fa-file-lines"></i> Logs</a></li>
                <li><a href="../delivery-map.php"><i class="fas fa-map-location-dot"></i> Map</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="admin-content">
            <div class="page-header">
                <h1>Product Reviews</h1>
                <div class="filters">
                    <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
                    <a href="?filter=pending" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending</a>
                    <a href="?filter=approved" class="btn <?= $filter === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">Approved</a>
                    <a href="?filter=rejected" class="btn <?= $filter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?>">Rejected</a>
                </div>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-success" style="background: #10b981; color: white; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="reviews-grid">
                <?php if (empty($reviews)): ?>
                    <p style="color: var(--text-secondary);">No reviews found</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div>
                                    <div class="review-product"><?= htmlspecialchars($review['product_name']) ?></div>
                                    <div class="review-user">
                                        By <?= htmlspecialchars($review['full_name'] ?? $review['username'] ?? 'Unknown') ?>
                                        on <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-<?= $review['is_approved'] ? 'approved' : 'pending' ?>">
                                    <?= $review['is_approved'] ? 'Approved' : 'Pending' ?>
                                </span>
                            </div>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="review-text"><?= htmlspecialchars($review['review_text']) ?></div>
                            <div class="review-actions">
                                <?php if (!$review['is_approved']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this review?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
