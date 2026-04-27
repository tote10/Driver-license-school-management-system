<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';
$message   = "";

// Safe initials
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

// --- ACTION: CREATE PROGRAM ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create'){
    $name = trim($_POST['name'] ?? '');
    $license_category = trim($_POST['license_category'] ?? '');
    $theory_hours = intval($_POST['theory_duration_hours'] ?? 0);
    $practical_hours = intval($_POST['practical_duration_hours'] ?? 0);
    $fee_amount = floatval($_POST['fee_amount'] ?? 0);
    $desc = trim($_POST['description'] ?? '');

    if(empty($name) || empty($license_category) || $fee_amount <= 0){
        $message = "<div class='toast show bg-danger'>Please fill all required fields correctly.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO training_programs (name, license_category, description, theory_duration_hours, practical_duration_hours, fee_amount, branch_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $license_category, $desc, $theory_hours, $practical_hours, $fee_amount, $branch_id, $_SESSION['user_id']]);
            $message = "<div class='toast show'>Successfully created program: $name</div>";
        } catch(PDOException $e) {
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch programs
$programs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM training_programs WHERE branch_id = ? ORDER BY name ASC");
    $stmt->execute([$branch_id]);
    $programs = $stmt->fetchAll();
} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Training Programs | Manager Dashboard</title>
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
        <?php $page_title = 'Training Programs'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card mb-2">
              <h3 class="card-subtitle">Add New Training Program</h3>
              <form method="POST" class="mt-3">
                  <input type="hidden" name="action" value="create">
                  <div class="grid grid-cols-4 gap-md">
                      <div class="form-group">
                          <label class="form-label">Program Name</label>
                          <input type="text" name="name" class="form-control" placeholder="e.g. Standard License" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">License Category</label>
                          <input type="text" name="license_category" class="form-control" placeholder="e.g. Auto, Manual" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Theory (Hours)</label>
                          <input type="number" name="theory_duration_hours" class="form-control" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Practical (Hours)</label>
                          <input type="number" name="practical_duration_hours" class="form-control" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Fee Amount ($)</label>
                          <input type="number" step="0.01" name="fee_amount" class="form-control" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Description</label>
                          <input type="text" name="description" class="form-control">
                      </div>
                  </div>
                  <button type="submit" class="btn btn-primary mt-2">Create Program</button>
              </form>
          </div>

          <div class="grid grid-cols-3">
            <?php foreach($programs as $p): ?>
            <div class="card">
              <h3 class="card-subtitle"><?php echo htmlspecialchars($p['name']); ?></h3>
              <div class="stat-value" style="font-size: 1.5rem; margin: 10px 0;">$<?php echo number_format($p['fee_amount'], 2); ?></div>
              <p class="text-sm text-muted mb-4"><?php echo htmlspecialchars($p['description']); ?></p>
              <div class="d-flex justify-between align-center">
                  <span class="badge badge-primary"><?php echo $p['theory_duration_hours'] + $p['practical_duration_hours']; ?> Hrs Total</span>
                  <button class="btn btn-outline btn-sm">Manage</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>