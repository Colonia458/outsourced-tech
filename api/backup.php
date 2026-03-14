<?php
// api/backup.php - Automated Database Backup Script
// This file can be called via cron or manually
// Usage: php backup.php or via URL with key

require_once __DIR__ . '/../api/config.php';

header('Content-Type: application/json');

// Security check - use hash_equals for timing-safe comparison
$secret_key = $_GET['key'] ?? $_SERVER['HTTP_X_BACKUP_KEY'] ?? '';
$expected_key = getenv('BACKUP_SECRET_KEY') ?: 'your-backup-key-change-me';

if (empty($secret_key) || !hash_equals($expected_key, $secret_key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Configuration
$backup_dir = __DIR__ . '/../backups';
$supabase_url = getenv('SUPABASE_URL') ?: 'https://xajtokukmeeyfgditwns.supabase.co';
$supabase_key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';

// Create backup directory if not exists
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'success',
    'files' => [],
    'errors' => []
];

// Generate backup filename
$backup_filename = 'backup_' . date('Y-m-d_His') . '.json';
$backup_path = $backup_dir . '/' . $backup_filename;

// Since Supabase is managed, we create a logical backup
// In production, you would use Supabase's pg_dump or their backup API

try {
    // Get all tables data
    $tables = ['users', 'products', 'orders', 'order_items', 'services', 
               'categories', 'service_bookings', 'delivery_zones', 
               'loyalty_tiers', 'payments', 'coupons'];
    
    $backup_data = [
        'generated_at' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'database' => 'outsourced_tech',
        'tables' => []
    ];
    
    foreach ($tables as $table) {
        try {
            // For Supabase, we need to use the REST API
            $response = @file_get_contents(
                $supabase_url . '/rest/v1/' . $table . '?select=*',
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => "Authorization: Bearer $supabase_key\r\n" .
                                    "apikey: $supabase_key\r\n"
                    ]
                ])
            );
            
            if ($response !== false) {
                $data = json_decode($response, true);
                $backup_data['tables'][$table] = [
                    'row_count' => count($data),
                    'data' => $data
                ];
            } else {
                $backup_data['tables'][$table] = [
                    'row_count' => 0,
                    'data' => [],
                    'error' => 'Could not fetch table data'
                ];
            }
        } catch (Exception $e) {
            $backup_data['tables'][$table] = [
                'row_count' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Save backup file
    $json_content = json_encode($backup_data, JSON_PRETTY_PRINT);
    $bytes_written = file_put_contents($backup_path, $json_content);
    
    if ($bytes_written === false) {
        throw new Exception('Failed to write backup file');
    }
    
    $result['files'][] = [
        'filename' => $backup_filename,
        'size_bytes' => $bytes_written,
        'path' => $backup_path
    ];
    
    // Clean up old backups (keep last 7)
    cleanup_old_backups($backup_dir, 7);
    
    $result['message'] = 'Backup completed successfully';
    
} catch (Exception $e) {
    $result['status'] = 'error';
    $result['errors'][] = $e->getMessage();
}

// Also backup logs directory
try {
    $logs_backup = $backup_dir . '/logs_' . date('Y-m-d') . '.tar.gz';
    
    // Create tar archive of logs (if shell is available)
    if (function_exists('exec')) {
        @exec('cd ' . __DIR__ . '/.. && tar -czf ' . escapeshellarg($logs_backup) . ' logs/ 2>/dev/null');
        
        if (file_exists($logs_backup)) {
            $result['files'][] = [
                'filename' => basename($logs_backup),
                'size_bytes' => filesize($logs_backup),
                'path' => $logs_backup
            ];
        }
    }
} catch (Exception $e) {
    $result['errors'][] = 'Log backup failed: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

/**
 * Clean up old backup files
 */
function cleanup_old_backups($dir, $keep_count) {
    $files = glob($dir . '/backup_*.json');
    
    if (count($files) <= $keep_count) {
        return;
    }
    
    // Sort by modification time (oldest first)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    // Delete oldest files
    $to_delete = count($files) - $keep_count;
    for ($i = 0; $i < $to_delete; $i++) {
        @unlink($files[$i]);
    }
}
