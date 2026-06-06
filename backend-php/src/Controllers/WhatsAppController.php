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

        // Trigger session initialization on the stable bot
        $botUrl = 'https://whatsappbackend-production-9e33.up.railway.app';
        $statusUrl = $botUrl . '/status?sessionId=' . urlencode($sessionId);

        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);

        echo json_encode([
            'message' => 'Session initialized',
            'session_id' => $sessionId
        ]);
    }

    public function deleteSession() {
        $tenant = TenantMiddleware::getTenantDetails();
        $input = json_decode(file_get_contents('php://input'), true);
        $sessionId = $input['session_id'] ?? null;

        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing session_id']);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM whatsapp_sessions WHERE tenant_id = ? AND session_id = ?");
        $stmt->execute([$tenant['id'], $sessionId]);

        echo json_encode(['message' => 'Session deleted successfully']);
    }

    public function getQR() {
        $tenant = TenantMiddleware::getTenantDetails();
        $sessionId = $_GET['session_id'] ?? null;

        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing session_id']);
            return;
        }

        $botUrl = 'https://whatsappbackend-production-9e33.up.railway.app';
        $statusUrl = $botUrl . '/status?sessionId=' . urlencode($sessionId);

        // Fetch status from the stable bot
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $status = 'disconnected';
        $qrCode = null;

        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['status'])) {
                $status = $data['status'];
                if ($status === 'qr_ready' && !empty($data['qr'])) {
                    $status = 'scanning';
                    $qrCode = $data['qr'];
                }
            }
        }

        // Update database with latest status from bot
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE whatsapp_sessions SET status = ?, qr_code = ?, last_connected = CASE WHEN ? = 'connected' THEN NOW() ELSE last_connected END WHERE tenant_id = ? AND session_id = ?");
        $stmt->execute([$status, $qrCode, $status, $tenant['id'], $sessionId]);

        echo json_encode([
            'status' => $status,
            'qr_code' => $qrCode
        ]);
    }
}
?>
