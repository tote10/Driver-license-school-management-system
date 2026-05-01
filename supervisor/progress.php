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

$progress = [];

try {
    $stmt = $pdo->prepare(
    "SELECT s.user_id, s.full_name AS student_name, tp.name AS program_name,
        e.current_progress_status,
        COUNT(tr.record_id) AS session_count,
        COUNT(tr.performance_score) AS scored_session_count,
        SUM(tr.performance_score) AS score_total,
        AVG(tr.performance_score) AS avg_score,
        SUM(CASE WHEN tr.attendance_status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN tr.instructor_recommendation_for_exam = 1 THEN 1 ELSE 0 END) AS exam_ready_count,
        MAX(tr.created_at) AS last_session
         FROM users s
         JOIN enrollments e ON e.student_user_id = s.user_id
         JOIN training_programs tp ON tp.program_id = e.program_id
         LEFT JOIN training_records tr ON tr.student_user_id = s.user_id
         LEFT JOIN training_schedules sch ON tr.schedule_id = sch.schedule_id
         WHERE s.branch_id = ?
           AND e.approval_status = 'approved'
           AND e.current_progress_status <> 'failed'
           AND tp.branch_id = ?
           AND (sch.branch_id = ? OR sch.branch_id IS NULL)
         GROUP BY s.user_id, s.full_name, tp.name, e.current_progress_status
         ORDER BY last_session DESC, s.full_name ASC"
    );
    $stmt->execute([$branch_id, $branch_id, $branch_id]);
    $progress = $stmt->fetchAll();
} catch(PDOException $e) {}

    $page_title = 'Student Progress Dashboard';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Progress</title>
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
            <h3 class="card-subtitle mb-2">Student Progress</h3>
            <p class="text-sm text-muted mb-3">Track branch progress across instructors and spot students approaching exam readiness.</p>

            <div class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100 mb-4">
              <div class="d-flex gap-md flex-wrap">
                <a href="dashboard.php" class="btn btn-outline">Supervisor Dashboard</a>
                <a href="assignments.php" class="btn btn-outline">Instructor Assignments</a>
                <a href="complaints.php" class="btn btn-outline">Complaints</a>
              </div>
              <div>
                <a href="schedules.php" class="btn btn-outline">Schedules</a>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Sessions</th>
                    <th>Avg Score</th>
                    <th>Present</th>
                    <th>Ready</th>
                    <th>Last Session</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($progress as $row): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($row['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['program_name']); ?></td>
                    <td><span class="badge badge-outline"><?php echo htmlspecialchars($row['current_progress_status']); ?></span></td>
                    <td><?php echo intval($row['session_count']); ?></td>
                    <td><?php echo $row['avg_score'] !== null ? number_format((float)$row['avg_score'], 2) : '-'; ?></td>
                    <td><?php echo intval($row['present_count']); ?></td>
                    <td><?php echo intval($row['exam_ready_count']) > 0 ? 'Yes' : 'No'; ?></td>
                    <td><?php echo $row['last_session'] ? date('Y-m-d H:i', strtotime($row['last_session'])) : '-'; ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($progress) === 0): ?>
                  <tr><td colspan="8" class="text-center text-muted">No student progress data available yet.</td></tr>
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
