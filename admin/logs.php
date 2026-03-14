<?php
// admin/logs.php - Log Viewer for Admin Panel
require_once __DIR__ . '/../src/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; z-index: 1000; }
        .sidebar h4 { color: #0d6efd; font-weight: 700; margin-bottom: 20px; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .log-container { 
            max-height: 600px; 
            overflow-y: auto; 
            font-family: 'Courier New', monospace; 
            font-size: 12px;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
        }
        .log-line { border-bottom: 1px solid #333; padding: 4px 0; }
        .log-error { color: #f48771; }
        .log-warning { color: #cca700; }
        .log-info { color: #75beff; }
        .log-success { color: #89d185; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4><i class="fas fa-microchip me-2"></i><?= APP_NAME ?? 'Admin' ?></h4>
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
        <a href="logs.php" class="active"><i class="fas fa-file-lines me-2"></i> Logs</a>
        <a href="delivery-map.php"><i class="fas fa-map-location-dot me-2"></i> Map</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <h2 class="mb-4"><i class="fas fa-file-alt"></i> System Logs</h2>
        
        <?php
        $log_type = $_GET['type'] ?? 'error';
        $lines = min((int)($_GET['lines'] ?? 50), 100);

        $log_dir = __DIR__ . '/../logs';
        
        // Create logs directory if it doesn't exist
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_files = [
            'error' => $log_dir . '/error.log',
            'activity' => $log_dir . '/activity.log',
            'emails' => $log_dir . '/emails.log',
            'security' => $log_dir . '/security.log',
            'low_stock' => $log_dir . '/low_stock.log',
            'stale_payments' => $log_dir . '/stale_payments.log'
        ];

        $log_file = $log_files[$log_type] ?? $log_files['error'];
        
        // Ensure log files exist
        foreach ($log_files as $type => $path) {
            if (!file_exists($path)) {
                @touch($path);
            }
        }
        ?>
        
        <!-- Log Type Tabs -->
        <ul class="nav nav-tabs mb-3">
            <?php foreach ($log_files as $type => $path): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $log_type === $type ? 'active' : ''; ?>" 
                       href="?type=<?php echo $type; ?>">
                        <?php echo ucfirst($type); ?>
                        <?php if (file_exists($path)): ?>
                            <span class="badge bg-secondary"><?php echo count(file($path)); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <!-- Controls -->
        <div class="mb-3">
            <label>Lines to show:</label>
            <select onchange="window.location.href='?type=<?php echo $log_type; ?>&lines='+this.value">
                <option value="20" <?php echo $lines === 20 ? 'selected' : ''; ?>>20</option>
                <option value="50" <?php echo $lines === 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $lines === 100 ? 'selected' : ''; ?>>100</option>
                <option value="200" <?php echo $lines === 200 ? 'selected' : ''; ?>>200</option>
            </select>
            <button class="btn btn-sm btn-primary ms-2" onclick="refreshLogs()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
        
        <!-- Log Content -->
        <div class="log-container" id="log-content">
            <?php
            if (file_exists($log_file) && filesize($log_file) > 0):
                $content = file($log_file);
                $recent = array_slice($content, -$lines);
                
                foreach (array_reverse($recent) as $line):
                    $css_class = 'log-info';
                    if (stripos($line, 'error') !== false) $css_class = 'log-error';
                    elseif (stripos($line, 'warning') !== false) $css_class = 'log-warning';
                    elseif (stripos($line, 'success') !== false) $css_class = 'log-success';
                    ?>
                    <div class="log-line <?php echo $css_class; ?>"><?php echo htmlspecialchars($line); ?></div>
                <?php 
                endforeach;
            else: 
                ?>
                <div class="text-light p-3">
                    <p class="text-muted">No log entries found.</p>
                    <p class="small">Log file: <?php echo htmlspecialchars($log_file); ?></p>
                    <p class="small">File exists: <?php echo file_exists($log_file) ? 'Yes' : 'No'; ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="main-content">
        <div class="mt-3">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
