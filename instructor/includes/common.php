<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}
require_once __DIR__ . '/../../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'instructor'){
    header('Location: ../login.php');
    exit();
}

$instructor_id = intval($_SESSION['user_id']);
$branch_id = intval($_SESSION['branch_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? 'Instructor';
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

function instructor_badge_class($value) {
    $value = strtolower(trim((string)$value));
    return match($value) {
        'present', 'completed', 'passed', 'approved', 'enrolled' => 'badge-success',
        'scheduled', 'pending', 'pending_approval' => 'badge-warning',
        'late', 'cancelled', 'absent', 'failed', 'rejected' => 'badge-danger',
        default => 'badge-outline',
    };
}

function instructor_is_active_assignment(PDO $pdo, int $instructorId, int $studentId): bool {
    $stmt = $pdo->prepare("SELECT assignment_id FROM instructor_assignments WHERE instructor_user_id = ? AND student_user_id = ? AND status = 'active'");
    $stmt->execute([$instructorId, $studentId]);
    return (bool)$stmt->fetchColumn();
}
