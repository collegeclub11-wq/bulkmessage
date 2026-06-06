<?php
namespace Services;

use Models\User;
use Middleware\AuthMiddleware;

class AuthService {
    public static function authenticate($tenantId, $email, $password) {
        $user = User::findByEmail($tenantId, $email);
        
        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (!$user['is_active']) {
            return false;
        }

        // Generate token
        $token = AuthMiddleware::generateJWT([
            'user_id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        return [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    }
}
?>
