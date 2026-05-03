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

$stats = [
    'active_instructors' => 0,
    'students_needing_assignment' => 0,
    'active_assignments' => 0,
    'open_complaints' => 0,
];

try {
    $stmtInstructors = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ? AND role = 'instructor' AND status = 'active'");
    $stmtInstructors->execute([$branch_id]);
    $stats['active_instructors'] = intval($stmtInstructors->fetchColumn());

    // Count distinct students with an active enrollment and no active assignment
    $stmtStudents = $pdo->prepare(
        "SELECT COUNT(DISTINCT u.user_id)
         FROM users u
         JOIN (
            SELECT student_user_id, MAX(enrollment_id) AS enrollment_id
            FROM enrollments
            WHERE approval_status = 'approved' AND current_progress_status = 'enrolled'
            GROUP BY student_user_id
         ) latest ON latest.student_user_id = u.user_id
         JOIN enrollments e ON e.enrollment_id = latest.enrollment_id
         JOIN training_programs tp ON e.program_id = tp.program_id
         LEFT JOIN instructor_assignments ia ON ia.student_user_id = u.user_id AND ia.status = 'active'
         WHERE u.branch_id = ?
           AND tp.branch_id = ?
           AND ia.assignment_id IS NULL"
    );
    $stmtStudents->execute([$branch_id, $branch_id]);
    $stats['students_needing_assignment'] = intval($stmtStudents->fetchColumn());

    $stmtAssignments = $pdo->prepare(
        "SELECT COUNT(*)
         FROM instructor_assignments ia
         JOIN users s ON ia.student_user_id = s.user_id
         JOIN users i ON ia.instructor_user_id = i.user_id
         WHERE s.branch_id = ? AND i.branch_id = ? AND ia.status = 'active'"
    );
    $stmtAssignments->execute([$branch_id, $branch_id]);
    $stats['active_assignments'] = intval($stmtAssignments->fetchColumn());

    $stmtComplaints = $pdo->prepare(
        "SELECT COUNT(*)
         FROM complaints c
         JOIN users u ON c.reporter_user_id = u.user_id
         WHERE u.branch_id = ? AND c.status IN ('open','forwarded')"
    );
    $stmtComplaints->execute([$branch_id]);
    $stats['open_complaints'] = intval($stmtComplaints->fetchColumn());

    // Fetch a short list of students needing assignment for quick actions on dashboard
    $stmtStudentsList = $pdo->prepare(
        "SELECT u.user_id, u.full_name, tp.name AS program_name
         FROM users u
         JOIN (
            SELECT student_user_id, MAX(enrollment_id) AS enrollment_id
            FROM enrollments
            WHERE approval_status = 'approved' AND current_progress_status = 'enrolled'
            GROUP BY student_user_id
         ) latest ON latest.student_user_id = u.user_id
         JOIN enrollments e ON e.enrollment_id = latest.enrollment_id
         JOIN training_programs tp ON e.program_id = tp.program_id
         LEFT JOIN instructor_assignments ia ON ia.student_user_id = u.user_id AND ia.status = 'active'
         WHERE u.branch_id = ?
           AND tp.branch_id = ?
           AND ia.assignment_id IS NULL
         ORDER BY e.enrollment_date DESC
         LIMIT 6"
    );
    $stmtStudentsList->execute([$branch_id, $branch_id]);
    $students_to_assign = $stmtStudentsList->fetchAll();
} catch(PDOException $e) {}

$page_title = 'Supervisor Dashboard';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Supervisor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include 'includes/topbar.php'; ?>

        <main class="page-content">
          <h2 class="welcome-heading" style="margin-bottom: 20px;">
            Welcome back, <span><?php echo htmlspecialchars($full_name); ?></span>!
          </h2>

          <div class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100">
            <div class="d-flex gap-md flex-wrap">
              <a href="assignments.php" class="btn btn-primary">Instructor Assignments</a>
              <a href="reviews.php" class="btn btn-outline">Training Reviews</a>
              <a href="complaints.php" class="btn btn-outline">Complaints</a>
            </div>
            <div>
              <a href="reviews.php" class="btn btn-outline">Training Review</a>
              <a href="schedules.php" class="btn btn-outline">Schedules</a>
            </div>
          </div>

          <div class="grid grid-cols-4 mb-4 mt-4">
            <div class="card">
              <h3 class="card-subtitle">Active Instructors</h3>
              <div class="stat-value text-primary"><?php echo $stats['active_instructors']; ?></div>
              <div class="text-sm text-muted mt-1">Available in branch</div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Students to Assign</h3>
                <div class="stat-value text-warning"><?php echo $stats['students_needing_assignment']; ?></div>
                <div class="text-sm text-muted mt-1">Awaiting instructor assignment</div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Active Assignments</h3>
              <div class="stat-value text-success"><?php echo $stats['active_assignments']; ?></div>
              <div class="text-sm text-muted mt-1">Ongoing instructor links</div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Open Complaints</h3>
              <div class="stat-value text-danger"><?php echo $stats['open_complaints']; ?></div>
              <div class="text-sm text-muted mt-1">Requires review</div>
            </div>
          </div>

          <div class="grid grid-cols-2">
            <div class="card">
              <h3 class="card-subtitle mb-2">Supervisor Responsibilities</h3>
              <div class="table-responsive">
                <table class="table">
                  <tbody>
                    <tr><td>Assign instructors to approved students</td><td><a href="assignments.php" class="btn btn-outline btn-sm">Open</a></td></tr>
                    <tr><td>Review training records and readiness</td><td><a href="reviews.php" class="btn btn-outline btn-sm">Open</a></td></tr>
                    <tr><td>Review training readiness across instructors</td><td><a href="reviews.php" class="btn btn-outline btn-sm">Open</a></td></tr>
                    <tr><td>Review branch schedules and session timing</td><td><a href="schedules.php" class="btn btn-outline btn-sm">Open</a></td></tr>
                    <tr><td>Handle complaints and forward serious ones</td><td><a href="complaints.php" class="btn btn-outline btn-sm">Open</a></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            
            <div class="card">
              <h3 class="card-subtitle mb-2">Branch Oversight</h3>
              <p class="text-sm text-muted mb-2">Supervisor pages are split into separate screens for clarity and to match the manager workflow style.</p>
              <p class="text-sm text-muted mb-0">Use the sidebar to move between dedicated pages for assignments, reviews, progress, schedules, and complaints.</p>
            </div>
          </div>

          <div class="card mt-3">
            <h3 class="card-subtitle mb-2">Students Awaiting Assignment</h3>
            <p class="text-sm text-muted mb-2">Quick assign: open the assignment form for the student.</p>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr><th>Student</th><th>Program</th><th>Action</th></tr>
                </thead>
                <tbody>
                  <?php if(!empty($students_to_assign)): foreach($students_to_assign as $sa): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($sa['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($sa['program_name']); ?></td>
                    <td><a href="assignments.php?student_id=<?php echo intval($sa['user_id']); ?>" class="btn btn-primary btn-sm">Assign</a></td>
                  </tr>
                  <?php endforeach; else: ?>
                  <tr><td colspan="3" class="text-center text-muted">No students waiting for assignment.</td></tr>
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
