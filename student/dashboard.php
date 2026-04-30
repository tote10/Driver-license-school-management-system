<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$branch_id = intval($_SESSION['branch_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? 'Student';
$page_title = 'Student Dashboard';

$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

function student_badge_class($value) {
    $value = strtolower(trim((string)$value));
    return match($value) {
        'present', 'passed', 'approved', 'enrolled' => 'badge-success',
        'scheduled', 'pending', 'pending_approval' => 'badge-warning',
        'failed', 'absent', 'rejected', 'cancelled' => 'badge-danger',
        default => 'badge-outline',
    };
}

$profile = [];
$active_enrollment = [];
$assigned_instructor = [];
$next_lesson = [];
$recent_records = [];
$exam_records = [];
$stats = [
    'avg_score' => null,
    'sessions' => 0,
    'present' => 0,
    'ready' => 0,
];

try {
    $stmtProfile = $pdo->prepare(
        "SELECT u.user_id, u.full_name, u.email, u.phone,
                s.license_category, s.registration_status, s.registration_date
         FROM users u
         JOIN students s ON u.user_id = s.user_id
         WHERE u.user_id = ? AND u.branch_id = ? AND u.role = 'student'
         LIMIT 1"
    );
    $stmtProfile->execute([$student_id, $branch_id]);
    $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtEnrollment = $pdo->prepare(
        "SELECT e.enrollment_id, e.approval_status, e.current_progress_status, e.enrollment_date, e.approved_date,
                tp.name AS program_name, tp.license_category AS program_license
         FROM enrollments e
         JOIN training_programs tp ON e.program_id = tp.program_id
         WHERE e.student_user_id = ?
           AND e.approval_status = 'approved'
           AND e.current_progress_status = 'enrolled'
           AND tp.branch_id = ?
         ORDER BY e.enrollment_date DESC
         LIMIT 1"
    );
    $stmtEnrollment->execute([$student_id, $branch_id]);
    $active_enrollment = $stmtEnrollment->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtInstructor = $pdo->prepare(
        "SELECT ia.assignment_id, ia.assigned_date,
                i.full_name AS instructor_name,
                COALESCE(ins.specialization, 'General') AS specialization
         FROM instructor_assignments ia
         JOIN users i ON ia.instructor_user_id = i.user_id
         LEFT JOIN instructors ins ON ins.user_id = i.user_id
         WHERE ia.student_user_id = ?
           AND ia.status = 'active'
         LIMIT 1"
    );
    $stmtInstructor->execute([$student_id]);
    $assigned_instructor = $stmtInstructor->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtNextLesson = $pdo->prepare(
        "SELECT sch.schedule_id, sch.lesson_type, sch.scheduled_datetime, sch.duration_minutes, sch.location,
                i.full_name AS instructor_name
         FROM training_schedules sch
         JOIN users i ON sch.instructor_user_id = i.user_id
         WHERE sch.student_user_id = ?
           AND sch.status = 'scheduled'
         ORDER BY sch.scheduled_datetime ASC
         LIMIT 1"
    );
    $stmtNextLesson->execute([$student_id]);
    $next_lesson = $stmtNextLesson->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtStats = $pdo->prepare(
        "SELECT COUNT(*) AS sessions,
                AVG(performance_score) AS avg_score,
                SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN instructor_recommendation_for_exam = 1 THEN 1 ELSE 0 END) AS ready_count
         FROM training_records
         WHERE student_user_id = ?"
    );
    $stmtStats->execute([$student_id]);
    $statsRow = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['sessions'] = intval($statsRow['sessions'] ?? 0);
    $stats['avg_score'] = $statsRow['avg_score'] !== null ? (float)$statsRow['avg_score'] : null;
    $stats['present'] = intval($statsRow['present_count'] ?? 0);
    $stats['ready'] = intval($statsRow['ready_count'] ?? 0);

    $stmtRecent = $pdo->prepare(
        "SELECT tr.record_id, tr.created_at, tr.lesson_type, tr.performance_score, tr.attendance_status,
                tr.instructor_recommendation_for_exam, tr.feedback,
                i.full_name AS instructor_name
         FROM training_records tr
         LEFT JOIN users i ON tr.instructor_user_id = i.user_id
         WHERE tr.student_user_id = ?
         ORDER BY tr.created_at DESC
         LIMIT 8"
    );
    $stmtRecent->execute([$student_id]);
    $recent_records = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    $stmtExams = $pdo->prepare(
        "SELECT exam_type, scheduled_date, taken_date, score, passed, status
         FROM exam_records
         WHERE student_user_id = ?
         ORDER BY COALESCE(taken_date, scheduled_date) DESC
         LIMIT 6"
    );
    $stmtExams->execute([$student_id]);
    $exam_records = $stmtExams->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
    <style>
      .page-shell {
        max-width: 1480px;
        margin: 0 auto;
        padding: 24px 16px 32px;
      }
      .hero-bar {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(59, 130, 246, 0.05));
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        padding: 20px 24px;
        margin-bottom: 20px;
      }
    </style>
  </head>
  <body>
    <div class="page-shell">
      <header class="topbar" style="position: static; border-radius: 18px; margin-bottom: 20px;">
        <div class="d-flex align-center gap-md">
          <div>
            <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
            <div class="text-sm text-muted">Your training and exam overview</div>
          </div>
        </div>
        <div class="d-flex align-center gap-md">
          <div class="topbar-profile" style="cursor: default;">
            <div class="d-flex flex-col text-right">
              <span class="name font-bold text-sm"><?php echo htmlspecialchars($full_name); ?></span>
              <span class="role"><span class="badge badge-primary">Student</span></span>
            </div>
            <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
          </div>
          <a href="../logout.php" class="btn btn-outline btn-sm text-danger" style="border-color: var(--danger)">Logout</a>
        </div>
      </header>

      <main class="page-content" style="padding: 0; max-width: none;">
        <div class="hero-bar d-flex flex-wrap justify-between gap-md align-center">
          <div>
            <h2 class="welcome-heading" style="margin-bottom: 6px;">Welcome back, <span><?php echo htmlspecialchars($name_parts[0]); ?></span>!</h2>
            <p class="text-sm text-muted mb-0">Follow your progress, instructor, and upcoming lessons from one place.</p>
          </div>
          <div class="d-flex gap-sm flex-wrap">
            <span class="badge badge-outline">License: <?php echo htmlspecialchars($profile['license_category'] ?? 'N/A'); ?></span>
            <span class="badge badge-outline">Registration: <?php echo htmlspecialchars($profile['registration_status'] ?? 'N/A'); ?></span>
          </div>
        </div>

        <div class="grid grid-cols-4 mb-4">
          <div class="card">
            <h3 class="card-subtitle">Current Program</h3>
            <div class="stat-value text-primary"><?php echo htmlspecialchars($active_enrollment['program_name'] ?? 'No active program'); ?></div>
            <div class="text-sm text-muted mt-1">Approved enrollment</div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">My Instructor</h3>
            <div class="stat-value text-success"><?php echo htmlspecialchars($assigned_instructor['instructor_name'] ?? 'Not assigned'); ?></div>
            <div class="text-sm text-muted mt-1"><?php echo htmlspecialchars($assigned_instructor['specialization'] ?? 'Waiting for assignment'); ?></div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">Average Score</h3>
            <div class="stat-value text-primary"><?php echo $stats['avg_score'] !== null ? number_format((float)$stats['avg_score'], 2) : '-'; ?></div>
            <div class="text-sm text-muted mt-1">Across all records</div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">Next Lesson</h3>
            <div class="stat-value text-warning"><?php echo !empty($next_lesson['scheduled_datetime']) ? date('M d, H:i', strtotime($next_lesson['scheduled_datetime'])) : '-'; ?></div>
            <div class="text-sm text-muted mt-1"><?php echo htmlspecialchars($next_lesson['lesson_type'] ?? 'No lesson scheduled'); ?></div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-md">
          <div class="card">
            <h3 class="card-subtitle mb-2">My Training Overview</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Enrolled</th>
                    <th>Instructor</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($active_enrollment['program_name'] ?? 'No active program'); ?></td>
                    <td><span class="badge <?php echo student_badge_class($active_enrollment['current_progress_status'] ?? 'pending'); ?>"><?php echo htmlspecialchars($active_enrollment['current_progress_status'] ?? 'Pending'); ?></span></td>
                    <td><?php echo !empty($active_enrollment['enrollment_date']) ? date('Y-m-d', strtotime($active_enrollment['enrollment_date'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($assigned_instructor['instructor_name'] ?? 'Not assigned'); ?></td>
                  </tr>
                  <?php if(empty($active_enrollment)): ?>
                  <tr><td colspan="4" class="text-center text-muted">You do not have an active enrolled program yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-2">Upcoming Lesson</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Instructor</th>
                    <th>Lesson</th>
                    <th>Date & Time</th>
                    <th>Location</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($next_lesson['instructor_name'] ?? 'No lesson yet'); ?></td>
                    <td><?php echo htmlspecialchars($next_lesson['lesson_type'] ?? '-'); ?></td>
                    <td><?php echo !empty($next_lesson['scheduled_datetime']) ? date('Y-m-d H:i', strtotime($next_lesson['scheduled_datetime'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($next_lesson['location'] ?? '-'); ?></td>
                  </tr>
                  <?php if(empty($next_lesson)): ?>
                  <tr><td colspan="4" class="text-center text-muted">No upcoming lesson scheduled yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-md mt-4">
          <div class="card">
            <h3 class="card-subtitle mb-2">Recent Training Records</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Date</th>
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
                    <td><?php echo htmlspecialchars($record['lesson_type']); ?></td>
                    <td><?php echo $record['performance_score'] !== null ? number_format((float)$record['performance_score'], 2) : '-'; ?></td>
                    <td><span class="badge <?php echo student_badge_class($record['attendance_status']); ?>"><?php echo htmlspecialchars($record['attendance_status']); ?></span></td>
                    <td><?php echo intval($record['instructor_recommendation_for_exam']) === 1 ? 'Yes' : 'No'; ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($recent_records) === 0): ?>
                  <tr><td colspan="5" class="text-center text-muted">No training records found yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-2">Exam History</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Scheduled</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($exam_records as $exam): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($exam['exam_type']); ?></td>
                    <td><span class="badge <?php echo student_badge_class($exam['status']); ?>"><?php echo htmlspecialchars($exam['status']); ?></span></td>
                    <td><?php echo $exam['score'] !== null ? intval($exam['score']) : '-'; ?></td>
                    <td><?php echo !empty($exam['scheduled_date']) ? date('Y-m-d H:i', strtotime($exam['scheduled_date'])) : '-'; ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($exam_records) === 0): ?>
                  <tr><td colspan="4" class="text-center text-muted">No exam records found yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-4 mt-4">
          <div class="card">
            <h3 class="card-subtitle">Sessions Logged</h3>
            <div class="stat-value text-primary"><?php echo intval($stats['sessions']); ?></div>
            <div class="text-sm text-muted mt-1">Training records</div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">Present Sessions</h3>
            <div class="stat-value text-success"><?php echo intval($stats['present']); ?></div>
            <div class="text-sm text-muted mt-1">Attendance marked present</div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">Exam Ready Marks</h3>
            <div class="stat-value text-warning"><?php echo intval($stats['ready']); ?></div>
            <div class="text-sm text-muted mt-1">Instructor recommendations</div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">Program Status</h3>
            <div class="stat-value text-primary"><?php echo htmlspecialchars($profile['registration_status'] ?? 'N/A'); ?></div>
            <div class="text-sm text-muted mt-1">Student registration</div>
          </div>
        </div>
      </main>
    </div>
  </body>
</html>
