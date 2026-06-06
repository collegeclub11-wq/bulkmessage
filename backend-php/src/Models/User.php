<?php
namespace Models;

use Database;
use PDO;

class User {
    public static function findByEmail($tenantId, $email) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE tenant_id = ? AND email = ?");
        $stmt->execute([$tenantId, $email]);
        return $stmt->fetch();
    }

    public static function findById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create($data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO users (tenant_id, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['tenant_id'],
            $data['username'],
            $data['email'],
            $data['password_hash'],
            $data['role'] ?? 'sender'
        ]);
        return $db->lastInsertId();
    }
}
?>
