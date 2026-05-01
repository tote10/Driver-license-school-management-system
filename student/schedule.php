<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../manager/includes/graduation_helpers.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$page_title = 'My Schedule';
$schedule_has_program_column = manager_table_has_column($pdo, 'training_schedules', 'program_id');

try {
  $stmt = $schedule_has_program_column
    ? $pdo->prepare("SELECT sch.*, u.full_name AS instructor_name, tp.name AS program_name FROM training_schedules sch LEFT JOIN users u ON sch.instructor_user_id = u.user_id LEFT JOIN training_programs tp ON sch.program_id = tp.program_id WHERE sch.student_user_id = ? ORDER BY sch.scheduled_datetime ASC")
    : $pdo->prepare("SELECT sch.*, u.full_name AS instructor_name, tp.name AS program_name FROM training_schedules sch LEFT JOIN users u ON sch.instructor_user_id = u.user_id LEFT JOIN enrollments e ON e.student_user_id = sch.student_user_id AND e.approval_status = 'approved' AND e.current_progress_status = 'enrolled' LEFT JOIN training_programs tp ON tp.program_id = e.program_id WHERE sch.student_user_id = ? ORDER BY sch.scheduled_datetime ASC");
    $stmt->execute([$student_id]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all = [];
}

$now = new DateTime();
$upcoming = $past = [];
foreach ($all as $row) {
    $dt = new DateTime($row['scheduled_datetime']);
    if ($dt >= $now) $upcoming[] = $row; else $past[] = $row;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
  </head>
  <body>
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>

      <main class="page-content">
        <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Upcoming Lessons</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr><th>Instructor</th><th>Program</th><th>Lesson</th><th>Date & Time</th><th>Location</th><th>Status</th></tr>
                </thead>
                <tbody>
                  <?php foreach($upcoming as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['instructor_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['program_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['lesson_type']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($r['scheduled_datetime'])); ?></td>
                    <td><?php echo htmlspecialchars($r['location'] ?? '-'); ?></td>
                    <td><span class="badge <?php echo htmlspecialchars($r['status'] === 'completed' ? 'badge-success' : 'badge-warning'); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($upcoming)===0): ?><tr><td colspan="6" class="text-center text-muted">No upcoming lessons.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
        </div>

          <div class="card">
            <h3 class="card-subtitle mb-2">Past Lessons</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr><th>Instructor</th><th>Program</th><th>Lesson</th><th>Date & Time</th><th>Location</th><th>Status</th></tr>
                </thead>
                <tbody>
                  <?php foreach($past as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['instructor_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['program_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['lesson_type']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($r['scheduled_datetime'])); ?></td>
                    <td><?php echo htmlspecialchars($r['location'] ?? '-'); ?></td>
                    <td><span class="badge <?php echo htmlspecialchars($r['status'] === 'completed' ? 'badge-success' : 'badge-outline'); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($past)===0): ?><tr><td colspan="6" class="text-center text-muted">No past lessons.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
      </main>
      </div>
    </div>
  </body>
</html>
