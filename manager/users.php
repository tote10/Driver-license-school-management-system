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

// --- ACTION: CREATE STAFF ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create'){
    $username = trim($_POST['username'] ?? '');
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if(empty($username) || empty($email) || empty($password)){
        $message = "<div class='toast show bg-danger'>Required fields missing.</div>";
    } else {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, branch_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmtUser->execute([$username, $email, $hash, $name, $role, $branch_id]);
            
            if($role == 'instructor'){
                $new_id = $pdo->lastInsertId();
                $stmtIns = $pdo->prepare("INSERT INTO instructors (user_id, years_experience) VALUES (?, 0)");
                $stmtIns->execute([$new_id]);
            }
            $pdo->commit();
            $message = "<div class='toast show'>Successfully created $role: $name</div>";
        } catch(Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// --- ACTION: UPDATE USER STATUS ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status'){
    $target_id = intval($_POST['user_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    if($target_id > 0 && in_array($new_status, ['active', 'pending', 'suspended', 'rejected'])){
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ? AND branch_id = ?");
            $stmt->execute([$new_status, $target_id, $branch_id]);
            $message = "<div class='toast show'>User status updated to $new_status.</div>";
        } catch(PDOException $e) {
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch users
$users = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE branch_id = ? ORDER BY role DESC, full_name ASC");
    $stmt->execute([$branch_id]);
    $users = $stmt->fetchAll();
} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Manage Users | Manager Dashboard</title>
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
        <?php $page_title = 'Manage Users'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card mb-2">
              <h3 class="card-subtitle">Add New Staff Member</h3>
              <form method="POST" class="mt-3">
                  <input type="hidden" name="action" value="create">
                  <div class="grid grid-cols-3 gap-md">
                      <div class="form-group">
                          <label class="form-label">Username</label>
                          <input type="text" name="username" class="form-control" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Full Name</label>
                          <input type="text" name="full_name" class="form-control" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Email</label>
                          <input type="email" name="email" class="form-control" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Password</label>
                          <input type="password" name="password" class="form-control" required>
                      </div>
                      <div class="form-group">
                          <label class="form-label">Role</label>
                          <select name="role" class="form-control">
                              <option value="instructor">Instructor</option>
                              <option value="supervisor">Supervisor</option>
                          </select>
                      </div>
                  </div>
                  <button type="submit" class="btn btn-primary mt-2">Create Account</button>
              </form>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-2">Branch Staff Directory</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($users as $u): ?>
                  <tr>
                    <td class="font-bold">
                        <?php echo htmlspecialchars($u['full_name']); ?><br>
                        <small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                    </td>
                    <td><span class="badge badge-primary"><?php echo strtoupper($u['role']); ?></span></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                      <span class="badge <?php echo $u['status'] == 'active' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo strtoupper($u['status']); ?>
                      </span>
                    </td>
                    <td>
                        <form method="POST" class="d-flex gap-sm align-center" style="margin:0;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                            <select name="status" class="form-control" style="padding: 0.3rem; font-size: 0.8rem; width: auto;">
                                <option value="active" <?php echo $u['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo $u['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="suspended" <?php echo $u['status'] == 'suspended' ? 'selected' : ''; ?>>Suspend</option>
                            </select>
                            <button type="submit" class="btn btn-outline btn-sm">Save</button>
                        </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>