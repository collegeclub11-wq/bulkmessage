<?php
namespace Middleware;

use Database;
use Services\RateLimiter;

class RateLimitMiddleware {
    public static function check($tenantId, $sessionId = null) {
        $db = Database::getConnection();
        $limiter = new RateLimiter($db, $tenantId);
        
        try {
            $limiter->checkLimit($sessionId);
            return true;
        } catch (\Exception $e) {
            http_response_code(429);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}
?>
