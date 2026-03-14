<?php
// src/invoice.php - PDF Invoice Generation System
// Uses Dompdf for PDF generation

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate PDF invoice for an order
 * @param int $order_id Order ID
 * @return string|null Path to generated PDF, null on failure
 */
function generate_invoice($order_id) {
    global $pdo;
    
    try {
        // Get order details
        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email, u.full_name, u.phone, u.address as user_address
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            error_log("Order not found: $order_id");
            return null;
        }
        
        // Get order items
        $stmt = $pdo->prepare("
            SELECT oi.*, p.image as product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get service bookings if any
        $bookings = [];
        $stmt = $pdo->prepare("
            SELECT sb.*, s.name as service_name, s.price as service_price, s.duration_minutes
            FROM service_bookings sb
            LEFT JOIN services s ON sb.service_id = s.id
            WHERE sb.user_id = ? AND sb.status IN ('pending', 'confirmed')
            AND sb.booking_date >= CURDATE()
            ORDER BY sb.booking_date ASC
            LIMIT 3
        ");
        $stmt->execute([$order['user_id']]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate HTML
        $html = generate_invoice_html($order, $items, $bookings);
        
        // Setup Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Create invoices directory
        $invoice_dir = __DIR__ . '/../invoices';
        if (!is_dir($invoice_dir)) {
            mkdir($invoice_dir, 0755, true);
        }
        
        // Save PDF
        $filename = 'invoice_' . $order['order_number'] . '.pdf';
        $filepath = $invoice_dir . '/' . $filename;
        
        $pdf_content = $dompdf->output();
        file_put_contents($filepath, $pdf_content);
        
        // Update order with invoice path
        $stmt = $pdo->prepare("UPDATE orders SET invoice_path = ? WHERE id = ?");
        $stmt->execute(['invoices/' . $filename, $order_id]);
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("Invoice generation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate invoice HTML template
 */
function generate_invoice_html($order, $items, $bookings = []) {
    $company_name = getenv('COMPANY_NAME') ?: 'Outsourced Technologies';
    $company_address = getenv('COMPANY_ADDRESS') ?: 'Mlolongo, Kenya';
    $company_phone = getenv('COMPANY_PHONE') ?: '+254 700 000 000';
    $company_email = getenv('COMPANY_EMAIL') ?: 'info@outsourcedtechnologies.co.ke';
    
    $items_html = '';
    $subtotal = 0;
    
    foreach ($items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $subtotal += $item_total;
        
        $items_html .= '
        <tr>
            <td style="padding: 12px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['name']) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">' . $item['quantity'] . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">KSh ' . number_format($item['price']) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">KSh ' . number_format($item_total) . '</td>
        </tr>';
    }
    
    $delivery_fee = $order['delivery_fee'] ?? 0;
    $total = $order['total_amount'];
    
    // Booking section if any
    $booking_html = '';
    if (!empty($bookings)) {
        $booking_html = '
        <div style="margin-top: 30px;">
            <h3 style="color: #198754; border-bottom: 2px solid #198754; padding-bottom: 10px;">Service Bookings Included</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <tr style="background: #f8f9fa;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Service</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Date</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Time</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #dee2e6;">Price</th>
                </tr>';
        
        foreach ($bookings as $booking) {
            $booking_html .= '
                <tr>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">' . htmlspecialchars($booking['service_name']) . '</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">' . date('d M Y', strtotime($booking['booking_date'])) . '</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">' . date('h:i A', strtotime($booking['booking_time'])) . '</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: right;">KSh ' . number_format($booking['service_price']) . '</td>
                </tr>';
        }
        
        $booking_html .= '</table></div>';
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice - ' . htmlspecialchars($order['order_number']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.5; color: #333; margin: 0; padding: 20px; }
            .invoice-container { max-width: 800px; margin: 0 auto; background: #fff; }
            .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 3px solid #0d6efd; padding-bottom: 20px; }
            .company-info h1 { margin: 0; color: #0d6efd; font-size: 28px; }
            .company-info p { margin: 5px 0; color: #666; }
            .invoice-details { text-align: right; }
            .invoice-details h2 { margin: 0 0 10px 0; color: #333; }
            .invoice-details p { margin: 3px 0; }
            .invoice-number { font-weight: bold; color: #0d6efd; }
            .customer-info { margin-bottom: 30px; }
            .customer-info h3 { color: #198754; border-bottom: 2px solid #198754; padding-bottom: 10px; margin-bottom: 15px; }
            .customer-info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th { background: #0d6efd; color: white; padding: 12px; text-align: left; }
            .totals { margin-top: 20px; text-align: right; }
            .totals table { width: 300px; margin-left: auto; }
            .totals td { padding: 8px; }
            .totals .total-row { background: #198754; color: white; font-weight: bold; font-size: 18px; }
            .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #eee; padding-top: 20px; }
            .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
            .status-paid { background: #d4edda; color: #155724; }
            .status-pending { background: #fff3cd; color: #856404; }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="header">
                <div class="company-info">
                    <h1>' . $company_name . '</h1>
                    <p>' . $company_address . '</p>
                    <p>Phone: ' . $company_phone . '</p>
                    <p>Email: ' . $company_email . '</p>
                </div>
                <div class="invoice-details">
                    <h2>INVOICE</h2>
                    <p class="invoice-number">' . htmlspecialchars($order['order_number']) . '</p>
                    <p>Date: ' . date('d M Y', strtotime($order['created_at'])) . '</p>
                    <p>Status: <span class="status-badge ' . ($order['payment_status'] === 'paid' ? 'status-paid' : 'status-pending') . '">' . strtoupper($order['payment_status']) . '</span></p>
                </div>
            </div>
            
            <div class="customer-info">
                <h3>Bill To:</h3>
                <p><strong>' . htmlspecialchars($order['full_name'] ?? $order['username']) . '</strong></p>
                <p>' . htmlspecialchars($order['email']) . '</p>
                <p>' . htmlspecialchars($order['phone'] ?? '') . '</p>
                ' . (!empty($order['delivery_address']) ? '<p>' . htmlspecialchars($order['delivery_address']) . '</p>' : '') . '
            </div>
            
            <h3 style="color: #0d6efd; border-bottom: 2px solid #0d6efd; padding-bottom: 10px;">Order Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $items_html . '
                </tbody>
            </table>
            
            <div class="totals">
                <table>
                    <tr>
                        <td>Subtotal:</td>
                        <td style="text-align: right;">KSh ' . number_format($subtotal) . '</td>
                    </tr>
                    <tr>
                        <td>Delivery:</td>
                        <td style="text-align: right;">KSh ' . number_format($delivery_fee) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total:</td>
                        <td style="text-align: right;">KSh ' . number_format($total) . '</td>
                    </tr>
                </table>
            </div>
            
            ' . $booking_html . '
            
            <div class="footer">
                <p>Thank you for your business!</p>
                <p>' . $company_name . ' - ' . $company_address . '</p>
                <p>This is a computer-generated invoice. No signature required.</p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Send invoice via email
 * @param int $order_id Order ID
 * @return bool Success status
 */
function send_invoice_email($order_id) {
    global $pdo;
    
    try {
        // Get order and user
        $stmt = $pdo->prepare("
            SELECT o.*, u.email, u.full_name, u.username
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order || empty($order['email'])) {
            return false;
        }
        
        // Generate invoice if not exists
        $invoice_path = null;
        if (empty($order['invoice_path'])) {
            $invoice_path = generate_invoice($order_id);
        } else {
            $invoice_path = __DIR__ . '/../' . $order['invoice_path'];
        }
        
        if (!$invoice_path || !file_exists($invoice_path)) {
            return false;
        }
        
        // Send email with attachment
        $subject = 'Invoice for Order ' . $order['order_number'];
        
        $html = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0d6efd;">Thank you for your order!</h2>
                <p>Dear ' . htmlspecialchars($order['full_name'] ?? $order['username']) . ',</p>
                <p>Your order <strong>' . $order['order_number'] . '</strong> has been confirmed.</p>
                <p>Please find attached your invoice for this transaction.</p>
                <p><strong>Order Total:</strong> KSh ' . number_format($order['total_amount']) . '</p>
                <p><strong>Payment Status:</strong> ' . strtoupper($order['payment_status']) . '</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    If you have any questions, please contact us at info@outsourcedtechnologies.co.ke
                </p>
            </div>
        </body>
        </html>';
        
        // Use the email sending function with attachment
        require_once __DIR__ . '/email.php';
        
        $config = get_email_config();
        return send_email_smtp(
            $order['email'], 
            $subject, 
            $html, 
            true, 
            $config, 
            [['path' => $invoice_path, 'name' => basename($invoice_path)]]
        );
        
    } catch (Exception $e) {
        error_log("Send invoice email error: " . $e->getMessage());
        return false;
    }
}
