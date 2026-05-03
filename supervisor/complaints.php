<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$supervisor_id = intval($_SESSION['user_id'] ?? 0);
$full_name = ds_display_name('Supervisor');
$initials = ds_display_initials($full_name, 'Supervisor');
$message = '';
$csrf_error = $_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validate_request();
if($csrf_error){
  $message = "<div class='toast show bg-danger'>Security validation failed. Please refresh and try again.</div>";
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

if($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_error && ($_POST['action'] ?? '') === 'update_complaint') {
  $complaint_id = intval($_POST['complaint_id'] ?? 0);
  $new_status = strtolower(trim((string)($_POST['new_status'] ?? '')));
  $internal_note = trim((string)($_POST['internal_note'] ?? ''));
  $student_reply = trim((string)($_POST['student_reply'] ?? ''));

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
    if($new_status === 'forwarded' && $internal_note === '') {
      throw new Exception('Add forwarding note before sending to manager.');
    }

    $final_resolution = $internal_note !== '' ? $internal_note : complaint_status_label($new_status) . ' by supervisor';

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
      $final_resolution,
      $complaint_id,
    ]);

    $statusLabel = complaint_status_label($new_status);
    $studentTitle = 'Complaint update from supervisor';
    $studentMessage = 'Your complaint #' . intval($complaint_id) . ' is now "' . $statusLabel . '".';
    if($student_reply !== '') {
      $studentMessage .= ' Response: ' . $student_reply;
    }
    send_notification(
      $pdo,
      intval($complaint['reporter_user_id'] ?? 0),
      'complaint_update',
      $studentTitle,
      $studentMessage
    );

    if($new_status === 'forwarded') {
      $managerTitle = 'Complaint forwarded by supervisor';
      $managerMessage = 'Complaint #' . intval($complaint_id)
        . ' from ' . ($complaint['reporter_name'] ?? 'student')
        . ' has been forwarded for manager review. Note: ' . $final_resolution;
      $notifiedCount = send_notification_to_branch_role(
        $pdo,
        $branch_id,
        'manager',
        'complaint_forwarded',
        $managerTitle,
        $managerMessage
      );

      if($notifiedCount === 0) {
        $message = "<div class='toast show bg-danger'>Complaint marked as forwarded, but no active manager in this branch received notification.</div>";
      } else {
        $message = "<div class='toast show'>Complaint forwarded to manager successfully.</div>";
      }
    } else {
      $message = "<div class='toast show'>Complaint updated successfully.</div>";
    }
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
} catch(PDOException $e) {
  error_log('Supervisor complaints query error: ' . $e->getMessage());
}

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
    <style>
      .complaints-table {
        width: 100%;
        table-layout: fixed;
      }
      .complaints-table th,
      .complaints-table td {
        overflow-wrap: anywhere;
        word-break: break-word;
        vertical-align: top;
      }
      .complaints-table .complaints-action-form {
        min-width: 240px;
        width: 100%;
      }
      .complaints-table .complaints-action-form .form-control,
      .complaints-table .complaints-action-form textarea {
        width: 100%;
      }
      .complaints-action-title {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted, #6b7280);
      }
      .complaints-action-note {
        font-size: 0.78rem;
        color: var(--text-muted, #6b7280);
        line-height: 1.4;
      }
    </style>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>
          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Complaints Dashboard</h3>
            <p class="text-sm text-muted mb-3">Review branch complaints, track resolution status, and forward serious cases when needed.</p>

            <div class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100 mb-4">
              <div class="d-flex gap-md flex-wrap">
                <a href="dashboard.php" class="btn btn-outline">Supervisor Dashboard</a>
                <a href="assignments.php" class="btn btn-outline">Instructor Assignments</a>
                <a href="reviews.php" class="btn btn-outline">Training Review</a>
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
              <table class="table complaints-table">
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
                    <td><span class="badge <?php echo complaint_status_class($complaint['status']); ?>"><?php echo htmlspecialchars(complaint_status_label($complaint['status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($complaint['reviewer_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($complaint['resolution'] ?? '-'); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($complaint['reported_date'])); ?></td>
                    <td>
                      <form method="post" class="d-flex flex-col gap-sm complaints-action-form" style="margin: 0;">
                        <input type="hidden" name="action" value="update_complaint">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['complaint_id']); ?>">
                        <div class="complaints-action-title">Update status</div>
                        <select name="new_status" class="form-control form-control-sm" required>
                          <option value="open" <?php echo $complaint['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                          <option value="in_review" <?php echo $complaint['status'] === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                          <option value="forwarded" <?php echo $complaint['status'] === 'forwarded' ? 'selected' : ''; ?>>Forward to Manager</option>
                          <option value="resolved" <?php echo $complaint['status'] === 'resolved' ? 'selected' : ''; ?>>Mark Resolved</option>
                          <option value="closed" <?php echo $complaint['status'] === 'closed' ? 'selected' : ''; ?>>Close</option>
                        </select>
                        <div>
                          <div class="complaints-action-title">Internal note</div>
                          <textarea name="internal_note" class="form-control form-control-sm" rows="2" placeholder="Internal note for staff and manager review (required when forwarding)"><?php echo htmlspecialchars($complaint['resolution'] ?? ''); ?></textarea>
                        </div>
                        <div>
                          <div class="complaints-action-title">Student reply</div>
                          <textarea name="student_reply" class="form-control form-control-sm" rows="2" placeholder="Message that will be sent to the student as a notification"></textarea>
                        </div>
                        <div class="complaints-action-note">Use internal note for private review details. Use student reply for the message the student will see.</div>
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
