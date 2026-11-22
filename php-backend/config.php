<?php
// Database configuration
// Local Laragon/MySQL configuration.
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'korabu_db');



// JWT configuration
define('JWT_SECRET', getenv('KORABO_JWT_SECRET') ?: 'korabo-laragon-local-secret-change-me');
define('APP_ENV', 'development');

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// CORS Header
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// Create connection
try {
    date_default_timezone_set('Asia/Jakarta');
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->exec("SET time_zone = '+07:00'");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    die(json_encode(['message' => 'Connection failed: ' . $e->getMessage()]));
}

function response($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}
