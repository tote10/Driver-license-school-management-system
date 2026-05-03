<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = ds_display_name('Manager');

$report_from = trim((string)($_GET['from'] ?? ''));
$report_to = trim((string)($_GET['to'] ?? ''));

function report_export_url(string $type, string $from, string $to): string {
  $params = ['export' => $type];
  if ($from !== '') {
    $params['from'] = $from;
  }
  if ($to !== '') {
    $params['to'] = $to;
  }
  return 'reports.php?' . http_build_query($params);
}

function stream_csv(string $filename, array $headers, array $rows): void {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $output = fopen('php://output', 'w');
  fputcsv($output, $headers);
  foreach ($rows as $row) {
    fputcsv($output, $row);
  }
  fclose($output);
  exit();
}

$initials = ds_display_initials($full_name, 'Manager');

$total_revenue = 0;
$total_enrollments = 0;
$total_completed_exams = 0;
$total_exam_passes = 0;
$total_exam_fails = 0;
$overall_pass_rate = 0;
$enrollments_over_time = [];
$exam_pass_rates = [];
$instructor_performance = [];
$top_programs = [];

$date_params = [];
if($report_from !== '') {
    $date_params[':from_date'] = $report_from;
}
if($report_to !== '') {
    $date_params[':to_date'] = $report_to;
}

try {
    $where_revenue = "u.branch_id = :branch_id AND p.status = 'paid'";
    if($report_from !== '') {
      $where_revenue .= " AND DATE(p.created_at) >= :from_date";
    }
    if($report_to !== '') {
      $where_revenue .= " AND DATE(p.created_at) <= :to_date";
    }

    $stmt_rev = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN users u ON p.student_user_id = u.user_id WHERE $where_revenue");
    $params_revenue = [':branch_id' => $branch_id] + $date_params;
    $stmt_rev->execute($params_revenue);
    $total_revenue = $stmt_rev->fetchColumn();

    $where_enroll = "u.branch_id = :branch_id AND e.approval_status = 'approved'";
    if($report_from !== '') {
      $where_enroll .= " AND DATE(e.enrollment_date) >= :from_date";
    }
    if($report_to !== '') {
      $where_enroll .= " AND DATE(e.enrollment_date) <= :to_date";
    }

    $stmt_total_enr = $pdo->prepare("SELECT COUNT(*) FROM enrollments e JOIN users u ON e.student_user_id = u.user_id WHERE $where_enroll");
    $stmt_total_enr->execute([':branch_id' => $branch_id] + $date_params);
    $total_enrollments = (int)$stmt_total_enr->fetchColumn();

    $where_exam = "u.branch_id = :branch_id AND er.status = 'completed'";
    if($report_from !== '') {
      $where_exam .= " AND DATE(COALESCE(er.taken_date, er.created_at)) >= :from_date";
    }
    if($report_to !== '') {
      $where_exam .= " AND DATE(COALESCE(er.taken_date, er.created_at)) <= :to_date";
    }

    $stmt_exam_overall = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN er.passed = 1 THEN 1 ELSE 0 END),0) AS passes FROM exam_records er JOIN users u ON er.student_user_id = u.user_id WHERE $where_exam");
    $stmt_exam_overall->execute([':branch_id' => $branch_id] + $date_params);
    $exam_overall = $stmt_exam_overall->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'passes' => 0];
    $total_completed_exams = (int)$exam_overall['total'];
    $total_exam_passes = (int)$exam_overall['passes'];
    $total_exam_fails = max(0, $total_completed_exams - $total_exam_passes);
    $overall_pass_rate = $total_completed_exams > 0 ? round(($total_exam_passes / $total_completed_exams) * 100, 1) : 0;
    
  // Enrollment counts per month (last 12 months)
  $stmt_enr_time = $pdo->prepare(
    "SELECT DATE_FORMAT(e.enrollment_date, '%Y-%m') AS ym, COUNT(*) AS cnt \n         FROM enrollments e \n         JOIN users u ON e.student_user_id = u.user_id\n         WHERE u.branch_id = ? AND e.enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)\n         GROUP BY ym ORDER BY ym ASC"
  );
  $stmt_enr_time->execute([$branch_id]);
  $enrollments_over_time = $stmt_enr_time->fetchAll();

  // Pass / Fail rates by exam type
  $stmt_pass = $pdo->prepare(
    "SELECT er.exam_type, COUNT(*) AS total, SUM(CASE WHEN er.passed = 1 THEN 1 ELSE 0 END) AS passes \n         FROM exam_records er \n         JOIN users u ON er.student_user_id = u.user_id\n         WHERE $where_exam\n         GROUP BY er.exam_type"
  );
  $stmt_pass->execute([':branch_id' => $branch_id] + $date_params);
  $exam_pass_rates = $stmt_pass->fetchAll();

  // Instructor performance (avg score, sessions)
  $instr_sql = "SELECT tr.instructor_user_id, u.full_name, COUNT(*) AS sessions, AVG(tr.performance_score) AS avg_score
                FROM training_records tr
                JOIN users u ON tr.instructor_user_id = u.user_id
                WHERE u.branch_id = ?";
  $instr_params = [$branch_id];
  if($report_from !== '') {
    $instr_sql .= " AND DATE(tr.created_at) >= ?";
    $instr_params[] = $report_from;
  }
  if($report_to !== '') {
    $instr_sql .= " AND DATE(tr.created_at) <= ?";
    $instr_params[] = $report_to;
  }
  $instr_sql .= " GROUP BY tr.instructor_user_id, u.full_name ORDER BY avg_score DESC LIMIT 10";
  $stmt_instr = $pdo->prepare($instr_sql);
  $stmt_instr->execute($instr_params);
  $instructor_performance = $stmt_instr->fetchAll();

  // Top programs by enrollment
  $prog_sql = "SELECT tp.program_id, tp.name, COUNT(*) AS enrolled_count
               FROM enrollments e
               JOIN training_programs tp ON e.program_id = tp.program_id
               JOIN users u ON e.student_user_id = u.user_id
               WHERE u.branch_id = ? AND e.approval_status = 'approved'";
  $prog_params = [$branch_id];
  if($report_from !== '') {
    $prog_sql .= " AND DATE(e.enrollment_date) >= ?";
    $prog_params[] = $report_from;
  }
  if($report_to !== '') {
    $prog_sql .= " AND DATE(e.enrollment_date) <= ?";
    $prog_params[] = $report_to;
  }
  $prog_sql .= " GROUP BY tp.program_id, tp.name ORDER BY enrolled_count DESC LIMIT 10";
  $stmt_top_prog = $pdo->prepare($prog_sql);
  $stmt_top_prog->execute($prog_params);
  $top_programs = $stmt_top_prog->fetchAll();
} catch(PDOException $e) {}

$export_type = trim((string)($_GET['export'] ?? ''));
if ($export_type !== '') {
    $date_label = date('Ymd_His');

    if ($export_type === 'summary') {
        stream_csv(
            'reports_summary_' . $date_label . '.csv',
            ['from', 'to', 'total_revenue', 'approved_enrollments', 'completed_exams', 'exam_passes', 'exam_fails', 'overall_pass_rate_percent'],
            [[
                $report_from !== '' ? $report_from : 'all',
                $report_to !== '' ? $report_to : 'all',
                number_format((float)$total_revenue, 2, '.', ''),
                $total_enrollments,
                $total_completed_exams,
                $total_exam_passes,
                $total_exam_fails,
                $overall_pass_rate,
            ]]
        );
    }

    // Only summary export retained. Other export types removed per UI simplification.
}

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
              <div class="card">
                  <h3 class="card-subtitle">Approved Enrollments</h3>
                  <div class="stat-value text-primary"><?php echo intval($total_enrollments); ?></div>
                  <p class="text-sm text-muted mt-1">Within selected period</p>
              </div>
              <div class="card">
                  <h3 class="card-subtitle">Completed Exams</h3>
                  <div class="stat-value text-warning"><?php echo intval($total_completed_exams); ?></div>
                  <p class="text-sm text-muted mt-1">Pass: <?php echo intval($total_exam_passes); ?> | Fail: <?php echo intval($total_exam_fails); ?></p>
              </div>
              <div class="card">
                  <h3 class="card-subtitle">Overall Pass Rate</h3>
                  <div class="stat-value text-success"><?php echo number_format($overall_pass_rate, 1); ?>%</div>
                  <p class="text-sm text-muted mt-1">Completed exams only</p>
              </div>
          </div>

            <div class="card mb-3">
              <h3 class="card-subtitle mb-2">Report Filters</h3>
              <form method="GET" class="d-flex gap-md flex-wrap align-end">
                <div class="form-group mb-0">
                  <label class="form-label">From</label>
                  <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($report_from); ?>">
                </div>
                <div class="form-group mb-0">
                  <label class="form-label">To</label>
                  <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($report_to); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="reports.php" class="btn btn-outline">Reset</a>
                <a href="<?php echo htmlspecialchars(report_export_url('summary', $report_from, $report_to)); ?>" class="btn btn-outline">Export Summary CSV</a>
              </form>
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
                    <thead><tr><th>Exam Type</th><th>Passes</th><th>Fails</th><th>Total</th><th>Rate</th></tr></thead>
                    <tbody>
                    <?php foreach($exam_pass_rates as $ep): $rate = $ep['total'] > 0 ? round(($ep['passes'] / $ep['total']) * 100,1) : 0; $fails = max(0, intval($ep['total']) - intval($ep['passes'])); ?>
                      <tr>
                        <td><?php echo htmlspecialchars($ep['exam_type']); ?></td>
                        <td><?php echo intval($ep['passes']); ?></td>
                        <td><?php echo intval($fails); ?></td>
                        <td><?php echo intval($ep['total']); ?></td>
                        <td><?php echo $rate; ?>%</td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if(empty($exam_pass_rates)): ?><tr><td colspan="5" class="text-center text-muted">No completed exams yet.</td></tr><?php endif; ?>
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
