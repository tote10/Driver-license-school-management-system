<?php
require_once __DIR__ . '/../config/db.php';

function log_audit(PDO $pdo, ?int $user_id, string $action_type, string $entity_type, ?int $entity_id = null, $details = null) {
    $sql = "INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (:user_id, :action_type, :entity_type, :entity_id, :details)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':action_type' => $action_type,
        ':entity_type' => $entity_type,
        ':entity_id' => $entity_id,
        ':details' => is_string($details) ? $details : json_encode($details),
    ]);
    return (int)$pdo->lastInsertId();
}

?>