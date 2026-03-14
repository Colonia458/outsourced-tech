<?php
// src/booking.php - Service Booking Availability & Calendar System

/**
 * Get available time slots for a service on a given date
 * @param int $service_id Service ID
 * @param string $date Date in Y-m-d format
 * @return array Available time slots
 */
function get_available_slots($service_id, $date) {
    global $pdo;
    
    // Define business hours (9 AM to 5 PM)
    $start_hour = 9;
    $end_hour = 17;
    $slot_duration = 60; // minutes
    
    // Get service duration
    $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($service && $service['duration_minutes']) {
        $slot_duration = (int)$service['duration_minutes'];
    }
    
    // Get bookings for this service on this date
    $stmt = $pdo->prepare("
        SELECT booking_time 
        FROM service_bookings 
        WHERE service_id = ? 
        AND booking_date = ? 
        AND status NOT IN ('cancelled')
    ");
    $stmt->execute([$service_id, $date]);
    $booked_times = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Generate all possible slots
    $available_slots = [];
    for ($hour = $start_hour; $hour < $end_hour; $hour++) {
        $time = sprintf('%02d:00:00', $hour);
        
        // Check if slot is booked
        if (!in_array($time, $booked_times)) {
            $available_slots[] = [
                'time' => $time,
                'display' => date('h:i A', strtotime($time))
            ];
        }
        
        // Add half-hour slots
        if ($slot_duration >= 30) {
            $time_half = sprintf('%02d:30:00', $hour);
            if (!in_array($time_half, $booked_times)) {
                $available_slots[] = [
                    'time' => $time_half,
                    'display' => date('h:i A', strtotime($time_half))
                ];
            }
        }
    }
    
    return $available_slots;
}

/**
 * Check if a time slot is available
 * @param int $service_id Service ID
 * @param string $date Date in Y-m-d format
 * @param string $time Time in H:i:s format
 * @return bool True if available
 */
function is_slot_available($service_id, $date, $time) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM service_bookings 
        WHERE service_id = ? 
        AND booking_date = ? 
        AND booking_time = ?
        AND status NOT IN ('cancelled')
    ");
    $stmt->execute([$service_id, $date, $time]);
    $count = $stmt->fetchColumn();
    
    return $count == 0;
}

/**
 * Block a time slot (prevent double booking)
 * @param int $booking_id Booking ID
 * @return bool Success
 */
function block_calendar_slot($booking_id) {
    global $pdo;
    
    // Mark the booking as confirmed to block the slot
    $stmt = $pdo->prepare("
        UPDATE service_bookings 
        SET status = 'confirmed' 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$booking_id]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Generate Google Calendar link for a booking
 * @param array $booking Booking data
 * @param array $service Service data
 * @return string Google Calendar URL
 */
function generate_google_calendar_link($booking, $service) {
    $title = urlencode($service['name'] . ' - Outsourced Technologies');
    $details = urlencode("Your service booking at Outsourced Technologies\n\nService: " . $service['name'] . "\nNotes: " . ($booking['notes'] ?? 'N/A'));
    
    $date = str_replace('-', '', $booking['booking_date']);
    $time = str_replace(':', '', $booking['booking_time']);
    $start_datetime = $date . 'T' . $time;
    
    // End time = start time + service duration (default 1 hour)
    $duration = $service['duration_minutes'] ?? 60;
    $end_timestamp = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']) + ($duration * 60);
    $end_datetime = date('YmdTHis', $end_timestamp);
    
    $location = urlencode(getenv('COMPANY_ADDRESS') ?: 'Mlolongo, Kenya');
    
    return "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$start_datetime}/{$end_datetime}&details={$details}&location={$location}";
}

/**
 * Generate iCal event for a booking
 * @param array $booking Booking data
 * @param array $service Service data
 * @return string iCal content
 */
function generate_ical_event($booking, $service) {
    $uid = uniqid() . '@outsourcedtechnologies.co.ke';
    $dtstamp = date('YmdTHis');
    
    $date = str_replace('-', '', $booking['booking_date']);
    $time = str_replace(':', '', $booking['booking_time']);
    $dtstart = $date . 'T' . $time;
    
    // End time
    $duration = $service['duration_minutes'] ?? 60;
    $end_timestamp = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']) + ($duration * 60);
    $dtend = date('YmdTHis', $end_timestamp);
    
    $summary = $service['name'] . ' - Outsourced Technologies';
    $description = "Service: " . $service['name'] . "\nNotes: " . ($booking['notes'] ?? 'N/A');
    $location = getenv('COMPANY_ADDRESS') ?: 'Mlolongo, Kenya';
    
    return <<<ICAL
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Outsourced Technologies//Booking//EN
BEGIN:VEVENT
UID:{$uid}
DTSTAMP:{$dtstamp}
DTSTART:{$dtstart}
DTEND:{$dtend}
SUMMARY:{$summary}
DESCRIPTION:{$description}
LOCATION:{$location}
END:VEVENT
END:VCALENDAR
ICAL;
}

/**
 * Get booking with service details
 * @param int $booking_id Booking ID
 * @return array|false Booking data
 */
function get_booking_details($booking_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT sb.*, s.name as service_name, s.price as service_price, 
               s.duration_minutes, s.description as service_description,
               u.username, u.email, u.full_name, u.phone
        FROM service_bookings sb
        LEFT JOIN services s ON sb.service_id = s.id
        LEFT JOIN users u ON sb.user_id = u.id
        WHERE sb.id = ?
    ");
    $stmt->execute([$booking_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Handle booking cancellation with refund
 * @param int $booking_id Booking ID
 * @return array Result with success status and message
 */
function cancel_booking($booking_id, $reason = '') {
    global $pdo;
    
    try {
        // Get booking details
        $booking = get_booking_details($booking_id);
        
        if (!$booking) {
            return ['success' => false, 'message' => 'Booking not found'];
        }
        
        if ($booking['status'] === 'cancelled') {
            return ['success' => false, 'message' => 'Booking already cancelled'];
        }
        
        if ($booking['status'] === 'completed') {
            return ['success' => false, 'message' => 'Cannot cancel completed booking'];
        }
        
        // Update booking status
        $stmt = $pdo->prepare("
            UPDATE service_bookings 
            SET status = 'cancelled', 
                admin_notes = ?,
                cancelled_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $booking_id]);
        
        // If there was a payment, initiate refund via M-Pesa
        // This would integrate with the M-Pesa B2C API
        // For now, we log the refund request
        if ($booking['payment_status'] ?? '' === 'paid') {
            // Log refund request - would integrate with M-Pesa B2C
            error_log("Refund requested for booking $booking_id - Amount: " . ($booking['service_price'] ?? 0));
        }
        
        // Send cancellation email
        send_booking_cancellation_email($booking);
        
        return ['success' => true, 'message' => 'Booking cancelled successfully'];
        
    } catch (Exception $e) {
        error_log("Cancel booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error cancelling booking'];
    }
}

/**
 * Send booking cancellation email
 */
function send_booking_cancellation_email($booking) {
    require_once __DIR__ . '/email.php';
    
    $subject = 'Booking Cancelled - ' . $booking['service_name'];
    $html = "
    <html>
    <body>
        <h2>Booking Cancellation Confirmation</h2>
        <p>Dear " . htmlspecialchars($booking['full_name'] ?? $booking['username'] ?? 'Customer') . ",</p>
        <p>Your service booking has been cancelled as requested.</p>
        <h3>Cancelled Booking Details:</h3>
        <ul>
            <li><strong>Service:</strong> " . htmlspecialchars($booking['service_name']) . "</li>
            <li><strong>Date:</strong> " . date('d M Y', strtotime($booking['booking_date'])) . "</li>
            <li><strong>Time:</strong> " . date('h:i A', strtotime($booking['booking_time'])) . "</li>
        </ul>
        <p>If you did not request this cancellation, please contact us immediately.</p>
        <p>To book again, visit: <a href='https://outsourcedtechnologies.co.ke/services.php'>Our Services</a></p>
    </body>
    </html>";
    
    if (!empty($booking['email'])) {
        send_email($booking['email'], $subject, $html);
    }
}

/**
 * Send booking confirmation with calendar links
 * @param int $booking_id Booking ID
 * @return bool Success
 */
function send_booking_confirmation_with_calendar($booking_id) {
    $booking = get_booking_details($booking_id);
    
    if (!$booking) {
        return false;
    }
    
    require_once __DIR__ . '/email.php';
    
    // Generate calendar links
    $google_cal_link = generate_google_calendar_link($booking, $booking);
    $ical_content = generate_ical_event($booking, $booking);
    
    $subject = 'Booking Confirmed - ' . $booking['service_name'];
    $html = "
    <html>
    <body>
        <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
            <div style='background: #198754; color: white; padding: 20px; text-align: center;'>
                <h2>Booking Confirmed!</h2>
            </div>
            <div style='padding: 20px; background: #f9f9f9;'>
                <p>Dear " . htmlspecialchars($booking['full_name'] ?? $booking['username'] ?? 'Customer') . ",</p>
                <p>Your service booking has been confirmed! Here are your booking details:</p>
                
                <div style='background: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0;'>{$booking['service_name']}</h3>
                    <p><strong>Date:</strong> " . date('l, d M Y', strtotime($booking['booking_date'])) . "</p>
                    <p><strong>Time:</strong> " . date('h:i A', strtotime($booking['booking_time'])) . "</p>
                    <p><strong>Duration:</strong> " . ($booking['duration_minutes'] ?? 60) . " minutes</p>
                    " . (!empty($booking['notes']) ? "<p><strong>Notes:</strong> " . htmlspecialchars($booking['notes']) . "</p>" : "") . "
                </div>
                
                <p><strong>Add to your calendar:</strong></p>
                <p>
                    <a href='{$google_cal_link}' style='display: inline-block; padding: 10px 20px; background: #4285f4; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Google Calendar</a>
                </p>
                
                <p style='margin-top: 20px;'>
                    <a href='https://outsourcedtechnologies.co.ke/orders.php' style='color: #0d6efd;'>View your bookings</a>
                </p>
            </div>
            <div style='padding: 20px; text-align: center; color: #666; font-size: 12px;'>
                <p>Outsourced Technologies - Mlolongo, Kenya</p>
            </div>
        </div>
    </body>
    </html>";
    
    $email_sent = false;
    if (!empty($booking['email'])) {
        $email_sent = send_email($booking['email'], $subject, $html);
    }
    
    // Also send SMS confirmation if phone available
    if (!empty($booking['phone'])) {
        require_once __DIR__ . '/sms.php';
        $sms_message = "Your {$booking['service_name']} booking for " . date('d M', strtotime($booking['booking_date'])) . " at " . date('h:i A', strtotime($booking['booking_time'])) . " is confirmed! - Outsourced Technologies";
        send_sms($booking['phone'], $sms_message, 'booking_confirmed');
    }
    
    return $email_sent;
}
