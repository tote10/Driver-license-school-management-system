<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$manager_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';
$message = "";

// Safe initials
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

//issue certificate
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'issue'){
    $sid = intval($_POST['student_id'] ?? 0);
    $eid = intval($_POST['enrollment_id'] ?? 0);
    
    if($sid > 0 && $eid > 0){
        try {
            $pdo->beginTransaction();

          $stmtExisting = $pdo->prepare("SELECT certificate_id FROM certificates WHERE enrollment_id = ?");
          $stmtExisting->execute([$eid]);
          if($stmtExisting->fetch()){
            throw new Exception('A certificate has already been issued for this enrollment.');
          }
            
            $stmtV = $pdo->prepare("
                SELECT u.branch_id 
                FROM users u
                JOIN enrollments e ON u.user_id = e.student_user_id
                WHERE u.user_id = ? AND e.enrollment_id = ?
                  AND e.approval_status = 'approved'
                  AND (SELECT COUNT(*) FROM exam_records WHERE student_user_id = u.user_id AND exam_type = 'theory' AND passed = 1 AND status = 'completed') > 0
                  AND (SELECT COUNT(*) FROM exam_records WHERE student_user_id = u.user_id AND exam_type = 'practical' AND passed = 1 AND status = 'completed') > 0
            ");
            $stmtV->execute([$sid, $eid]);
            $res = $stmtV->fetch();

            if(!$res || $res['branch_id'] != $branch_id){
                throw new Exception("Security: Student is not eligible or belongs to another branch.");
            }

            $cert_no = "CERT-" . date('Y') . "-" . str_pad($eid, 5, '0', STR_PAD_LEFT);
            
            $stmtC = $pdo->prepare("INSERT INTO certificates (student_user_id, enrollment_id, certificate_number, issued_by) VALUES (?, ?, ?, ?)");
            $stmtC->execute([$sid, $eid, $cert_no, $manager_id]);
            
            $stmtE = $pdo->prepare("UPDATE enrollments SET current_progress_status = 'completed' WHERE enrollment_id = ?");
            $stmtE->execute([$eid]);
            
            $pdo->commit();
            $message = "<div class='toast show'>Certificate Issued: $cert_no. Student has graduated!</div>";
        } catch(Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

$eligible = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, e.enrollment_id, tp.name as program_name
        FROM users u
        JOIN enrollments e ON u.user_id = e.student_user_id
        JOIN training_programs tp ON e.program_id = tp.program_id
        WHERE u.branch_id = ? 
          AND e.approval_status = 'approved'
          AND e.current_progress_status = 'enrolled'
          AND (SELECT COUNT(*) FROM exam_records WHERE student_user_id = u.user_id AND exam_type = 'theory' AND passed = 1 AND status = 'completed') > 0
          AND (SELECT COUNT(*) FROM exam_records WHERE student_user_id = u.user_id AND exam_type = 'practical' AND passed = 1 AND status = 'completed') > 0
    ");
    $stmt->execute([$branch_id]);
    $eligible = $stmt->fetchAll();
} catch(PDOException $e) {}

$issued = [];
try {
    $stmt_i = $pdo->prepare("
        SELECT c.*, u.full_name, tp.name as program_name
        FROM certificates c
        JOIN users u ON c.student_user_id = u.user_id
        JOIN enrollments e ON c.enrollment_id = e.enrollment_id
        JOIN training_programs tp ON e.program_id = tp.program_id
        WHERE u.branch_id = ?
        ORDER BY c.issue_date DESC
    ");
    $stmt_i->execute([$branch_id]);
    $issued = $stmt_i->fetchAll();
} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Certificates | Manager Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/app.js" defer></script>
    <style>
      .bg-danger { background-color: var(--danger) !important; }
    </style>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>

      <div class="main-content">
        <?php $page_title = 'Certificates & Graduation'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card mb-4">
            <h3 class="card-subtitle mb-3">Eligible for Graduation</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($eligible as $s): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($s['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['program_name']); ?></td>
                    <td><span class="badge badge-success">PASSED ALL EXAMS</span></td>
                    <td>
                      <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="issue">
                        <input type="hidden" name="student_id" value="<?php echo $s['user_id']; ?>">
                        <input type="hidden" name="enrollment_id" value="<?php echo $s['enrollment_id']; ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Issue Certificate</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($eligible) == 0): ?>
                  <tr><td colspan="4" class="text-center text-muted">No students ready for graduation.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
              <h3 class="card-subtitle mb-3">Issued Certificates Archive</h3>
              <div class="table-responsive">
                  <table class="table">
                      <thead>
                          <tr>
                              <th>Certificate #</th>
                              <th>Student</th>
                              <th>Program</th>
                              <th>Issue Date</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach($issued as $i): ?>
                          <tr>
                              <td class="font-bold text-primary"><?php echo $i['certificate_number']; ?></td>
                              <td><?php echo htmlspecialchars($i['full_name']); ?></td>
                              <td><?php echo htmlspecialchars($i['program_name']); ?></td>
                              <td><?php echo date('Y-m-d', strtotime($i['issue_date'])); ?></td>
                          </tr>
                          <?php endforeach; ?>
                          <?php if(count($issued) == 0): ?>
                          <tr><td colspan="4" class="text-center text-muted">No certificates issued yet.</td></tr>
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
