<?php
require_once __DIR__ . '/../backend-php/config/config.php';
require_once __DIR__ . '/../backend-php/config/database.php';

$db = Database::getConnection();

echo "=== Testing Tenant Creation User Integration ===\n";

// Generate a random key
$tenantKey = 'test-tenant-' . rand(1000, 9999);
$email = $tenantKey . '@example.com';
$password = 'TestPassword123!';

// Fake request input
$input = [
    'tenant_key' => $tenantKey,
    'company_name' => 'Test Tenant Inc.',
    'email' => $email,
    'password' => $password,
    'subscription_plan' => 'basic'
];

try {
    // Manually run the createTenant logic since we are testing
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO tenants (tenant_key, company_name, email, subscription_plan, status) 
                           VALUES (?, ?, ?, ?, 'active')");
    $stmt->execute([
        $input['tenant_key'],
        $input['company_name'],
        $input['email'],
        $input['subscription_plan']
    ]);
    
    $tenantId = $db->lastInsertId();
    echo "Tenant inserted with ID: $tenantId\n";

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $username = strstr($email, '@', true) ?: 'admin';
    $stmtUser = $db->prepare("INSERT INTO users (tenant_id, username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
    $stmtUser->execute([
        $tenantId,
        $username,
        $email,
        $passwordHash
    ]);
    echo "Admin user created in users table.\n";

    // Verify
    $stmtVerifyUser = $db->prepare("SELECT * FROM users WHERE tenant_id = ?");
    $stmtVerifyUser->execute([$tenantId]);
    $user = $stmtVerifyUser->fetch();
    echo "Verification - User email: " . $user['email'] . ", Role: " . $user['role'] . "\n";
    if (password_verify($password, $user['password_hash'])) {
        echo "Password verification: SUCCESS!\n";
    } else {
        echo "Password verification: FAILED!\n";
    }

    // Test Reset Password logic
    $newPassword = 'NewPassword789!';
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    $stmtUpdate = $db->prepare("UPDATE users SET password_hash = ? WHERE tenant_id = ?");
    $stmtUpdate->execute([$newHash, $tenantId]);
    echo "Password updated/reset in users table.\n";

    $stmtVerifyUser->execute([$tenantId]);
    $user = $stmtVerifyUser->fetch();
    if (password_verify($newPassword, $user['password_hash'])) {
        echo "New password verification: SUCCESS!\n";
    } else {
        echo "New password verification: FAILED!\n";
    }

    $db->rollBack();
    echo "Transaction rolled back successfully.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Test failed: " . $e->getMessage() . "\n";
}
