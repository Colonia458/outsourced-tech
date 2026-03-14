<?php
// public/profile.php - User Dashboard

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/loyalty.php';

require_login();

$user_id = $_SESSION['user_id'];

$user = fetchOne(
    "SELECT id, username, email, full_name, phone, loyalty_points, created_at 
     FROM users WHERE id = ?",
    [$user_id]
);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Handle profile update
$update_message = '';
$update_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    if (!empty($full_name)) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $user_id]);
        $user = fetchOne("SELECT id, username, email, full_name, phone, loyalty_points, created_at FROM users WHERE id = ?", [$user_id]);
        $update_message = 'Profile updated successfully!';
        $update_type = 'success';
    }
}

// Get user's loyalty tier
$user_tier = get_user_tier($user_id);
$tier_badges = [
    'Bronze' => ['icon' => '🥉', 'color' => '#cd7f32', 'bg' => '#fff4e6'],
    'Silver' => ['icon' => '🥈', 'color' => '#c0c0c0', 'bg' => '#f8f9fa'],
    'Gold' => ['icon' => '🥇', 'color' => '#ffd700', 'bg' => '#fffbf0'],
    'Platinum' => ['icon' => '💎', 'color' => '#e5e4e2', 'bg' => '#f0f8ff'],
];
$current_tier = $user_tier ?: ['name' => 'Bronze', 'badge_color' => '#cd7f32', 'badge_icon' => '🥉', 'discount_percent' => 0, 'free_delivery' => 0];
$badge_info = $tier_badges[$current_tier['name']] ?? $tier_badges['Bronze'];

// Get stats
$orders_count = fetchOne("SELECT COUNT(*) as cnt FROM orders WHERE user_id = ?", [$user_id]);
$bookings_count = fetchOne("SELECT COUNT(*) as cnt FROM service_bookings WHERE user_id = ?", [$user_id]);
$active_bookings = fetchOne("SELECT COUNT(*) as cnt FROM service_bookings WHERE user_id = ? AND booking_date >= CURDATE()", [$user_id]);
$reviews_count = fetchOne("SELECT COUNT(*) as cnt FROM product_reviews WHERE user_id = ?", [$user_id]);

// Get recent orders
$recent_orders = fetchAll("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$user_id]);

// Get upcoming bookings
$upcoming_bookings = fetchAll(
    "SELECT sb.*, s.name as service_name, s.price as service_price 
     FROM service_bookings sb
     LEFT JOIN services s ON sb.service_id = s.id
     WHERE sb.user_id = ? AND sb.booking_date >= CURDATE()
     ORDER BY sb.booking_date ASC, sb.booking_time ASC
     LIMIT 3",
    [$user_id]
);

$page_title = 'My Dashboard - ' . APP_NAME;
require_once __DIR__ . '/../templates/header.php';
?>

<style>
.dashboard-container {
    min-height: calc(100vh - 200px);
    padding: 30px 0;
}

.dashboard-sidebar {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    padding: 24px;
    position: sticky;
    top: 90px;
}

.user-mini-profile {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.user-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: white;
    margin: 0 auto 12px;
}

.user-name {
    font-weight: 700;
    font-size: 18px;
    margin-bottom: 4px;
}

.user-email {
    color: #6c757d;
    font-size: 13px;
    margin-bottom: 8px;
}

.member-since {
    font-size: 12px;
    color: #adb5bd;
}

.dashboard-nav .nav-link {
    padding: 12px 16px;
    border-radius: 10px;
    color: #495057;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 4px;
    transition: all 0.2s;
}

.dashboard-nav .nav-link:hover {
    background: #f8f9fa;
    color: #0d6efd;
}

.dashboard-nav .nav-link.active {
    background: #e7f1ff;
    color: #0d6efd;
}

.dashboard-nav .nav-link i {
    width: 20px;
    text-align: center;
}

.dashboard-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    padding: 28px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}

.summary-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.summary-value {
    font-size: 28px;
    font-weight: 800;
    line-height: 1;
}

.summary-label {
    font-size: 13px;
    color: #6c757d;
    margin-top: 4px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.order-row {
    display: flex;
    align-items: center;
    padding: 16px;
    border: 1px solid #eee;
    border-radius: 12px;
    margin-bottom: 12px;
    transition: box-shadow 0.2s;
}

.order-row:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.order-thumbnail {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    background: #f8f9fa;
}

.booking-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.booking-date {
    font-size: 24px;
    font-weight: 800;
    color: #0d6efd;
}

.booking-month {
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-icon {
    font-size: 48px;
    color: #dee2e6;
    margin-bottom: 16px;
}

.empty-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
}

.empty-text {
    color: #6c757d;
    font-size: 14px;
}

@media (max-width: 991px) {
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .dashboard-sidebar {
        position: static;
        margin-bottom: 24px;
    }
}

@media (max-width: 576px) {
    .summary-cards {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-container">
    <div class="container">
        <!-- Alert Messages -->
        <?php if ($update_message): ?>
        <div class="alert alert-<?= $update_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($update_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="dashboard-sidebar">
                    <div class="user-mini-profile">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-name"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
                        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                        <div class="member-since">Member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
                    </div>
                    
                    <nav class="dashboard-nav">
                        <a href="profile.php" class="nav-link active">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a href="orders.php" class="nav-link">
                            <i class="fas fa-shopping-bag"></i> My Orders
                        </a>
                        <a href="wishlist.php" class="nav-link">
                            <i class="fas fa-heart"></i> Wishlist
                        </a>
                        <a href="services.php" class="nav-link">
                            <i class="fas fa-calendar-check"></i> My Bookings
                        </a>
                        <hr>
                        <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="dashboard-content">
                    <!-- Summary Cards -->
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="summary-icon" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white;">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div>
                                <div class="summary-value"><?= $orders_count['cnt'] ?? 0 ?></div>
                                <div class="summary-label">Total Orders</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white;">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div>
                                <div class="summary-value"><?= $active_bookings['cnt'] ?? 0 ?></div>
                                <div class="summary-label">Active Bookings</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon" style="background: linear-gradient(135deg, #ffc107 0%, #ffaa00 100%); color: white;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <div class="summary-value"><?= number_format($user['loyalty_points']) ?></div>
                                <div class="summary-label">Reward Points</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Recent Orders -->
                        <div class="col-md-7">
                            <div class="section-title">
                                <span><i class="fas fa-shopping-bag text-primary me-2"></i>Recent Orders</span>
                                <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            
                            <?php if (empty($recent_orders)): ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-shopping-bag"></i></div>
                                <div class="empty-title">No orders yet</div>
                                <div class="empty-text">Start shopping to see your orders here</div>
                                <a href="products.php" class="btn btn-primary btn-sm mt-3">Start Shopping</a>
                            </div>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                <div class="order-row">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong>#<?= htmlspecialchars($order['order_number']) ?></strong>
                                                <span class="badge bg-<?= ($order['status'] == 'delivered' ? 'success' : ($order['status'] == 'cancelled' ? 'danger' : 'warning')) ?> ms-2">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </div>
                                            <span class="text-muted small"><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-success">KSh <?= number_format($order['total_amount']) ?></span>
                                            <?php if ($order['delivery_type'] === 'home_delivery' && !in_array($order['status'], ['delivered', 'cancelled'])): ?>
                                            <a href="track-order.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">Track</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Upcoming Bookings -->
                        <div class="col-md-5">
                            <div class="section-title">
                                <span><i class="fas fa-calendar-check text-success me-2"></i>Upcoming Services</span>
                                <a href="services.php" class="btn btn-sm btn-outline-success">View All</a>
                            </div>
                            
                            <?php if (empty($upcoming_bookings)): ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-calendar"></i></div>
                                <div class="empty-title">No upcoming bookings</div>
                                <div class="empty-text">Book a service to get started</div>
                                <a href="services.php" class="btn btn-success btn-sm mt-3">Book Service</a>
                            </div>
                            <?php else: ?>
                                <?php foreach ($upcoming_bookings as $booking): ?>
                                <div class="booking-card mb-3">
                                    <div class="booking-date"><?= date('d', strtotime($booking['booking_date'])) ?></div>
                                    <div class="booking-month"><?= date('M', strtotime($booking['booking_date'])) ?></div>
                                    <div class="fw-bold mt-2"><?= htmlspecialchars($booking['service_name']) ?></div>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($booking['booking_time'])) ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Account Info Quick View -->
                    <div class="mt-4 pt-4 border-top">
                        <div class="section-title">
                            <span><i class="fas fa-user-circle text-info me-2"></i>Account Information</span>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-edit me-1"></i> Edit
                            </button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block">Full Name</small>
                                    <strong><?= htmlspecialchars($user['full_name'] ?: 'Not set') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block">Phone</small>
                                    <strong><?= htmlspecialchars($user['phone'] ?: 'Not set') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block">Loyalty Tier</small>
                                    <strong><span class="badge" style="background: <?= $badge_info['bg'] ?>; color: <?= $badge_info['color'] ?>;">
                                        <?= $badge_info['icon'] ?> <?= $current_tier['name'] ?>
                                    </span></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="profile.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <small class="text-muted">Email cannot be changed</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
