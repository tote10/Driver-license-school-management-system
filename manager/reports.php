<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';

// Safe initials
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

// Fetch stats...
try {
    $stmt_rev = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments p JOIN users u ON p.student_user_id = u.user_id WHERE u.branch_id = ? AND p.status='paid'");
    $stmt_rev->execute([$branch_id]);
    $total_revenue = $stmt_rev->fetchColumn();
    
  // Enrollment counts per month (last 12 months)
  $stmt_enr_time = $pdo->prepare(
    "SELECT DATE_FORMAT(e.enrollment_date, '%Y-%m') AS ym, COUNT(*) AS cnt \n         FROM enrollments e \n         JOIN users u ON e.student_user_id = u.user_id\n         WHERE u.branch_id = ? AND e.enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)\n         GROUP BY ym ORDER BY ym ASC"
  );
  $stmt_enr_time->execute([$branch_id]);
  $enrollments_over_time = $stmt_enr_time->fetchAll();

  // Pass / Fail rates by exam type
  $stmt_pass = $pdo->prepare(
    "SELECT er.exam_type, COUNT(*) AS total, SUM(CASE WHEN er.passed = 1 THEN 1 ELSE 0 END) AS passes \n         FROM exam_records er \n         JOIN users u ON er.student_user_id = u.user_id\n         WHERE u.branch_id = ? AND er.status = 'completed'\n         GROUP BY er.exam_type"
  );
  $stmt_pass->execute([$branch_id]);
  $exam_pass_rates = $stmt_pass->fetchAll();

  // Instructor performance (avg score, sessions)
  $stmt_instr = $pdo->prepare(
    "SELECT tr.instructor_user_id, u.full_name, COUNT(*) AS sessions, AVG(tr.performance_score) AS avg_score \n         FROM training_records tr \n         JOIN users u ON tr.instructor_user_id = u.user_id\n         WHERE u.branch_id = ?\n         GROUP BY tr.instructor_user_id, u.full_name\n         ORDER BY avg_score DESC LIMIT 10"
  );
  $stmt_instr->execute([$branch_id]);
  $instructor_performance = $stmt_instr->fetchAll();

  // Top programs by enrollment
  $stmt_top_prog = $pdo->prepare(
    "SELECT tp.program_id, tp.name, COUNT(*) AS enrolled_count \n         FROM enrollments e \n         JOIN training_programs tp ON e.program_id = tp.program_id\n         JOIN users u ON e.student_user_id = u.user_id\n         WHERE u.branch_id = ? AND e.approval_status = 'approved'\n         GROUP BY tp.program_id, tp.name\n         ORDER BY enrolled_count DESC LIMIT 10"
  );
  $stmt_top_prog->execute([$branch_id]);
  $top_programs = $stmt_top_prog->fetchAll();
} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Reports | Manager Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>

      <div class="main-content">
        <?php $page_title = 'Reports'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="grid grid-cols-4 mb-4">
              <div class="card">
                  <h3 class="card-subtitle">Branch Revenue</h3>
                  <div class="stat-value text-success">$<?php echo number_format($total_revenue, 2); ?></div>
                  <p class="text-sm text-muted mt-1">Total collections</p>
              </div>
          </div>
          
            <div class="card">
              <h3 class="card-subtitle mb-2">Performance Analytics</h3>
              <div class="grid grid-cols-2 gap-md">
                <div class="card p-2">
                  <h4 class="text-sm">Enrollments (Last 12 months)</h4>
                  <table class="table table-sm">
                    <thead><tr><th>Month</th><th>Count</th></tr></thead>
                    <tbody>
                    <?php foreach($enrollments_over_time as $row): ?>
                      <tr><td><?php echo htmlspecialchars($row['ym']); ?></td><td><?php echo intval($row['cnt']); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if(empty($enrollments_over_time)): ?><tr><td colspan="2" class="text-center text-muted">No data</td></tr><?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="card p-2">
                  <h4 class="text-sm">Exam Pass Rates</h4>
                  <table class="table table-sm">
                    <thead><tr><th>Exam Type</th><th>Passes</th><th>Total</th><th>Rate</th></tr></thead>
                    <tbody>
                    <?php foreach($exam_pass_rates as $ep): $rate = $ep['total'] > 0 ? round(($ep['passes'] / $ep['total']) * 100,1) : 0; ?>
                      <tr>
                        <td><?php echo htmlspecialchars($ep['exam_type']); ?></td>
                        <td><?php echo intval($ep['passes']); ?></td>
                        <td><?php echo intval($ep['total']); ?></td>
                        <td><?php echo $rate; ?>%</td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if(empty($exam_pass_rates)): ?><tr><td colspan="4" class="text-center text-muted">No completed exams yet.</td></tr><?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-md mt-2">
                <div class="card p-2">
                  <h4 class="text-sm">Top Programs (by enrollments)</h4>
                  <table class="table table-sm">
                    <thead><tr><th>Program</th><th>Enrollments</th></tr></thead>
                    <tbody>
                    <?php foreach($top_programs as $tp): ?>
                      <tr><td><?php echo htmlspecialchars($tp['name']); ?></td><td><?php echo intval($tp['enrolled_count']); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if(empty($top_programs)): ?><tr><td colspan="2" class="text-center text-muted">No enrollments yet.</td></tr><?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="card p-2">
                  <h4 class="text-sm">Instructor Performance</h4>
                  <table class="table table-sm">
                    <thead><tr><th>Instructor</th><th>Sessions</th><th>Avg Score</th></tr></thead>
                    <tbody>
                    <?php foreach($instructor_performance as $ip): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($ip['full_name']); ?></td>
                        <td><?php echo intval($ip['sessions']); ?></td>
                        <td><?php echo $ip['avg_score'] !== null ? number_format($ip['avg_score'],2) : '-'; ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if(empty($instructor_performance)): ?><tr><td colspan="3" class="text-center text-muted">No training records yet.</td></tr><?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
        </main>
      </div>
    </div>
  </body>
</html>
