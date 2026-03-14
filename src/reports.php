<?php
// src/reports.php - Automated Admin Reports System

/**
 * Generate daily sales report
 * @return array Report data
 */
function generate_daily_sales_report() {
    global $pdo;
    
    $report = [
        'date' => date('Y-m-d'),
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => [],
        'orders' => [],
        'top_products' => [],
        'top_services' => [],
        'payments' => []
    ];
    
    // Get summary stats
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
            SUM(delivery_fee) as total_delivery_fees
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $report['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get orders for the day
    $stmt = $pdo->query("
        SELECT o.*, u.username, u.full_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) = CURDATE()
        ORDER BY o.created_at DESC
    ");
    $report['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top selling products
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.sku, SUM(oi.quantity) as units_sold, 
               SUM(oi.subtotal) as revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) = CURDATE() AND o.payment_status = 'paid'
        GROUP BY p.id
        ORDER BY units_sold DESC
        LIMIT 10
    ");
    $report['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment methods breakdown
    $stmt = $pdo->query("
        SELECT payment_method, COUNT(*) as count, SUM(amount) as total
        FROM orders 
        WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'
        GROUP BY payment_method
    ");
    $report['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $report;
}

/**
 * Generate weekly summary report
 * @return array Report data
 */
function generate_weekly_summary_report() {
    global $pdo;
    
    $report = [
        'week_start' => date('Y-m-d', strtotime('monday this week')),
        'week_end' => date('Y-m-d', strtotime('sunday this week')),
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => [],
        'daily_breakdown' => [],
        'top_products' => [],
        'top_services' => [],
        'new_customers' => 0,
        'bookings' => []
    ];
    
    // Get weekly summary
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
            AVG(CASE WHEN payment_status = 'paid' THEN total_amount ELSE NULL END) as average_order_value,
            COUNT(DISTINCT user_id) as unique_customers
        FROM orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $report['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get daily breakdown
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, 
               COUNT(*) as orders,
               SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as revenue
        FROM orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $report['daily_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top products for the week
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.sku, SUM(oi.quantity) as units_sold, 
               SUM(oi.subtotal) as revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND o.payment_status = 'paid'
        GROUP BY p.id
        ORDER BY units_sold DESC
        LIMIT 10
    ");
    $report['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get new customers
    $stmt = $pdo->query("
        SELECT COUNT(*) as count FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $report['new_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get bookings summary
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            s.name as service_name,
            COUNT(sb.id) as bookings
        FROM service_bookings sb
        JOIN services s ON sb.service_id = s.id
        WHERE sb.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY s.id
    ");
    $report['bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $report;
}

/**
 * Generate low stock report
 * @return array Report data
 */
function generate_low_stock_report() {
    global $pdo;
    
    $report = [
        'generated_at' => date('Y-m-d H:i:s'),
        'critical' => [],
        'low' => [],
        'reorder_suggestions' => []
    ];
    
    // Get critical stock (0 or very low)
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.visible = 1 AND p.stock <= 3
        ORDER BY p.stock ASC
    ");
    $report['critical'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.visible = 1 AND p.stock > 3 AND p.stock <= p.reorder_level
        ORDER BY p.stock ASC
    ");
    $report['low'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate reorder suggestions (based on sales velocity)
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.stock, p.reorder_level,
               COALESCE(SUM(oi.quantity) / 7, 0) as daily_sales_velocity,
               CEIL(COALESCE(SUM(oi.quantity) / 7, 0) * 30) as suggested_monthly_reorder
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        WHERE p.visible = 1
        GROUP BY p.id
        HAVING p.stock <= p.reorder_level OR p.stock <= 10
        ORDER BY daily_sales_velocity DESC
        LIMIT 20
    ");
    $report['reorder_suggestions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $report;
}

/**
 * Send report via email
 * @param string $report_type Type of report
 * @param array $report_data Report data
 * @param array $recipients Email addresses
 * @return bool Success
 */
function send_report_email($report_type, $report_data, $recipients) {
    require_once __DIR__ . '/email.php';
    
    $subject = '';
    $html = '';
    
    switch ($report_type) {
        case 'daily_sales':
            $subject = 'Daily Sales Report - ' . date('d M Y');
            $html = generate_daily_report_html($report_data);
            break;
        case 'weekly_summary':
            $subject = 'Weekly Summary Report - Week of ' . date('d M Y', strtotime('monday this week'));
            $html = generate_weekly_report_html($report_data);
            break;
        case 'low_stock':
            $subject = '⚠️ Low Stock Alert Report - ' . date('d M Y');
            $html = generate_low_stock_report_html($report_data);
            break;
        default:
            $subject = 'Report - ' . date('d M Y');
            $html = '<pre>' . json_encode($report_data, JSON_PRETTY_PRINT) . '</pre>';
    }
    
    $success = true;
    foreach ($recipients as $email) {
        if (!send_email($email, $subject, $html)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Generate HTML for daily sales report
 */
function generate_daily_report_html($data) {
    $summary = $data['summary'];
    $currency = 'KES ';
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 800px; margin: 0 auto;'>
            <h1 style='color: #0d6efd;'>Daily Sales Report</h1>
            <p><strong>Date:</strong> {$data['date']}</p>
            <p><strong>Generated:</strong> {$data['generated_at']}</p>
            
            <h2 style='color: #198754;'>Summary</h2>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background: #f8f9fa;'>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Total Orders</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$summary['total_orders']}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Paid Orders</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$summary['paid_orders']}</td>
                </tr>
                <tr style='background: #d4edda;'>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Total Revenue</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>{$currency}" . number_format($summary['total_revenue']) . "</strong></td>
                </tr>
            </table>
            
            <h2>Top Selling Products</h2>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background: #0d6efd; color: white;'>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Product</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Units</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: right;'>Revenue</th>
                </tr>
    ";
    
    foreach ($data['top_products'] as $product) {
        echo "
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$product['name']}</td>
                    <td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>{$product['units_sold']}</td>
                    <td style='padding: 10px; border: 1px solid #ddd; text-align: right;'>{$currency}" . number_format($product['revenue']) . "</td>
                </tr>
        ";
    }
    
    return "
            </table>
            <p style='margin-top: 20px;'>
                <a href='https://outsourcedtechnologies.co.ke/admin/dashboard.php' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>View Full Dashboard</a>
            </p>
        </div>
    </body>
    </html>";
}

/**
 * Generate HTML for weekly report
 */
function generate_weekly_report_html($data) {
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 800px; margin: 0 auto;'>
            <h1 style='color: #0d6efd;'>Weekly Summary Report</h1>
            <p><strong>Week:</strong> {$data['week_start']} to {$data['week_end']}</p>
            <p><strong>Generated:</strong> {$data['generated_at']}</p>
            
            <h2 style='color: #198754;'>Week at a Glance</h2>
            <ul>
                <li><strong>Total Orders:</strong> {$data['summary']['total_orders']}</li>
                <li><strong>Completed Orders:</strong> {$data['summary']['completed_orders']}</li>
                <li><strong>Total Revenue:</strong> KES " . number_format($data['summary']['total_revenue']) . "</li>
                <li><strong>Average Order Value:</strong> KES " . number_format($data['summary']['average_order_value']) . "</li>
                <li><strong>Unique Customers:</strong> {$data['summary']['unique_customers']}</li>
                <li><strong>New Customers:</strong> {$data['new_customers']}</li>
            </ul>
            
            <p style='margin-top: 20px;'>
                <a href='https://outsourcedtechnologies.co.ke/admin/dashboard.php' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>View Full Dashboard</a>
            </p>
        </div>
    </body>
    </html>";
}

/**
 * Generate HTML for low stock report
 */
function generate_low_stock_report_html($data) {
    $total_issues = count($data['critical']) + count($data['low']);
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 800px; margin: 0 auto;'>
            <h1 style='color: #dc3545;'>⚠️ Low Stock Alert Report</h1>
            <p><strong>Generated:</strong> {$data['generated_at']}</p>
            <p><strong>Total Items Needing Attention:</strong> {$total_issues}</p>
            
            <h2 style='color: #dc3545;'>Critical (Out of Stock)</h2>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background: #dc3545; color: white;'>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Product</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Stock</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Category</th>
                </tr>
    ";
    
    foreach ($data['critical'] as $product) {
        echo "
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$product['name']}</td>
                    <td style='padding: 10px; border: 1px solid #ddd; text-align: center; color: #dc3545; font-weight: bold;'>{$product['stock']}</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$product['category_name']}</td>
                </tr>
        ";
    }
    
    echo "</table>";
    
    if (!empty($data['low'])) {
        echo "
            <h2 style='color: #ffc107;'>Low Stock</h2>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background: #ffc107; color: black;'>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Product</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Stock</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Category</th>
                </tr>
        ";
        
        foreach ($data['low'] as $product) {
            echo "
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$product['name']}</td>
                    <td style='padding: 10px; border: 1px solid #ddd; text-align: center; color: #ffc107; font-weight: bold;'>{$product['stock']}</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$product['category_name']}</td>
                </tr>
            ";
        }
        
        echo "</table>";
    }
    
    return "
            <p style='margin-top: 20px;'>
                <a href='https://outsourcedtechnologies.co.ke/admin/products/list.php?filter=low_stock' style='padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>Update Stock Now</a>
            </p>
        </div>
    </body>
    </html>";
}

/**
 * Process scheduled reports (called by cron)
 */
function process_scheduled_reports() {
    global $pdo;
    
    $today = date('N'); // 1-7 (Monday-Sunday)
    $day = date('j'); // 1-31
    
    // Get active reports to run
    $stmt = $pdo->query("SELECT * FROM report_schedules WHERE is_active = TRUE");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $reports_sent = 0;
    
    foreach ($schedules as $schedule) {
        $should_run = false;
        
        // Check if should run today
        switch ($schedule['frequency']) {
            case 'daily':
                $should_run = true;
                break;
            case 'weekly':
                $should_run = ($today == $schedule['day_of_week']);
                break;
            case 'monthly':
                $should_run = ($day == $schedule['day_of_month']);
                break;
        }
        
        if (!$should_run) continue;
        
        // Check if already ran today
        if ($schedule['last_run'] && date('Y-m-d', strtotime($schedule['last_run'])) === date('Y-m-d')) {
            continue;
        }
        
        // Generate report
        $report_data = [];
        switch ($schedule['report_type']) {
            case 'daily_sales':
                $report_data = generate_daily_sales_report();
                break;
            case 'weekly_summary':
                $report_data = generate_weekly_summary_report();
                break;
            case 'low_stock':
                $report_data = generate_low_stock_report();
                break;
        }
        
        // Send report
        $recipients = json_decode($schedule['recipients'], true);
        $success = send_report_email($schedule['report_type'], $report_data, $recipients);
        
        // Update last run time
        $stmt = $pdo->prepare("UPDATE report_schedules SET last_run = NOW() WHERE id = ?");
        $stmt->execute([$schedule['id']]);
        
        // Log
        $stmt = $pdo->prepare("
            INSERT INTO report_logs (report_type, status, recipients, record_count)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $schedule['report_type'], 
            $success ? 'success' : 'failed',
            $schedule['recipients'],
            count($report_data['orders'] ?? [])
        ]);
        
        if ($success) $reports_sent++;
    }
    
    return $reports_sent;
}
