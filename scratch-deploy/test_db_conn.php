<?php
require_once __DIR__ . '/../backend-php/config/config.php';
require_once __DIR__ . '/../backend-php/config/database.php';

echo "DB_HOST: " . getenv('DB_HOST') . "\n";
echo "DB_PORT: " . getenv('DB_PORT') . "\n";
echo "DB_DATABASE: " . getenv('DB_DATABASE') . "\n";
echo "DB_USER: " . getenv('DB_USER') . "\n";
echo "DB_PASSWORD (raw): ";
var_dump(getenv('DB_PASSWORD'));

try {
    $db = Database::getConnection();
    echo "Connection successful!\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
