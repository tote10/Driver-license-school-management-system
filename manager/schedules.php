<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/includes/graduation_helpers.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'manager'){
    header('Location: ../login.php');
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$manager_id = intval($_SESSION['user_id']);
$full_name = ds_display_name('Manager');
$message = '';
$selected_student_id = intval($_GET['student_id'] ?? 0);
$page_title = 'Lesson Schedules';

$initials = ds_display_initials($full_name, 'Manager');

function schedule_status_class($status) {
    $status = strtolower(trim((string)$status));
    return match($status) {
        'scheduled' => 'badge-warning',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        default => 'badge-outline',
    };
}

function lesson_type_label($type) {
    $type = strtolower(trim((string)$type));
    return match($type) {
        'theory' => 'Theory',
        'practical' => 'Practical',
        default => ucfirst($type),
    };
}

function log_audit_action($pdo, $user_id, $action_type, $entity_type, $entity_id, $details) {
    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmtLog->execute([$user_id, $action_type, $entity_type, $entity_id, $details]);
}

$schedule_message = '';
$students = [];
$selected_student = null;
$student_programs = [];
$eligible_instructors = [];
$schedules = [];
$stats = [
    'total' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0,
];
  $schedule_has_program_column = manager_table_has_column($pdo, 'training_schedules', 'program_id');

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_schedule') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $instructor_id = intval($_POST['instructor_id'] ?? 0);
  $program_id = intval($_POST['program_id'] ?? 0);
    $lesson_type = trim((string)($_POST['lesson_type'] ?? ''));
    $scheduled_datetime = trim((string)($_POST['scheduled_datetime'] ?? ''));
    $duration_minutes = intval($_POST['duration_minutes'] ?? 60);
    $location = trim((string)($_POST['location'] ?? ''));

    try {
        if($student_id <= 0 || $instructor_id <= 0 || $program_id <= 0 || $lesson_type === '' || $scheduled_datetime === '') {
            throw new Exception('Fill all required fields.');
        }
        if(!in_array($lesson_type, ['theory', 'practical'], true)) {
            throw new Exception('Select a valid lesson type.');
        }
        if($duration_minutes <= 0) {
            throw new Exception('Duration must be greater than zero.');
        }

        $stmtStudent = $pdo->prepare(
            "SELECT s.user_id, s.full_name, st.license_category,
                    tp.name AS program_name, tp.license_category AS program_license
             FROM users s
             JOIN students st ON st.user_id = s.user_id
             JOIN (
                SELECT student_user_id, MAX(enrollment_id) AS enrollment_id
                FROM enrollments
                WHERE approval_status = 'approved' AND current_progress_status = 'enrolled'
                GROUP BY student_user_id
             ) latest ON latest.student_user_id = s.user_id
             JOIN enrollments e ON e.enrollment_id = latest.enrollment_id
             JOIN training_programs tp ON tp.program_id = e.program_id
             WHERE s.user_id = ? AND s.branch_id = ? AND tp.branch_id = ?
             LIMIT 1"
        );
        $stmtStudent->execute([$student_id, $branch_id, $branch_id]);
        $selected_student = $stmtStudent->fetch(PDO::FETCH_ASSOC);
        if(!$selected_student) {
            throw new Exception('Student not found in your branch or not actively enrolled.');
        }

        $stmtInstructor = $pdo->prepare(
            "SELECT u.user_id, u.full_name, COALESCE(i.specialization, '') AS specialization
             FROM users u
             LEFT JOIN instructors i ON u.user_id = i.user_id
             WHERE u.user_id = ? AND u.branch_id = ? AND u.role = 'instructor' AND u.status = 'active'
             LIMIT 1"
        );
        $stmtInstructor->execute([$instructor_id, $branch_id]);
        $instructor = $stmtInstructor->fetch(PDO::FETCH_ASSOC);
        if(!$instructor) {
            throw new Exception('Instructor not found in your branch or not active.');
        }

        $stmtProgram = $pdo->prepare(
          "SELECT e.enrollment_id, tp.name AS program_name
           FROM enrollments e
           JOIN training_programs tp ON tp.program_id = e.program_id
           WHERE e.student_user_id = ?
             AND e.program_id = ?
             AND e.approval_status = 'approved'
             AND e.current_progress_status = 'enrolled'
             AND tp.branch_id = ?
           LIMIT 1"
        );
        $stmtProgram->execute([$student_id, $program_id, $branch_id]);
        $enrollment_program = $stmtProgram->fetch(PDO::FETCH_ASSOC);
        if(!$enrollment_program) {
          throw new Exception('Selected program is not active for this student.');
        }

        $stmtAssignment = $pdo->prepare(
            "SELECT assignment_id FROM instructor_assignments WHERE student_user_id = ? AND instructor_user_id = ? AND status = 'active' LIMIT 1"
        );
        $stmtAssignment->execute([$student_id, $instructor_id]);
        if(!$stmtAssignment->fetchColumn()) {
            throw new Exception('Instructor is not assigned to this student.');
        }

        if ($schedule_has_program_column) {
          $stmtDuplicate = $pdo->prepare(
            "SELECT schedule_id FROM training_schedules
             WHERE student_user_id = ? AND instructor_user_id = ? AND program_id = ? AND lesson_type = ? AND scheduled_datetime = ? AND status = 'scheduled'
             LIMIT 1"
          );
          $stmtDuplicate->execute([$student_id, $instructor_id, $program_id, $lesson_type, $scheduled_datetime]);
        } else {
          $stmtDuplicate = $pdo->prepare(
            "SELECT schedule_id FROM training_schedules
             WHERE student_user_id = ? AND instructor_user_id = ? AND lesson_type = ? AND scheduled_datetime = ? AND status = 'scheduled'
             LIMIT 1"
          );
          $stmtDuplicate->execute([$student_id, $instructor_id, $lesson_type, $scheduled_datetime]);
        }
        if($stmtDuplicate->fetchColumn()) {
            throw new Exception('This lesson is already scheduled at that time.');
        }

        if ($schedule_has_program_column) {
          $stmtInsert = $pdo->prepare(
            "INSERT INTO training_schedules (student_user_id, instructor_user_id, program_id, lesson_type, scheduled_datetime, duration_minutes, location, branch_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')"
          );
          $stmtInsert->execute([$student_id, $instructor_id, $program_id, $lesson_type, $scheduled_datetime, $duration_minutes, $location, $branch_id]);
        } else {
          $stmtInsert = $pdo->prepare(
            "INSERT INTO training_schedules (student_user_id, instructor_user_id, lesson_type, scheduled_datetime, duration_minutes, location, branch_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')"
          );
          $stmtInsert->execute([$student_id, $instructor_id, $lesson_type, $scheduled_datetime, $duration_minutes, $location, $branch_id]);
        }
        $schedule_id = intval($pdo->lastInsertId());
        log_audit_action($pdo, $manager_id, 'lesson_schedule_created', 'training_schedule', $schedule_id, 'Scheduled ' . $lesson_type . ' lesson for student ' . $selected_student['full_name'] . ' in program ' . $enrollment_program['program_name']);
        $schedule_message = "<div class='toast show'>Lesson scheduled successfully.</div>";
        $selected_student_id = $student_id;
    } catch(Exception $e) {
        $schedule_message = "<div class='toast show bg-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

try {
    $stmtStudents = $pdo->prepare(
        "SELECT s.user_id, s.full_name,
                COALESCE(ia.instructor_user_id, 0) AS assigned_instructor_id,
                tp.name AS program_name,
                tp.license_category,
                i.full_name AS instructor_name
         FROM users s
         JOIN (
            SELECT student_user_id, MAX(enrollment_id) AS enrollment_id
            FROM enrollments
            WHERE approval_status = 'approved' AND current_progress_status = 'enrolled'
            GROUP BY student_user_id
         ) latest ON latest.student_user_id = s.user_id
         JOIN enrollments e ON e.enrollment_id = latest.enrollment_id
         JOIN training_programs tp ON tp.program_id = e.program_id
         LEFT JOIN instructor_assignments ia ON ia.student_user_id = s.user_id AND ia.status = 'active'
         LEFT JOIN users i ON ia.instructor_user_id = i.user_id
         WHERE s.branch_id = ? AND tp.branch_id = ?
         ORDER BY s.full_name ASC"
    );
    $stmtStudents->execute([$branch_id, $branch_id]);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    if($selected_student_id > 0) {
        $stmtPrograms = $pdo->prepare(
            "SELECT e.program_id, tp.name, tp.license_category
             FROM enrollments e
             JOIN training_programs tp ON e.program_id = tp.program_id
             WHERE e.student_user_id = ?
               AND e.approval_status = 'approved'
               AND e.current_progress_status = 'enrolled'
               AND tp.branch_id = ?
             ORDER BY tp.name ASC"
        );
        $stmtPrograms->execute([$selected_student_id, $branch_id]);
        $student_programs = $stmtPrograms->fetchAll(PDO::FETCH_ASSOC);

        $stmtSelected = $pdo->prepare(
            "SELECT s.user_id, s.full_name,
                    tp.name AS program_name,
                    tp.license_category,
                    ia.instructor_user_id,
                    i.full_name AS instructor_name
             FROM users s
             JOIN (
                SELECT student_user_id, MAX(enrollment_id) AS enrollment_id
                FROM enrollments
                WHERE approval_status = 'approved' AND current_progress_status = 'enrolled'
                GROUP BY student_user_id
             ) latest ON latest.student_user_id = s.user_id
             JOIN enrollments e ON e.enrollment_id = latest.enrollment_id
             JOIN training_programs tp ON tp.program_id = e.program_id
             LEFT JOIN instructor_assignments ia ON ia.student_user_id = s.user_id AND ia.status = 'active'
             LEFT JOIN users i ON ia.instructor_user_id = i.user_id
             WHERE s.user_id = ? AND s.branch_id = ? AND tp.branch_id = ?
             LIMIT 1"
        );
        $stmtSelected->execute([$selected_student_id, $branch_id, $branch_id]);
        $selected_student = $stmtSelected->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $stmtInstructors = $pdo->prepare(
        "SELECT u.user_id, u.full_name, COALESCE(i.specialization, '') AS specialization
         FROM users u
         JOIN instructors i ON u.user_id = i.user_id
         WHERE u.branch_id = ? AND u.role = 'instructor' AND u.status = 'active'
         ORDER BY u.full_name ASC"
    );
    $stmtInstructors->execute([$branch_id]);
    $eligible_instructors = $stmtInstructors->fetchAll(PDO::FETCH_ASSOC);

    if($selected_student_id > 0) {
        $stmtAssignments = $pdo->prepare(
            "SELECT u.user_id, u.full_name, COALESCE(i.specialization, '') AS specialization
             FROM users u
             JOIN instructors i ON u.user_id = i.user_id
             JOIN instructor_assignments ia ON ia.instructor_user_id = u.user_id
             WHERE ia.student_user_id = ? AND ia.status = 'active' AND u.branch_id = ?
             ORDER BY u.full_name ASC"
        );
        $stmtAssignments->execute([$selected_student_id, $branch_id]);
        $eligible_instructors = $stmtAssignments->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmtSchedules = $schedule_has_program_column
      ? $pdo->prepare("SELECT sch.schedule_id, sch.lesson_type, sch.scheduled_datetime, sch.duration_minutes, sch.location, sch.status, s.full_name AS student_name, i.full_name AS instructor_name, tp.name AS program_name FROM training_schedules sch JOIN users s ON sch.student_user_id = s.user_id JOIN users i ON sch.instructor_user_id = i.user_id LEFT JOIN training_programs tp ON tp.program_id = sch.program_id WHERE sch.branch_id = ? ORDER BY sch.scheduled_datetime DESC LIMIT 50")
      : $pdo->prepare("SELECT sch.schedule_id, sch.lesson_type, sch.scheduled_datetime, sch.duration_minutes, sch.location, sch.status, s.full_name AS student_name, i.full_name AS instructor_name, tp.name AS program_name FROM training_schedules sch JOIN users s ON sch.student_user_id = s.user_id JOIN users i ON sch.instructor_user_id = i.user_id LEFT JOIN enrollments e ON e.student_user_id = sch.student_user_id AND e.approval_status = 'approved' AND e.current_progress_status = 'enrolled' LEFT JOIN training_programs tp ON tp.program_id = e.program_id WHERE sch.branch_id = ? ORDER BY sch.scheduled_datetime DESC LIMIT 50");
    $stmtSchedules->execute([$branch_id]);
    $schedules = $stmtSchedules->fetchAll(PDO::FETCH_ASSOC);

    $stmtStats = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
         FROM training_schedules
         WHERE branch_id = ?"
    );
    $stmtStats->execute([$branch_id]);
    $statsRow = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['total'] = intval($statsRow['total'] ?? 0);
    $stats['scheduled'] = intval($statsRow['scheduled_count'] ?? 0);
    $stats['completed'] = intval($statsRow['completed_count'] ?? 0);
    $stats['cancelled'] = intval($statsRow['cancelled_count'] ?? 0);
} catch(PDOException $e) {
    $schedule_message = "<div class='toast show bg-danger'>Error loading schedules: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lesson Schedules</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
    <style>
      .bg-danger { background-color: var(--danger) !important; }
    </style>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content">
        <?php $page_title = 'Lesson Schedules'; include 'includes/topbar.php'; ?>
        <main class="page-content">
          <div class="toast-container"><?php if($schedule_message) echo $schedule_message; ?></div>

          <div class="grid grid-cols-4 mb-4">
            <div class="card"><h3 class="card-subtitle">Total</h3><div class="stat-value text-primary"><?php echo intval($stats['total']); ?></div></div>
            <div class="card"><h3 class="card-subtitle">Scheduled</h3><div class="stat-value text-warning"><?php echo intval($stats['scheduled']); ?></div></div>
            <div class="card"><h3 class="card-subtitle">Completed</h3><div class="stat-value text-success"><?php echo intval($stats['completed']); ?></div></div>
            <div class="card"><h3 class="card-subtitle">Cancelled</h3><div class="stat-value text-danger"><?php echo intval($stats['cancelled']); ?></div></div>
          </div>

          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Create Lesson Schedule</h3>
            <p class="text-sm text-muted mb-3">Create branch lessons only for actively enrolled students and their assigned instructors.</p>
            <form method="GET" class="mb-3">
              <div class="form-group mb-0">
                <label class="form-label">Filter by student</label>
                <select name="student_id" class="form-control" onchange="this.form.submit()" style="min-width: 260px;">
                  <option value="">-- Show all students --</option>
                  <?php foreach($students as $student): ?>
                    <option value="<?php echo intval($student['user_id']); ?>" <?php echo $selected_student_id === intval($student['user_id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['program_name']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>

            <?php if($selected_student): ?>
            <div class="mb-2 text-sm text-muted">Selected student: <span class="font-bold"><?php echo htmlspecialchars($selected_student['full_name']); ?></span></div>
            <form method="POST" class="grid grid-cols-2 gap-md align-end">
              <input type="hidden" name="action" value="create_schedule">
              <input type="hidden" name="student_id" value="<?php echo intval($selected_student_id); ?>">
              <div class="form-group">
                <label class="form-label">Instructor</label>
                <select name="instructor_id" class="form-control" required>
                  <option value="">-- Choose Instructor --</option>
                  <?php foreach($eligible_instructors as $instructor): ?>
                    <option value="<?php echo intval($instructor['user_id']); ?>">
                      <?php echo htmlspecialchars($instructor['full_name']); ?>
                      <?php echo $instructor['specialization'] ? ' (' . htmlspecialchars($instructor['specialization']) . ')' : ''; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Only instructors assigned to the selected student are shown when a student filter is active.</small>
              </div>
              <div class="form-group">
                <label class="form-label">Program</label>
                <select name="program_id" class="form-control" required>
                  <option value="">-- Choose Program --</option>
                  <?php foreach($student_programs as $program): ?>
                    <option value="<?php echo intval($program['program_id']); ?>">
                      <?php echo htmlspecialchars($program['name']); ?> (<?php echo htmlspecialchars($program['license_category']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Pick the exact program this lesson belongs to.</small>
              </div>
              <div class="form-group">
                <label class="form-label">Lesson Type</label>
                <select name="lesson_type" class="form-control" required>
                  <option value="theory">Theory</option>
                  <option value="practical">Practical</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Date & Time</label>
                <input type="datetime-local" name="scheduled_datetime" class="form-control" required>
              </div>
              <div class="form-group">
                <label class="form-label">Duration (minutes)</label>
                <input type="number" name="duration_minutes" class="form-control" min="15" step="15" value="60" required>
              </div>
              <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" placeholder="Branch classroom or driving area">
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-primary">Create Schedule</button>
              </div>
            </form>
            <?php else: ?>
              <div class="text-sm text-muted">Select a student above to load their enrolled programs and assigned instructors.</div>
            <?php endif; ?>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-2">Branch Lesson Schedules</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Instructor</th>
                    <th>Program</th>
                    <th>Lesson</th>
                    <th>Date & Time</th>
                    <th>Duration</th>
                    <th>Location</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($schedules as $schedule): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($schedule['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['instructor_name']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['program_name'] ?? 'N/A'); ?></td>
                    <td><span class="badge badge-outline"><?php echo htmlspecialchars(lesson_type_label($schedule['lesson_type'])); ?></span></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($schedule['scheduled_datetime'])); ?></td>
                    <td><?php echo intval($schedule['duration_minutes']); ?> min</td>
                    <td><?php echo htmlspecialchars($schedule['location'] ?? '-'); ?></td>
                    <td><span class="badge <?php echo schedule_status_class($schedule['status']); ?>"><?php echo htmlspecialchars($schedule['status']); ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($schedules) === 0): ?>
                  <tr><td colspan="8" class="text-center text-muted">No lesson schedules created yet.</td></tr>
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
