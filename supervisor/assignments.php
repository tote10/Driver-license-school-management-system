<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$supervisor_id = intval($_SESSION['user_id']);
$full_name = $_SESSION['full_name'] ?? 'Supervisor';
$message = '';
function log_audit_action($pdo, $user_id, $action_type, $entity_type, $entity_id, $details){
    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmtLog->execute([$user_id, $action_type, $entity_type, $entity_id, $details]);
}
function is_specialization_compatible($student_license, $instructor_specialization){
  $student_license = trim((string)$student_license);
  $instructor_specialization = trim((string)$instructor_specialization);
  return $instructor_specialization === ''
    || strcasecmp($instructor_specialization, 'General') === 0
    || strcasecmp($student_license, $instructor_specialization) === 0;
}
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_instructor'){
    $student_id=intval($_POST['student_id'] ?? 0);
    $instructor_id=intval($_POST['instructor_id'] ?? 0);
    try{
        if($student_id <= 0 || $instructor_id <= 0){
            throw new Exception("Invalid student or instructor selection.");
        }
        $stmtStudent = $pdo->prepare("SELECT u.user_id, u.full_name, e.enrollment_id, tp.name AS program_name,tp.license_category
        FROM users u
        JOIN enrollments e ON e.student_user_id = u.user_id
        JOIN training_programs tp ON e.program_id = tp.program_id
        WHERE u.user_id=? AND u.branch_id=? AND e.approval_status='approved' AND e.current_progress_status='enrolled' AND tp.branch_id=?
        ORDER BY e.enrollment_date DESC LIMIT 1");
        $stmtStudent->execute([$student_id, $branch_id, $branch_id]);
        $student = $stmtStudent->fetch();
        if(!$student){
            throw new Exception("Selected student is not eligible for assignment.");
        }
        $stmtInstructor = $pdo->prepare("SELECT u.user_id ,u.full_name, COALESCE(i.specialization, '') AS specialization
        FROM users u
        LEFT JOIN instructors i ON u.user_id = i.user_id
        WHERE u.user_id = ? AND u.branch_id= ? AND u.role = 'instructor' AND u.status = 'active'
        LIMIT 1");
        $stmtInstructor->execute([$instructor_id, $branch_id]);
        $instructor = $stmtInstructor->fetch();
        if(!$instructor){
            throw new Exception("Selected instructor is not available for assignment.");
        }
        if(!is_specialization_compatible($student['license_category'], $instructor['specialization'])){
          throw new Exception("Selected instructor specialization does not match the student's license category.");
        }
        $pdo->beginTransaction();
        
        $stmtDeactivate = $pdo->prepare("UPDATE instructor_assignments SET status = 'inactive' WHERE student_user_id = ? AND status = 'active'");
        $stmtDeactivate->execute([$student_id]);
        $stmtInsert = $pdo->prepare("INSERT INTO instructor_assignments (student_user_id, instructor_user_id, assigned_by, status) VALUES (?, ?, ?, 'active')");
        $stmtInsert->execute([$student_id, $instructor_id, $supervisor_id]);
        $assignment_id = $pdo->lastInsertId();
        log_audit_action(
            $pdo,
            $supervisor_id,
            'instructor_assigned',
            'instructor_assignments',
            $assignment_id,
            'Assigned instructor ' .$instructor['full_name'] .' to student ' . $student['full_name'] .' for program ' . $student['program_name']
        );
        $pdo->commit();
        $message = "Instructor assigned successfully to student.";
    } catch (Exception $e) {
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }
        $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
$available_instructors = [];
$students_needing_assignment = [];
$active_assignments = [];

try{
    $stmtInstructors = $pdo->prepare("SELECT u.user_id, u.full_name, COALESCE(i.specialization, '') AS specialization
    FROM users u
    LEFT JOIN instructors i ON u.user_id = i.user_id
    WHERE u.branch_id = ? AND u.role = 'instructor' AND u.status = 'active'
    ORDER BY u.full_name ASC");
    $stmtInstructors->execute([$branch_id]);
    $available_instructors = $stmtInstructors->fetchAll();


    $stmtStudents = $pdo->prepare("
        SELECT u.user_id, u.full_name, tp.name AS program_name, tp.license_category, e.enrollment_id
        FROM users u
        JOIN enrollments e ON e.student_user_id = u.user_id
        JOIN training_programs tp ON e.program_id = tp.program_id
        LEFT JOIN instructor_assignments ia ON ia.student_user_id = u.user_id AND ia.status = 'active'
        WHERE u.branch_id = ?
          AND e.approval_status = 'approved'
          AND e.current_progress_status = 'enrolled'
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
} catch(PDOException $e) {}
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
                          <option value="">-- Choose Instructor --</option>
                          <?php foreach($available_instructors as $instructor): ?>
                            <?php if(is_specialization_compatible($student['license_category'], $instructor['specialization'])): ?>
                              <option value="<?php echo intval($instructor['user_id']); ?>">
                                <?php echo htmlspecialchars($instructor['full_name']); ?>
                                <?php echo $instructor['specialization'] ? ' (' . htmlspecialchars($instructor['specialization']) . ')' : ''; ?>
                              </option>
                            <?php endif; ?>
                          <?php endforeach; ?>
                          <?php if(!array_filter($available_instructors, function($instructor) use ($student) {
                              return is_specialization_compatible($student['license_category'], $instructor['specialization']);
                          })): ?>
                            <option value="" disabled>No compatible instructors available</option>
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