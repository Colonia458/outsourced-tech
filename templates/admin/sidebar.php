<style>
.admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #212529;
    color: white;
    padding: 20px 0;
    z-index: 1000;
    overflow-y: auto;
}

.admin-sidebar .logo {
    padding: 0 20px 20px;
    border-bottom: 1px solid #343a40;
    margin-bottom: 20px;
}

.admin-sidebar .logo h4 {
    margin: 0;
    font-weight: 700;
    color: #0d6efd;
}

.admin-sidebar .nav-item {
    margin: 0;
}

.admin-sidebar .nav-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #adb5bd;
    text-decoration: none;
    transition: all 0.2s;
}

.admin-sidebar .nav-link:hover,
.admin-sidebar .nav-link.active {
    background: #343a40;
    color: white;
    border-left: 3px solid #0d6efd;
}

.admin-sidebar .nav-link i {
    width: 25px;
    margin-right: 10px;
}

.admin-sidebar .logout-link {
    color: #dc3545;
}

.admin-sidebar .logout-link:hover {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}
</style>

<div class="admin-sidebar">
    <div class="logo">
        <h4><i class="fas fa-microchip me-2"></i><?= APP_NAME ?? 'Admin' ?></h4>
    </div>
    
    <nav>
        <a href="../index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard') !== false || strpos($_SERVER['PHP_SELF'], 'index.php') !== false ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="../products/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="../services/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'services') !== false ? 'active' : '' ?>">
            <i class="fas fa-tools"></i> Services
        </a>
        <a href="../orders/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i> Orders
        </a>
        <a href="../users/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <a href="../service-bookings/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'service-bookings') !== false ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Bookings
        </a>
        <a href="../chatbot/conversations.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'chatbot') !== false ? 'active' : '' ?>">
            <i class="fas fa-robot"></i> Chatbot
        </a>
        <a href="../delivery-zones/manage.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'delivery-zones') !== false ? 'active' : '' ?>">
            <i class="fas fa-truck"></i> Delivery
        </a>
        <a href="../coupons/manage.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'coupons') !== false ? 'active' : '' ?>">
            <i class="fas fa-ticket"></i> Coupons
        </a>
        <a href="manage.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'loyalty') !== false ? 'active' : '' ?>">
            <i class="fas fa-award"></i> Loyalty
        </a>
        <a href="../reviews/manage.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'reviews') !== false ? 'active' : '' ?>">
            <i class="fas fa-star"></i> Reviews
        </a>
        <a href="../system-status.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'system-status') !== false ? 'active' : '' ?>">
            <i class="fas fa-server"></i> System
        </a>
        <a href="../logs.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'logs.php') !== false ? 'active' : '' ?>">
            <i class="fas fa-file-lines"></i> Logs
        </a>
        <a href="../delivery-map.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'delivery-map') !== false ? 'active' : '' ?>">
            <i class="fas fa-map-location-dot"></i> Map
        </a>
        <a href="../logout.php" class="nav-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>
