<?php
namespace Models;

use Database;
use PDO;

class Template {
    public static function all($tenantId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM templates WHERE tenant_id = ? ORDER BY id DESC");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public static function create($data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO templates (tenant_id, name, category, message, image_url, variables) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['tenant_id'],
            $data['name'],
            $data['category'] ?? 'marketing',
            $data['message'],
            $data['image_url'] ?? null,
            isset($data['variables']) ? json_encode($data['variables']) : null
        ]);
        return $db->lastInsertId();
    }

    public static function update($tenantId, $id, $data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE templates 
                              SET name = ?, category = ?, message = ?, image_url = ?, variables = ? 
                              WHERE tenant_id = ? AND id = ?");
        return $stmt->execute([
            $data['name'],
            $data['category'] ?? 'marketing',
            $data['message'],
            $data['image_url'] ?? null,
            isset($data['variables']) ? json_encode($data['variables']) : null,
            $tenantId,
            $id
        ]);
    }

    public static function delete($tenantId, $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM templates WHERE tenant_id = ? AND id = ?");
        return $stmt->execute([$tenantId, $id]);
    }
}
?>
