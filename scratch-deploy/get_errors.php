<?php
require 'backend-php/config/database.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT id, contact_id, status, error_message, attempt_count FROM campaign_contacts ORDER BY id DESC LIMIT 10");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
