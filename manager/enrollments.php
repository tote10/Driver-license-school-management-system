<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';
$message = "";

// Safe initials
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

// Action: Approve
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'approve'){
    $eid = intval($_POST['enrollment_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE enrollments SET approval_status = 'approved', approved_date = NOW() WHERE enrollment_id = ?");
        $stmt->execute([$eid]);
        $message = "<div class='toast show'>Enrollment approved!</div>";
    } catch(PDOException $e) { $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>"; }
}

// Fetch pending
$pending = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name, tp.name as program_name 
        FROM enrollments e 
        JOIN users u ON e.student_user_id = u.user_id 
        JOIN training_programs tp ON e.program_id = tp.program_id
        WHERE u.branch_id = ? AND e.approval_status = 'pending'
    ");
    $stmt->execute([$branch_id]);
    $pending = $stmt->fetchAll();
} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Registrations | Manager Dashboard</title>
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
        <?php $page_title = 'Registrations & Enrollments'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-3">Pending Student Registrations</h3>
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
                    <td class="font-bold"><?php echo htmlspecialchars($p['full_name']); ?></td>
                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($p['program_name']); ?></span></td>
                    <td><?php echo date('Y-m-d', strtotime($p['enrollment_date'])); ?></td>
                    <td>
                      <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="enrollment_id" value="<?php echo $p['enrollment_id']; ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Approve Student</button>
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
        </main>
      </div>
    </div>
  </body>
</html>