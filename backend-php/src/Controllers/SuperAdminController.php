<?php
namespace Controllers;

use Database;
use PDO;
use Middleware\AuthMiddleware;

class SuperAdminController {
    private function checkSuperAdmin() {
        $payload = AuthMiddleware::validateJWT();
        if (($payload['role'] ?? '') !== 'superadmin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Super Admin privileges required.']);
            exit;
        }
        return $payload;
    }

    public function listTenants() {
        $this->checkSuperAdmin();
        $db = Database::getConnection();
        
        $stmt = $db->query("SELECT * FROM tenants ORDER BY id DESC");
        $tenants = $stmt->fetchAll();
        
        echo json_encode(['tenants' => $tenants]);
    }

    public function createTenant() {
        $this->checkSuperAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        $tenantKey = $input['tenant_key'] ?? '';
        $companyName = $input['company_name'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $phone = $input['phone'] ?? null;
        $plan = $input['subscription_plan'] ?? 'basic';
        $rateLimitMin = $input['rate_limit_per_minute'] ?? 20;
        $rateLimitHour = $input['rate_limit_per_hour'] ?? 200;
        $rateLimitDay = $input['rate_limit_per_day'] ?? 1000;
        $maxMessagesLimit = $input['max_messages_limit'] ?? 10000;

        if (empty($tenantKey) || empty($companyName) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant key, company name, email, and password are required']);
            return;
        }

        $db = Database::getConnection();
        
        // Check if exists
        $stmtCheck = $db->prepare("SELECT id FROM tenants WHERE tenant_key = ?");
        $stmtCheck->execute([$tenantKey]);
        if ($stmtCheck->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant key already exists']);
            return;
        }

        $stmt = $db->prepare("INSERT INTO tenants (tenant_key, company_name, email, phone, subscription_plan, rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day, max_messages_limit, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $tenantKey,
            $companyName,
            $email,
            $phone,
            $plan,
            $rateLimitMin,
            $rateLimitHour,
            $rateLimitDay,
            $maxMessagesLimit
        ]);

        $tenantId = $db->lastInsertId();
        
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

        echo json_encode(['message' => 'Tenant created successfully', 'tenant_id' => $tenantId]);
    }

    public function updateTenantStatus() {
        $this->checkSuperAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        $id = $input['id'] ?? null;
        $status = $input['status'] ?? null;
        $plan = $input['subscription_plan'] ?? null;
        $rateLimitMin = $input['rate_limit_per_minute'] ?? null;
        $rateLimitHour = $input['rate_limit_per_hour'] ?? null;
        $rateLimitDay = $input['rate_limit_per_day'] ?? null;
        $maxMessagesLimit = $input['max_messages_limit'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID is required']);
            return;
        }

        $db = Database::getConnection();
        
        $fields = [];
        $params = [];
        
        if ($status !== null) {
            $fields[] = "status = ?";
            $params[] = $status;
        }
        if ($plan !== null) {
            $fields[] = "subscription_plan = ?";
            $params[] = $plan;
        }
        if ($rateLimitMin !== null) {
            $fields[] = "rate_limit_per_minute = ?";
            $params[] = $rateLimitMin;
        }
        if ($rateLimitHour !== null) {
            $fields[] = "rate_limit_per_hour = ?";
            $params[] = $rateLimitHour;
        }
        if ($rateLimitDay !== null) {
            $fields[] = "rate_limit_per_day = ?";
            $params[] = $rateLimitDay;
        }
        if ($maxMessagesLimit !== null) {
            $fields[] = "max_messages_limit = ?";
            $params[] = $maxMessagesLimit;
            // Reset sent count back to 0 when limit is updated/refilled
            $fields[] = "total_messages_sent = 0";
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $params[] = $id;
        $sql = "UPDATE tenants SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['message' => 'Tenant updated successfully']);
    }

    public function listKeys() {
        $this->checkSuperAdmin();
        $db = Database::getConnection();

        $stmt = $db->query("SELECT k.*, t.company_name FROM api_keys k 
                            JOIN tenants t ON k.tenant_id = t.id 
                            ORDER BY k.id DESC");
        $keys = $stmt->fetchAll();

        echo json_encode(['keys' => $keys]);
    }

    public function createKey() {
        $this->checkSuperAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        $tenantId = $input['tenant_id'] ?? null;
        $name = $input['name'] ?? 'API Key';

        if (!$tenantId) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID is required']);
            return;
        }

        // Generate api_key and api_secret
        $apiKey = 'apikey_' . bin2hex(random_bytes(16));
        $apiSecretPlain = bin2hex(random_bytes(32));
        $apiSecretHash = password_hash($apiSecretPlain, PASSWORD_BCRYPT);

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO api_keys (tenant_id, api_key, api_secret, name, permissions, is_active) 
                              VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $tenantId,
            $apiKey,
            $apiSecretHash,
            $name,
            json_encode(['read' => true, 'write' => true])
        ]);

        echo json_encode([
            'message' => 'API Key created successfully',
            'api_key' => $apiKey,
            'api_secret' => $apiSecretPlain // Show plain secret once to user
        ]);
    }

    public function revokeKey() {
        $this->checkSuperAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Key ID is required']);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['message' => 'API Key revoked successfully']);
    }

    public function resetTenantPassword() {
        $this->checkSuperAdmin();
        $input = json_decode(file_get_contents('php://input'), true);

        $tenantId = $input['tenant_id'] ?? null;
        $password = $input['password'] ?? '';

        if (!$tenantId || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID and password are required']);
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $db = Database::getConnection();

        // Check if tenant exists
        $stmtTenant = $db->prepare("SELECT email FROM tenants WHERE id = ?");
        $stmtTenant->execute([$tenantId]);
        $tenant = $stmtTenant->fetch();
        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['error' => 'Tenant not found']);
            return;
        }

        // Check if user exists for this tenant
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE tenant_id = ?");
        $stmtCheck->execute([$tenantId]);
        if (!$stmtCheck->fetch()) {
            // Insert admin user
            $email = $tenant['email'];
            $username = strstr($email, '@', true) ?: 'admin';
            
            $stmtInsert = $db->prepare("INSERT INTO users (tenant_id, username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
            $stmtInsert->execute([$tenantId, $username, $email, $passwordHash]);
            $msg = 'Password set and admin user created successfully';
        } else {
            // Update existing users
            $stmtUpdate = $db->prepare("UPDATE users SET password_hash = ? WHERE tenant_id = ?");
            $stmtUpdate->execute([$passwordHash, $tenantId]);
            $msg = 'Password reset successfully';
        }

        echo json_encode(['message' => $msg]);
    }
}
?>
