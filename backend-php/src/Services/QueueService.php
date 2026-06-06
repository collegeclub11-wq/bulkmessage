<?php
namespace Services;

use Database;
use PDO;

class QueueService {
    public static function queueCampaign($tenantId, $campaignId) {
        $db = Database::getConnection();
        
        // Fetch group contacts
        $stmt = $db->prepare("SELECT cg.id, bc.group_id 
                              FROM bulk_campaigns bc 
                              JOIN contact_groups cg ON bc.group_id = cg.id 
                              WHERE bc.id = ? AND bc.tenant_id = ?");
        $stmt->execute([$campaignId, $tenantId]);
        $campaignInfo = $stmt->fetch();
        
        if (!$campaignInfo) {
            throw new \Exception("Campaign or target contact group not found");
        }
        
        $groupId = $campaignInfo['group_id'];
        
        // Find contacts in this group
        $stmt = $db->prepare("SELECT id FROM contacts WHERE tenant_id = ? AND group_id = ? AND status = 'active'");
        $stmt->execute([$tenantId, $groupId]);
        $contacts = $stmt->fetchAll();
        
        if (empty($contacts)) {
            throw new \Exception("The contact group contains no active contacts");
        }
        
        $db->beginTransaction();
        try {
            // Insert campaign contacts mapping
            $insertStmt = $db->prepare("INSERT INTO campaign_contacts (campaign_id, contact_id, status) VALUES (?, ?, 'pending')");
            foreach ($contacts as $contact) {
                $insertStmt->execute([$campaignId, $contact['id']]);
            }
            
            // Update campaign status to pending queue
            $updateStmt = $db->prepare("UPDATE bulk_campaigns SET status = 'pending', total_contacts = ?, pending_count = ? WHERE id = ?");
            $updateStmt->execute([count($contacts), count($contacts), $campaignId]);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
?>
