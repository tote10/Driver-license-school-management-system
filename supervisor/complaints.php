<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? 'Supervisor';
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

$complaints = [];
$status_filter = strtolower(trim((string)($_GET['status'] ?? 'all')));

function complaint_status_label($status) {
  $status = strtolower(trim((string)$status));
  return match($status) {
    'open' => 'Open',
    'in_review' => 'In Review',
    'forwarded' => 'Forwarded',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
    default => ucfirst($status),
  };
}

function complaint_status_class($status) {
  $status = strtolower(trim((string)$status));
  return match($status) {
    'open' => 'badge-warning',
    'in_review' => 'badge-outline',
    'forwarded' => 'badge-danger',
    'resolved' => 'badge-success',
    'closed' => 'badge-danger',
    default => 'badge-outline',
  };
}

function complaint_status_is_archive($status) {
  return in_array(strtolower(trim((string)$status)), ['resolved', 'closed'], true);
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_complaint') {
  $complaint_id = intval($_POST['complaint_id'] ?? 0);
  $new_status = strtolower(trim((string)($_POST['new_status'] ?? '')));
  $resolution = trim((string)($_POST['resolution'] ?? ''));

  try {
    if($complaint_id <= 0) {
      throw new Exception('Invalid complaint selected.');
    }
    if(!in_array($new_status, ['open', 'in_review', 'forwarded', 'resolved', 'closed'], true)) {
      throw new Exception('Select a valid complaint status.');
    }

    $stmtComplaint = $pdo->prepare(
      "SELECT c.complaint_id, c.reporter_user_id, c.status, reporter.branch_id, reporter.full_name AS reporter_name
       FROM complaints c
       JOIN users reporter ON c.reporter_user_id = reporter.user_id
       WHERE c.complaint_id = ?
       LIMIT 1"
    );
    $stmtComplaint->execute([$complaint_id]);
    $complaint = $stmtComplaint->fetch(PDO::FETCH_ASSOC);

    if(!$complaint) {
      throw new Exception('Complaint not found.');
    }
    if(intval($complaint['branch_id']) !== $branch_id) {
      throw new Exception('Complaint is not in your branch.');
    }

    $stmtUpdate = $pdo->prepare(
      "UPDATE complaints
       SET status = ?,
           reviewed_by = ?,
           resolution = ?,
           resolution_date = CURRENT_TIMESTAMP
       WHERE complaint_id = ?"
    );
    $stmtUpdate->execute([
      $new_status,
      $supervisor_id,
      $resolution !== '' ? $resolution : complaint_status_label($new_status) . ' by supervisor',
      $complaint_id,
    ]);

    $message = "<div class='toast show'>Complaint updated successfully.</div>";
  } catch(Exception $e) {
    $message = "<div class='toast show bg-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
  }
}

try {
  $sql =
    "SELECT c.complaint_id, c.subject_type, c.description, c.status, c.resolution, c.reported_date, c.resolution_date,
        reporter.full_name AS reporter_name,
        reviewer.full_name AS reviewer_name
     FROM complaints c
     JOIN users reporter ON c.reporter_user_id = reporter.user_id
     LEFT JOIN users reviewer ON c.reviewed_by = reviewer.user_id
     WHERE reporter.branch_id = ?";
  $params = [$branch_id];
  if($status_filter !== 'all') {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
  }
  $sql .= " ORDER BY c.reported_date DESC LIMIT 50";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
    $complaints = $stmt->fetchAll();
} catch(PDOException $e) {}

$page_title = 'Complaints Dashboard';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Complaints</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <main class="page-content">
          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Complaints Dashboard</h3>
            <p class="text-sm text-muted mb-3">Review branch complaints, track resolution status, and forward serious cases when needed.</p>

            <div class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100 mb-4">
              <div class="d-flex gap-md flex-wrap">
                <a href="dashboard.php" class="btn btn-outline">Supervisor Dashboard</a>
                <a href="assignments.php" class="btn btn-outline">Instructor Assignments</a>
                <a href="progress.php" class="btn btn-outline">Student Progress</a>
              </div>
              <div>
                <a href="schedules.php" class="btn btn-outline">Schedules</a>
              </div>
            </div>

            <div class="text-sm text-muted mb-4">Current filter: <?php echo htmlspecialchars(complaint_status_label($status_filter)); ?></div>

            <div class="d-flex flex-wrap gap-sm mb-4">
              <a href="complaints.php" class="btn btn-outline btn-sm">All</a>
              <a href="complaints.php?status=open" class="btn btn-outline btn-sm">Open</a>
              <a href="complaints.php?status=in_review" class="btn btn-outline btn-sm">In Review</a>
              <a href="complaints.php?status=forwarded" class="btn btn-outline btn-sm">Forwarded</a>
              <a href="complaints.php?status=resolved" class="btn btn-outline btn-sm">Resolved Archive</a>
              <a href="complaints.php?status=closed" class="btn btn-outline btn-sm">Closed Archive</a>
            </div>

            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Reporter</th>
                    <th>Subject</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Reviewer</th>
                    <th>Resolution</th>
                    <th>Reported</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($complaints as $complaint): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($complaint['reporter_name']); ?></td>
                    <td><?php echo htmlspecialchars($complaint['subject_type']); ?></td>
                    <td><?php echo htmlspecialchars($complaint['description']); ?></td>
                    <td><span class="badge <?php echo complaint_status_class($complaint['status']); ?>"><?php echo htmlspecialchars($complaint['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($complaint['reviewer_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($complaint['resolution'] ?? '-'); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($complaint['reported_date'])); ?></td>
                    <td>
                      <form method="post" class="d-flex flex-col gap-sm" style="min-width: 240px; margin: 0;">
                        <input type="hidden" name="action" value="update_complaint">
                        <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['complaint_id']); ?>">
                        <select name="new_status" class="form-control form-control-sm" required>
                          <option value="open" <?php echo $complaint['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                          <option value="in_review" <?php echo $complaint['status'] === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                          <option value="forwarded" <?php echo $complaint['status'] === 'forwarded' ? 'selected' : ''; ?>>Forward to Manager</option>
                          <option value="resolved" <?php echo $complaint['status'] === 'resolved' ? 'selected' : ''; ?>>Mark Resolved</option>
                          <option value="closed" <?php echo $complaint['status'] === 'closed' ? 'selected' : ''; ?>>Close</option>
                        </select>
                        <input type="text" name="resolution" class="form-control form-control-sm" placeholder="Add notes or resolution" value="<?php echo htmlspecialchars($complaint['resolution'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($complaints) === 0): ?>
                  <tr><td colspan="8" class="text-center text-muted">No complaints found in this branch.</td></tr>
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
