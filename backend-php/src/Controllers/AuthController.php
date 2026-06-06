<?php
namespace Controllers;

use Database;
use Services\AuthService;
use Models\User;
use Models\Tenant;
use Middleware\AuthMiddleware;
use Middleware\TenantMiddleware;

class AuthController {
    public function login() {
        // Read JSON payload
        $input = json_decode(file_get_contents('php://input'), true);
        
        $tenantKey = isset($input['tenant_key']) ? $input['tenant_key'] : '';
        $email = isset($input['email']) ? $input['email'] : '';
        $password = isset($input['password']) ? $input['password'] : '';

        if (empty($tenantKey) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing tenant key, email, or password credentials']);
            return;
        }

        $tenant = Tenant::findByKey($tenantKey);
        if (!$tenant || $tenant['status'] !== 'active') {
            http_response_code(403);
            echo json_encode(['error' => 'Tenant configuration is inactive or not found']);
            return;
        }

        $authResult = AuthService::authenticate($tenant['id'], $email, $password);
        if (!$authResult) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password combination']);
            return;
        }

        echo json_encode([
            'message' => 'Successfully authenticated',
            'token' => $authResult['token'],
            'user' => $authResult['user'],
            'tenant' => [
                'company_name' => $tenant['company_name'],
                'tenant_key' => $tenant['tenant_key']
            ]
        ]);
    }

    public function register() {
        $input = json_decode(file_get_contents('php://input'), true);

        $tenantKey = $input['tenant_key'] ?? '';
        $companyName = $input['company_name'] ?? '';
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($tenantKey) || empty($companyName) || empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Please fill in all registration fields']);
            return;
        }

        $db = Database::getConnection();

        // Check if tenant key already exists
        $tenant = Tenant::findByKey($tenantKey);
        if ($tenant) {
            http_response_code(409);
            echo json_encode(['error' => 'Tenant key is already registered']);
            return;
        }

        $db->beginTransaction();
        try {
            // Create tenant
            $tenantId = Tenant::create([
                'tenant_key' => $tenantKey,
                'company_name' => $companyName,
                'email' => $email
            ]);

            // Create user
            $user_id = User::create([
                'tenant_id' => $tenantId,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => 'admin'
            ]);

            $db->commit();
            echo json_encode(['message' => 'Registration complete. You can login now.']);
        } catch (\Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }

    public function me() {
        $payload = AuthMiddleware::validateJWT();
        echo json_encode(['user' => $payload]);
    }
}
?>
