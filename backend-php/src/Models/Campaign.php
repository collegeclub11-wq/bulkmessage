<?php
namespace Models;

use Database;
use PDO;

class Campaign {
    public static function all($tenantId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT bc.*, t.name as template_name, cg.name as group_name 
                              FROM bulk_campaigns bc 
                              LEFT JOIN templates t ON bc.template_id = t.id 
                              LEFT JOIN contact_groups cg ON bc.group_id = cg.id 
                              WHERE bc.tenant_id = ? ORDER BY bc.id DESC");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public static function findById($tenantId, $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM bulk_campaigns WHERE tenant_id = ? AND id = ?");
        $stmt->execute([$tenantId, $id]);
        return $stmt->fetch();
    }

    public static function create($data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO bulk_campaigns (tenant_id, campaign_name, template_id, group_id, schedule_type, scheduled_time, total_contacts, pending_count, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['tenant_id'],
            $data['campaign_name'],
            $data['template_id'],
            $data['group_id'],
            $data['schedule_type'] ?? 'immediate',
            $data['scheduled_time'] ?? null,
            $data['total_contacts'] ?? 0,
            $data['total_contacts'] ?? 0,
            $data['status'] ?? 'pending'
        ]);
        return $db->lastInsertId();
    }

    public static function updateStatus($id, $status) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE bulk_campaigns SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}
?>
