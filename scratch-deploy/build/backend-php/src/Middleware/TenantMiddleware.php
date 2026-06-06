<?php
namespace Middleware;

use Database;
use PDO;

class TenantMiddleware {
    public static function getTenantDetails() {
        $headers = getallheaders();
        $tenantKey = isset($headers['X-Tenant-Key']) ? $headers['X-Tenant-Key'] : '';

        if (empty($tenantKey) && isset($headers['x-tenant-key'])) {
            $tenantKey = $headers['x-tenant-key'];
        }

        if (empty($tenantKey)) {
            // Check if JWT payload contains tenant_id
            $payload = AuthMiddleware::validateJWT();
            if (isset($payload['tenant_id'])) {
                return self::fetchTenantById($payload['tenant_id']);
            }

            http_response_code(400);
            echo json_encode(['error' => 'Missing X-Tenant-Key header or invalid session context']);
            exit;
        }

        return self::fetchTenantByKey($tenantKey);
    }

    private static function fetchTenantByKey($key) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE tenant_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$key]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            http_response_code(403);
            echo json_encode(['error' => 'Tenant account is inactive, invalid or expired']);
            exit;
        }

        return $tenant;
    }

    private static function fetchTenantById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$id]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            http_response_code(403);
            echo json_encode(['error' => 'Tenant context not found or inactive']);
            exit;
        }

        return $tenant;
    }
}
?>
