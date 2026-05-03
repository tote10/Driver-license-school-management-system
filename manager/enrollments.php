<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = ds_display_name('Manager');
$message = "";
$selected_student_id = intval($_GET['student_id'] ?? 0);

function log_audit_action($pdo, $user_id, $action_type, $entity_type, $entity_id, $details) {
  $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)");
  $stmtLog->execute([$user_id, $action_type, $entity_type, $entity_id, $details]);
}

function license_category_matches($student_category, $program_category) {
  return trim((string)$student_category) !== ''
    && trim((string)$program_category) !== ''
    && strcasecmp(trim((string)$student_category), trim((string)$program_category)) === 0;
}

$initials = ds_display_initials($full_name, 'Manager');
$csrf_error = $_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validate_request();
if($csrf_error){
  $message = "<div class='toast show bg-danger'>Security validation failed. Please refresh and try again.</div>";
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && !$csrf_error && isset($_POST['action']) && $_POST['action'] == 'reject'){
    $type = $_POST['type'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    try {
        if ($type === 'account') {
            $stmtUser = $pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND branch_id = ? AND role = 'student' LIMIT 1");
            $stmtUser->execute([$id, $branch_id]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if(!$user){
              throw new Exception('Security: this student account does not belong to your branch.');
            }

            $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE user_id = ?");
            $stmt->execute([$id]);
            $stmt2 = $pdo->prepare("UPDATE students SET registration_status = 'rejected' WHERE user_id = ?");
            $stmt2->execute([$id]);
            log_audit_action($pdo, $_SESSION['user_id'], 'account_rejected', 'user', $id, 'Rejected student account ' . $id);
            send_notification($pdo, $id, 'account_rejected', 'Account rejected', 'Your student account registration was rejected. Please contact the branch for more details.');
            $message = "<div class='toast show bg-danger'>Student account rejected.</div>";
        } elseif ($type === 'enrollment') {
            $stmtCheck = $pdo->prepare("
              SELECT e.enrollment_id, e.student_user_id, u.branch_id, tp.name AS program_name
              FROM enrollments e
              JOIN users u ON e.student_user_id = u.user_id
              JOIN training_programs tp ON e.program_id = tp.program_id
              WHERE e.enrollment_id = ?
            ");
            $stmtCheck->execute([$id]);
            $enrollment = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if(!$enrollment || intval($enrollment['branch_id']) !== intval($branch_id)){
              throw new Exception('Security: this enrollment does not belong to your branch.');
            }

            $stmt = $pdo->prepare("UPDATE enrollments SET approval_status = 'rejected' WHERE enrollment_id = ?");
            $stmt->execute([$id]);
            log_audit_action($pdo, $_SESSION['user_id'], 'enrollment_rejected', 'enrollment', $id, 'Rejected enrollment ' . $id);
            send_notification($pdo, intval($enrollment['student_user_id']), 'enrollment_rejected', 'Enrollment rejected', 'Your enrollment for ' . ($enrollment['program_name'] ?? 'your selected program') . ' was rejected.');
            $message = "<div class='toast show bg-danger'>Program enrollment rejected.</div>";
        }
    } catch(Exception $e) {
        $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>";
    }
}

// Action: Approve
if($_SERVER['REQUEST_METHOD'] == 'POST' && !$csrf_error && isset($_POST['action']) && $_POST['action'] == 'approve'){
    $type = $_POST['type'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    try {
        if ($type === 'account') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            $stmt->execute([$id]);
            $stmt2 = $pdo->prepare("UPDATE students SET registration_status = 'approved' WHERE user_id = ?");
            $stmt2->execute([$id]);
            log_audit_action($pdo, $_SESSION['user_id'], 'account_approved', 'user', $id, 'Approved student account ' . $id);
            send_notification($pdo, $id, 'account_approved', 'Account approved', 'Your student account has been approved. You can now log in and continue registration.');
            $message = "<div class='toast show'>Student account activated! They can now log in and select a program.</div>";
        } elseif ($type === 'enrollment') {
            $stmtCheck = $pdo->prepare("
              SELECT e.enrollment_id, e.student_user_id, e.program_id, u.branch_id, s.license_category AS student_license, tp.license_category AS program_license
              FROM enrollments e
              JOIN users u ON e.student_user_id = u.user_id
              JOIN students s ON e.student_user_id = s.user_id
              JOIN training_programs tp ON e.program_id = tp.program_id
              WHERE e.enrollment_id = ?
            ");
            $stmtCheck->execute([$id]);
            $enrollment = $stmtCheck->fetch();

            if(!$enrollment || intval($enrollment['branch_id']) !== intval($branch_id)){
              throw new Exception('Security: this enrollment does not belong to your branch.');
            }

            if(!license_category_matches($enrollment['student_license'], $enrollment['program_license'])){
              throw new Exception('License category mismatch. This program is not allowed for the student.');
            }

            // Prevent approving if student already has a certificate for this program
            $stmtCertCheck = $pdo->prepare("SELECT c.certificate_id FROM certificates c JOIN enrollments e2 ON c.enrollment_id = e2.enrollment_id WHERE c.student_user_id = ? AND e2.program_id = ?");
            $stmtCertCheck->execute([$enrollment['student_user_id'], $enrollment['program_id']]);
            if($stmtCertCheck->fetch()){
              throw new Exception('Student already holds a certificate for this program. Approval blocked.');
            }

            $stmt = $pdo->prepare("UPDATE enrollments SET approval_status = 'approved', approved_date = NOW() WHERE enrollment_id = ?");
            $stmt->execute([$id]);
            log_audit_action($pdo, $_SESSION['user_id'], 'enrollment_approved', 'enrollment', $id, 'Approved enrollment ' . $id);
            send_notification($pdo, intval($enrollment['student_user_id']), 'enrollment_approved', 'Enrollment approved', 'Your enrollment for ' . ($enrollment['program_license'] ?? 'your program') . ' has been approved.');
            $message = "<div class='toast show'>Program enrollment approved!</div>";
        }
        } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
}

// Action: Enroll Student Manually
if($_SERVER['REQUEST_METHOD'] == 'POST' && !$csrf_error && isset($_POST['action']) && $_POST['action'] == 'enroll'){
    $student_id = intval($_POST['student_id'] ?? 0);
    $program_id = intval($_POST['program_id'] ?? 0);
    try {
          $stmtMatch = $pdo->prepare("
            SELECT s.license_category AS student_license, tp.license_category AS program_license
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            JOIN training_programs tp ON tp.program_id = ?
            WHERE s.user_id = ? AND u.branch_id = ? AND tp.branch_id = ?
          ");
          $stmtMatch->execute([$program_id, $student_id, $branch_id, $branch_id]);
          $match = $stmtMatch->fetch();

          if(!$match){
            throw new Exception('Student or program not found in your branch.');
          }

          if(!license_category_matches($match['student_license'], $match['program_license'])){
            throw new Exception('License category mismatch. Select a program that matches the student license category.');
          }

        // Check if already enrolled (active)
        $chk = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_user_id=? AND program_id=? AND current_progress_status='enrolled'");
        $chk->execute([$student_id, $program_id]);
        if($chk->fetch()){
          $message = "<div class='toast show bg-danger'>Student is already enrolled in this program!</div>";
        } else {
          // Block if student already graduated
          $chkCert = $pdo->prepare("SELECT certificate_id FROM certificates WHERE student_user_id = ? LIMIT 1");
          $chkCert->execute([$student_id]);
          if($chkCert->fetch()){
            $message = "<div class='toast show bg-danger'>Student has already graduated and cannot be enrolled again.</div>";
          } else {
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_user_id, program_id, approval_status, approved_by, approved_date, current_progress_status) VALUES (?, ?, 'approved', ?, NOW(), 'enrolled')");
            $stmt->execute([$student_id, $program_id, $_SESSION['user_id']]);
            log_audit_action($pdo, $_SESSION['user_id'], 'manual_enrollment_created', 'enrollment', intval($pdo->lastInsertId()), 'Manually enrolled student ' . $student_id . ' into program ' . $program_id);
            $stmtProgramName = $pdo->prepare("SELECT name FROM training_programs WHERE program_id = ? LIMIT 1");
            $stmtProgramName->execute([$program_id]);
            $program_name = (string)($stmtProgramName->fetchColumn() ?: 'your training program');
            send_notification($pdo, $student_id, 'manual_enrollment', 'Enrollment added', 'You have been enrolled in ' . $program_name . '.');
            $message = "<div class='toast show'>Student officially enrolled into program!</div>";
          }
        }
    } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
}

// Fetch pending
$pending = [];
try {
    // 1. Pending Accounts
    $stmt1 = $pdo->prepare("
        SELECT u.user_id as id, u.full_name, s.license_category as program_name, s.registration_date as date_applied, 'account' as type 
        FROM users u 
        JOIN students s ON u.user_id = s.user_id 
        WHERE u.branch_id = ? AND u.status = 'pending' AND u.role = 'student'
    ");
    $stmt1->execute([$branch_id]);
    $pending_accounts = $stmt1->fetchAll();

    // 2. Pending Enrollments
    $stmt2 = $pdo->prepare("
        SELECT e.enrollment_id as id, u.full_name, tp.name as program_name, e.enrollment_date as date_applied, 'enrollment' as type 
        FROM enrollments e 
        JOIN users u ON e.student_user_id = u.user_id 
        JOIN training_programs tp ON e.program_id = tp.program_id
        WHERE u.branch_id = ? AND e.approval_status = 'pending'
    ");
    $stmt2->execute([$branch_id]);
    $pending_enrolls = $stmt2->fetchAll();

    $pending = array_merge($pending_accounts, $pending_enrolls);
    // Sort by date applied
    usort($pending, function($a, $b) {
        return strtotime($b['date_applied']) - strtotime($a['date_applied']);
    });

    // Fetch data for Manual Enrollment form
    $stmt_st = $pdo->prepare("SELECT user_id, full_name, email, status FROM users WHERE role='student' AND status='active' AND branch_id=?");
    $stmt_st->execute([$branch_id]);
    $active_students = $stmt_st->fetchAll();

    $active_programs = [];
    if($selected_student_id > 0){
      $stmt_sel = $pdo->prepare("SELECT license_category FROM students WHERE user_id = ?");
      $stmt_sel->execute([$selected_student_id]);
      $selected_student = $stmt_sel->fetch();

      if($selected_student){
        $stmt_pr = $pdo->prepare("SELECT program_id, name, fee_amount, license_category FROM training_programs WHERE branch_id=? AND license_category = ? ORDER BY name ASC");
        $stmt_pr->execute([$branch_id, $selected_student['license_category']]);
        $active_programs = $stmt_pr->fetchAll();
      }
    }

    // Fetch Active Enrollments
    $stmt_act = $pdo->prepare("
        SELECT e.*, u.full_name, tp.name as program_name 
        FROM enrollments e 
        JOIN users u ON e.student_user_id = u.user_id 
        JOIN training_programs tp ON e.program_id = tp.program_id
        WHERE u.branch_id = ? AND e.approval_status = 'approved'
        ORDER BY e.approved_date DESC
    ");
    $stmt_act->execute([$branch_id]);
    $active_enrollments = $stmt_act->fetchAll();

} catch(PDOException $e) {
  error_log('Enrollments page query error: ' . $e->getMessage());
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Registrations | Manager Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
    <style>
      .bg-danger { background-color: var(--danger) !important; }
    </style>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>

      <div class="main-content">
        <?php $page_title = 'Registrations & Enrollments'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card p-3 py-2 mb-1">
            <h3 class="card-subtitle">➕ Manual Student Enrollment</h3>
            <form method="GET" class="mt-2 mb-3">
              <div class="form-group">
                <label class="form-label">Load programs for student</label>
                <input type="text" class="form-control mb-2" placeholder="Filter students by name or email" data-student-filter="load-student-select" autocomplete="off">
                <select id="load-student-select" name="student_id" class="form-control" onchange="this.form.submit()">
                  <option value="">-- Select Student --</option>
                  <?php foreach($active_students as $st): ?>
                    <option value="<?php echo $st['user_id']; ?>" <?php echo $selected_student_id == $st['user_id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($st['full_name']); ?> (<?php echo htmlspecialchars($st['email']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>
            <form method="POST" class="mt-3">
              <input type="hidden" name="action" value="enroll">
              <?php csrf_input(); ?>
              <div class="grid grid-cols-2 gap-md">
                <div class="form-group">
                  <label class="form-label">Select Active Student</label>
                  <input type="text" class="form-control mb-2" placeholder="Filter students by name or email" data-student-filter="manual-student-select" autocomplete="off">
                  <select id="manual-student-select" name="student_id" class="form-control" required>
                    <option value="">-- Choose Student --</option>
                    <?php foreach($active_students as $st): ?>
                      <option value="<?php echo $st['user_id']; ?>" <?php echo $selected_student_id == $st['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['full_name']); ?> (<?php echo htmlspecialchars($st['email']); ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Select Training Program</label>
                  <select name="program_id" class="form-control" required>
                    <option value="">-- Choose Program --</option>
                    <?php if($selected_student_id > 0): ?>
                      <?php foreach($active_programs as $pr): ?>
                        <option value="<?php echo $pr['program_id']; ?>"><?php echo htmlspecialchars($pr['name']); ?> (<?php echo htmlspecialchars($pr['license_category']); ?>) - $<?php echo htmlspecialchars($pr['fee_amount']); ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="">Select a student first</option>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
              <button type="submit" class="btn btn-primary mt-2">Enroll Student</button>
            </form>
          </div>

          <div class="card p-3 mb-1">
            <h3 class="card-subtitle mb-1">Pending Student Registrations</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>Date Applied</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($pending as $p): ?>
                  <tr>
                    <td class="font-bold">
                        <?php echo htmlspecialchars($p['full_name']); ?>
                        <?php if($p['type'] === 'account'): ?>
                            <span class="badge badge-warning text-xs" style="margin-left: 5px;">New Account</span>
                        <?php else: ?>
                            <span class="badge badge-primary text-xs" style="margin-left: 5px;">New Program</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-outline">
                            <?php echo htmlspecialchars($p['program_name'] ?? 'None'); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($p['date_applied'])); ?></td>
                    <td>
                      <form method="POST" style="margin:0; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="type" value="<?php echo $p['type']; ?>">
                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">
                            <?php echo $p['type'] === 'account' ? 'Activate Account' : 'Approve Enrollment'; ?>
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-outline btn-sm" style="border-color: var(--danger); color: var(--danger);">
                            <?php echo $p['type'] === 'account' ? 'Reject Account' : 'Reject Enrollment'; ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($pending) == 0): ?>
                  <tr><td colspan="4" class="text-center text-muted">No pending registrations found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-1">Active Enrolled Students</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>Date Enrolled</th>
                    <th>Progress Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($active_enrollments as $ae): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($ae['full_name']); ?></td>
                    <td><span class="badge badge-outline"><?php echo htmlspecialchars($ae['program_name']); ?></span></td>
                    <td><?php echo date('Y-m-d', strtotime($ae['approved_date'])); ?></td>
                    <td>
                      <?php if($ae['current_progress_status'] === 'enrolled'): ?>
                          <span class="badge badge-primary">Enrolled</span>
                      <?php elseif($ae['current_progress_status'] === 'completed'): ?>
                          <span class="badge badge-success">Completed</span>
                      <?php else: ?>
                          <span class="badge badge-warning"><?php echo htmlspecialchars($ae['current_progress_status']); ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($active_enrollments) == 0): ?>
                  <tr><td colspan="4" class="text-center text-muted">No active enrollments found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
    </div>
    <script>
      (function () {
        var filterInputs = document.querySelectorAll('[data-student-filter]');

        function setupFilter(inputEl) {
          var selectId = inputEl.getAttribute('data-student-filter');
          var selectEl = document.getElementById(selectId);
          if (!selectEl) {
            return;
          }

          var optionSnapshot = Array.from(selectEl.options).map(function (opt) {
            return {
              value: opt.value,
              text: opt.text,
              selected: opt.selected
            };
          });

          function renderFilteredOptions(term) {
            var normalized = String(term || '').trim().toLowerCase();
            var currentValue = selectEl.value;
            var matchedCount = 0;

            selectEl.innerHTML = '';

            optionSnapshot.forEach(function (opt) {
              var isPlaceholder = opt.value === '';
              var isSelected = currentValue !== '' && opt.value === currentValue;
              var matches = isPlaceholder || normalized === '' || opt.text.toLowerCase().indexOf(normalized) !== -1 || isSelected;
              if (!matches) {
                return;
              }

              var newOpt = document.createElement('option');
              newOpt.value = opt.value;
              newOpt.text = opt.text;
              if (isSelected || (!currentValue && opt.selected)) {
                newOpt.selected = true;
              }
              selectEl.appendChild(newOpt);

              if (!isPlaceholder) {
                matchedCount++;
              }
            });

            if (matchedCount === 0) {
              var noResultOption = document.createElement('option');
              noResultOption.value = '';
              noResultOption.text = '-- No matching students --';
              selectEl.appendChild(noResultOption);
            }
          }

          inputEl.addEventListener('input', function () {
            renderFilteredOptions(inputEl.value);
          });
        }

        filterInputs.forEach(setupFilter);
      })();
    </script>
  </body>
</html>