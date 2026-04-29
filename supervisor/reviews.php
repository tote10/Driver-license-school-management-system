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

$reviews = [];
try {
    $stmt = $pdo->prepare(
        "SELECT tr.record_id, tr.created_at, tr.lesson_type, tr.attendance_status, tr.performance_score,
                tr.feedback, tr.review_notes, tr.instructor_recommendation_for_exam,
                s.full_name AS student_name, i.full_name AS instructor_name, tp.name AS program_name
         FROM training_records tr
         JOIN users s ON tr.student_user_id = s.user_id
         JOIN users i ON tr.instructor_user_id = i.user_id
         LEFT JOIN enrollments e ON e.student_user_id = s.user_id AND e.approval_status = 'approved'
         LEFT JOIN training_programs tp ON tp.program_id = e.program_id
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
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($reviews) === 0): ?>
                  <tr><td colspan="6" class="text-center text-muted">No training records found.</td></tr>
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
