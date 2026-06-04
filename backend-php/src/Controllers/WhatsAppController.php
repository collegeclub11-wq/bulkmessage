<?php
namespace Controllers;

use Database;
use Middleware\TenantMiddleware;
use PDO;

class WhatsAppController {
    public function sessions() {
        $tenant = TenantMiddleware::getTenantDetails();
        $db = Database::getConnection();
        
        $stmt = $db->prepare("SELECT id, session_id, phone_number, device_name, status, reconnect_attempts, last_connected FROM whatsapp_sessions WHERE tenant_id = ?");
        $stmt->execute([$tenant['id']]);
        $sessions = $stmt->fetchAll();
        
        echo json_encode(['sessions' => $sessions]);
    }

    public function createSession() {
        $tenant = TenantMiddleware::getTenantDetails();
        $input = json_decode(file_get_contents('php://input'), true);

        $deviceName = $input['device_name'] ?? 'Web Device';
        $sessionId = 'session_' . $tenant['id'] . '_' . uniqid();

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO whatsapp_sessions (tenant_id, session_id, device_name, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$tenant['id'], $sessionId, $deviceName]);

        // Trigger Node.js initialization request via HTTP
        $this->pingNodeServiceInit($tenant['id'], $sessionId);

        echo json_encode([
            'message' => 'Session initialized',
            'session_id' => $sessionId
        ]);
    }

    public function getQR() {
        $tenant = TenantMiddleware::getTenantDetails();
        $sessionId = $_GET['session_id'] ?? null;

        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing session_id']);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT status, qr_code FROM whatsapp_sessions WHERE tenant_id = ? AND session_id = ?");
        $stmt->execute([$tenant['id'], $sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            return;
        }

        // Trigger node boot if session is in pending state
        if ($session['status'] === 'pending' || $session['status'] === 'disconnected') {
            $this->pingNodeServiceInit($tenant['id'], $sessionId);
            // Update local status representation temporarily
            $session['status'] = 'scanning';
        }

        echo json_encode([
            'status' => $session['status'],
            'qr_code' => $session['qr_code']
        ]);
    }

    private function pingNodeServiceInit($tenantId, $sessionId) {
        $host = $_ENV['NODE_HOST'] ?? $_SERVER['NODE_HOST'] ?? getenv('NODE_HOST') ?: '127.0.0.1';
        $port = $_ENV['NODE_PORT'] ?? $_SERVER['NODE_PORT'] ?? getenv('NODE_PORT') ?: '3000';
        
        if (strpos($host, 'http://') === 0 || strpos($host, 'https://') === 0) {
            $nodeUrl = rtrim($host, '/') . '/api/session/init';
        } else {
            $protocol = (strpos($host, '.') !== false && strpos($host, 'localhost') === false) ? 'https://' : 'http://';
            $portSuffix = (!empty($port) && $port !== '80' && $port !== '443') ? ":$port" : "";
            $nodeUrl = "$protocol$host$portSuffix/api/session/init";
        }
        
        $payload = json_encode([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId
        ]);

        $ch = curl_init($nodeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8); // slightly longer timeout to allow resolution
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $res = curl_exec($ch);
        if ($res === false) {
            error_log("cURL Error: " . curl_error($ch) . " URL: " . $nodeUrl);
        }
        curl_close($ch);
    }
}
?>
