<?php
// admin/system-status.php - System Monitoring Dashboard
require_once __DIR__ . '/../src/config.php';

$page_title = 'System Status';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; z-index: 1000; }
        .sidebar h4 { color: #0d6efd; font-weight: 700; margin-bottom: 20px; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .status-card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status-ok { border-left: 4px solid #28a745; }
        .status-warning { border-left: 4px solid #ffc107; }
        .status-error { border-left: 4px solid #dc3545; }
        .metric-value { font-size: 2rem; font-weight: bold; }
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
        <a href="system-status.php" class="active"><i class="fas fa-server me-2"></i> System</a>
        <a href="logs.php"><i class="fas fa-file-lines me-2"></i> Logs</a>
        <a href="delivery-map.php"><i class="fas fa-map-location-dot me-2"></i> Map</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <div class="main-content">
        <h2 class="mb-4"><i class="fas fa-server"></i> System Status</h2>
        
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card status-card <?php echo isset($db) && $db ? 'status-ok' : 'status-warning'; ?>">
                    <div class="card-body">
                        <h6 class="text-muted">Database</h6>
                        <?php
                        $db_status = 'Not Connected';
                        $db_icon = 'fa-exclamation-triangle';
                        $db_class = 'text-warning';
                        if (isset($db) && $db) {
                            try {
                                $db->query("SELECT 1");
                                $db_status = 'Connected';
                                $db_icon = 'fa-check';
                                $db_class = 'text-success';
                            } catch (Exception $e) {
                                $db_status = 'Error: ' . $e->getMessage();
                            }
                        }
                        ?>
                        <div class="metric-value <?php echo $db_class; ?>">
                            <i class="fas <?php echo $db_icon; ?>"></i> <?php echo $db_status; ?>
                        </div>
                        <small class="text-muted"><?php echo DB_DRIVER . '://' . DB_HOST . '/' . DB_NAME; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card status-ok">
                    <div class="card-body">
                        <h6 class="text-muted">PHP Version</h6>
                        <div class="metric-value"><?php echo phpversion(); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card status-ok">
                    <div class="card-body">
                        <h6 class="text-muted">Disk Usage</h6>
                        <?php
                        $disk_free = @disk_free_space('.');
                        $disk_total = @disk_total_space('.');
                        $disk_percent = $disk_total ? round(($disk_free / $disk_total) * 100, 1) : 'N/A';
                        ?>
                        <div class="metric-value"><?php echo $disk_percent; ?>% free</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card status-ok">
                    <div class="card-body">
                        <h6 class="text-muted">Server Time</h6>
                        <div class="metric-value" style="font-size: 1.2rem;"><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- PHP Extensions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-puzzle-piece"></i> PHP Extensions</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $required_extensions = ['pdo', 'pdo_pgsql', 'json', 'mbstring', 'curl', 'openssl', 'gd'];
                        foreach ($required_extensions as $ext): 
                            $loaded = extension_loaded($ext);
                        ?>
                            <span class="badge <?php echo $loaded ? 'bg-success' : 'bg-danger'; ?> me-2 mb-2">
                                <?php echo $ext; ?>: <?php echo $loaded ? 'OK' : 'Missing'; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Log Files Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Log Files</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $log_dir = __DIR__ . '/../logs';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0755, true);
                        }
                        
                        $log_files = [
                            'error' => $log_dir . '/error.log',
                            'activity' => $log_dir . '/activity.log',
                            'emails' => $log_dir . '/emails.log',
                            'security' => $log_dir . '/security.log'
                        ];
                        
                        foreach ($log_files as $name => $path):
                            $exists = file_exists($path);
                            $size = $exists ? filesize($path) : 0;
                        ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span><?php echo ucfirst($name); ?> log</span>
                                <span class="<?php echo $exists && $size > 0 ? 'text-success' : 'text-muted'; ?>">
                                    <?php if ($exists): ?>
                                        <i class="fas fa-check"></i> <?php echo number_format($size); ?> bytes
                                    <?php else: ?>
                                        <i class="fas fa-times"></i> Not created yet
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="logs.php" class="btn btn-primary me-2">
                            <i class="fas fa-file-alt"></i> View Logs
                        </a>
                        <a href="../api/health.php" target="_blank" class="btn btn-info me-2">
                            <i class="fas fa-heartbeat"></i> Health Check
                        </a>
                        <a href="test.php" class="btn btn-secondary">
                            <i class="fas fa-info-circle"></i> PHP Info
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Database Info -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header <?php echo isset($db) && $db ? 'bg-success' : 'bg-warning'; ?>">
                        <h5 class="mb-0"><i class="fas fa-database"></i> Database Configuration</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db) && $db): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>Database is connected!</strong>
                                <p>Driver: <?php echo DB_DRIVER; ?><br>
                                Host: <?php echo DB_HOST; ?><br>
                                Database: <?php echo DB_NAME; ?></p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Database is not connected.</strong>
                                <p>To fix this, set your database credentials in <code>.env</code> file or <code>src/config.php</code>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
