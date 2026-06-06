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
        $host = getenv('NODE_HOST') ?: '127.0.0.1';
        $port = getenv('NODE_PORT');
        
        if (strpos($host, 'http://') === 0 || strpos($host, 'https://') === 0) {
            $nodeUrl = $host;
        } else {
            $nodeUrl = "http://" . $host;
        }
        
        if (!empty($port)) {
            $nodeUrl .= ":" . $port;
        }
        
        $nodeUrl .= "/api/session/init";
        
        $payload = json_encode([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId
        ]);

        $ch = curl_init($nodeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // short timeout

        curl_exec($ch);
        curl_close($ch);
    }
}
?>
