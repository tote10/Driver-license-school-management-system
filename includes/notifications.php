<?php
require_once __DIR__ . '/../config/db.php';

function send_notification(PDO $pdo, ?int $recipient_user_id, string $type, string $title, string $message, $meta = null) {
    $sql = "INSERT INTO notifications (recipient_user_id, notification_type, title, message) VALUES (:rid, :type, :title, :message)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':rid' => $recipient_user_id,
        ':type' => $type,
        ':title' => $title,
        ':message' => $message,
    ]);
    return $pdo->lastInsertId();
}

function fetch_user_notifications(PDO $pdo, int $user_id, int $limit = 50) {
    $sql = "SELECT notification_id, title, message, notification_type, is_read, sent_at FROM notifications WHERE recipient_user_id = :rid OR recipient_user_id IS NULL ORDER BY sent_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':rid', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_unread_count(PDO $pdo, int $user_id) {
    $sql = "SELECT COUNT(*) FROM notifications WHERE (recipient_user_id = :rid OR recipient_user_id IS NULL) AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':rid' => $user_id]);
    return (int)$stmt->fetchColumn();
}

function send_notification_to_user_ids(PDO $pdo, array $user_ids, string $type, string $title, string $message, $meta = null) {
    $sent = 0;
    foreach ($user_ids as $user_id) {
        $recipient_id = intval($user_id);
        if ($recipient_id > 0) {
            send_notification($pdo, $recipient_id, $type, $title, $message, $meta);
            $sent++;
        }
    }
    return $sent;
}

function send_notification_to_branch_role(PDO $pdo, int $branch_id, string $role, string $type, string $title, string $message, $meta = null) {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE branch_id = ? AND role = ? AND status = 'active'");
    $stmt->execute([$branch_id, $role]);
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return send_notification_to_user_ids($pdo, $user_ids, $type, $title, $message, $meta);
}

function mark_notification_read(PDO $pdo, int $notification_id, int $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND (recipient_user_id = :rid OR recipient_user_id IS NULL)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $notification_id, ':rid' => $user_id]);
}

?>