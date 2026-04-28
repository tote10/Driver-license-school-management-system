<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';

$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

$logs = [];
try {
    $stmt = $pdo->prepare(
        "SELECT al.*, u.full_name AS actor_name, u.role AS actor_role
         FROM audit_logs al
         JOIN users u ON al.user_id = u.user_id
         WHERE u.branch_id = ?
         ORDER BY al.created_at DESC
         LIMIT 100"
    );
    $stmt->execute([$branch_id]);
    $logs = $stmt->fetchAll();
} catch(PDOException $e) {}

function action_badge_class($actionType) {
    switch($actionType) {
        case 'certificate_issued':
        case 'enrollment_approved':
        case 'exam_result_approved':
        case 'account_approved':
            return 'badge-success';
        case 'certificate_rejected':
        case 'manual_enrollment_created':
            return 'badge-warning';
        case 'exam_scheduled':
        case 'exam_result_recorded':
            return 'badge-primary';
        default:
            return 'badge-outline';
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Audit Logs | Manager Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>

      <div class="main-content">
        <?php $page_title = 'Audit Logs'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="card">
            <h3 class="card-subtitle mb-3">Recent Manager Activity</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($logs as $log): ?>
                  <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                    <td class="font-bold"><?php echo htmlspecialchars($log['actor_name']); ?></td>
                    <td><span class="badge <?php echo action_badge_class($log['action_type']); ?>"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                    <td><?php echo htmlspecialchars($log['entity_type']); ?> #<?php echo intval($log['entity_id']); ?></td>
                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($logs) === 0): ?>
                  <tr><td colspan="5" class="text-center text-muted">No audit logs found yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>
