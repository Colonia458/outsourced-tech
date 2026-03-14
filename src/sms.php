<?php
// src/sms.php - SMS Notification Service using Africa's Talking

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Get SMS configuration
 */
function get_sms_config() {
    return [
        'api_key' => getenv('AFRICASTALKING_API_KEY') ?: '',
        'username' => getenv('AFRICASTALKING_USERNAME') ?: 'sandbox',
        'from' => getenv('SMS_SENDER_ID') ?: 'OUTSOURCED',
        'enabled' => !empty(getenv('AFRICASTALKING_API_KEY')),
    ];
}

/**
 * Send SMS message
 * 
 * @param string $phoneNumber Recipient phone number (format: +254...)
 * @param string $message Message content
 * @param string|null $eventType Event type for logging
 * @param int|null $userId User ID for tracking
 * @param int|null $orderId Order ID for tracking
 * @return array Result with success status and details
 */
function send_sms($phoneNumber, $message, $eventType = null, $userId = null, $orderId = null) {
    $config = get_sms_config();
    
    // Format phone number
    $phoneNumber = format_phone_number($phoneNumber);
    
    // Log the SMS attempt
    $logId = log_sms($phoneNumber, $message, $eventType, 'pending', null, $userId, $orderId);
    
    // If SMS is disabled, simulate success
    if (!$config['enabled']) {
        update_sms_log($logId, 'sent', 'SMS disabled - simulated success');
        return [
            'success' => true,
            'message' => 'SMS simulated (disabled)',
            'sms_id' => $logId,
        ];
    }
    
    // Send via Africa's Talking
    $url = 'https://api.africastalking.com/version1/messaging';
    
    $data = [
        'username' => $config['username'],
        'to' => $phoneNumber,
        'message' => $message,
        'from' => $config['from'],
    ];
    
    $headers = [
        'ApiKey: ' . $config['api_key'],
        'Content-Type: application/x-www-form-urlencoded',
    ];
    
    $response = make_http_request('POST', $url, $data, $headers);
    
    if ($response['success'] && isset($response['data']['SMSMessageData']['Messages'])) {
        $messages = $response['data']['SMSMessageData']['Messages'];
        if (!empty($messages) && isset($messages[0]['status'])) {
            $status = ($messages[0]['status'] === 'Success') ? 'sent' : 'failed';
            $updateData = [
                'status' => $status,
                'gateway_response' => json_encode($messages[0]),
            ];
            update_sms_log($logId, $status, json_encode($messages[0]));
            
            return [
                'success' => ($status === 'sent'),
                'message' => $messages[0]['status'] ?? 'Unknown status',
                'sms_id' => $logId,
                'response' => $messages[0],
            ];
        }
    }
    
    // Log failure
    update_sms_log($logId, 'failed', $response['error'] ?? 'Unknown error');
    
    return [
        'success' => false,
        'message' => $response['error'] ?? 'Failed to send SMS',
        'sms_id' => $logId,
    ];
}

/**
 * Format phone number to international format
 */
function format_phone_number($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 0, replace with 254
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }
    
    // If doesn't start with 254, add it
    if (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }
    
    return '+' . $phone;
}

/**
 * Make HTTP request
 */
function make_http_request($method, $url, $data = [], $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
    ];
}

/**
 * Log SMS to database
 */
function log_sms($phone, $message, $eventType, $status, $gatewayResponse = null, $userId = null, $orderId = null) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        INSERT INTO sms_log (phone_number, message, event_type, status, gateway_response, user_id, order_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('sssssii', $phone, $message, $eventType, $status, $gatewayResponse, $userId, $orderId);
    $stmt->execute();
    
    return $db->insert_id;
}

/**
 * Update SMS log status
 */
function update_sms_log($logId, $status, $gatewayResponse) {
    $db = Database::getInstance()->getConnection();
    
    $sentAt = ($status === 'sent') ? 'CURRENT_TIMESTAMP' : 'NULL';
    
    $sql = "UPDATE sms_log SET status = ?, gateway_response = ?";
    if ($status === 'sent') {
        $sql .= ", sent_at = CURRENT_TIMESTAMP";
    }
    $sql .= " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssi', $status, $gatewayResponse, $logId);
    $stmt->execute();
}

/**
 * Get SMS template by event type
 */
function get_sms_template($eventType) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT * FROM sms_templates 
        WHERE event_type = ? AND is_active = TRUE
    ");
    $stmt->bind_param('s', $eventType);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Process SMS template with variables
 */
function process_sms_template($template, $variables) {
    $message = $template;
    
    foreach ($variables as $key => $value) {
        $message = str_replace('{' . $key . '}', $value, $message);
    }
    
    return $message;
}

/**
 * Send order confirmation SMS
 */
function send_order_confirmation_sms($orderId, $phoneNumber, $total) {
    $template = get_sms_template('order_confirmed');
    
    if (!$template) {
        return ['success' => false, 'message' => 'Template not found'];
    }
    
    $trackingUrl = get_base_url() . '/public/track-order.php?order_id=' . $orderId;
    
    $message = process_sms_template($template['message_template'], [
        'order_id' => $orderId,
        'total' => number_format($total, 2),
        'tracking_url' => $trackingUrl,
    ]);
    
    return send_sms($phoneNumber, $message, 'order_confirmed', null, $orderId);
}

/**
 * Send order shipped SMS
 */
function send_order_shipped_sms($orderId, $phoneNumber, $deliveryDays = 3) {
    $template = get_sms_template('order_shipped');
    
    if (!$template) {
        return ['success' => false, 'message' => 'Template not found'];
    }
    
    $trackingUrl = get_base_url() . '/public/track-order.php?order_id=' . $orderId;
    
    $message = process_sms_template($template['message_template'], [
        'order_id' => $orderId,
        'delivery_days' => $deliveryDays,
        'tracking_url' => $trackingUrl,
    ]);
    
    return send_sms($phoneNumber, $message, 'order_shipped', null, $orderId);
}

/**
 * Send order delivered SMS
 */
function send_order_delivered_sms($orderId, $phoneNumber) {
    $template = get_sms_template('order_delivered');
    
    if (!$template) {
        return ['success' => false, 'message' => 'Template not found'];
    }
    
    $reviewUrl = get_base_url() . '/public/product.php?id=';
    
    $message = process_sms_template($template['message_template'], [
        'order_id' => $orderId,
        'review_url' => $reviewUrl,
    ]);
    
    return send_sms($phoneNumber, $message, 'order_delivered', null, $orderId);
}

/**
 * Send payment received SMS
 */
function send_payment_received_sms($orderId, $phoneNumber, $amount) {
    $template = get_sms_template('payment_received');
    
    if (!$template) {
        return ['success' => false, 'message' => 'Template not found'];
    }
    
    $message = process_sms_template($template['message_template'], [
        'order_id' => $orderId,
        'amount' => number_format($amount, 2),
    ]);
    
    return send_sms($phoneNumber, $message, 'payment_received', null, $orderId);
}

/**
 * Send promotional SMS
 */
function send_promotional_sms($phoneNumber, $message) {
    $template = get_sms_template('promotion');
    
    if (!$template) {
        // Send generic message
        return send_sms($phoneNumber, $message, 'promotion');
    }
    
    $processedMessage = process_sms_template($template['message_template'], [
        'message' => $message,
    ]);
    
    return send_sms($phoneNumber, $processedMessage, 'promotion');
}

/**
 * Get SMS subscription for user
 */
function get_sms_subscription($userId) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM sms_subscriptions WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Update SMS subscription
 */
function update_sms_subscription($userId, $data) {
    $db = Database::getInstance()->getConnection();
    
    $fields = [];
    $types = '';
    $values = [];
    
    foreach ($data as $key => $value) {
        $fields[] = "$key = ?";
        $types .= 's';
        $values[] = $value;
    }
    
    $values[] = $userId;
    $types .= 'i';
    
    $sql = "UPDATE sms_subscriptions SET " . implode(', ', $fields) . " WHERE user_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$values);
    
    return $stmt->execute();
}

/**
 * Verify phone number with OTP
 */
function verify_phone_otp($userId, $code) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT * FROM sms_subscriptions 
        WHERE user_id = ? AND verification_code = ? 
        AND verification_expires > NOW()
    ");
    $stmt->bind_param('is', $userId, $code);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $db->prepare("
            UPDATE sms_subscriptions 
            SET is_verified = TRUE, verification_code = NULL, verification_expires = NULL
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        return ['success' => true];
    }
    
    return ['success' => false, 'message' => 'Invalid or expired verification code'];
}

/**
 * Get base URL
 */
function get_base_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . '/outsourced';
}
