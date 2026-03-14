<?php
// Restrict CORS to the configured frontend origin (never use wildcard in production)
$allowed_origin = getenv('ALLOWED_ORIGIN') ?: 'http://localhost';
$request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($request_origin === $allowed_origin) {
    header('Access-Control-Allow-Origin: ' . $allowed_origin);
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$supabaseUrl = getenv('SUPABASE_URL') ?: '';
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
$dbUrl       = getenv('SUPABASE_DB_URL') ?: '';

if (empty($supabaseUrl) || empty($supabaseKey) || empty($dbUrl)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error']);
    exit();
}

try {
    $dbParams = parse_url($dbUrl);
    $host = $dbParams['host'];
    $port = $dbParams['port'] ?? 5432;
    $dbname = ltrim($dbParams['path'], '/');
    $user = $dbParams['user'];
    $password = $dbParams['pass'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function generateOrderNumber() {
    return 'ORD-' . strtoupper(uniqid());
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
