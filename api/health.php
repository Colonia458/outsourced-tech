<?php
// api/health.php - System Health Check Endpoint
// Works without requiring database connection

header('Content-Type: application/json');

$start_time = microtime(true);

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '1.0.0',
    'checks' => []
];

// 1. Check PHP Version
$health['checks']['php'] = [
    'status' => 'ok',
    'version' => phpversion()
];

// 2. Check Disk Space
try {
    $disk_free = disk_free_space('.');
    $disk_total = disk_total_space('.');
    $disk_percent = $disk_total ? round(($disk_free / $disk_total) * 100, 2) : 0;
    
    $health['checks']['disk'] = [
        'status' => $disk_percent > 10 ? 'ok' : 'warning',
        'free_space_mb' => round($disk_free / 1024 / 1024, 2),
        'total_space_mb' => round($disk_total / 1024 / 1024, 2),
        'percent_free' => $disk_percent
    ];
} catch (Exception $e) {
    $health['checks']['disk'] = [
        'status' => 'unknown',
        'message' => $e->getMessage()
    ];
}

// 3. Check PHP Extensions
$required_extensions = ['pdo', 'json', 'mbstring', 'curl'];
$extensions_status = [];
foreach ($required_extensions as $ext) {
    $extensions_status[$ext] = extension_loaded($ext);
}
$health['checks']['extensions'] = [
    'status' => in_array(false, $extensions_status) ? 'warning' : 'ok',
    'extensions' => $extensions_status
];

// 4. Check Log Directory
$log_dir = __DIR__ . '/../logs';
$log_status = [];
if (is_dir($log_dir)) {
    $log_files = glob($log_dir . '/*.log');
    $log_status['file_count'] = count($log_files);
    $log_status['directory_exists'] = true;
} else {
    $log_status['directory_exists'] = false;
}
$health['checks']['logs'] = [
    'status' => $log_status['directory_exists'] ? 'ok' : 'warning',
    'details' => $log_status
];

// 5. Try Database Connection (optional)
$health['checks']['database'] = [
    'status' => 'unavailable',
    'message' => 'Database connection not configured or failed'
];

try {
    // Try to include config - if it fails, we'll catch the exception
    if (file_exists(__DIR__ . '/config.php')) {
        // Don't require - just try to check if connection would work
        $config_content = file_get_contents(__DIR__ . '/config.php');
        if (strpos($config_content, 'YOUR-PASSWORD') !== false || 
            strpos($config_content, '[YOUR-PASSWORD]') !== false) {
            $health['checks']['database']['message'] = 'Database password not configured';
        } else {
            // Try actual connection
            try {
                require_once __DIR__ . '/config.php';
                if (isset($pdo) && $pdo) {
                    $test = $pdo->query("SELECT 1")->fetch();
                    $health['checks']['database'] = [
                        'status' => 'ok',
                        'message' => 'Database connected successfully'
                    ];
                }
            } catch (Exception $e) {
                $health['checks']['database']['message'] = 'Database connection failed: ' . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    // Ignore database errors
}

$health['response_time_ms'] = round((microtime(true) - $start_time) * 1000, 2);

// Set HTTP status code
$http_status = 200;
if ($health['checks']['disk']['status'] === 'critical') {
    $http_status = 503;
}

http_response_code($http_status);
echo json_encode($health, JSON_PRETTY_PRINT);
