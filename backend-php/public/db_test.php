<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT 'Database Connection Successful!' AS message");
    $result = $stmt->fetch();
    
    // Also get MySQL grant details
    $grants = [];
    try {
        $stmt2 = $db->query("SHOW GRANTS");
        $grants = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $grants = ['Error fetching grants: ' . $e->getMessage()];
    }
    
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'grants' => $grants
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
