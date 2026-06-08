<?php
// backend-php/config/config.php
date_default_timezone_set('Asia/Kolkata');

// Load .env variables manually
$envFile = dirname(dirname(__DIR__)) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Application globals and config
define('APP_NAME', 'WhatsApp Bulk Sender');
define('JWT_SECRET', getenv('SECRET_KEY') ?: 'demo_super_secret_jwt_key_default_value_123_abc');
define('JWT_EXPIRY', 86400); // 1 day

// CORS configuration
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Tenant-Key");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error reporting settings (can toggle off in true prod)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Autoloader function for MVC classes
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $class = str_replace('\\', '/', $class);
    $file = dirname(__DIR__) . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
?>
