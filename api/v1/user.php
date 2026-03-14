<?php
// api/v1/user.php - User authentication API

header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config.php';

session_start();

// Check login status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'check') {
    echo json_encode([
        'logged_in' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null
    ]);
    exit;
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit;
    }
    
    require_once __DIR__ . '/../src/database.php';
    
    $user = fetchOne(
        "SELECT id, username, password_hash FROM users WHERE email = ?",
        [$email]
    );
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    }
    exit;
}

// Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($email) || empty($password) || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }
    
    require_once __DIR__ . '/../src/database.php';
    
    // Check if email exists
    $existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $id = db_insert('users', [
        'email' => $email,
        'username' => explode('@', $email)[0],
        'password_hash' => $hash,
        'full_name' => $full_name,
        'phone' => $phone,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($id) {
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = explode('@', $email)[0];
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
    exit;
}

// Logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Default - return not logged in
echo json_encode(['logged_in' => false]);
