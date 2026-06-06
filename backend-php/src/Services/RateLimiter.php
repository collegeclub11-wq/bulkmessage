<?php
namespace Services;

use PDO;

class RateLimiter {
    private $db;
    private $tenantId;
    private $limits;
    
    public function __construct($db, $tenantId) {
        $this->db = $db;
        $this->tenantId = $tenantId;
        $this->loadLimits();
    }
    
    private function loadLimits() {
        $query = "SELECT rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day 
                  FROM tenants WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->tenantId]);
        $this->limits = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function checkLimit($sessionId = null) {
        if (!$this->limits) return true;

        // Check minute limit
        $minuteCount = $this->getCountInLastMinutes(1);
        if ($minuteCount >= $this->limits['rate_limit_per_minute']) {
            throw new \Exception("Rate limit exceeded: Max {$this->limits['rate_limit_per_minute']} messages per minute");
        }
        
        // Check hour limit
        $hourCount = $this->getCountInLastMinutes(60);
        if ($hourCount >= $this->limits['rate_limit_per_hour']) {
            throw new \Exception("Rate limit exceeded: Max {$this->limits['rate_limit_per_hour']} messages per hour");
        }
        
        // Check day limit
        $dayCount = $this->getCountToday();
        if ($dayCount >= $this->limits['rate_limit_per_day']) {
            throw new \Exception("Rate limit exceeded: Max {$this->limits['rate_limit_per_day']} messages per day");
        }
        
        return true;
    }
    
    private function getCountInLastMinutes($minutes) {
        $query = "SELECT COUNT(*) as count FROM message_logs 
                  WHERE tenant_id = ? AND sent_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->tenantId, $minutes]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    private function getCountToday() {
        $query = "SELECT COUNT(*) as count FROM message_logs 
                  WHERE tenant_id = ? AND DATE(sent_at) = CURDATE()";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    public function logRateLimit($sessionId, $action, $phoneNumber) {
        $query = "INSERT INTO rate_limit_logs (tenant_id, session_id, action, phone_number) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->tenantId, $sessionId, $action, $phoneNumber]);
    }
}
?>
