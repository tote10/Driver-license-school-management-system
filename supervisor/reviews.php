<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../manager/includes/graduation_helpers.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$supervisor_id = intval($_SESSION['user_id']);
$full_name = $_SESSION['full_name'] ?? 'Supervisor';
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

$message = '';
$reviews = [];

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_readiness') {
  $record_id = intval($_POST['record_id'] ?? 0);
  try {
    $stmt = $pdo->prepare(
      "UPDATE training_records
       SET reviewed_by = ?, review_notes = ?, created_at = created_at
       WHERE record_id = ?
         AND instructor_recommendation_for_exam = 1"
    );
    $stmt->execute([$supervisor_id, 'Approved by supervisor for exam readiness', $record_id]);
    $message = "<div class='toast show'>Readiness approved.</div>";
  } catch(PDOException $e) {
    $message = "<div class='toast show bg-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
  }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_readiness') {
  $record_id = intval($_POST['record_id'] ?? 0);
  try {
    $stmt = $pdo->prepare(
      "UPDATE training_records
       SET reviewed_by = ?, review_notes = ?, created_at = created_at
       WHERE record_id = ?
         AND instructor_recommendation_for_exam = 1"
    );
    $stmt->execute([$supervisor_id, 'Rejected by supervisor for exam readiness', $record_id]);
    $message = "<div class='toast show bg-danger'>Readiness rejected.</div>";
  } catch(PDOException $e) {
    $message = "<div class='toast show bg-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
  }
}

$reviews = [];
try {
  $stmt = $pdo->prepare(
    "SELECT tr.record_id, tr.created_at, tr.lesson_type, tr.attendance_status, tr.performance_score,
            tr.feedback, tr.review_notes, tr.instructor_recommendation_for_exam,
            s.full_name AS student_name, i.full_name AS instructor_name, tp.name AS program_name
     FROM training_records tr
     JOIN users s ON tr.student_user_id = s.user_id
     JOIN users i ON tr.instructor_user_id = i.user_id
     LEFT JOIN training_programs tp ON tr.program_id = tp.program_id
     WHERE s.branch_id = ? AND i.branch_id = ?
     ORDER BY tr.created_at DESC
     LIMIT 50"
  );
  $stmt->execute([$branch_id, $branch_id]);
  $reviews = $stmt->fetchAll();
} catch(PDOException $e) {}

$page_title = 'Training Reviews';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Training Reviews</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <main class="page-content">
          <div class="toast-container"><?php if($message) echo $message; ?></div>
          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Training Reviews</h3>
            <p class="text-sm text-muted mb-3">Review records submitted by instructors. Supervisor sign-off is managed from here in the next step.</p>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Instructor</th>
                    <th>Program</th>
                    <th>Score</th>
                    <th>Attendance</th>
                    <th>Ready</th>
                    <th>Supervisor</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($reviews as $review): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($review['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($review['instructor_name']); ?></td>
                    <td><?php echo htmlspecialchars($review['program_name'] ?? 'N/A'); ?></td>
                    <td><?php echo $review['performance_score'] !== null ? number_format((float)$review['performance_score'], 2) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($review['attendance_status']); ?></td>
                    <td><?php echo ((int)$review['instructor_recommendation_for_exam'] === 1) ? 'Yes' : 'No'; ?></td>
                    <td>
                      <?php if((int)$review['instructor_recommendation_for_exam'] === 1 && empty($review['review_notes'])): ?>
                        <form method="POST" style="margin:0;">
                          <input type="hidden" name="action" value="approve_readiness">
                          <input type="hidden" name="record_id" value="<?php echo intval($review['record_id']); ?>">
                          <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                        </form>
                        <form method="POST" style="margin:0; margin-top:8px;">
                          <input type="hidden" name="action" value="reject_readiness">
                          <input type="hidden" name="record_id" value="<?php echo intval($review['record_id']); ?>">
                          <button type="submit" class="btn btn-outline btn-sm" style="border-color: var(--danger); color: var(--danger);">Reject</button>
                        </form>
                      <?php elseif(stripos((string)$review['review_notes'], 'Rejected by supervisor') === 0): ?>
                        <span class="badge badge-danger">Rejected</span>
                      <?php elseif(stripos((string)$review['review_notes'], 'Approved by supervisor') === 0): ?>
                        <span class="badge badge-success">Approved</span>
                      <?php else: ?>
                        <span class="badge badge-outline">Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($reviews) === 0): ?>
                  <tr><td colspan="7" class="text-center text-muted">No training records found.</td></tr>
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
