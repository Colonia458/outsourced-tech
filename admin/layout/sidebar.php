<?php
// Sidebar Component
// Navigation structure for the admin panel

// Get current page info
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Define navigation items
$nav_items = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fa-solid fa-chart-line',
        'url' => 'index.php',
        'category' => 'main'
    ],
    'orders' => [
        'title' => 'Orders',
        'icon' => 'fa-solid fa-shopping-cart',
        'url' => 'orders/list.php',
        'category' => 'management',
        'badge' => 12
    ],
    'products' => [
        'title' => 'Products',
        'icon' => 'fa-solid fa-box',
        'url' => 'products/list.php',
        'category' => 'management'
    ],
    'services' => [
        'title' => 'Services',
        'icon' => 'fa-solid fa-tools',
        'url' => 'services/list.php',
        'category' => 'management'
    ],
    'users' => [
        'title' => 'Users',
        'icon' => 'fa-solid fa-users',
        'url' => 'users/list.php',
        'category' => 'management'
    ],
    'coupons' => [
        'title' => 'Coupons',
        'icon' => 'fa-solid fa-ticket',
        'url' => 'coupons/manage.php',
        'category' => 'management'
    ],
    'reviews' => [
        'title' => 'Reviews',
        'icon' => 'fa-solid fa-star',
        'url' => 'reviews/manage.php',
        'category' => 'management'
    ],
    'delivery_zones' => [
        'title' => 'Delivery Zones',
        'icon' => 'fa-solid fa-truck',
        'url' => 'delivery-zones/manage.php',
        'category' => 'management'
    ],
    'loyalty_tiers' => [
        'title' => 'Loyalty Tiers',
        'icon' => 'fa-solid fa-award',
        'url' => 'loyalty-tiers/manage.php',
        'category' => 'management'
    ],
    'service_bookings' => [
        'title' => 'Service Bookings',
        'icon' => 'fa-solid fa-calendar-check',
        'url' => 'service-bookings/list.php',
        'category' => 'management'
    ],
    'chatbot' => [
        'title' => 'Chatbot',
        'icon' => 'fa-solid fa-robot',
        'url' => 'chatbot/conversations.php',
        'category' => 'management'
    ],
    'system_status' => [
        'title' => 'System Status',
        'icon' => 'fa-solid fa-server',
        'url' => 'system-status.php',
        'category' => 'settings'
    ],
    'logs' => [
        'title' => 'Activity Logs',
        'icon' => 'fa-solid fa-file-lines',
        'url' => 'logs.php',
        'category' => 'settings'
    ],
    'delivery_map' => [
        'title' => 'Delivery Map',
        'icon' => 'fa-solid fa-map-location-dot',
        'url' => 'delivery-map.php',
        'category' => 'settings'
    ]
];

// User info (would typically come from session)
$user_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
$user_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Administrator';
$user_initials = strtoupper(substr($user_name, 0, 2));
?>
<!-- Sidebar -->
<aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <span class="sidebar-logo-text">Outsourced</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            
            <a href="index.php" class="nav-item <?php echo ($current_page == 'index' || $current_page == 'dashboard') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <!-- Management Section -->
        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            
            <a href="orders/list.php" class="nav-item <?php echo ($current_dir == 'orders') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-shopping-cart"></i></span>
                <span class="nav-text">Orders</span>
                <?php if (isset($nav_items['orders']['badge'])): ?>
                <span class="nav-badge"><?php echo $nav_items['orders']['badge']; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="products/list.php" class="nav-item <?php echo ($current_dir == 'products') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-box"></i></span>
                <span class="nav-text">Products</span>
            </a>
            
            <a href="services/list.php" class="nav-item <?php echo ($current_dir == 'services') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-tools"></i></span>
                <span class="nav-text">Services</span>
            </a>
            
            <a href="users/list.php" class="nav-item <?php echo ($current_dir == 'users') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                <span class="nav-text">Users</span>
            </a>
            
            <a href="coupons/manage.php" class="nav-item <?php echo ($current_dir == 'coupons') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-ticket"></i></span>
                <span class="nav-text">Coupons</span>
            </a>
            
            <a href="reviews/manage.php" class="nav-item <?php echo ($current_dir == 'reviews') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
                <span class="nav-text">Reviews</span>
            </a>
            
            <a href="delivery-zones/manage.php" class="nav-item <?php echo ($current_dir == 'delivery-zones') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-truck"></i></span>
                <span class="nav-text">Delivery Zones</span>
            </a>
            
            <a href="loyalty-tiers/manage.php" class="nav-item <?php echo ($current_dir == 'loyalty-tiers') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                <span class="nav-text">Loyalty Tiers</span>
            </a>
            
            <a href="service-bookings/list.php" class="nav-item <?php echo ($current_dir == 'service-bookings') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span>
                <span class="nav-text">Service Bookings</span>
            </a>
            
            <a href="chatbot/conversations.php" class="nav-item <?php echo ($current_dir == 'chatbot') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-robot"></i></span>
                <span class="nav-text">Chatbot</span>
            </a>
        </div>
        
        <!-- Settings Section -->
        <div class="nav-section">
            <div class="nav-section-title">Settings</div>
            
            <a href="system-status.php" class="nav-item <?php echo ($current_page == 'system-status') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-server"></i></span>
                <span class="nav-text">System Status</span>
            </a>
            
            <a href="logs.php" class="nav-item <?php echo ($current_page == 'logs') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-file-lines"></i></span>
                <span class="nav-text">Activity Logs</span>
            </a>
            
            <a href="delivery-map.php" class="nav-item <?php echo ($current_page == 'delivery-map') ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fa-solid fa-map-location-dot"></i></span>
                <span class="nav-text">Delivery Map</span>
            </a>
        </div>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
            </div>
        </div>
    </div>
</aside>

<script>
    // Sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const navbar = document.getElementById('navbar');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        // Check for saved state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('sidebar-collapsed');
            if (navbar) navbar.classList.add('sidebar-collapsed');
            toggleBtn.querySelector('i').classList.add('fa-chevron-right');
            toggleBtn.querySelector('i').classList.remove('fa-chevron-left');
        }
        
        // Toggle button click
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
                }
                
                if (navbar) {
                    navbar.classList.toggle('sidebar-collapsed', isCollapsed);
                }
                
                // Toggle icon
                const icon = toggleBtn.querySelector('i');
                if (isCollapsed) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
                
                // Save state
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            });
        }
    });
</script>
