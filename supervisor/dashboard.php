<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$manager_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Supervisor';
$message = '';

function log_audit_action($pdo, $user_id, $action_type, $entity_type, $entity_id, $details) {
    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmtLog->execute([$user_id, $action_type, $entity_type, $entity_id, $details]);
}

$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_instructor'){
    $student_id = intval($_POST['student_id'] ?? 0);
    $instructor_id = intval($_POST['instructor_id'] ?? 0);

    try {
        if($student_id <= 0 || $instructor_id <= 0){
            throw new Exception('Select both a student and an instructor.');
        }

        $stmtStudent = $pdo->prepare(
            "SELECT u.user_id, u.full_name, u.branch_id, e.enrollment_id, tp.name AS program_name
             FROM users u
             JOIN enrollments e ON e.student_user_id = u.user_id
             JOIN training_programs tp ON e.program_id = tp.program_id
             WHERE u.user_id = ?
               AND u.branch_id = ?
               AND e.approval_status = 'approved'
               AND e.current_progress_status = 'enrolled'
               AND tp.branch_id = ?
             ORDER BY e.enrollment_date DESC
             LIMIT 1"
        );
        $stmtStudent->execute([$student_id, $branch_id, $branch_id]);
        $student = $stmtStudent->fetch();

        if(!$student){
            throw new Exception('Student not found in your branch or does not have an active enrollment.');
        }

        $stmtInstructor = $pdo->prepare(
            "SELECT u.user_id, u.full_name, u.branch_id, i.specialization
             FROM users u
             JOIN instructors i ON u.user_id = i.user_id
             WHERE u.user_id = ?
               AND u.branch_id = ?
               AND u.role = 'instructor'
               AND u.status = 'active'
             LIMIT 1"
        );
        $stmtInstructor->execute([$instructor_id, $branch_id]);
        $instructor = $stmtInstructor->fetch();

        if(!$instructor){
            throw new Exception('Instructor not found in your branch or is not active.');
        }

        $pdo->beginTransaction();

        $stmtDeactivate = $pdo->prepare("UPDATE instructor_assignments SET status = 'inactive' WHERE student_user_id = ? AND status = 'active'");
        $stmtDeactivate->execute([$student_id]);

        $stmtInsert = $pdo->prepare(
            "INSERT INTO instructor_assignments (student_user_id, instructor_user_id, assigned_by, status)
             VALUES (?, ?, ?, 'active')"
        );
        $stmtInsert->execute([$student_id, $instructor_id, $manager_id]);
        $assignment_id = intval($pdo->lastInsertId());

        log_audit_action(
            $pdo,
            $manager_id,
            'instructor_assigned',
            'instructor_assignment',
            $assignment_id,
            'Assigned instructor ' . $instructor['full_name'] . ' to student ' . $student['full_name'] . ' for program ' . $student['program_name']
        );

        $pdo->commit();
        $message = "<div class='toast show'>Instructor assigned successfully.</div>";
    } catch(Exception $e) {
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }
        $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

$available_instructors = [];
$students_needing_assignment = [];
$active_assignments = [];
$stats = [
    'students_needing_assignment' => 0,
    'active_assignments' => 0,
    'active_instructors' => 0,
];

try {
    $stmtInstructors = $pdo->prepare(
        "SELECT u.user_id, u.full_name, COALESCE(i.specialization, '') AS specialization
         FROM users u
         JOIN instructors i ON u.user_id = i.user_id
         WHERE u.branch_id = ? AND u.role = 'instructor' AND u.status = 'active'
         ORDER BY u.full_name ASC"
    );
    $stmtInstructors->execute([$branch_id]);
    $available_instructors = $stmtInstructors->fetchAll();

    $stmtStudents = $pdo->prepare(
        "SELECT u.user_id, u.full_name, tp.name AS program_name, tp.license_category, e.enrollment_id
         FROM users u
         JOIN enrollments e ON e.student_user_id = u.user_id
         JOIN training_programs tp ON e.program_id = tp.program_id
         LEFT JOIN instructor_assignments ia ON ia.student_user_id = u.user_id AND ia.status = 'active'
         WHERE u.branch_id = ?
           AND e.approval_status = 'approved'
           AND e.current_progress_status = 'enrolled'
           AND tp.branch_id = ?
           AND ia.assignment_id IS NULL
         ORDER BY e.enrollment_date DESC"
    );
    $stmtStudents->execute([$branch_id, $branch_id]);
    $students_needing_assignment = $stmtStudents->fetchAll();

    $stmtActiveAssignments = $pdo->prepare(
        "SELECT ia.assignment_id, ia.assigned_date, s.full_name AS student_name, i.full_name AS instructor_name,
                tp.name AS program_name, tp.license_category
         FROM instructor_assignments ia
         JOIN users s ON ia.student_user_id = s.user_id
         JOIN users i ON ia.instructor_user_id = i.user_id
         LEFT JOIN enrollments e ON e.student_user_id = s.user_id AND e.approval_status = 'approved' AND e.current_progress_status = 'enrolled'
         LEFT JOIN training_programs tp ON tp.program_id = e.program_id
         WHERE s.branch_id = ? AND i.branch_id = ? AND ia.status = 'active'
         ORDER BY ia.assigned_date DESC"
    );
    $stmtActiveAssignments->execute([$branch_id, $branch_id]);
    $active_assignments = $stmtActiveAssignments->fetchAll();

    $stats['students_needing_assignment'] = count($students_needing_assignment);
    $stats['active_assignments'] = count($active_assignments);
    $stats['active_instructors'] = count($available_instructors);
} catch(PDOException $e) {}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Supervisor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/app.js" defer></script>
    <style>
      .supervisor-shell {
        min-height: 100vh;
        background: linear-gradient(180deg, #f7f9fc 0%, #eef2f7 100%);
      }
      .hero-card {
        background: linear-gradient(135deg, #1f3c88 0%, #2b6cb0 100%);
        color: #fff;
      }
      .badge-soft {
        background: rgba(255,255,255,0.15);
        color: #fff;
      }
      .muted-note {
        color: #667085;
        font-size: 0.9rem;
      }
    </style>
  </head>
  <body class="supervisor-shell">
    <div class="app-wrapper">
      <?php include '../includes/header.php'; ?>

      <div class="main-content">
        <main class="page-content">
          <div class="card hero-card mb-4">
            <div class="d-flex justify-between align-center flex-wrap gap-md">
              <div>
                <span class="badge badge-soft mb-2">Supervisor</span>
                <h2 class="mb-1">Welcome, <?php echo htmlspecialchars($name_parts[0]); ?></h2>
                <p class="mb-0">Assign instructors to active students and keep the branch training flow moving.</p>
              </div>
              <div class="d-flex gap-md flex-wrap">
                <div class="card p-2" style="min-width: 150px; background: rgba(255,255,255,0.12); color:#fff;">
                  <div class="text-sm">Students to assign</div>
                  <div class="stat-value" style="color:#fff;"><?php echo intval($stats['students_needing_assignment']); ?></div>
                </div>
                <div class="card p-2" style="min-width: 150px; background: rgba(255,255,255,0.12); color:#fff;">
                  <div class="text-sm">Active assignments</div>
                  <div class="stat-value" style="color:#fff;"><?php echo intval($stats['active_assignments']); ?></div>
                </div>
                <div class="card p-2" style="min-width: 150px; background: rgba(255,255,255,0.12); color:#fff;">
                  <div class="text-sm">Active instructors</div>
                  <div class="stat-value" style="color:#fff;"><?php echo intval($stats['active_instructors']); ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Assign Instructor</h3>
            <p class="muted-note mb-3">Students shown here are approved and actively enrolled, but do not yet have an active instructor assignment.</p>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>License Category</th>
                    <th>Instructor</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($students_needing_assignment as $student): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['program_name']); ?></td>
                    <td><span class="badge badge-outline"><?php echo htmlspecialchars($student['license_category']); ?></span></td>
                    <td>
                      <form method="POST" class="d-flex gap-sm align-center flex-wrap" style="margin:0;">
                        <input type="hidden" name="action" value="assign_instructor">
                        <input type="hidden" name="student_id" value="<?php echo intval($student['user_id']); ?>">
                        <select name="instructor_id" class="form-control" required style="min-width: 220px;">
                          <option value="">-- Choose Instructor --</option>
                          <?php foreach($available_instructors as $instructor): ?>
                            <option value="<?php echo intval($instructor['user_id']); ?>"><?php echo htmlspecialchars($instructor['full_name']); ?><?php echo $instructor['specialization'] ? ' (' . htmlspecialchars($instructor['specialization']) . ')' : ''; ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Assign</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($students_needing_assignment) === 0): ?>
                  <tr><td colspan="4" class="text-center text-muted">No students are waiting for instructor assignment.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-md">
            <div class="card">
              <h3 class="card-subtitle mb-2">Active Assignments</h3>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Instructor</th>
                      <th>Program</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($active_assignments as $assignment): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($assignment['student_name']); ?></td>
                      <td><?php echo htmlspecialchars($assignment['instructor_name']); ?></td>
                      <td><?php echo htmlspecialchars($assignment['program_name'] ?? 'N/A'); ?></td>
                      <td><?php echo date('Y-m-d', strtotime($assignment['assigned_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($active_assignments) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted">No active assignments yet.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card">
              <h3 class="card-subtitle mb-2">Available Instructors</h3>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Specialization</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($available_instructors as $instructor): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($instructor['full_name']); ?></td>
                      <td><?php echo htmlspecialchars($instructor['specialization'] ?: 'General'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($available_instructors) === 0): ?>
                    <tr><td colspan="2" class="text-center text-muted">No active instructors found in this branch.</td></tr>
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
