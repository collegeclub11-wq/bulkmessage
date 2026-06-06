<?php
namespace Controllers;

use Models\Template;
use Middleware\TenantMiddleware;

class TemplateController {
    public function index() {
        $tenant = TenantMiddleware::getTenantDetails();
        $templates = Template::all($tenant['id']);
        echo json_encode(['templates' => $templates]);
    }

    public function store() {
        $tenant = TenantMiddleware::getTenantDetails();
        
        // Support both JSON input and form data
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            $input = $_POST;
        }

        if (empty($input['name']) || empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Template name and message content are required']);
            return;
        }

        $imageUrl = $input['image_url'] ?? null;

        // Check for direct file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(dirname(__DIR__)) . '/public/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $destPath = $uploadDir . '/' . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $imageUrl = $protocol . $host . "/bulk/backend-php/public/uploads/" . $newFileName;
                }
            }
        }

        // Auto-detect variables from message content (e.g. {{name}}, {{city}})
        preg_match_all('/\{\{([^}]+)\}\}/', $input['message'], $matches);
        $variables = array_unique($matches[1]);

        $templateId = Template::create([
            'tenant_id' => $tenant['id'],
            'name' => $input['name'],
            'category' => $input['category'] ?? 'marketing',
            'message' => $input['message'],
            'image_url' => $imageUrl,
            'variables' => $variables
        ]);

        echo json_encode(['message' => 'Template created successfully', 'template_id' => $templateId]);
    }

    public function update() {
        $tenant = TenantMiddleware::getTenantDetails();

        // Support both JSON input and form data
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            $input = $_POST;
        }

        $templateId = $input['id'] ?? null;
        if (!$templateId) {
            http_response_code(400);
            echo json_encode(['error' => 'Template ID is required for updating']);
            return;
        }

        if (empty($input['name']) || empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Template name and message content are required']);
            return;
        }

        $imageUrl = $input['image_url'] ?? null;

        // Check for direct file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(dirname(__DIR__)) . '/public/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $destPath = $uploadDir . '/' . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $imageUrl = $protocol . $host . "/bulk/backend-php/public/uploads/" . $newFileName;
                }
            }
        }

        // Auto-detect variables from message content (e.g. {{name}}, {{city}})
        preg_match_all('/\{\{([^}]+)\}\}/', $input['message'], $matches);
        $variables = array_unique($matches[1]);

        $success = Template::update($tenant['id'], $templateId, [
            'name' => $input['name'],
            'category' => $input['category'] ?? 'marketing',
            'message' => $input['message'],
            'image_url' => $imageUrl,
            'variables' => $variables
        ]);

        if ($success) {
            echo json_encode(['message' => 'Template updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update template']);
        }
    }

    public function delete() {
        $tenant = TenantMiddleware::getTenantDetails();
        $templateId = $_GET['id'] ?? null;

        if (!$templateId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing template ID']);
            return;
        }

        Template::delete($tenant['id'], $templateId);
        echo json_encode(['message' => 'Template deleted successfully']);
    }
}
?>
