<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$supervisor_id = intval($_SESSION['user_id']);
$full_name = ds_display_name('Supervisor');
$message = '';

function log_audit_action($pdo, $user_id, $action_type, $entity_type, $entity_id, $details) {
    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmtLog->execute([$user_id, $action_type, $entity_type, $entity_id, $details]);
}


function is_specialization_compatible($student_license, $instructor_specialization) {
    $student_license = trim((string)$student_license);
    $instructor_specialization = trim((string)$instructor_specialization);
    return $instructor_specialization === '' || strcasecmp($instructor_specialization, 'General') === 0 || strcasecmp($instructor_specialization, $student_license) === 0;

}
$initials = ds_display_initials($full_name, 'Supervisor');

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_instructor'){
    $student_id = intval($_POST['student_id'] ?? 0);
    $instructor_id = intval($_POST['instructor_id'] ?? 0);

    try {
        if($student_id <= 0 || $instructor_id <= 0){
            throw new Exception('Select both a student and an instructor.');
        }

        $stmtStudent = $pdo->prepare("
            SELECT u.user_id, u.full_name, e.enrollment_id, tp.name AS program_name, tp.license_category
            FROM users u
            JOIN enrollments e ON e.student_user_id = u.user_id
            JOIN training_programs tp ON e.program_id = tp.program_id
            WHERE u.user_id = ?
              AND u.branch_id = ?
              AND e.approval_status = 'approved'
              AND e.current_progress_status = 'enrolled'
              AND tp.branch_id = ?
            ORDER BY e.enrollment_date DESC
            LIMIT 1
        ");
        $stmtStudent->execute([$student_id, $branch_id, $branch_id]);
        $student = $stmtStudent->fetch();

        if(!$student){
            throw new Exception('Student not found in your branch or does not have an active enrollment.');
        }

        $stmtInstructor = $pdo->prepare("
            SELECT u.user_id, u.full_name, COALESCE(i.specialization, '') AS specialization
            FROM users u
            LEFT JOIN instructors i ON u.user_id = i.user_id
            WHERE u.user_id = ?
              AND u.branch_id = ?
              AND u.role = 'instructor'
              AND u.status = 'active'
            LIMIT 1
        ");
        $stmtInstructor->execute([$instructor_id, $branch_id]);
        $instructor = $stmtInstructor->fetch();

        if(!$instructor){
            throw new Exception('Instructor not found in your branch or is not active.');
        }
        if(!is_specialization_compatible($student['license_category'], $instructor['specialization'])){
            throw new Exception('Instructor specialization does not match student license category.');
        }
        $pdo->beginTransaction();
        $stmtActive = $pdo->prepare("
        SELECT assignment_id, instructor_user_id
        FROM instructor_assignments
        WHERE student_user_id = ? AND status = 'active'
          LIMIT 1
          FOR UPDATE
        ");
        $stmtActive->execute([$student_id]);
        $active = $stmtActive->fetch();
        if($active && intval($active['instructor_user_id']) === $instructor_id){
        throw new Exception('Student already assigned to this instructor.');
}
        $stmtDeactivate = $pdo->prepare("UPDATE instructor_assignments SET status = 'inactive' WHERE student_user_id = ? AND status = 'active'");
        $stmtDeactivate->execute([$student_id]);

        $stmtInsert = $pdo->prepare("
            INSERT INTO instructor_assignments (student_user_id, instructor_user_id, assigned_by, status)
            VALUES (?, ?, ?, 'active')
        ");
        $stmtInsert->execute([$student_id, $instructor_id, $supervisor_id]);

        $assignment_id = intval($pdo->lastInsertId());

        send_notification($pdo, $student_id, 'instructor_assigned', 'Instructor assigned', 'An instructor has been assigned to your current training program: ' . $student['program_name']);
        send_notification($pdo, $instructor_id, 'assigned_student', 'New student assigned', 'You have been assigned to student ' . $student['full_name'] . ' for ' . $student['program_name'] . '.');

        log_audit_action(
            $pdo,
            $supervisor_id,
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
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unassign'){
    $assignment_id = intval($_POST['assignment_id'] ?? 0);

    try{
        $pdo->beginTransaction();
        if($assignment_id <= 0) throw new Exception('Invalid assignment.');
        $stmt = $pdo->prepare("
            SELECT ia.assignment_id, ia.student_user_id, ia.instructor_user_id,
                   s.branch_id AS student_branch, i.branch_id AS instructor_branch,
                   s.full_name AS student_name, i.full_name AS instructor_name
            FROM instructor_assignments ia
            JOIN users s ON ia.student_user_id = s.user_id
            JOIN users i ON ia.instructor_user_id = i.user_id
            WHERE ia.assignment_id = ? AND ia.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$assignment_id]);
        $row = $stmt->fetch();
        if(!$row){
            throw new Exception('Active assignment not found.');
        }
        if(intval($row['student_branch']) !== $branch_id || intval($row['instructor_branch']) !== $branch_id){
            throw new Exception('Assignment not in your branch.');
        }
        $stmtUpdate = $pdo->prepare("UPDATE instructor_assignments SET status = 'inactive' WHERE assignment_id = ?");
        $stmtUpdate->execute([$assignment_id]);
        send_notification($pdo, intval($row['student_user_id']), 'instructor_unassigned', 'Instructor unassigned', 'Your instructor assignment has been removed. A new assignment may be created later.');
        send_notification($pdo, intval($row['instructor_user_id']), 'student_unassigned', 'Student unassigned', 'Your assignment to ' . $row['student_name'] . ' has been removed.');
        log_audit_action(
            $pdo,
            $supervisor_id,
            'unassign_instructor',
            'instructor_assignments',
            $assignment_id,
            'Unassigned instructor ' . $row['instructor_name'] . ' from student ' . $row['student_name']
        );
        $pdo->commit();
        $message = "<div class='toast show'>Assignment removed.</div>";
      } catch(Exception $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

$available_instructors = [];
$students_needing_assignment = [];
$active_assignments = [];

try {
    $stmtInstructors = $pdo->prepare("
        SELECT u.user_id, u.full_name, COALESCE(i.specialization, '') AS specialization
        FROM users u
        LEFT JOIN instructors i ON u.user_id = i.user_id
        WHERE u.branch_id = ? AND u.role = 'instructor' AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmtInstructors->execute([$branch_id]);
    $available_instructors = $stmtInstructors->fetchAll();

    $stmtStudents = $pdo->prepare("
        SELECT u.user_id, u.full_name, tp.name AS program_name, tp.license_category, e.enrollment_id
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
    ");
    $stmtStudents->execute([$branch_id, $branch_id]);
    $students_needing_assignment = $stmtStudents->fetchAll();

    $stmtActiveAssignments = $pdo->prepare("
        SELECT ia.assignment_id, ia.assigned_date, s.full_name AS student_name, i.full_name AS instructor_name,
               tp.name AS program_name, tp.license_category
        FROM instructor_assignments ia
        JOIN users s ON ia.student_user_id = s.user_id
        JOIN users i ON ia.instructor_user_id = i.user_id
        LEFT JOIN enrollments e ON e.student_user_id = s.user_id AND e.approval_status = 'approved' AND e.current_progress_status = 'enrolled'
        LEFT JOIN training_programs tp ON tp.program_id = e.program_id
        WHERE s.branch_id = ? AND i.branch_id = ? AND ia.status = 'active'
        ORDER BY ia.assigned_date DESC
    ");
    $stmtActiveAssignments->execute([$branch_id, $branch_id]);
    $active_assignments = $stmtActiveAssignments->fetchAll();
} catch(PDOException $e) {
  $message = "<div class='toast show bg-danger'>Error loading assignment data: " . htmlspecialchars($e->getMessage()) . "</div>";
}

$page_title = 'Instructor Assignments';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Instructor Assignments</title>
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
        <?php include 'includes/topbar.php'; ?>

        <main class="page-content">
          <h2 class="welcome-heading" style="margin-bottom: 20px;">
            Instructor <span>Assignments</span>
          </h2>

          <div class="action-bar d-flex flex-wrap gap-md align-center justify-between w-100">
            <div class="d-flex gap-md flex-wrap">
              <a href="dashboard.php" class="btn btn-outline">Supervisor Dashboard</a>
              <a href="reviews.php" class="btn btn-outline">Training Reviews</a>
              <a href="progress.php" class="btn btn-outline">Student Progress</a>
            </div>
            <div>
              <a href="complaints.php" class="btn btn-outline">Complaints</a>
            </div>
          </div>

          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Assign Instructor to Student</h3>
            <p class="text-sm text-muted mb-3">Only approved students with active enrollments and no active assignment are listed here.</p>
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
                        <?php
                        $compatible = array_values(array_filter($available_instructors, function($ins) use ($student) {
                        return is_specialization_compatible($student['license_category'], $ins['specialization']);
                         })    
                        );
                        if(count($compatible) === 0): ?>
                        <option value="" disabled>No compatible instructors available</option>
                        <?php else: ?>
                         <?php foreach($compatible as $instructor): ?>
                         <option value="<?php echo intval($instructor['user_id']); ?>">
                          <?php echo htmlspecialchars($instructor['full_name']); ?>
                          <?php echo $instructor['specialization'] ? ' (' . htmlspecialchars($instructor['specialization']) . ')' : ''; ?>
                         </option>
                         <?php endforeach; ?>
                        <?php endif; ?>
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
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($active_assignments as $assignment): ?>
                    <tr>
                      <td class="font-bold"><?php echo htmlspecialchars($assignment['student_name']); ?></td>
                      <td><?php echo htmlspecialchars($assignment['instructor_name']); ?></td>
                      <td><?php echo htmlspecialchars($assignment['program_name'] ?? 'N/A'); ?></td>
                      <td><?php echo date('Y-m-d', strtotime($assignment['assigned_date'])); ?></td>
                      <td>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Unassign this instructor?');">
                          <input type="hidden" name="action" value="unassign">
                          <input type="hidden" name="assignment_id" value="<?php echo intval($assignment['assignment_id']); ?>">
                          <button type="submit" class="btn btn-danger btn-sm">Unassign</button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($active_assignments) === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted">No active assignments yet.</td></tr>
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