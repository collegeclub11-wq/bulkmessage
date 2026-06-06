<?php
namespace Models;

use Database;
use PDO;

class MessageLog {
    public static function getCampaignLogs($tenantId, $campaignId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT ml.*, c.name as contact_name 
                              FROM message_logs ml 
                              LEFT JOIN contacts c ON ml.contact_id = c.id 
                              WHERE ml.tenant_id = ? AND ml.campaign_id = ? 
                              ORDER BY ml.id ASC");
        $stmt->execute([$tenantId, $campaignId]);
        return $stmt->fetchAll();
    }

    public static function log($data) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO message_logs (tenant_id, campaign_id, contact_id, session_id, phone_number, message_content, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['tenant_id'],
            $data['campaign_id'] ?? null,
            $data['contact_id'] ?? null,
            $data['session_id'] ?? null,
            $data['phone_number'],
            $data['message_content'],
            $data['status'] ?? 'queued'
        ]);
    }
}
?>
