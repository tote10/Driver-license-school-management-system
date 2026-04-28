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

// create program
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
// update program
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_program'){
    $pid = intval($_POST['program_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $license_category = trim($_POST['license_category'] ?? '');
    $theory_hours = intval($_POST['theory_duration_hours'] ?? 0);
    $practical_hours = intval($_POST['practical_duration_hours'] ?? 0);
    $fee_amount = floatval($_POST['fee_amount'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    if($pid <= 0 || empty($name) || empty($license_category) || $fee_amount <= 0){
        $message = "<div class='toast show bg-danger'>Please fill all required fields correctly.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE training_programs SET name=?, license_category=?, description=?, theory_duration_hours=?, practical_duration_hours=?, fee_amount=? WHERE program_id=? AND branch_id=?");
            $stmt->execute([$name, $license_category, $desc, $theory_hours, $practical_hours, $fee_amount, $pid, $branch_id]);
            $message = "<div class='toast show'>Successfully updated program: $name</div>";
        } catch(PDOException $e) {
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}
// delete program
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_program'){
    $pid = intval($_POST['program_id'] ?? 0);
    if($pid > 0){
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE program_id = ?");
            $chk->execute([$pid]);
            if($chk->fetchColumn() > 0){
                $message = "<div class='toast show bg-danger'>Cannot delete: students are enrolled in this program.</div>";
            } else {
                $del = $pdo->prepare("DELETE FROM training_programs WHERE program_id = ? AND branch_id = ?");
                $del->execute([$pid, $branch_id]);
                $message = "<div class='toast show'>Program deleted.</div>";
            }
        } catch(PDOException $e){
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
        <?php $page_title = 'Training Programs'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card p-3 mb-1">
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
                          <select name="license_category" class="form-control" required>
                            <option value="">-- Select Category --</option>
                              <option value="Auto">Auto</option>
                              <option value="Level 1">Level 1</option>
                              <option value="Level 2">Level 2</option>
                              <option value="Level 3">Level 3</option>
                              <option value="Level 4">Level 4</option>
                              <option value="Level 5">Level 5</option>
                              <option value="Level 6">Level 6</option>
                          </select>
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
        <!-- Edit Program Card (hidden) -->
<div class="card p-3 mb-1" id="editProgramCard" style="display:none;">
  <h3 class="card-subtitle">Edit Training Program</h3>
  <form method="POST" id="editProgramForm" class="mt-3">
    <input type="hidden" name="action" value="update_program">
    <input type="hidden" name="program_id" id="edit_program_id">

    <div class="grid grid-cols-4 gap-md">
      <div class="form-group">
        <label class="form-label">Program Name</label>
        <input type="text" name="name" id="edit_name" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">License Category</label>
        <select name="license_category" id="edit_license_category" class="form-control" required>
          <option value="">-- Select Category --</option>
          <option value="Auto">Auto</option>
          <option value="Level 1">Level 1</option>
          <option value="Level 2">Level 2</option>
          <option value="Level 3">Level 3</option>
          <option value="Level 4">Level 4</option>
          <option value="Level 5">Level 5</option>
          <option value="Level 6">Level 6</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Theory (Hours)</label>
        <input type="number" name="theory_duration_hours" id="edit_theory" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Practical (Hours)</label>
        <input type="number" name="practical_duration_hours" id="edit_practical" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Fee Amount ($)</label>
        <input type="number" step="0.01" name="fee_amount" id="edit_fee" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input type="text" name="description" id="edit_description" class="form-control">
      </div>
    </div>

    <div class="d-flex gap-sm mt-2">
      <button type="submit" class="btn btn-primary">Save Program</button>
      <button type="button" class="btn btn-outline" id="cancelEditProgram">Cancel</button>
    </div>
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
                  <div>
                    <button type="button" class="btn btn-outline btn-sm edit-program-btn" data-program="<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>">Edit</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this program? This cannot be undone.');">
                      <input type="hidden" name="action" value="delete_program">
                      <input type="hidden" name="program_id" value="<?php echo $p['program_id']; ?>">
                      <button type="submit" class="btn btn-outline btn-sm">Delete</button>
                    </form>
                  </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </main>
      </div>
    </div>


    <script>
  document.querySelectorAll('.edit-program-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = JSON.parse(btn.dataset.program);
      document.getElementById('edit_program_id').value = p.program_id || '';
      document.getElementById('edit_name').value = p.name || '';
      document.getElementById('edit_license_category').value = p.license_category || (p.license_category ?? '');
      document.getElementById('edit_theory').value = p.theory_duration_hours || 0;
      document.getElementById('edit_practical').value = p.practical_duration_hours || 0;
      document.getElementById('edit_fee').value = p.fee_amount || 0;
      document.getElementById('edit_description').value = p.description || '';
      document.getElementById('editProgramCard').style.display = 'block';
      document.getElementById('editProgramCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  document.getElementById('cancelEditProgram')?.addEventListener('click', () => {
    document.getElementById('editProgramCard').style.display = 'none';
  });
</script>
  </body>
</html>