<?php
require_once __DIR__ . '/../config/db.php';

function log_audit(PDO $pdo, ?int $user_id, string $action, string $entity, ?int $entity_id = null, $details = null) {
    $sql = "INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address) VALUES (:user_id, :action, :entity, :entity_id, :details, :ip)";
    $stmt = $pdo->prepare($sql);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':entity' => $entity,
        ':entity_id' => $entity_id,
        ':details' => $details ? json_encode($details) : null,
        ':ip' => $ip,
    ]);
    return $pdo->lastInsertId();
}

?>