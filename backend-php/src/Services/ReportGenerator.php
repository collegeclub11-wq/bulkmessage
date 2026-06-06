<?php
namespace Services;

use Database;
use PDO;

class ReportGenerator {
    public static function getCampaignStats($tenantId, $campaignId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                              FROM campaign_contacts 
                              WHERE campaign_id = (SELECT id FROM bulk_campaigns WHERE id = ? AND tenant_id = ?)");
        $stmt->execute([$campaignId, $tenantId]);
        return $stmt->fetch();
    }
    
    public static function generateCsvReport($tenantId, $campaignId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT 
                                cc.status, 
                                cc.attempt_count, 
                                cc.sent_at, 
                                cc.delivered_at, 
                                cc.error_message, 
                                c.phone_number, 
                                c.name 
                              FROM campaign_contacts cc 
                              JOIN contacts c ON cc.contact_id = c.id 
                              JOIN bulk_campaigns bc ON cc.campaign_id = bc.id 
                              WHERE bc.id = ? AND bc.tenant_id = ?");
        $stmt->execute([$campaignId, $tenantId]);
        $rows = $stmt->fetchAll();
        
        $output = fopen('php://temp', 'w');
        fputcsv($output, ['Contact Name', 'Phone Number', 'Status', 'Attempts', 'Sent At', 'Delivered At', 'Error Message']);
        
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['name'],
                $row['phone_number'],
                $row['status'],
                $row['attempt_count'],
                $row['sent_at'],
                $row['delivered_at'],
                $row['error_message']
            ]);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
}
?>
