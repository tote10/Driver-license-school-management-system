<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$branch_id = intval($_SESSION['branch_id'] ?? 0);
$full_name = ds_display_name('Student');
$page_title = 'Student Dashboard';

$initials = ds_display_initials($full_name, 'Student');

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
$active_enrollments = [];
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
        "SELECT e.enrollment_id, e.program_id, e.approval_status, e.current_progress_status, e.enrollment_date, e.approved_date,
                tp.name AS program_name, tp.license_category AS program_license
         FROM enrollments e
         JOIN training_programs tp ON e.program_id = tp.program_id
         WHERE e.student_user_id = ?
           AND e.approval_status = 'approved'
           AND e.current_progress_status IN ('enrolled','theory_training','theory_completed','practical_training','completed','graduated')
           AND tp.branch_id = ?
         ORDER BY e.enrollment_date DESC"
    );
    $stmtEnrollment->execute([$student_id, $branch_id]);
    $active_enrollments = $stmtEnrollment->fetchAll(PDO::FETCH_ASSOC);

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
    require_once __DIR__ . '/../includes/notifications.php';
    $notifications = fetch_user_notifications($pdo, $student_id, 3);
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
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>

      <main class="page-content">
        <div class="hero-bar d-flex flex-wrap justify-between gap-md align-center">
          <div>
            <h2 class="welcome-heading" style="margin-bottom: 6px;">Welcome back, <span><?php echo htmlspecialchars($full_name); ?></span>!</h2>
            <p class="text-sm text-muted mb-0">Quick overview of your program, instructor and upcoming lesson.</p>
          </div>
          <div class="d-flex gap-sm flex-wrap">
            <span class="badge badge-outline">License: <?php echo htmlspecialchars($profile['license_category'] ?? 'N/A'); ?></span>
            <span class="badge badge-outline">Registration: <?php echo htmlspecialchars($profile['registration_status'] ?? 'N/A'); ?></span>
          </div>
        </div>

        <div class="grid grid-cols-4 mb-4">
          <div class="card">
            <h3 class="card-subtitle">Current Program</h3>
            <div class="stat-value text-primary"><?php echo count($active_enrollments) > 0 ? intval(count($active_enrollments)) : 0; ?></div>
            <div class="text-sm text-muted mt-1">Active programs</div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">My Instructor</h3>
            <div class="stat-value text-success"><?php echo htmlspecialchars($assigned_instructor['instructor_name'] ?? 'Not assigned'); ?></div>
            <div class="text-sm text-muted mt-1"><?php echo htmlspecialchars($assigned_instructor['specialization'] ?? 'Waiting for assignment'); ?></div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">Sessions</h3>
            <div class="stat-value text-primary"><?php echo intval($stats['sessions']); ?></div>
            <div class="text-sm text-muted mt-1">Total training sessions</div>
          </div>
          <div class="card">
            <h3 class="card-subtitle">Next Lesson</h3>
            <div class="stat-value text-warning"><?php echo !empty($next_lesson['scheduled_datetime']) ? date('M d, H:i', strtotime($next_lesson['scheduled_datetime'])) : '-'; ?></div>
            <div class="text-sm text-muted mt-1"><?php echo htmlspecialchars($next_lesson['lesson_type'] ?? 'No lesson scheduled'); ?></div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-md">
          <div class="card">
            <h3 class="card-subtitle mb-2">Notifications</h3>
            <?php if(!empty($notifications)): ?>
              <ul class="list-unstyled">
                <?php foreach($notifications as $n): ?>
                  <li class="mb-2">
                    <div class="font-bold"><?php echo htmlspecialchars($n['title']); ?></div>
                    <div class="text-sm text-muted mb-1"><?php echo htmlspecialchars(substr($n['message'],0,120)); ?><?php echo strlen($n['message'])>120? '…': ''; ?></div>
                    <div class="text-xs text-muted"><?php echo !empty($n['sent_at']) ? date('M d, H:i', strtotime($n['sent_at'])) : '-'; ?></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted">No notifications</div>
            <?php endif; ?>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-2">Program Progress</h3>
            <?php if(!empty($active_enrollments)): ?>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Program</th>
                      <th>Status</th>
                      <th>Enrolled</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($active_enrollments as $enrollment): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($enrollment['program_name']); ?></td>
                      <td><span class="badge <?php echo student_badge_class($enrollment['current_progress_status']); ?>"><?php echo htmlspecialchars($enrollment['current_progress_status']); ?></span></td>
                      <td><?php echo !empty($enrollment['enrollment_date']) ? date('Y-m-d', strtotime($enrollment['enrollment_date'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="text-muted">No active programs</div>
            <?php endif; ?>
          </div>

        </div>
      </main>
      </div>
    </div>
  </body>
</html>
