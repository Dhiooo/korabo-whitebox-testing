<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'korabu_db');

// JWT configuration
define('JWT_SECRET', getenv('KORABO_JWT_SECRET') ?: 'korabo-laragon-local-secret-change-me');
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'development');
}

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');

class HttpResponseException extends RuntimeException
{
    public array $payload;
    public int $statusCode;

    public function __construct(array $payload, int $statusCode)
    {
        parent::__construct($payload['message'] ?? 'HTTP Response', $statusCode);
        $this->payload = $payload;
        $this->statusCode = $statusCode;
    }
}

if (APP_ENV !== 'testing') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        exit;
    }

    try {
        date_default_timezone_set('Asia/Jakarta');
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->exec("SET time_zone = '+07:00'");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die(json_encode(['message' => 'Connection failed: ' . $e->getMessage()]));
    }
}

function getJsonInput(): array
{
    if (isset($GLOBALS['__MOCK_JSON_INPUT__']) && is_array($GLOBALS['__MOCK_JSON_INPUT__'])) {
        return $GLOBALS['__MOCK_JSON_INPUT__'];
    }
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function response($data, $status = 200)
{
    if (APP_ENV === 'testing') {
        throw new HttpResponseException((array)$data, (int)$status);
    }
    http_response_code($status);
    echo json_encode($data);
    exit;
}