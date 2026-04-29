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

$schedules = [];
try {
    $stmt = $pdo->prepare(
        "SELECT sch.schedule_id, sch.lesson_type, sch.scheduled_datetime, sch.duration_minutes, sch.location,
                s.full_name AS student_name, i.full_name AS instructor_name
         FROM training_schedules sch
         JOIN users s ON sch.student_user_id = s.user_id
         JOIN users i ON sch.instructor_user_id = i.user_id
         WHERE sch.branch_id = ?
         ORDER BY sch.scheduled_datetime ASC
         LIMIT 50"
    );
    $stmt->execute([$branch_id]);
    $schedules = $stmt->fetchAll();
} catch(PDOException $e) {}

$page_title = 'Training Schedules';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Training Schedules</title>
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
            <h3 class="card-subtitle mb-2">Training Schedules</h3>
            <p class="text-sm text-muted mb-3">View branch training sessions here. Scheduling actions can be added later without changing the page structure.</p>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Instructor</th>
                    <th>Lesson</th>
                    <th>Date & Time</th>
                    <th>Duration</th>
                    <th>Location</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($schedules as $schedule): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($schedule['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['instructor_name']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['lesson_type']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($schedule['scheduled_datetime'])); ?></td>
                    <td><?php echo intval($schedule['duration_minutes']); ?> min</td>
                    <td><?php echo htmlspecialchars($schedule['location'] ?? '-'); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($schedules) === 0): ?>
                  <tr><td colspan="6" class="text-center text-muted">No schedules found in this branch.</td></tr>
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
