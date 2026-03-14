<?php
require_once 'config.php';

class MpesaAPI {
    private $consumerKey;
    private $consumerSecret;
    private $shortcode;
    private $passkey;
    private $callbackUrl;
    private $environment;

    public function __construct() {
        $this->consumerKey    = getenv('MPESA_CONSUMER_KEY')    ?: '';
        $this->consumerSecret = getenv('MPESA_CONSUMER_SECRET') ?: '';
        $this->shortcode      = getenv('MPESA_SHORTCODE')       ?: '';
        $this->passkey        = getenv('MPESA_PASSKEY')         ?: '';
        $this->callbackUrl    = getenv('MPESA_CALLBACK_URL')    ?: '';
        $this->environment    = getenv('MPESA_ENVIRONMENT')     ?: 'sandbox';

        // Fail loudly if required credentials are missing
        if (empty($this->consumerKey) || empty($this->consumerSecret) || empty($this->shortcode) || empty($this->passkey)) {
            error_log('M-Pesa credentials not configured. Set MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, MPESA_SHORTCODE, MPESA_PASSKEY environment variables.');
        }
    }

    private function getAccessToken() {
        $url = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response);
        return $result->access_token ?? null;
    }

    public function initiateSTKPush($phone, $amount, $accountReference, $transactionDesc) {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }

        $url = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $phone = preg_replace('/^0/', '254', $phone);
        if (!preg_match('/^254\d{9}$/', $phone)) {
            return ['success' => false, 'message' => 'Invalid phone number format'];
        }

        $data = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
            return [
                'success' => true,
                'message' => 'STK Push sent successfully',
                'checkout_request_id' => $result['CheckoutRequestID'],
                'merchant_request_id' => $result['MerchantRequestID']
            ];
        }

        return [
            'success' => false,
            'message' => $result['errorMessage'] ?? 'STK Push failed'
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'initiate_payment') {
        $phone = $input['phone'] ?? '';
        $amount = $input['amount'] ?? 0;
        $orderId = $input['order_id'] ?? '';

        if (empty($phone) || $amount <= 0 || empty($orderId)) {
            sendResponse(['success' => false, 'message' => 'Invalid payment data'], 400);
        }

        $mpesa = new MpesaAPI();
        $result = $mpesa->initiateSTKPush(
            $phone,
            $amount,
            $orderId,
            'TechHub Pro - Order Payment'
        );

        if ($result['success']) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE payments
                    SET mpesa_transaction_id = ?
                    WHERE order_id = ?
                ");
                $stmt->execute([$result['checkout_request_id'], $orderId]);
            } catch (PDOException $e) {
            }
        }

        sendResponse($result);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = json_decode(file_get_contents('php://input'), true);

    $callbackData = $input['Body']['stkCallback'] ?? null;

    if ($callbackData) {
        $resultCode = $callbackData['ResultCode'] ?? 1;
        $checkoutRequestId = $callbackData['CheckoutRequestID'] ?? '';

        try {
            $stmt = $pdo->prepare("
                SELECT order_id FROM payments
                WHERE mpesa_transaction_id = ?
            ");
            $stmt->execute([$checkoutRequestId]);
            $payment = $stmt->fetch();

            if ($payment) {
                if ($resultCode == 0) {
                    $mpesaReceiptNumber = '';
                    if (isset($callbackData['CallbackMetadata']['Item'])) {
                        foreach ($callbackData['CallbackMetadata']['Item'] as $item) {
                            if ($item['Name'] === 'MpesaReceiptNumber') {
                                $mpesaReceiptNumber = $item['Value'];
                                break;
                            }
                        }
                    }

                    $stmt = $pdo->prepare("
                        UPDATE payments
                        SET status = 'completed', mpesa_receipt_number = ?
                        WHERE mpesa_transaction_id = ?
                    ");
                    $stmt->execute([$mpesaReceiptNumber, $checkoutRequestId]);

                    $stmt = $pdo->prepare("
                        UPDATE orders
                        SET payment_status = 'paid', status = 'processing'
                        WHERE id = ?
                    ");
                    $stmt->execute([$payment['order_id']]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE payments
                        SET status = 'failed'
                        WHERE mpesa_transaction_id = ?
                    ");
                    $stmt->execute([$checkoutRequestId]);

                    $stmt = $pdo->prepare("
                        UPDATE orders
                        SET payment_status = 'failed'
                        WHERE id = ?
                    ");
                    $stmt->execute([$payment['order_id']]);
                }
            }

            sendResponse(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        } catch (PDOException $e) {
            sendResponse(['ResultCode' => 1, 'ResultDesc' => 'Failed'], 500);
        }
    }
}
