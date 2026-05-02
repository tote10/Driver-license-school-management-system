<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/includes/graduation_helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$manager_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';
$message = "";

function log_audit_action($pdo, $user_id, $action_type, $entity_type, $entity_id, $details) {
  $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)");
  $stmtLog->execute([$user_id, $action_type, $entity_type, $entity_id, $details]);
}

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
    
    if($sid > 0){
        try {
            $pdo->beginTransaction();

        $stmtExisting = $pdo->prepare("SELECT certificate_id FROM certificates WHERE student_user_id = ? LIMIT 1");
          $stmtExisting->execute([$sid]);
          if($stmtExisting->fetch()){
            throw new Exception('A graduation certificate has already been issued for this student.');
          }

        $stmtEnrollment = $pdo->prepare(
          "SELECT e.enrollment_id, e.student_user_id, e.program_id, tp.name AS program_name
           FROM enrollments e
           JOIN training_programs tp ON e.program_id = tp.program_id
           JOIN users u ON e.student_user_id = u.user_id
           WHERE e.enrollment_id = ? AND e.student_user_id = ? AND u.branch_id = ? AND tp.branch_id = ?
           LIMIT 1"
        );
        $stmtEnrollment->execute([$eid, $sid, $branch_id, $branch_id]);
        $enrollment = $stmtEnrollment->fetch(PDO::FETCH_ASSOC);
        if(!$enrollment) {
          throw new Exception('Enrollment not found in your branch.');
        }

        if(!manager_student_graduation_ready($pdo, $sid, $branch_id)) {
          throw new Exception('Student must complete theory and practical exams for all enrolled programs before graduation.');
        }
            
            $stmtV = $pdo->prepare("
          SELECT u.branch_id
          FROM users u
          WHERE u.user_id = ? AND u.branch_id = ?
            ");
        $stmtV->execute([$sid, $branch_id]);
            $res = $stmtV->fetch();

        if(!$res){
                throw new Exception("Security: Student is not eligible or belongs to another branch.");
            }

            $cert_no = "CERT-" . date('Y') . "-" . str_pad($sid, 5, '0', STR_PAD_LEFT);
            
            $stmtC = $pdo->prepare("INSERT INTO certificates (student_user_id, enrollment_id, certificate_number, issued_by) VALUES (?, ?, ?, ?)");
            $stmtC->execute([$sid, $eid, $cert_no, $manager_id]);
            $certificate_id = $pdo->lastInsertId();
            
            $stmtE = $pdo->prepare("UPDATE enrollments SET current_progress_status = 'graduated', last_progress_update = NOW() WHERE student_user_id = ? AND approval_status = 'approved'");
            $stmtE->execute([$sid]);

            $stmtStudent = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
            $stmtStudent->execute([$sid]);
            $studentName = (string)($stmtStudent->fetchColumn() ?: 'Student');
            send_notification($pdo, $sid, 'certificate_issued', 'Certificate issued', 'Congratulations ' . $studentName . ', your certificate ' . $cert_no . ' has been issued.');

            log_audit_action($pdo, $manager_id, 'certificate_issued', 'certificate', $certificate_id, 'Issued certificate ' . $cert_no . ' for student ' . $sid . ' in enrollment ' . $eid);
            
            $pdo->commit();
            $message = "<div class='toast show'>Certificate Issued: $cert_no. Student has graduated!</div>";
        } catch(Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

      // reject certificate
      if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reject'){
        $sid = intval($_POST['student_id'] ?? 0);
        $eid = intval($_POST['enrollment_id'] ?? 0);

        if($sid > 0 && $eid > 0){
          try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("
              SELECT u.branch_id, e.student_user_id
              FROM enrollments e
              JOIN users u ON e.student_user_id = u.user_id
              WHERE e.enrollment_id = ? AND e.student_user_id = ?
            ");
            $stmtCheck->execute([$eid, $sid]);
            $row = $stmtCheck->fetch();

            if(!$row || intval($row['branch_id']) !== intval($branch_id)){
              throw new Exception('Security: enrollment not found in your branch.');
            }

            $stmtReject = $pdo->prepare("UPDATE enrollments SET current_progress_status = 'failed', last_progress_update = NOW() WHERE enrollment_id = ?");
            $stmtReject->execute([$eid]);

            send_notification($pdo, $sid, 'graduation_rejected', 'Graduation rejected', 'Your graduation request has been rejected. You can continue training and re-apply later.');

            log_audit_action($pdo, $manager_id, 'certificate_rejected', 'enrollment', $eid, 'Rejected graduation for student ' . $sid . ' on enrollment ' . $eid);

            $pdo->commit();
            $message = "<div class='toast show bg-danger'>Graduation rejected. The student can re-enroll later.</div>";
          } catch(Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
          }
        }
      }

$eligible = [];
try {
    $stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, MAX(e.enrollment_id) AS enrollment_id
        FROM users u
        JOIN enrollments e ON u.user_id = e.student_user_id
        JOIN training_programs tp ON e.program_id = tp.program_id
        WHERE u.branch_id = ? 
          AND e.approval_status = 'approved'
      AND tp.branch_id = ?
    GROUP BY u.user_id, u.full_name
    ORDER BY u.full_name ASC
    ");
  $stmt->execute([$branch_id, $branch_id]);
  $eligibleRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($eligibleRows as $row){
    if(!manager_student_graduation_ready($pdo, intval($row['user_id']), $branch_id)) {
      continue;
    }
    $eligible[] = $row;
  }
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
                    <td>All enrolled programs</td>
                    <td><span class="badge badge-success">READY FOR GRADUATION</span></td>
                    <td>
                      <form method="POST" style="margin:0; display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="hidden" name="student_id" value="<?php echo $s['user_id']; ?>">
                        <input type="hidden" name="enrollment_id" value="<?php echo $s['enrollment_id']; ?>">
                        <button type="submit" name="action" value="issue" class="btn btn-primary btn-sm">Issue Certificate</button>
                        <button type="submit" name="action" value="reject" class="btn btn-outline btn-sm" style="border-color: var(--danger); color: var(--danger);">Reject</button>
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
