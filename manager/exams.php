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

// --- ACTIONS: SCHEDULE, RECORD, APPROVE ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])){
    
    if($_POST['action'] == 'schedule'){
        $sid = intval($_POST['student_id'] ?? 0);
        $type = $_POST['exam_type'] ?? '';
        $date = $_POST['scheduled_date'] ?? '';

        if($sid > 0 && !empty($type) && !empty($date)){
            try {
                // Duplicate check
                $stmtD = $pdo->prepare("SELECT COUNT(*) FROM exam_records WHERE student_user_id = ? AND exam_type = ? AND status IN ('scheduled','pending_approval')");
                $stmtD->execute([$sid, $type]);
                if($stmtD->fetchColumn() > 0) throw new Exception("Student already has an active $type exam.");

                // Theory-first prerequisite
                if ($type == 'practical') {
                    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM exam_records WHERE student_user_id = ? AND exam_type = 'theory' AND passed = 1 AND status = 'completed'");
                    $stmtC->execute([$sid]);
                    if ($stmtC->fetchColumn() == 0) throw new Exception("Student must pass the Theory exam before scheduling Practical.");
                }

                $stmt = $pdo->prepare("INSERT INTO exam_records (student_user_id, exam_type, scheduled_date, status) VALUES (?, ?, ?, 'scheduled')");
                $stmt->execute([$sid, $type, $date]);
                $message = "<div class='toast show'>Exam scheduled successfully!</div>";
            } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
        }
    }

    if(strpos($_POST['action'], 'result_') === 0){
        $eid = intval(str_replace('result_', '', $_POST['action']));
        $score = intval($_POST["score_$eid"] ?? 0);
        $passed = isset($_POST["passed_$eid"]) ? 1 : 0;
        try {
            $stmt = $pdo->prepare("UPDATE exam_records SET score = ?, passed = ?, status = 'pending_approval', taken_date = NOW(), recorded_by = ? WHERE exam_id = ?");
            $stmt->execute([$score, $passed, $manager_id, $eid]);
            $message = "<div class='toast show'>Result saved, awaiting approval.</div>";
        } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
    }

    if(strpos($_POST['action'], 'approve_') === 0){
        $eid = intval(str_replace('approve_', '', $_POST['action']));
        try {
            $stmt = $pdo->prepare("UPDATE exam_records SET status = 'completed', approved_by = ? WHERE exam_id = ?");
            $stmt->execute([$manager_id, $eid]);
            $message = "<div class='toast show'>Exam result approved!</div>";
        } catch(Exception $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
    }
}

// Fetch lists
$students = $pdo->prepare("SELECT user_id, full_name FROM users WHERE branch_id = ? AND role = 'student' AND status = 'active'");
$students->execute([$branch_id]);
$students = $students->fetchAll();

$exams = $pdo->prepare("SELECT er.*, u.full_name FROM exam_records er JOIN users u ON er.student_user_id = u.user_id WHERE u.branch_id = ? AND er.status = 'scheduled' ORDER BY er.scheduled_date ASC");
$exams->execute([$branch_id]);
$exams = $exams->fetchAll();

$pending = $pdo->prepare("SELECT er.*, u.full_name FROM exam_records er JOIN users u ON er.student_user_id = u.user_id WHERE u.branch_id = ? AND er.status = 'pending_approval'");
$pending->execute([$branch_id]);
$pending = $pending->fetchAll();

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Exam Schedule | Manager Dashboard</title>
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
        <?php $page_title = 'Exam Schedule'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card mb-4">
              <h3 class="card-subtitle">Schedule New Exam</h3>
              <form method="POST" class="mt-3 d-flex gap-md flex-wrap align-end">
                  <input type="hidden" name="action" value="schedule">
                  <div class="form-group mb-0">
                      <label class="form-label">Student</label>
                      <select name="student_id" class="form-control" style="min-width: 200px;">
                          <?php foreach($students as $s): ?>
                              <option value="<?php echo $s['user_id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
                          <?php endforeach; ?>
                      </select>
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
              <h3 class="card-subtitle mb-3">Active Schedules (Record Results)</h3>
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
              <h3 class="card-subtitle mb-3">Awaiting Manager Approval</h3>
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
