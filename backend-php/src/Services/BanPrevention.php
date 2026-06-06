<?php
namespace Services;

use PDO;

class BanPrevention {
    private $db;
    private $tenantId;
    
    public function __construct($db, $tenantId) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }
    
    public function shouldRotateNumber($sessionId) {
        $query = "SELECT COUNT(*) as count, AVG(TIMESTAMPDIFF(SECOND, sent_at, NOW())) as avg_delay
                  FROM message_logs 
                  WHERE tenant_id = ? AND session_id = ? 
                  AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->tenantId, $sessionId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Rotate if sending too fast (>50 per hour) or delay is too low
        return ($data['count'] > 50 || $data['avg_delay'] < 30);
    }
    
    public function addToBlocklist($phoneNumber, $reason) {
        $query = "INSERT INTO blocklist (tenant_id, phone_number, reason) 
                  VALUES (?, ?, ?) 
                  ON DUPLICATE KEY UPDATE reason = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$this->tenantId, $phoneNumber, $reason, $reason]);
    }
    
    public function isBlocked($phoneNumber) {
        $query = "SELECT 1 FROM blocklist WHERE tenant_id = ? AND phone_number = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->tenantId, $phoneNumber]);
        return $stmt->fetch() !== false;
    }
    
    public function getOptimalDelay() {
        $query = "SELECT AVG(TIMESTAMPDIFF(SECOND, sent_at, delivered_at)) as avg_delivery_time
                  FROM message_logs 
                  WHERE tenant_id = ? AND delivered_at IS NOT NULL 
                  AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $baseDelay = 2000; // 2 seconds
        if ($result && $result['avg_delivery_time'] > 5) {
            $baseDelay += 1000;
        }
        
        return min($baseDelay, 10000);
    }
    
    public function markSessionBanned($sessionId) {
        $query = "UPDATE whatsapp_sessions SET status = 'banned', last_disconnected = NOW() 
                  WHERE session_id = ? AND tenant_id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$sessionId, $this->tenantId]);
    }
}
?>
