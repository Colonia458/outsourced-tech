<?php
/**
 * PDF Invoice Generation
 * Outsourced Technologies E-Commerce Platform
 * 
 * Requires: composer require tecnickcom/tcpdf
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TCPDF;

/**
 * Generate PDF invoice for an order
 * 
 * @param array $order Order details
 * @param array $user User details
 * @param array $items Order items
 * @param array $business_info Business information
 * @return string PDF content
 */
function generate_pdf_invoice(array $order, array $user, array $items, array $business_info = []): string {
    // Default business info
    $default_info = [
        'name' => 'Outsourced Technologies',
        'address' => 'Mlolongo, Kenya',
        'phone' => '+254 700 000 000',
        'email' => 'info@outsourcedtechnologies.co.ke',
        'website' => 'www.outsourcedtechnologies.co.ke'
    ];
    
    $info = array_merge($default_info, $business_info);
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator($info['name']);
    $pdf->SetAuthor($info['name']);
    $pdf->SetTitle('Invoice ' . $order['order_number']);
    $pdf->SetSubject('Order Invoice');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(true, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Colors
    $primary_color = [13, 110, 253]; // Bootstrap primary
    
    // Header - Company Logo/Name
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->Cell(0, 20, $info['name'], 0, true, 'C', true);
    
    $pdf->Ln(5);
    
    // Company details
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, $info['address'], 0, true, 'C');
    $pdf->Cell(0, 5, 'Phone: ' . $info['phone'] . ' | Email: ' . $info['email'], 0, true, 'C');
    $pdf->Cell(0, 5, $info['website'], 0, true, 'C');
    
    $pdf->Ln(10);
    
    // Invoice title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'INVOICE', 0, true, 'C');
    
    $pdf->Ln(10);
    
    // Invoice details table
    $pdf->SetFont('helvetica', '', 10);
    
    // Left side - Bill To
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(90, 6, 'BILL TO:', 0, 0);
    $pdf->Cell(90, 6, 'INVOICE DETAILS:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(90, 5, htmlspecialchars($user['full_name'] ?? $user['username']), 0, 0);
    $pdf->Cell(90, 5, 'Invoice #: ' . $order['order_number'], 0, 1);
    
    $pdf->Cell(90, 5, htmlspecialchars($user['email']), 0, 0);
    $pdf->Cell(90, 5, 'Date: ' . date('F d, Y', strtotime($order['created_at'])), 0, 1);
    
    if (!empty($user['phone'])) {
        $pdf->Cell(90, 5, htmlspecialchars($user['phone']), 0, 0);
    }
    $pdf->Cell(90, 5, 'Status: ' . strtoupper($order['payment_status']), 0, 1);
    
    $pdf->Ln(10);
    
    // Items table header
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(80, 8, 'ITEM', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'QTY', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'UNIT PRICE', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'TOTAL', 1, 1, 'C', true);
    
    // Items table body
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    
    $fill = false;
    foreach ($items as $item) {
        $pdf->Cell(80, 8, htmlspecialchars($item['product_name']), 1, 0, 'L', $fill);
        $pdf->Cell(25, 8, (string)$item['quantity'], 1, 0, 'C', $fill);
        $pdf->Cell(40, 8, 'KSh ' . number_format($item['price']), 1, 0, 'R', $fill);
        $pdf->Cell(40, 8, 'KSh ' . number_format($item['price'] * $item['quantity']), 1, 1, 'R', $fill);
        $fill = !$fill;
    }
    
    // Totals
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(105, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(40, 6, 'KSh ' . number_format($order['subtotal']), 0, 1, 'R');
    
    if ($order['delivery_fee'] > 0) {
        $pdf->Cell(105, 6, 'Delivery:', 0, 0, 'R');
        $pdf->Cell(40, 6, 'KSh ' . number_format($order['delivery_fee']), 0, 1, 'R');
    }
    
    if ($order['discount'] > 0) {
        $pdf->Cell(105, 6, 'Discount:', 0, 0, 'R');
        $pdf->Cell(40, 6, '- KSh ' . number_format($order['discount']), 0, 1, 'R');
    }
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(105, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(40, 8, 'KSh ' . number_format($order['total_amount']), 0, 1, 'R');
    
    $pdf->Ln(15);
    
    // Delivery info
    if ($order['delivery_type'] !== 'pickup') {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'DELIVERY ADDRESS:', 0, 1);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 5, htmlspecialchars($order['delivery_address']), 0, 'L');
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Store Pickup', 0, 1);
    }
    
    $pdf->Ln(15);
    
    // Payment info
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'PAYMENT INFORMATION:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Payment Method: M-Pesa', 0, 1);
    $pdf->Cell(0, 5, 'Payment Status: ' . strtoupper($order['payment_status']), 0, 1);
    
    if (!empty($order['transaction_id'])) {
        $pdf->Cell(0, 5, 'Transaction ID: ' . htmlspecialchars($order['transaction_id']), 0, 1);
    }
    
    $pdf->Ln(20);
    
    // Footer
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5, 'Thank you for your business!', 0, true, 'C');
    $pdf->Cell(0, 5, 'For inquiries, contact us at ' . $info['email'], 0, true, 'C');
    
    // Output PDF
    return $pdf->Output('Invoice_' . $order['order_number'] . '.pdf', 'S');
}

/**
 * Generate and save PDF invoice
 * 
 * @param int $order_id Order ID
 * @param string $save_path Path to save PDF
 * @return bool|string False on failure, path on success
 */
function generate_and_save_invoice(int $order_id, string $save_path): bool|string {
    global $pdo;
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        return false;
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    // Get user details
    $user = [
        'full_name' => $order['full_name'],
        'email' => $order['email'],
        'phone' => $order['phone']
    ];
    
    // Generate PDF
    $pdf_content = generate_pdf_invoice($order, $user, $items);
    
    // Save to file
    if (file_put_contents($save_path, $pdf_content) !== false) {
        return $save_path;
    }
    
    return false;
}

/**
 * Download PDF invoice
 * 
 * @param int $order_id Order ID
 */
function download_invoice(int $order_id): void {
    global $pdo;
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id'] ?? 0]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die('Order not found');
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    $user = [
        'full_name' => $order['full_name'],
        'email' => $order['email'],
        'phone' => $order['phone']
    ];
    
    // Generate and output PDF
    $pdf_content = generate_pdf_invoice($order, $user, $items);
    
    // Send headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Invoice_' . $order['order_number'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf_content));
    
    echo $pdf_content;
    exit;
}
