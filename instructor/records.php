<?php
require_once __DIR__ . '/includes/common.php';

$page_title = 'Training Records';
$message = '';
$selected_student_id = intval($_GET['student_id'] ?? 0);
$students = [];
$selected_student = [];
$student_schedules = [];
$recent_records = [];

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_record') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    $lesson_type = trim((string)($_POST['lesson_type'] ?? ''));
    $attendance_status = trim((string)($_POST['attendance_status'] ?? 'present'));
    $performance_score = trim((string)($_POST['performance_score'] ?? ''));
    $feedback = trim((string)($_POST['feedback'] ?? ''));
    $recommend_exam = isset($_POST['instructor_recommendation_for_exam']) ? 1 : 0;

    try {
        if($student_id <= 0 || $lesson_type === '') {
            throw new Exception('Select a student and lesson type.');
        }
        if(!in_array($attendance_status, ['present', 'absent', 'late'], true)) {
            throw new Exception('Select a valid attendance status.');
        }
        if(!in_array($lesson_type, ['theory', 'practical'], true)) {
            throw new Exception('Select a valid lesson type.');
        }

        $stmtStudent = $pdo->prepare(
            "SELECT s.user_id, s.full_name
             FROM users s
             JOIN instructor_assignments ia ON ia.student_user_id = s.user_id
             WHERE s.user_id = ?
               AND s.branch_id = ?
               AND ia.instructor_user_id = ?
               AND ia.status = 'active'"
        );
        $stmtStudent->execute([$student_id, $branch_id, $instructor_id]);
        $selected_student = $stmtStudent->fetch(PDO::FETCH_ASSOC);
        if(!$selected_student) {
            throw new Exception('Student is not assigned to you.');
        }

        $schedule = null;
        if($schedule_id > 0) {
            $stmtSchedule = $pdo->prepare(
                "SELECT sch.schedule_id, sch.lesson_type, sch.status, sch.student_user_id, sch.instructor_user_id
                 FROM training_schedules sch
                 JOIN users s ON sch.student_user_id = s.user_id
                 WHERE sch.schedule_id = ?
                   AND sch.instructor_user_id = ?
                   AND s.branch_id = ?"
            );
            $stmtSchedule->execute([$schedule_id, $instructor_id, $branch_id]);
            $schedule = $stmtSchedule->fetch(PDO::FETCH_ASSOC);
            if(!$schedule) {
                throw new Exception('Selected schedule is not available to you.');
            }
            if(intval($schedule['student_user_id']) !== $student_id) {
                throw new Exception('Selected schedule does not belong to the selected student.');
            }
            if($lesson_type === '') {
                $lesson_type = $schedule['lesson_type'];
            }

            $stmtRecordCheck = $pdo->prepare("SELECT record_id FROM training_records WHERE schedule_id = ? LIMIT 1");
            $stmtRecordCheck->execute([$schedule_id]);
            if($stmtRecordCheck->fetchColumn()) {
                throw new Exception('This schedule already has a training record.');
            }
        }

        $scoreValue = $performance_score === '' ? null : (float)$performance_score;
        $stmtInsert = $pdo->prepare(
            "INSERT INTO training_records (schedule_id, student_user_id, instructor_user_id, lesson_type, performance_score, feedback, attendance_status, instructor_recommendation_for_exam, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtInsert->execute([
            $schedule_id > 0 ? $schedule_id : null,
            $student_id,
            $instructor_id,
            $lesson_type,
            $scoreValue,
            $feedback,
            $attendance_status,
            $recommend_exam,
            $instructor_id,
        ]);

        if($schedule_id > 0) {
            $stmtUpdateSchedule = $pdo->prepare("UPDATE training_schedules SET status = 'completed' WHERE schedule_id = ? AND instructor_user_id = ?");
            $stmtUpdateSchedule->execute([$schedule_id, $instructor_id]);
        }

        $message = "<div class='toast show'>Training record saved successfully.</div>";
        $selected_student_id = $student_id;
    } catch(Exception $e) {
        $message = "<div class='toast show bg-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

try {
    $stmtStudents = $pdo->prepare(
        "SELECT ia.student_user_id AS student_id, s.full_name AS student_name,
                st.license_category,
                tp.name AS program_name
         FROM instructor_assignments ia
         JOIN users s ON ia.student_user_id = s.user_id
         JOIN students st ON st.user_id = s.user_id
         JOIN (
            SELECT student_user_id, MAX(enrollment_id) AS enrollment_id
            FROM enrollments
            WHERE approval_status = 'approved' AND current_progress_status = 'enrolled'
            GROUP BY student_user_id
         ) latest ON latest.student_user_id = s.user_id
         JOIN enrollments e ON e.enrollment_id = latest.enrollment_id
         JOIN training_programs tp ON tp.program_id = e.program_id
         WHERE ia.instructor_user_id = ?
           AND ia.status = 'active'
           AND s.branch_id = ?
           AND tp.branch_id = ?
         ORDER BY s.full_name ASC"
    );
    $stmtStudents->execute([$instructor_id, $branch_id, $branch_id]);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    if($selected_student_id > 0) {
        $stmtSelected = $pdo->prepare(
            "SELECT s.user_id AS student_id, s.full_name AS student_name,
                    st.license_category,
                    tp.name AS program_name
             FROM users s
             JOIN students st ON st.user_id = s.user_id
             JOIN instructor_assignments ia ON ia.student_user_id = s.user_id
             JOIN (
                SELECT student_user_id, MAX(enrollment_id) AS enrollment_id
                FROM enrollments
                WHERE approval_status = 'approved' AND current_progress_status = 'enrolled'
                GROUP BY student_user_id
             ) latest ON latest.student_user_id = s.user_id
             JOIN enrollments e ON e.enrollment_id = latest.enrollment_id
             JOIN training_programs tp ON tp.program_id = e.program_id
             WHERE s.user_id = ? AND s.branch_id = ? AND ia.instructor_user_id = ? AND ia.status = 'active' AND tp.branch_id = ?
             LIMIT 1"
        );
        $stmtSelected->execute([$selected_student_id, $branch_id, $instructor_id, $branch_id]);
        $selected_student = $stmtSelected->fetch(PDO::FETCH_ASSOC) ?: [];

        if($selected_student) {
            $stmtSchedules = $pdo->prepare(
                "SELECT schedule_id, lesson_type, scheduled_datetime, duration_minutes, location, status
                 FROM training_schedules
                 WHERE student_user_id = ? AND instructor_user_id = ? AND branch_id = ?
                 ORDER BY scheduled_datetime DESC"
            );
            $stmtSchedules->execute([$selected_student_id, $instructor_id, $branch_id]);
            $student_schedules = $stmtSchedules->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $stmtRecent = $pdo->prepare(
        "SELECT tr.record_id, tr.created_at, tr.lesson_type, tr.performance_score, tr.attendance_status,
                tr.instructor_recommendation_for_exam, tr.feedback,
                s.full_name AS student_name
         FROM training_records tr
         JOIN users s ON tr.student_user_id = s.user_id
         WHERE tr.instructor_user_id = ?
         ORDER BY tr.created_at DESC
         LIMIT 12"
    );
    $stmtRecent->execute([$instructor_id]);
    $recent_records = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Training Records</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        <main class="page-content">
          <div class="toast-container"><?php if($message) echo $message; ?></div>

          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Record Training Session</h3>
            <p class="text-sm text-muted mb-3">Record sessions only for students assigned to you by the Supervisor.</p>

            <form method="GET" class="mb-3">
              <div class="form-group mb-0">
                <label class="form-label">Select Student</label>
                <select name="student_id" class="form-control" onchange="this.form.submit()" style="min-width: 260px;">
                  <option value="">-- Select Assigned Student --</option>
                  <?php foreach($students as $student): ?>
                    <option value="<?php echo intval($student['student_id']); ?>" <?php echo $selected_student_id === intval($student['student_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($student['student_name']); ?> (<?php echo htmlspecialchars($student['program_name']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>

            <form method="POST" class="grid grid-cols-2 gap-md align-end">
              <input type="hidden" name="action" value="save_record">
              <div class="form-group">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-control" required>
                  <option value="">-- Choose Student --</option>
                  <?php foreach($students as $student): ?>
                    <option value="<?php echo intval($student['student_id']); ?>" <?php echo $selected_student_id === intval($student['student_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($student['student_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Schedule</label>
                <select name="schedule_id" class="form-control">
                  <option value="">-- Optional Schedule --</option>
                  <?php foreach($student_schedules as $schedule): ?>
                    <option value="<?php echo intval($schedule['schedule_id']); ?>">
                      <?php echo htmlspecialchars($schedule['lesson_type']); ?> - <?php echo date('Y-m-d H:i', strtotime($schedule['scheduled_datetime'])); ?> (<?php echo intval($schedule['duration_minutes']); ?> min)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Lesson Type</label>
                <select name="lesson_type" class="form-control" required>
                  <option value="theory">Theory</option>
                  <option value="practical">Practical</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Attendance</label>
                <select name="attendance_status" class="form-control" required>
                  <option value="present">Present</option>
                  <option value="late">Late</option>
                  <option value="absent">Absent</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Performance Score</label>
                <input type="number" name="performance_score" class="form-control" step="0.01" min="0" max="100" placeholder="e.g. 88.5">
              </div>
              <div class="form-group">
                <label class="form-label">Feedback</label>
                <textarea name="feedback" class="form-control" rows="3" placeholder="Lesson notes and feedback"></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Exam Recommendation</label>
                <label class="d-flex align-center gap-sm" style="margin-top: 0.5rem;">
                  <input type="checkbox" name="instructor_recommendation_for_exam" value="1">
                  <span>Recommend this student for exam readiness</span>
                </label>
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-primary">Save Training Record</button>
              </div>
            </form>
          </div>

          <div class="grid grid-cols-2 gap-md">
            <div class="card">
              <h3 class="card-subtitle mb-2">Assigned Student Sessions</h3>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Program</th>
                      <th>Sessions</th>
                      <th>Progress</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($students as $student): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($student['student_name']); ?></td>
                      <td><?php echo htmlspecialchars($student['program_name']); ?></td>
                      <td><?php echo intval($student['session_count'] ?? 0); ?></td>
                      <td><?php echo $selected_student_id === intval($student['student_id']) ? 'Selected' : 'Active'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($students) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted">No assigned students yet.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card">
              <h3 class="card-subtitle mb-2">Selected Student Schedule</h3>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Lesson</th>
                      <th>Date</th>
                      <th>Duration</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($student_schedules as $schedule): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($schedule['lesson_type']); ?></td>
                      <td><?php echo date('Y-m-d H:i', strtotime($schedule['scheduled_datetime'])); ?></td>
                      <td><?php echo intval($schedule['duration_minutes']); ?> min</td>
                      <td><span class="badge <?php echo instructor_badge_class($schedule['status']); ?>"><?php echo htmlspecialchars($schedule['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($student_schedules) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted">Choose a student to see their lesson schedule.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <h3 class="card-subtitle mb-2">Recent Records</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Lesson</th>
                    <th>Score</th>
                    <th>Attendance</th>
                    <th>Ready</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($recent_records as $record): ?>
                  <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></td>
                    <td class="font-bold"><?php echo htmlspecialchars($record['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($record['lesson_type']); ?></td>
                    <td><?php echo $record['performance_score'] !== null ? number_format((float)$record['performance_score'], 2) : '-'; ?></td>
                    <td><span class="badge <?php echo instructor_badge_class($record['attendance_status']); ?>"><?php echo htmlspecialchars($record['attendance_status']); ?></span></td>
                    <td><?php echo intval($record['instructor_recommendation_for_exam']) === 1 ? 'Yes' : 'No'; ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($recent_records) === 0): ?>
                  <tr><td colspan="6" class="text-center text-muted">No training records found yet.</td></tr>
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
