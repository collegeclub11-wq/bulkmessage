<?php
/**
 * Intelligent Rate Limiting with 7-Day Warmup and Adaptive Breaks
 */
class SmartScheduler {
    private $redis;
    private $config;
    private $warmupSchedule = [
        1 => 15,   // Day 1: 15 messages/day
        2 => 25,   // Day 2: 25 messages/day
        3 => 40,   // Day 3: 40 messages/day
        4 => 55,   // Day 4: 55 messages/day
        5 => 70,   // Day 5: 70 messages/day
        6 => 85,   // Day 6: 85 messages/day
        7 => 100   // Day 7+: 100 messages/day
    ];
    
    public function __construct($redisConfig) {
        $this->redis = new Predis\Client($redisConfig);
        $this->config = [
            'max_daily' => 100,
            'max_hourly' => 15,
            'batch_size' => 5,
            'break_minutes' => [5, 12],
            'message_interval_seconds' => [30, 90],
            'retry_backoff_seconds' => [300, 1800] // 5-30 minutes
        ];
    }
    
    public function getCurrentDailyLimit(): int {
        $accountAge = $this->getAccountAgeDays();
        $limit = $this->warmupSchedule[$accountAge] ?? $this->config['max_daily'];
        return $limit;
    }
    
    public function canSend(string $phoneNumber, string $contactType = 'stranger'): array {
        $dailyLimit = $this->getCurrentDailyLimit();
        $today = date('Y-m-d');
        $currentHour = date('Y-m-d H:00:00');
        
        // Check various rate limits
        $dailyCount = (int)$this->redis->get("stats:daily:{$today}") ?: 0;
        $hourlyCount = (int)$this->redis->get("stats:hourly:{$currentHour}") ?: 0;
        $userCount = (int)$this->redis->get("stats:user:{$phoneNumber}:{$today}") ?: 0;
        
        // Contact type penalty (strangers = higher risk)
        $contactPenalty = ($contactType === 'stranger') ? 0.7 : 1.0;
        $effectiveDailyLimit = $dailyLimit * $contactPenalty;
        
        $can = [
            'can_send' => false,
            'reason' => null,
            'remaining_daily' => 0,
            'remaining_hourly' => 0,
            'next_window' => null,
            'adaptive_delay_ms' => $this->getAdaptiveDelay($dailyCount, $hourlyCount)
        ];
        
        if ($dailyCount >= $effectiveDailyLimit) {
            $can['reason'] = "Daily limit reached ({$dailyCount}/{$effectiveDailyLimit})";
            $can['next_window'] = strtotime('tomorrow 00:00:00');
        } elseif ($hourlyCount >= $this->config['max_hourly']) {
            $can['reason'] = "Hourly limit reached ({$hourlyCount}/{$this->config['max_hourly']})";
            $can['next_window'] = strtotime(date('Y-m-d H:00:00') . ' +1 hour');
        } elseif ($userCount >= 3 && $contactType === 'stranger') {
            $can['reason'] = "Max 3 messages to unresponsive contacts per day";
            $can['next_window'] = strtotime('tomorrow 00:00:00');
        } else {
            $can['can_send'] = true;
            $can['remaining_daily'] = max(0, $effectiveDailyLimit - $dailyCount);
            $can['remaining_hourly'] = max(0, $this->config['max_hourly'] - $hourlyCount);
        }
        
        return $can;
    }
    
    private function getAdaptiveDelay(int $dailyCount, int $hourlyCount): int {
        $baseDelay = rand($this->config['message_interval_seconds'][0], 
                         $this->config['message_interval_seconds'][1]) * 1000;
        
        // Increase delay if approaching limits
        $dailyLimit = $this->getCurrentDailyLimit();
        $dailyRatio = $dailyCount / max(1, $dailyLimit);
        $hourlyRatio = $hourlyCount / max(1, $this->config['max_hourly']);
        
        $multiplier = 1.0;
        if ($dailyRatio > 0.8) $multiplier = 1.5;
        if ($dailyRatio > 0.9) $multiplier = 2.0;
        if ($hourlyRatio > 0.8) $multiplier *= 1.3;
        
        return (int)($baseDelay * $multiplier);
    }
    
    public function recordSend(string $phoneNumber, string $contactType): void {
        $today = date('Y-m-d');
        $currentHour = date('Y-m-d H:00:00');
        
        // Increment counters
        $this->redis->incr("stats:daily:{$today}");
        $this->redis->incr("stats:hourly:{$currentHour}");
        $this->redis->incr("stats:user:{$phoneNumber}:{$today}");
        
        // Set expiry
        $this->redis->expire("stats:daily:{$today}", 86400);
        $this->redis->expire("stats:hourly:{$currentHour}", 3600);
        $this->redis->expire("stats:user:{$phoneNumber}:{$today}", 86400);
        
        // Track for reply ratio
        if ($contactType === 'stranger') {
            $this->redis->incr("stats:stranger_sent:{$today}");
        }
    }
    
    public function recordReply(string $phoneNumber): void {
        $today = date('Y-m-d');
        $this->redis->incr("stats:replies:{$today}");
        $this->redis->incr("stats:user_reply:{$phoneNumber}:{$today}");
        $this->redis->expire("stats:user_reply:{$phoneNumber}:{$today}", 86400);
    }
    
    public function getReplyRatio(): float {
        $today = date('Y-m-d');
        $sent = (int)$this->redis->get("stats:stranger_sent:{$today}") ?: 1;
        $replies = (int)$this->redis->get("stats:replies:{$today}") ?: 0;
        return $replies / $sent;
    }
    
    public function shouldTakeExtendedBreak(): bool {
        $replyRatio = $this->getReplyRatio();
        // If reply ratio drops below 10%, take a break
        if ($replyRatio < 0.1 && $replyRatio > 0) {
            return true;
        }
        return false;
    }
    
    private function getAccountAgeDays(): int {
        $firstUse = $this->redis->get('meta:first_use_date');
        if (!$firstUse) {
            $firstUse = date('Y-m-d');
            $this->redis->set('meta:first_use_date', $firstUse);
            return 1;
        }
        $days = (strtotime(date('Y-m-d')) - strtotime($firstUse)) / 86400;
        return max(1, min(7, (int)$days));
    }
}
