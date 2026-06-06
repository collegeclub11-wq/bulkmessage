<?php
namespace Models;

use Database;
use PDO;

class Tenant {
    public static function findById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findByKey($key) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE tenant_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetch();
    }

    public static function create($data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO tenants (tenant_key, company_name, email, phone, subscription_plan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['tenant_key'],
            $data['company_name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['subscription_plan'] ?? 'basic'
        ]);
        return $db->lastInsertId();
    }
}
?>
