<?php
require_once __DIR__ . '/includes/common.php';

$page_title = 'Instructor Dashboard';

$profile = [];
$stats = [
    'students' => 0,
    'today' => 0,
    'pending' => 0,
    'avg_score' => null,
];
$assigned_students = [];
$today_lessons = [];
$recent_records = [];

try {
    $stmtProfile = $pdo->prepare(
        "SELECT u.user_id, u.full_name, u.email, u.phone,
                COALESCE(i.specialization, 'General') AS specialization,
                COALESCE(i.years_experience, 0) AS years_experience
         FROM users u
         LEFT JOIN instructors i ON u.user_id = i.user_id
         WHERE u.user_id = ? AND u.branch_id = ? AND u.role = 'instructor'
         LIMIT 1"
    );
    $stmtProfile->execute([$instructor_id, $branch_id]);
    $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtStudentsCount = $pdo->prepare(
      "SELECT COUNT(DISTINCT student_user_id)
       FROM instructor_assignments
       WHERE instructor_user_id = ? AND status = 'active'"
    );
    $stmtStudentsCount->execute([$instructor_id]);
    $stats['students'] = intval($stmtStudentsCount->fetchColumn());

    $stmtTodayCount = $pdo->prepare(
      "SELECT COUNT(*)
       FROM training_schedules
       WHERE instructor_user_id = ? AND DATE(scheduled_datetime) = CURDATE()"
    );
    $stmtTodayCount->execute([$instructor_id]);
    $stats['today'] = intval($stmtTodayCount->fetchColumn());

    $stmtPendingCount = $pdo->prepare(
      "SELECT COUNT(*)
       FROM training_schedules
       WHERE instructor_user_id = ? AND status = 'scheduled' AND scheduled_datetime >= NOW()"
    );
    $stmtPendingCount->execute([$instructor_id]);
    $stats['pending'] = intval($stmtPendingCount->fetchColumn());

    $stmtAvgScore = $pdo->prepare(
      "SELECT AVG(performance_score)
       FROM training_records
       WHERE instructor_user_id = ?"
    );
    $stmtAvgScore->execute([$instructor_id]);
    $avgScore = $stmtAvgScore->fetchColumn();
    $stats['avg_score'] = $avgScore !== null ? (float)$avgScore : null;

    $stmtStudents = $pdo->prepare(
        "SELECT ia.assignment_id, ia.assigned_date,
                s.user_id AS student_id, s.full_name AS student_name,
                st.license_category,
                tp.name AS program_name,
                tp.license_category AS program_category,
                (SELECT COUNT(*) FROM training_records tr WHERE tr.student_user_id = s.user_id AND tr.instructor_user_id = ia.instructor_user_id) AS session_count,
                (SELECT AVG(tr.performance_score) FROM training_records tr WHERE tr.student_user_id = s.user_id AND tr.instructor_user_id = ia.instructor_user_id) AS avg_score,
                (SELECT MAX(tr.created_at) FROM training_records tr WHERE tr.student_user_id = s.user_id AND tr.instructor_user_id = ia.instructor_user_id) AS last_session
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
         ORDER BY ia.assigned_date DESC
         LIMIT 10"
    );
    $stmtStudents->execute([$instructor_id, $branch_id, $branch_id]);
    $assigned_students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    $stmtToday = $pdo->prepare(
        "SELECT sch.schedule_id, sch.lesson_type, sch.scheduled_datetime, sch.duration_minutes, sch.location,
                s.full_name AS student_name, sch.status
         FROM training_schedules sch
         JOIN users s ON sch.student_user_id = s.user_id
         WHERE sch.instructor_user_id = ?
           AND DATE(sch.scheduled_datetime) = CURDATE()
         ORDER BY sch.scheduled_datetime ASC"
    );
    $stmtToday->execute([$instructor_id]);
    $today_lessons = $stmtToday->fetchAll(PDO::FETCH_ASSOC);

    $stmtRecords = $pdo->prepare(
        "SELECT tr.record_id, tr.created_at, tr.lesson_type, tr.performance_score, tr.attendance_status,
                tr.instructor_recommendation_for_exam, tr.feedback,
                s.full_name AS student_name
         FROM training_records tr
         JOIN users s ON tr.student_user_id = s.user_id
         WHERE tr.instructor_user_id = ?
         ORDER BY tr.created_at DESC
         LIMIT 8"
    );
    $stmtRecords->execute([$instructor_id]);
    $recent_records = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Instructor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        <main class="page-content">
          <h2 class="welcome-heading" style="margin-bottom: 20px;">
            Welcome back, <span><?php echo htmlspecialchars($name_parts[0]); ?></span>!
          </h2>

          <div class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100">
            <div class="d-flex gap-md flex-wrap">
              <a href="students.php" class="btn btn-primary">My Students</a>
              <a href="records.php" class="btn btn-outline">Record Training</a>
              <a href="schedule.php" class="btn btn-outline">My Schedule</a>
            </div>
            <div>
              <a href="profile.php" class="btn btn-outline">Update Profile</a>
            </div>
          </div>

          <div class="grid grid-cols-4 mb-4 mt-4">
            <div class="card">
              <h3 class="card-subtitle">Assigned Students</h3>
              <div class="stat-value text-primary"><?php echo intval($stats['students']); ?></div>
              <div class="text-sm text-muted mt-1">Active assignments</div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Today&apos;s Lessons</h3>
              <div class="stat-value text-success"><?php echo intval($stats['today']); ?></div>
              <div class="text-sm text-muted mt-1">Scheduled for today</div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Pending Lessons</h3>
              <div class="stat-value text-warning"><?php echo intval($stats['pending']); ?></div>
              <div class="text-sm text-muted mt-1">Still upcoming</div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Average Score</h3>
              <div class="stat-value text-primary"><?php echo $stats['avg_score'] !== null ? number_format((float)$stats['avg_score'], 2) : '-'; ?></div>
              <div class="text-sm text-muted mt-1">Across training records</div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-md">
            <div class="card">
              <h3 class="card-subtitle mb-2">Today&apos;s Schedule</h3>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Lesson</th>
                      <th>Time</th>
                      <th>Location</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($today_lessons as $lesson): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($lesson['student_name']); ?></td>
                      <td><span class="badge <?php echo instructor_badge_class($lesson['status']); ?>"><?php echo htmlspecialchars($lesson['lesson_type']); ?></span></td>
                      <td><?php echo date('H:i', strtotime($lesson['scheduled_datetime'])); ?></td>
                      <td><?php echo htmlspecialchars($lesson['location'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($today_lessons) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted">No lessons scheduled for today.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card">
              <h3 class="card-subtitle mb-2">Quick Profile</h3>
              <ul class="list-group mt-2">
                <li class="list-group-item text-sm">Email: <?php echo htmlspecialchars($profile['email'] ?? '-'); ?></li>
                <li class="list-group-item text-sm">Phone: <?php echo htmlspecialchars($profile['phone'] ?? '-'); ?></li>
                <li class="list-group-item text-sm">Specialization: <?php echo htmlspecialchars($profile['specialization'] ?? 'General'); ?></li>
                <li class="list-group-item text-sm">Experience: <?php echo intval($profile['years_experience'] ?? 0); ?> years</li>
              </ul>
            </div>
          </div>

          <div class="card mt-4">
            <h3 class="card-subtitle mb-2">My Assigned Students</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>License</th>
                    <th>Sessions</th>
                    <th>Avg Score</th>
                    <th>Last Session</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($assigned_students as $student): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($student['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['program_name']); ?></td>
                    <td><span class="badge badge-outline"><?php echo htmlspecialchars($student['license_category']); ?></span></td>
                    <td><?php echo intval($student['session_count']); ?></td>
                    <td><?php echo $student['avg_score'] !== null ? number_format((float)$student['avg_score'], 2) : '-'; ?></td>
                    <td><?php echo $student['last_session'] ? date('Y-m-d H:i', strtotime($student['last_session'])) : '-'; ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($assigned_students) === 0): ?>
                  <tr><td colspan="6" class="text-center text-muted">No active student assignments yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card mt-4">
            <h3 class="card-subtitle mb-2">Recent Training Records</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Lesson</th>
                    <th>Score</th>
                    <th>Attendance</th>
                    <th>Ready</th>
                    <th>Created</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($recent_records as $record): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($record['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($record['lesson_type']); ?></td>
                    <td><?php echo $record['performance_score'] !== null ? number_format((float)$record['performance_score'], 2) : '-'; ?></td>
                    <td><span class="badge <?php echo instructor_badge_class($record['attendance_status']); ?>"><?php echo htmlspecialchars($record['attendance_status']); ?></span></td>
                    <td><?php echo intval($record['instructor_recommendation_for_exam']) === 1 ? 'Yes' : 'No'; ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></td>
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
