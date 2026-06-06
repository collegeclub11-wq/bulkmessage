<?php
namespace Controllers;

use Models\Contact;
use Middleware\TenantMiddleware;
use Services\ExcelProcessor;
use Database;

class ContactController {
    public function index() {
        $tenant = TenantMiddleware::getTenantDetails();
        $contacts = Contact::all($tenant['id']);
        echo json_encode(['contacts' => $contacts]);
    }

    public function listGroups() {
        $tenant = TenantMiddleware::getTenantDetails();
        $groups = Contact::findGroups($tenant['id']);
        echo json_encode(['groups' => $groups]);
    }

    public function store() {
        $tenant = TenantMiddleware::getTenantDetails();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['phone_number'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Phone number is required']);
            return;
        }

        try {
            $contactId = Contact::create([
                'tenant_id' => $tenant['id'],
                'group_id' => $input['group_id'] ?? null,
                'phone_number' => $input['phone_number'],
                'name' => $input['name'] ?? null,
                'email' => $input['email'] ?? null,
                'custom_fields' => $input['custom_fields'] ?? []
            ]);
            echo json_encode(['message' => 'Contact saved successfully', 'contact_id' => $contactId]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save contact: ' . $e->getMessage()]);
        }
    }

    public function upload() {
        $tenant = TenantMiddleware::getTenantDetails();
        
        $groupId = $_POST['group_id'] ?? null;
        $groupName = $_POST['group_name'] ?? null;

        if (empty($_FILES['file']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded']);
            return;
        }

        $db = Database::getConnection();

        // Create group if a new name is specified
        if (empty($groupId) && !empty($groupName)) {
            $groupId = Contact::createGroup($tenant['id'], $groupName, 'Uploaded contacts group');
        }

        try {
            $processor = new ExcelProcessor($db, $tenant['id']);
            $result = $processor->processContacts($_FILES['file']['tmp_name'], $_FILES['file']['name'], $groupId);
            
            // Update total contact count in the group
            if ($groupId) {
                $stmt = $db->prepare("UPDATE contact_groups SET total_contacts = (SELECT COUNT(*) FROM contacts WHERE group_id = ?) WHERE id = ?");
                $stmt->execute([$groupId, $groupId]);
            }

            echo json_encode([
                'message' => 'File processed successfully',
                'results' => $result
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to parse file: ' . $e->getMessage()]);
        }
    }
}
?>
