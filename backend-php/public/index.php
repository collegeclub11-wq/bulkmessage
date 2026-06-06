<?php
// backend-php/public/index.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Parse query string and request URI
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Clean request URI from subfolder if running inside a subdirectory (e.g. /bulk/backend-php/public/index.php)
// Find /api/ location in string
$apiPos = strpos($requestUri, '/api/');
if ($apiPos !== false) {
    $requestUri = substr($requestUri, $apiPos);
}

$routes = require_once __DIR__ . '/../config/routes.php';
$routeKey = $requestMethod . $requestUri;

if (isset($routes[$routeKey])) {
    list($controllerName, $methodName) = $routes[$routeKey];

    // Autoload should resolve this, but let's double check class definition
    if (class_exists("Controllers\\$controllerName")) {
        $fullControllerClass = "Controllers\\$controllerName";
        $controller = new $fullControllerClass();
        
        try {
            $controller->$methodName();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => "Controller class Controllers\\$controllerName not found."]);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'API Endpoint not found', 'route' => $routeKey]);
}
?>
