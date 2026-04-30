<?php
require_once __DIR__ . '/includes/common.php';

$page_title = 'My Students';
$students = [];

try {
    $stmt = $pdo->prepare(
        "SELECT ia.assignment_id, ia.assigned_date,
                s.user_id AS student_id, s.full_name AS student_name,
                st.license_category,
                tp.name AS program_name,
                tp.license_category AS program_category,
                COUNT(tr.record_id) AS session_count,
                AVG(tr.performance_score) AS avg_score,
                SUM(CASE WHEN tr.attendance_status = 'present' THEN 1 ELSE 0 END) AS present_count,
                MAX(tr.created_at) AS last_session,
                COUNT(DISTINCT sch.schedule_id) AS upcoming_count
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
         LEFT JOIN training_records tr ON tr.student_user_id = s.user_id AND tr.instructor_user_id = ia.instructor_user_id
         LEFT JOIN training_schedules sch ON sch.student_user_id = s.user_id AND sch.instructor_user_id = ia.instructor_user_id AND sch.status = 'scheduled'
         WHERE ia.instructor_user_id = ?
           AND ia.status = 'active'
           AND s.branch_id = ?
           AND tp.branch_id = ?
         GROUP BY ia.assignment_id, ia.assigned_date, s.user_id, s.full_name, st.license_category, tp.name, tp.license_category
         ORDER BY s.full_name ASC"
    );
    $stmt->execute([$instructor_id, $branch_id, $branch_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Students</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        <main class="page-content">
          <div class="card">
            <h3 class="card-subtitle mb-2">My Students</h3>
            <p class="text-sm text-muted mb-3">These are the students assigned to you by the Supervisor. You can only work with students from your branch.</p>
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
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($students as $student): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($student['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['program_name']); ?></td>
                    <td><span class="badge badge-outline"><?php echo htmlspecialchars($student['license_category']); ?></span></td>
                    <td><?php echo intval($student['session_count']); ?></td>
                    <td><?php echo $student['avg_score'] !== null ? number_format((float)$student['avg_score'], 2) : '-'; ?></td>
                    <td><?php echo $student['last_session'] ? date('Y-m-d H:i', strtotime($student['last_session'])) : '-'; ?></td>
                    <td><a href="records.php?student_id=<?php echo intval($student['student_id']); ?>" class="btn btn-outline btn-sm">Record Session</a></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($students) === 0): ?>
                  <tr><td colspan="7" class="text-center text-muted">No students assigned to you yet.</td></tr>
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
