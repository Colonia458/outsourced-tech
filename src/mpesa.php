<?php
// src/mpesa.php – Daraja API helpers

require_once __DIR__ . '/config.php';

/**
 * Get OAuth access token
 */
function mpesa_get_token(): ?string {
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    $url = MPESA_ENV === 'live'
        ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Basic $credentials"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Initiate STK Push (Lipa na M-Pesa Online)
 */
function mpesa_stk_push(string $phone, float $amount, string $account_ref, string $order_id): array {
    $token = mpesa_get_token();
    if (!$token) return ['success' => false, 'message' => 'Failed to get access token'];

    $timestamp = date('YmdHis');
    $password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int) round($amount),
        'PartyA'            => $phone,                // 2547xxxxxxxx
        'PartyB'            => MPESA_SHORTCODE,
        'PhoneNumber'       => $phone,
        'CallBackURL'       => MPESA_CALLBACK_URL,
        'AccountReference'  => $account_ref,
        'TransactionDesc'   => "Order #$order_id"
    ];

    $url = MPESA_ENV === 'live'
        ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            'Content-Type: application/json'
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 200 && ($data['ResponseCode'] ?? '1') === '0') {
        return [
            'success'        => true,
            'checkout_id'    => $data['CheckoutRequestID'],
            'message'        => 'STK Push sent – check phone'
        ];
    }

    return [
        'success' => false,
        'message' => $data['errorMessage'] ?? $data['CustomerMessage'] ?? 'STK Push failed'
    ];
}