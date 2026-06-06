<?php
namespace Models;

use Database;
use PDO;

class Contact {
    public static function all($tenantId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT c.*, cg.name as group_name 
                              FROM contacts c 
                              LEFT JOIN contact_groups cg ON c.group_id = cg.id 
                              WHERE c.tenant_id = ? ORDER BY c.id DESC");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public static function findGroups($tenantId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM contact_groups WHERE tenant_id = ? ORDER BY id DESC");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public static function createGroup($tenantId, $name, $description = null) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO contact_groups (tenant_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$tenantId, $name, $description]);
        return $db->lastInsertId();
    }

    public static function create($data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO contacts (tenant_id, group_id, phone_number, name, email, custom_fields) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['tenant_id'],
            $data['group_id'] ?? null,
            $data['phone_number'],
            $data['name'] ?? null,
            $data['email'] ?? null,
            isset($data['custom_fields']) ? json_encode($data['custom_fields']) : null
        ]);
        return $db->lastInsertId();
    }
}
?>
