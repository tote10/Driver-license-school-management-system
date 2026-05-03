<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$full_name = ds_display_name('Supervisor');
$initials = ds_display_initials($full_name, 'Supervisor');

$schedules = [];

try {
    $stmt = $pdo->prepare(
    "SELECT sch.schedule_id, sch.lesson_type, sch.scheduled_datetime, sch.duration_minutes, sch.location, sch.status,
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
            <p class="text-sm text-muted mb-3">Review branch lesson timing, track status, and spot upcoming sessions quickly.</p>

            <div class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100 mb-4">
              <div class="d-flex gap-md flex-wrap">
                <a href="dashboard.php" class="btn btn-outline">Supervisor Dashboard</a>
                <a href="assignments.php" class="btn btn-outline">Instructor Assignments</a>
                <a href="reviews.php" class="btn btn-outline">Training Review</a>
              </div>
              <div>
                <a href="complaints.php" class="btn btn-outline">Complaints</a>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-md mb-3">
              <div class="form-group mb-0">
                <label class="form-label">Filter by student</label>
                <input type="text" id="schedule-filter-student" class="form-control" placeholder="Type student name" autocomplete="off">
              </div>
              <div class="form-group mb-0">
                <label class="form-label">Filter by instructor</label>
                <input type="text" id="schedule-filter-instructor" class="form-control" placeholder="Type instructor name" autocomplete="off">
              </div>
              <div class="form-group mb-0">
                <label class="form-label">Filter by lesson</label>
                <input type="text" id="schedule-filter-lesson" class="form-control" placeholder="Type lesson type" autocomplete="off">
              </div>
            </div>

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
                  <tr data-student="<?php echo htmlspecialchars(strtolower((string)$schedule['student_name'])); ?>" data-instructor="<?php echo htmlspecialchars(strtolower((string)$schedule['instructor_name'])); ?>" data-lesson="<?php echo htmlspecialchars(strtolower((string)$schedule['lesson_type'])); ?>">
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
    <script>
      (function () {
        var studentFilter = document.getElementById('schedule-filter-student');
        var instructorFilter = document.getElementById('schedule-filter-instructor');
        var lessonFilter = document.getElementById('schedule-filter-lesson');
        var rows = document.querySelectorAll('tbody tr[data-student]');

        function applyFilters() {
          var studentTerm = (studentFilter?.value || '').trim().toLowerCase();
          var instructorTerm = (instructorFilter?.value || '').trim().toLowerCase();
          var lessonTerm = (lessonFilter?.value || '').trim().toLowerCase();

          rows.forEach(function (row) {
            var student = row.getAttribute('data-student') || '';
            var instructor = row.getAttribute('data-instructor') || '';
            var lesson = row.getAttribute('data-lesson') || '';

            var matchStudent = studentTerm === '' || student.indexOf(studentTerm) !== -1;
            var matchInstructor = instructorTerm === '' || instructor.indexOf(instructorTerm) !== -1;
            var matchLesson = lessonTerm === '' || lesson.indexOf(lessonTerm) !== -1;

            row.style.display = matchStudent && matchInstructor && matchLesson ? '' : 'none';
          });
        }

        [studentFilter, instructorFilter, lessonFilter].forEach(function (input) {
          if (input) input.addEventListener('input', applyFilters);
        });
      })();
    </script>
  </body>
</html>
