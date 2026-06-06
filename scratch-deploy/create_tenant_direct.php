<?php
require_once __DIR__ . '/../backend-php/config/config.php';
require_once __DIR__ . '/../backend-php/config/database.php';
require_once __DIR__ . '/../backend-php/src/Controllers/SuperAdminController.php';

// Simulate input
$tenantKey = 'demo';
$companyName = 'aurifie';
$email = 'demo@tezikaro.com';
$password = '123456';
$phone = '7870603149';
$plan = 'professional';
$rateLimitMin = 20;
$rateLimitHour = 200;
$rateLimitDay = 1000;

try {
    $db = Database::getConnection();
    
    // Check if exists
    $stmtCheck = $db->prepare("SELECT id FROM tenants WHERE tenant_key = ?");
    $stmtCheck->execute([$tenantKey]);
    if ($stmtCheck->fetch()) {
        echo "Error: Tenant key already exists\n";
        exit;
    }

    $stmt = $db->prepare("INSERT INTO tenants (tenant_key, company_name, email, phone, subscription_plan, rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([
        $tenantKey,
        $companyName,
        $email,
        $phone,
        $plan,
        $rateLimitMin,
        $rateLimitHour,
        $rateLimitDay
    ]);

    $tenantId = $db->lastInsertId();
    echo "Tenant created successfully with ID: $tenantId\n";
    
    // Create the primary administrator user
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $username = strstr($email, '@', true) ?: 'admin';
    $stmtUser = $db->prepare("INSERT INTO users (tenant_id, username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
    $stmtUser->execute([
        $tenantId,
        $username,
        $email,
        $passwordHash
    ]);
    echo "Admin user created successfully\n";

} catch (Exception $e) {
    echo "Database/execution error: " . $e->getMessage() . "\n";
}
