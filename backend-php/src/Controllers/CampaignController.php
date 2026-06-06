<?php
namespace Controllers;

use Models\Campaign;
use Models\MessageLog;
use Middleware\TenantMiddleware;
use Services\QueueService;
use Services\ReportGenerator;
use Database;

class CampaignController {
    public function index() {
        $tenant = TenantMiddleware::getTenantDetails();
        $campaigns = Campaign::all($tenant['id']);
        echo json_encode(['campaigns' => $campaigns]);
    }

    public function store() {
        $tenant = TenantMiddleware::getTenantDetails();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['campaign_name']) || empty($input['template_id']) || empty($input['group_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing campaign name, template ID, or target group ID']);
            return;
        }

        $campaignId = Campaign::create([
            'tenant_id' => $tenant['id'],
            'campaign_name' => $input['campaign_name'],
            'template_id' => $input['template_id'],
            'group_id' => $input['group_id'],
            'schedule_type' => $input['schedule_type'] ?? 'immediate',
            'scheduled_time' => $input['scheduled_time'] ?? null,
            'status' => 'draft'
        ]);

        try {
            QueueService::queueCampaign($tenant['id'], $campaignId);
            echo json_encode(['message' => 'Campaign registered and queued successfully', 'campaign_id' => $campaignId]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Campaign registered but queuing failed: ' . $e->getMessage()]);
        }
    }

    public function show() {
        $tenant = TenantMiddleware::getTenantDetails();
        $campaignId = $_GET['id'] ?? null;
        
        if (!$campaignId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing campaign ID']);
            return;
        }

        $campaign = Campaign::findById($tenant['id'], $campaignId);
        if (!$campaign) {
            http_response_code(404);
            echo json_encode(['error' => 'Campaign not found']);
            return;
        }

        $stats = ReportGenerator::getCampaignStats($tenant['id'], $campaignId);
        $logs = MessageLog::getCampaignLogs($tenant['id'], $campaignId);

        echo json_encode([
            'campaign' => $campaign,
            'stats' => $stats,
            'logs' => $logs
        ]);
    }

    public function dashboardStats() {
        $tenant = TenantMiddleware::getTenantDetails();
        $db = Database::getConnection();

        // Get total stats
        $stmt = $db->prepare("SELECT 
                                COUNT(DISTINCT bc.id) as total_campaigns,
                                COALESCE(SUM(bc.total_contacts), 0) as total_contacts_targeted,
                                COALESCE(SUM(bc.sent_count), 0) as total_sent,
                                COALESCE(SUM(bc.failed_count), 0) as total_failed
                              FROM bulk_campaigns bc 
                              WHERE bc.tenant_id = ?");
        $stmt->execute([$tenant['id']]);
        $totals = $stmt->fetch();

        // Get count of active sessions
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM whatsapp_sessions WHERE tenant_id = ? AND status = 'connected'");
        $stmt->execute([$tenant['id']]);
        $sessions = $stmt->fetch();
        $totals['active_sessions'] = $sessions['count'];

        // Get tenant's message limits
        $stmt = $db->prepare("SELECT max_messages_limit, total_messages_sent FROM tenants WHERE id = ?");
        $stmt->execute([$tenant['id']]);
        $tenantInfo = $stmt->fetch();
        $totals['max_messages_limit'] = $tenantInfo['max_messages_limit'] ?? 0;
        $totals['total_messages_sent'] = $tenantInfo['total_messages_sent'] ?? 0;

        echo json_encode($totals);
    }

    public function exportReport() {
        $tenant = TenantMiddleware::getTenantDetails();
        $campaignId = $_GET['id'] ?? null;

        if (!$campaignId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing campaign ID']);
            return;
        }

        $csv = ReportGenerator::generateCsvReport($tenant['id'], $campaignId);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="campaign_report_' . $campaignId . '.csv"');
        echo $csv;
        exit;
    }

    public function pause() {
        $tenant = TenantMiddleware::getTenantDetails();
        $input = json_decode(file_get_contents('php://input'), true);
        $campaignId = $input['campaign_id'] ?? null;

        if (!$campaignId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing campaign ID']);
            return;
        }

        $campaign = Campaign::findById($tenant['id'], $campaignId);
        if (!$campaign) {
            http_response_code(404);
            echo json_encode(['error' => 'Campaign not found']);
            return;
        }

        Campaign::updateStatus($campaignId, 'paused');
        echo json_encode(['message' => 'Campaign paused successfully']);
    }

    public function resume() {
        $tenant = TenantMiddleware::getTenantDetails();
        $input = json_decode(file_get_contents('php://input'), true);
        $campaignId = $input['campaign_id'] ?? null;

        if (!$campaignId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing campaign ID']);
            return;
        }

        $campaign = Campaign::findById($tenant['id'], $campaignId);
        if (!$campaign) {
            http_response_code(404);
            echo json_encode(['error' => 'Campaign not found']);
            return;
        }

        Campaign::updateStatus($campaignId, 'pending');
        echo json_encode(['message' => 'Campaign resumed successfully']);
    }
}
?>
