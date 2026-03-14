<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/ratelimit.php';

// Apply strict rate limiting to all auth endpoints (5 attempts per 5 minutes per IP)
rate_limit_auth();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'signup':
        handleSignup($pdo, $input);
        break;
    case 'login':
        handleLogin($pdo, $input);
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleSignup($pdo, $input) {
    $fullName = $input['full_name'] ?? '';
    $email = $input['email'] ?? '';
    $phone = $input['phone'] ?? '';
    $password = $input['password'] ?? '';
    $address = $input['address'] ?? '';

    if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
        sendResponse(['success' => false, 'message' => 'All fields are required'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    if ($stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Email or phone already exists'], 400);
    }

    $passwordHash = hashPassword($password);

    $stmt = $pdo->prepare("
        INSERT INTO users (email, phone, full_name, password_hash, address, loyalty_points, loyalty_badge)
        VALUES (?, ?, ?, ?, ?, 0, 'bronze')
        RETURNING id, email, phone, full_name, loyalty_points, loyalty_badge, address, created_at
    ");
    $stmt->execute([$email, $phone, $fullName, $passwordHash, $address]);
    $user = $stmt->fetch();

    sendResponse([
        'success' => true,
        'message' => 'Account created successfully',
        'user' => $user
    ]);
}

function handleLogin($pdo, $input) {
    $identifier = $input['identifier'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        sendResponse(['success' => false, 'message' => 'All fields are required'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT id, email, phone, full_name, password_hash, loyalty_points, loyalty_badge, address
        FROM users
        WHERE email = ? OR phone = ?
    ");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        sendResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
    }

    unset($user['password_hash']);

    sendResponse([
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ]);
}
