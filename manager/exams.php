<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/includes/graduation_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$manager_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';
$message = "";
$selected_student_id = intval($_GET['student_id'] ?? 0);

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

// --- ACTIONS: SCHEDULE, RECORD, APPROVE ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])){
    
    if($_POST['action'] == 'schedule'){
        $sid = intval($_POST['student_id'] ?? 0);
        $program_id = intval($_POST['program_id'] ?? 0);
        $type = $_POST['exam_type'] ?? '';
        $date = $_POST['scheduled_date'] ?? '';

        if($sid > 0 && $program_id > 0 && !empty($type) && !empty($date)){
            try {
          // Ensure the student is actively enrolled in the selected program
          $stmtEnrChk = $pdo->prepare("SELECT e.enrollment_id, tp.name AS program_name FROM enrollments e JOIN users u ON e.student_user_id = u.user_id JOIN training_programs tp ON e.program_id = tp.program_id WHERE e.student_user_id = ? AND e.program_id = ? AND u.branch_id = ? AND tp.branch_id = ? AND e.approval_status = 'approved' AND e.current_progress_status = 'enrolled' LIMIT 1");
          $stmtEnrChk->execute([$sid, $program_id, $branch_id, $branch_id]);
          $enrollmentRow = $stmtEnrChk->fetch();
          if(!$enrollmentRow) throw new Exception("Student is not enrolled in the selected program.");

            if (!manager_student_exam_ready($pdo, $sid, $program_id)) {
              throw new Exception("Student must be recommended by the instructor and approved by the supervisor before scheduling exams.");
            }

                // Duplicate check
              $stmtD = $pdo->prepare("SELECT COUNT(*) FROM exam_records WHERE student_user_id = ? AND program_id = ? AND exam_type = ? AND status IN ('scheduled','pending_approval')");
              $stmtD->execute([$sid, $program_id, $type]);
                if($stmtD->fetchColumn() > 0) throw new Exception("Student already has an active $type exam.");

                // Theory-first prerequisite
                if ($type == 'practical') {
                  $stmtC = $pdo->prepare("SELECT COUNT(*) FROM exam_records WHERE student_user_id = ? AND program_id = ? AND exam_type = 'theory' AND passed = 1 AND status = 'completed'");
                  $stmtC->execute([$sid, $program_id]);
                    if ($stmtC->fetchColumn() == 0) throw new Exception("Student must pass the Theory exam before scheduling Practical.");
                }

              $stmt = $pdo->prepare("INSERT INTO exam_records (student_user_id, program_id, exam_type, scheduled_date, status) VALUES (?, ?, ?, ?, 'scheduled')");
              $stmt->execute([$sid, $program_id, $type, $date]);
                log_audit_action($pdo, $manager_id, 'exam_scheduled', 'exam_record', intval($pdo->lastInsertId()), 'Scheduled ' . $type . ' exam for student ' . $sid . ' in program ' . $program_id);
                $message = "<div class='toast show'>Exam scheduled successfully!</div>";
            } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
        }
    }

    if(strpos($_POST['action'], 'result_') === 0){
        $eid = intval(str_replace('result_', '', $_POST['action']));
        $score = intval($_POST["score_$eid"] ?? 0);
        $passed = isset($_POST["passed_$eid"]) ? 1 : 0;
        try {
        // Verify exam belongs to a student in this branch
        $stmtChk = $pdo->prepare("SELECT u.branch_id FROM exam_records er JOIN users u ON er.student_user_id = u.user_id WHERE er.exam_id = ?");
        $stmtChk->execute([$eid]);
        $rowChk = $stmtChk->fetch();
        if(!$rowChk || $rowChk['branch_id'] != $branch_id) throw new Exception('Security: exam record not found in your branch.');

        $stmt = $pdo->prepare("UPDATE exam_records SET score = ?, passed = ?, status = 'pending_approval', taken_date = NOW(), recorded_by = ? WHERE exam_id = ?");
        $stmt->execute([$score, $passed, $manager_id, $eid]);
        log_audit_action($pdo, $manager_id, 'exam_result_recorded', 'exam_record', $eid, 'Recorded result for exam ' . $eid . ' with score ' . $score . ' and passed=' . $passed);
            $message = "<div class='toast show'>Result saved, awaiting approval.</div>";
        } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
    }

    if(strpos($_POST['action'], 'approve_') === 0){
        $eid = intval(str_replace('approve_', '', $_POST['action']));
        try {
        // Verify exam belongs to a student in this branch before approving
        $stmtChk2 = $pdo->prepare("SELECT u.branch_id FROM exam_records er JOIN users u ON er.student_user_id = u.user_id WHERE er.exam_id = ?");
        $stmtChk2->execute([$eid]);
        $rowChk2 = $stmtChk2->fetch();
        if(!$rowChk2 || $rowChk2['branch_id'] != $branch_id) throw new Exception('Security: exam record not found in your branch.');

        $stmt = $pdo->prepare("UPDATE exam_records SET status = 'completed', approved_by = ? WHERE exam_id = ?");
        $stmt->execute([$manager_id, $eid]);
        log_audit_action($pdo, $manager_id, 'exam_result_approved', 'exam_record', $eid, 'Approved exam result for exam ' . $eid);
            $message = "<div class='toast show'>Exam result approved!</div>";
        } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
    }
}

// Fetch lists
$students = $pdo->prepare(
  "SELECT u.user_id, u.full_name,
    COALESCE((
      SELECT tp.name FROM enrollments e
      JOIN training_programs tp ON e.program_id = tp.program_id
      WHERE e.student_user_id = u.user_id
        AND e.approval_status = 'approved'
        AND e.current_progress_status = 'enrolled'
        AND tp.branch_id = ?
      LIMIT 1
    ), '') AS program_name
   FROM users u
   WHERE u.branch_id = ? AND u.role = 'student' AND u.status = 'active'
  "
);
$students->execute([$branch_id, $branch_id]);
$students = $students->fetchAll();

$selected_student = null;
$student_programs = [];
if($selected_student_id > 0){
  $stmtSelectedStudent = $pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND branch_id = ? AND role = 'student' LIMIT 1");
  $stmtSelectedStudent->execute([$selected_student_id, $branch_id]);
  $selected_student = $stmtSelectedStudent->fetch();

  if($selected_student){
    $stmtPrograms = $pdo->prepare(
      "SELECT e.program_id, tp.name, tp.license_category
       FROM enrollments e
       JOIN training_programs tp ON e.program_id = tp.program_id
       WHERE e.student_user_id = ?
         AND e.approval_status = 'approved'
         AND e.current_progress_status = 'enrolled'
         AND tp.branch_id = ?
       ORDER BY tp.name ASC"
    );
    $stmtPrograms->execute([$selected_student_id, $branch_id]);
    $student_programs = $stmtPrograms->fetchAll();
  }
}

$examsSql = "SELECT er.*, u.full_name, tp.name AS program_name FROM exam_records er JOIN users u ON er.student_user_id = u.user_id LEFT JOIN training_programs tp ON er.program_id = tp.program_id WHERE u.branch_id = ? AND er.status = 'scheduled' ORDER BY er.scheduled_date ASC";
$exams = $pdo->prepare($examsSql);
$exams->execute([$branch_id]);
$exams = $exams->fetchAll();

$pendingSql = "SELECT er.*, u.full_name, tp.name AS program_name FROM exam_records er JOIN users u ON er.student_user_id = u.user_id LEFT JOIN training_programs tp ON er.program_id = tp.program_id WHERE u.branch_id = ? AND er.status = 'pending_approval'";
$pending = $pdo->prepare($pendingSql);
$pending->execute([$branch_id]);
$pending = $pending->fetchAll();

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Exam Schedule | Manager Dashboard</title>
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
        <?php $page_title = 'Exam Schedule'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card p-3 mb-1">
              <h3 class="card-subtitle">Schedule New Exam</h3>
              <form method="GET" class="mb-3">
                <div class="form-group mb-0">
                  <label class="form-label">Choose Student</label>
                  <select name="student_id" class="form-control" onchange="this.form.submit()" style="min-width: 240px;">
                    <option value="">-- Select Student --</option>
                    <?php foreach($students as $s): ?>
                    <option value="<?php echo $s['user_id']; ?>" <?php echo $selected_student_id == $s['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['full_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="text-muted">Pick a student first to load only the programs they are currently enrolled in.</small>
                </div>
              </form>
              <?php if($selected_student && !empty($student_programs)): ?>
              <div class="mb-2 text-sm text-muted">Selected student: <?php echo htmlspecialchars($selected_student['full_name']); ?></div>
              <?php endif; ?>
              <form method="POST" class="mt-3 d-flex gap-md flex-wrap align-end">
                  <input type="hidden" name="action" value="schedule">
                <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                  <div class="form-group mb-0">
                  <label class="form-label">Enrolled Program</label>
                  <select name="program_id" class="form-control" style="min-width: 240px;" required <?php echo $selected_student_id > 0 ? '' : 'disabled'; ?>>
                    <option value="">-- Choose Program --</option>
                    <?php if($selected_student_id > 0): ?>
                      <?php foreach($student_programs as $program): ?>
                      <option value="<?php echo $program['program_id']; ?>"><?php echo htmlspecialchars($program['name']); ?> (<?php echo htmlspecialchars($program['license_category']); ?>)</option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                      </select>
                  <small class="text-muted">Only programs the selected student is actively enrolled in will appear here.</small>
                  </div>
                  <div class="form-group mb-0">
                      <label class="form-label">Exam Type</label>
                      <select name="exam_type" class="form-control">
                          <option value="theory">Theory</option>
                          <option value="practical">Practical</option>
                      </select>
                  </div>
                  <div class="form-group mb-0">
                      <label class="form-label">Date & Time</label>
                      <input type="datetime-local" name="scheduled_date" class="form-control" required>
                  </div>
                  <button type="submit" class="btn btn-primary">Schedule Exam</button>
              </form>
          </div>

          <div class="grid grid-cols-2">
            <div class="card">
              <h3 class="card-subtitle mb-1">Active Schedules (Record Results)</h3>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Type / Date</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($exams as $ex): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($ex['full_name']); ?></td>
                      <td>
                          <span class="badge badge-primary"><?php echo strtoupper($ex['exam_type']); ?></span><br>
                          <small class="text-muted"><?php echo htmlspecialchars($ex['program_name'] ?? 'Program N/A'); ?></small><br>
                          <small class="text-muted"><?php echo date('M d, H:i', strtotime($ex['scheduled_date'])); ?></small>
                      </td>
                      <td>
                          <form method="POST" class="d-flex gap-sm align-center" style="margin:0;">
                              <input type="number" name="score_<?php echo $ex['exam_id']; ?>" placeholder="Score" class="form-control" style="width:70px; padding:0.3rem;" required>
                              <label class="text-sm d-flex align-center gap-sm">
                                  <input type="checkbox" name="passed_<?php echo $ex['exam_id']; ?>"> Pass
                              </label>
                              <button type="submit" name="action" value="result_<?php echo $ex['exam_id']; ?>" class="btn btn-success btn-sm">Save</button>
                          </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($exams) == 0): ?>
                    <tr><td colspan="3" class="text-center text-muted">No scheduled exams.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card">
              <h3 class="card-subtitle mb-1">Pending Exam Results Approval</h3>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Result</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($pending as $p): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($p['full_name']); ?></td>
                      <td>
                          <div class="text-sm text-muted"><?php echo htmlspecialchars($p['program_name'] ?? 'Program N/A'); ?></div>
                          <span class="badge <?php echo $p['passed'] ? 'badge-success' : 'badge-danger'; ?>">
                              <?php echo $p['passed'] ? 'PASS' : 'FAIL'; ?> (<?php echo $p['score']; ?>)
                          </span>
                      </td>
                      <td>
                          <form method="POST" style="margin:0;">
                              <button type="submit" name="action" value="approve_<?php echo $p['exam_id']; ?>" class="btn btn-primary btn-sm">Approve Result</button>
                          </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($pending) == 0): ?>
                    <tr><td colspan="3" class="text-center text-muted">No results awaiting approval.</td></tr>
                    <?php endif; ?>
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
