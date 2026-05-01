<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/includes/graduation_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';
$pending_enrolls = 0;
$pending_exams = 0;
$ready_graduation = 0;
$total_students = 0;
$total_instructors = 0;
$exam_stats = ['total' => 0, 'passes' => 0];
$pass_rate = 0;
$reg_target_pct = 0;
$total_revenue = 0;
$revenue_target_pct = 0;

// Initials for avatar
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

try {
    // 1. Core Alerts (Action Items)
    $stmt_p1 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ? AND status = 'pending' AND role = 'student'");
    $stmt_p1->execute([$branch_id]);
    $pending_accounts = $stmt_p1->fetchColumn();

    $stmt_p2 = $pdo->prepare("SELECT COUNT(*) FROM enrollments e JOIN users u ON e.student_user_id = u.user_id WHERE u.branch_id = ? AND e.approval_status = 'pending'");
    $stmt_p2->execute([$branch_id]);
    $pending_enrolls_only = $stmt_p2->fetchColumn();
    
    $pending_enrolls = $pending_accounts + $pending_enrolls_only;

    $stmt_ex = $pdo->prepare("SELECT COUNT(*) FROM exam_records er JOIN users u ON er.student_user_id = u.user_id WHERE u.branch_id = ? AND er.status = 'pending_approval'");
    $stmt_ex->execute([$branch_id]);
    $pending_exams = $stmt_ex->fetchColumn();

    $stmt_grad = $pdo->prepare("SELECT e.enrollment_id, e.student_user_id, e.program_id FROM enrollments e JOIN users u ON e.student_user_id = u.user_id JOIN training_programs tp ON e.program_id = tp.program_id WHERE u.branch_id = ? AND tp.branch_id = ? AND e.approval_status = 'approved' AND e.current_progress_status = 'enrolled' ORDER BY u.full_name ASC");
    $stmt_grad->execute([$branch_id, $branch_id]);
    $ready_graduation = 0;
    foreach ($stmt_grad->fetchAll(PDO::FETCH_ASSOC) as $row) {
      if (
        manager_student_all_enrolled_programs_complete($pdo, intval($row['student_user_id']), $branch_id) &&
        manager_student_program_complete($pdo, intval($row['student_user_id']), intval($row['program_id']))
      ) {
        $ready_graduation++;
      }
    }

    // 2. Stats
    $stmt_s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='student' AND branch_id=? AND status='active'");
    $stmt_s->execute([$branch_id]);
    $total_students = $stmt_s->fetchColumn();

    $stmt_ins = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'student' AND branch_id=? AND status='active'");
    $stmt_ins->execute([$branch_id]);
    $total_instructors = $stmt_ins->fetchColumn();

    // 3. Analytics
    // Pass Rate
    $stmt_exams = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN passed=1 THEN 1 ELSE 0 END) as passes FROM exam_records er JOIN users u ON er.student_user_id = u.user_id WHERE u.branch_id = ? AND er.status='completed'");
    $stmt_exams->execute([$branch_id]);
    $exam_stats = $stmt_exams->fetch();
    $pass_rate = ($exam_stats['total'] > 0) ? round(($exam_stats['passes'] / $exam_stats['total']) * 100, 1) : 0;

    // Registration Target (Goal: 50 active students per branch)
    $reg_target_pct = ($total_students > 0) ? min(100, round(($total_students / 50) * 100)) : 0;

    // Revenue Projections (Goal: $10,000 per branch)
    $stmt_rev = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments p JOIN users u ON p.student_user_id = u.user_id WHERE u.branch_id = ? AND p.status='paid'");
    $stmt_rev->execute([$branch_id]);
    $total_revenue = $stmt_rev->fetchColumn();
    $revenue_target_pct = ($total_revenue > 0) ? min(100, round(($total_revenue / 10000) * 100)) : 0;

} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>School Overview | Manager Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <!-- Sidebar Navigation -->
      <?php include 'includes/sidebar.php'; ?>

      <!-- Main Content Area -->
      <div class="main-content">
        <!-- App Topbar -->
        <?php $page_title = 'School Overview'; include 'includes/topbar.php'; ?>

        <!-- Page Content -->
        <main class="page-content">
          <h2 class="welcome-heading" style="margin-bottom: 20px;">
            Welcome back, <span><?php echo htmlspecialchars($name_parts[0]); ?></span>!
          </h2>

          <!-- Action Bar -->
          <div
            class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100"
          >
            <div class="d-flex gap-md flex-wrap">
              <a href="users.php" class="btn btn-primary">+ Add New User</a>
              <a href="programs.php" class="btn btn-outline">+ New Program</a>
              <a href="enrollments.php" class="btn btn-outline">Approve Registrations (<?php echo $pending_enrolls; ?>)</a>
              <a href="schedules.php" class="btn btn-outline">Lesson Schedules</a>
            </div>
            <div>
              <a href="reports.php" class="btn btn-outline">Generate Reports</a>
            </div>
          </div>

          <div class="grid grid-cols-4 mb-4 mt-4">
            <div class="card">
              <h3 class="card-subtitle">Total Students</h3>
              <div class="stat-value text-primary"><?php echo $total_students; ?></div>
              <div class="text-sm text-success font-bold mt-1">
                Active in branch
              </div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Active Staff</h3>
              <div class="stat-value"><?php echo $total_instructors; ?></div>
              <div class="text-sm text-muted mt-1">
                Total branch staff
              </div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Overall Pass Rate</h3>
              <div class="stat-value text-success"><?php echo $pass_rate; ?>%</div>
              <div class="text-sm text-muted mt-1">
                Based on <?php echo $exam_stats['total'] ?? 0; ?> completed exams
              </div>
            </div>
            <div class="card">
              <h3 class="card-subtitle">Action Required</h3>
              <div class="stat-value text-warning"><?php echo ($pending_enrolls + $pending_exams + $ready_graduation); ?></div>
              <div class="text-sm text-muted mt-1">Pending Approvals</div>
            </div>
          </div>

          <div class="grid grid-cols-2">
            <div class="card">
              <div class="d-flex justify-between align-center mb-2">
                <h3 class="card-subtitle mb-0">Recent / Pending Items</h3>
                <span class="badge badge-warning">Action Needed</span>
              </div>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Type</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if($pending_enrolls > 0): ?>
                    <tr>
                      <td>
                        <span class="badge badge-warning">Registration</span>
                      </td>
                      <td class="font-bold"><?php echo $pending_enrolls; ?> students</td>
                      <td>
                        <a href="enrollments.php" class="btn btn-outline btn-sm">Review</a>
                      </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if($pending_exams > 0): ?>
                    <tr>
                      <td>
                        <span class="badge badge-success">Exam Result</span>
                      </td>
                      <td class="font-bold"><?php echo $pending_exams; ?> awaiting</td>
                      <td>
                        <a href="exams.php" class="btn btn-primary btn-sm">Approve</a>
                      </td>
                    </tr>
                    <?php endif; ?>

                    <?php if($ready_graduation > 0): ?>
                    <tr>
                      <td>
                        <span class="badge badge-primary">Graduation</span>
                      </td>
                      <td class="font-bold"><?php echo $ready_graduation; ?> ready</td>
                      <td>
                        <a href="certificates.php" class="btn btn-outline btn-sm">
                          Issue Cert.
                        </a>
                      </td>
                    </tr>
                    <?php endif; ?>

                    <?php if(!$pending_enrolls && !$pending_exams && !$ready_graduation): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted">All clear!</td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="d-flex flex-col gap-lg">
              <div class="card">
                <h3 class="card-subtitle">Reports & Analytics Highlights</h3>
                <div class="mt-4">
                  <div class="d-flex justify-between text-sm mb-1 font-bold">
                    <span>Registration Target (50)</span>
                    <span class="text-primary"><?php echo $reg_target_pct; ?>%</span>
                  </div>
                  <div class="progress" style="height: 12px">
                    <div class="progress-bar" style="width: <?php echo $reg_target_pct; ?>%"></div>
                  </div>

                  <div class="d-flex justify-between text-sm mb-1 font-bold mt-3">
                    <span>Revenue Projections ($10k)</span>
                    <span class="text-success"><?php echo $revenue_target_pct; ?>%</span>
                  </div>
                  <div class="progress" style="height: 12px">
                    <div class="progress-bar success" style="width: <?php echo $revenue_target_pct; ?>%"></div>
                  </div>
                </div>
              </div>
              <div class="card">
                <h3 class="card-subtitle">System Health & Notifications</h3>
                <ul class="list-group mt-2">
                  <li class="list-group-item text-sm">
                    ✅ Branch Database: Connected
                  </li>
                  <li class="list-group-item text-sm">
                    ⚠️ <?php echo $pending_enrolls; ?> Registration requests pending
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>
