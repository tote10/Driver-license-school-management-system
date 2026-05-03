<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = ds_display_name('Manager');

$initials = ds_display_initials($full_name, 'Manager');

$action_filter = trim((string)($_GET['action'] ?? ''));
$actor_filter = trim((string)($_GET['actor'] ?? ''));
$date_from = trim((string)($_GET['from'] ?? ''));
$date_to = trim((string)($_GET['to'] ?? ''));

$valid_actions = [
  'account_approved',
  'enrollment_approved',
  'manual_enrollment_created',
  'exam_scheduled',
  'exam_result_recorded',
  'exam_result_approved',
  'certificate_issued',
  'certificate_rejected',
  'instructor_assigned',
  'unassign_instructor',
];

$filters = ["(u.branch_id = :branch_id OR al.user_id IS NULL)"];
$params = [':branch_id' => $branch_id];

if($action_filter !== '' && in_array($action_filter, $valid_actions, true)) {
  $filters[] = "al.action_type = :action_type";
  $params[':action_type'] = $action_filter;
}

if($actor_filter !== '') {
  $filters[] = "u.full_name LIKE :actor_name";
  $params[':actor_name'] = '%' . $actor_filter . '%';
}

if($date_from !== '') {
  $filters[] = "DATE(al.created_at) >= :date_from";
  $params[':date_from'] = $date_from;
}

if($date_to !== '') {
  $filters[] = "DATE(al.created_at) <= :date_to";
  $params[':date_to'] = $date_to;
}

$where_clause = implode(' AND ', $filters);

$logs = [];
try {
    $stmt = $pdo->prepare(
    "SELECT al.*, u.full_name AS actor_name, u.role AS actor_role
         FROM audit_logs al
     LEFT JOIN users u ON al.user_id = u.user_id
     WHERE $where_clause
         ORDER BY al.created_at DESC
         LIMIT 100"
    );
  $stmt->execute($params);
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
            <form method="GET" class="grid grid-cols-4 gap-md mb-3 align-end">
              <div class="form-group mb-0">
                <label class="form-label">Action</label>
                <select name="action" class="form-control">
                  <option value="">All actions</option>
                  <?php foreach($valid_actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>><?php echo htmlspecialchars($action); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group mb-0">
                <label class="form-label">Actor Name</label>
                <input type="text" name="actor" class="form-control" value="<?php echo htmlspecialchars($actor_filter); ?>" placeholder="Search actor name">
              </div>
              <div class="form-group mb-0">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
              </div>
              <div class="form-group mb-0">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
              </div>
              <div class="form-group mb-0 d-flex gap-sm">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="audit_logs.php" class="btn btn-outline">Reset</a>
              </div>
            </form>
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
                    <td class="font-bold"><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                    <td><span class="badge <?php echo action_badge_class($log['action_type']); ?>"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                    <td>
                      <?php echo htmlspecialchars((string)$log['entity_type']); ?>
                      <?php if(!empty($log['entity_id'])): ?>
                        #<?php echo intval($log['entity_id']); ?>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)$log['details']); ?></td>
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
