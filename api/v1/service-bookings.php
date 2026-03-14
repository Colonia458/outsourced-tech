<?php
// api/v1/service-bookings.php - Handle service bookings

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../src/config.php';
require_once '../../src/database.php';
require_once '../../src/auth.php';

header('Content-Type: application/json');

// Get action from query string or JSON body
$action = $_GET['action'] ?? '';
$json_data = json_decode(file_get_contents('php://input'), true);
$data = $json_data ?? $_POST;

if (empty($action) && isset($data['action'])) {
    $action = $data['action'];
}

// List bookings (for logged in user)
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $user_id = $_SESSION['user_id'];
    $bookings = fetchAll(
        "SELECT sb.*, s.name as service_name, s.price as service_price 
         FROM service_bookings sb 
         LEFT JOIN services s ON sb.service_id = s.id 
         WHERE sb.user_id = ? 
         ORDER BY sb.booking_date DESC",
        [$user_id]
    );
    echo json_encode(['success' => true, 'bookings' => $bookings]);
    exit;
}

// Create booking
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Allow both logged in users and guests
    $user_id = $_SESSION['user_id'] ?? null;
    $service_id = (int)($data['service_id'] ?? 0);
    $booking_date = trim($data['booking_date'] ?? '');
    $booking_time = trim($data['booking_time'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $notes = trim($data['notes'] ?? '');
    
    if ($service_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Service ID is required']);
        exit;
    }
    
    if (empty($booking_date)) {
        echo json_encode(['success' => false, 'message' => 'Booking date is required']);
        exit;
    }
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number is required']);
        exit;
    }
    
    // Get service details
    $service = fetchOne("SELECT name, price FROM services WHERE id = ?", [$service_id]);
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit;
    }
    
    // For guests, we need a phone to identify them
    // Create booking
    try {
        $booking_id = db_insert('service_bookings', [
            'user_id' => $user_id,
            'service_id' => $service_id,
            'booking_date' => $booking_date,
            'booking_time' => $booking_time ?: '10:00:00',
            'phone' => $phone,
            'notes' => $notes,
            'status' => 'pending'
        ]);
    } catch (Exception $e) {
        error_log("Booking error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
    if ($booking_id) {
        echo json_encode([
            'success' => true, 
            'booking_id' => $booking_id,
            'message' => 'Service booked successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create booking']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
