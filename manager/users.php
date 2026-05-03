<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}
$branch_id = $_SESSION['branch_id'];
$full_name = ds_display_name('Manager');
$message   = "";
// Filters
$filter_role = trim((string)($_GET['filter_role'] ?? ''));
$filter_status = trim((string)($_GET['filter_status'] ?? ''));
$filter_q = trim((string)($_GET['q'] ?? ''));
$initials = ds_display_initials($full_name, 'Manager');

// --- ACTION: CREATE STAFF ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create'){
    $username = trim($_POST['username'] ?? '');
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? '';
  $specialization = trim($_POST['specialization'] ?? '');
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
              if($specialization === ''){
                $specialization = 'General';
              }
              $stmtIns = $pdo->prepare("INSERT INTO instructors (user_id, years_experience, specialization) VALUES (?, 0, ?)");
              $stmtIns->execute([$new_id, $specialization]);
            }
            $pdo->commit();
            $message = "<div class='toast show'>Successfully created $role: $name</div>";
        } catch(Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}
//update user status
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

    // update user details
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_user'){
      $target_id = intval($_POST['user_id'] ?? 0);
      $name      = trim($_POST['full_name'] ?? '');
      $email     = trim($_POST['email'] ?? '');
      $phone     = trim($_POST['phone'] ?? '');
      $role      = $_POST['role'] ?? '';
      $status    = $_POST['status'] ?? '';
      $password  = trim($_POST['password'] ?? '');

      if($target_id > 0 && $name !== '' && $email !== '' && in_array($role, ['student', 'instructor', 'supervisor']) && in_array($status, ['active', 'pending', 'suspended', 'rejected'])){
        try {
          $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE user_id = ? AND branch_id = ?");
          $stmt->execute([$name, $email, $phone, $role, $status, $target_id, $branch_id]);

          if($password !== ''){
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtPw = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND branch_id = ?");
            $stmtPw->execute([$hash, $target_id, $branch_id]);
          }

          $message = "<div class='toast show'>User details updated successfully.</div>";
        } catch(PDOException $e) {
          $message = "<div class='toast show bg-danger'>Error: " . $e->getMessage() . "</div>";
        }
      } else {
        $message = "<div class='toast show bg-danger'>Please fill in all required fields correctly.</div>";
      }
    }

// Fetch users with filters
$users = [];
try {
  $sql = "SELECT * FROM users WHERE branch_id = ?";
  $params = [$branch_id];
  if($filter_role !== ''){ $sql .= " AND role = ?"; $params[] = $filter_role; }
  if($filter_status !== ''){ $sql .= " AND status = ?"; $params[] = $filter_status; }
  if($filter_q !== ''){ $sql .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ? )"; $like = '%'.$filter_q.'%'; $params[] = $like; $params[] = $like; $params[] = $like; }
  $sql .= " ORDER BY role DESC, full_name ASC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $users = $stmt->fetchAll();

  // Attach latest profile photo and ID document to each user for manager view
  foreach($users as &$uu){
    $uid = intval($uu['user_id']);
    $stmtDoc = $pdo->prepare("SELECT document_type, file_url FROM documents WHERE entity_id = ? AND entity_type = 'user' ORDER BY uploaded_at DESC");
    $stmtDoc->execute([$uid]);
    $docs = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
    $uu['profile_photo'] = '';
    $uu['id_document'] = '';
    foreach($docs as $doc){
      if($uu['profile_photo'] === '' && $doc['document_type'] === 'profile_photo') $uu['profile_photo'] = $doc['file_url'];
      if($uu['id_document'] === '' && $doc['document_type'] === 'id_document') $uu['id_document'] = $doc['file_url'];
    }
  }
  unset($uu);
} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Manage Users | Manager Dashboard</title>
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
        <?php $page_title = 'Manage Users'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card p-3 mb-1" id="editUserCard" style="display:none;">
            <h3 class="card-subtitle">Edit User Details</h3>
            <form method="POST" class="mt-3" id="editUserForm">
              <input type="hidden" name="action" value="update_user">
              <input type="hidden" name="user_id" id="edit_user_id">
              <div class="grid grid-cols-3 gap-md">
                <div class="form-group">
                  <label class="form-label">Full Name</label>
                  <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                  <div class="form-group">
                    <label class="form-label">Profile Photo</label>
                    <div id="edit_profile_photo_preview"></div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">ID Document</label>
                    <div id="edit_id_document_link"></div>
                  </div>
                <div class="form-group">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Phone</label>
                  <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <div class="form-group">
                  <label class="form-label">Role</label>
                  <select name="role" id="edit_role" class="form-control" required>
                    <option value="student">Student</option>
                    <option value="instructor">Instructor</option>
                    <option value="supervisor">Supervisor</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Status</label>
                  <select name="status" id="edit_status" class="form-control" required>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                    <option value="rejected">Rejected</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">New Password</label>
                  <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep current password">
                </div>
              </div>
              <div class="d-flex gap-sm mt-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-outline" id="cancelEditUser">Cancel</button>
              </div>
            </form>
          </div>

          <div class="card p-3 mb-1">
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
                        <div class="form-group">
                          <label class="form-label">Specialization</label>
                          <select name="specialization" class="form-control">
                            <option value="">General</option>
                            <option value="Auto">Auto</option>
                            <option value="Level 1">Level 1</option>
                            <option value="Level 2">Level 2</option>
                            <option value="Level 3">Level 3</option>
                            <option value="Level 4">Level 4</option>
                            <option value="Level 5">Level 5</option>
                            <option value="Level 6">Level 6</option>
                          </select>
                        </div>
                  </div>
                  <button type="submit" class="btn btn-primary mt-2">Create Account</button>
              </form>
          </div>

          <div class="card p-2 mb-2">
            <h3 class="card-subtitle mb-1">Filters</h3>
            <form method="GET" class="d-flex gap-sm align-center">
              <div class="form-group mb-0">
                <label class="form-label">Role</label>
                <select name="filter_role" class="form-control" onchange="this.form.submit()">
                  <option value="">All Roles</option>
                  <option value="student" <?php echo $filter_role==='student' ? 'selected' : ''; ?>>Student</option>
                  <option value="instructor" <?php echo $filter_role==='instructor' ? 'selected' : ''; ?>>Instructor</option>
                  <option value="supervisor" <?php echo $filter_role==='supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                  <option value="manager" <?php echo $filter_role==='manager' ? 'selected' : ''; ?>>Manager</option>
                </select>
              </div>
              <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select name="filter_status" class="form-control" onchange="this.form.submit()">
                  <option value="">Any</option>
                  <option value="active" <?php echo $filter_status==='active' ? 'selected' : ''; ?>>Active</option>
                  <option value="pending" <?php echo $filter_status==='pending' ? 'selected' : ''; ?>>Pending</option>
                  <option value="suspended" <?php echo $filter_status==='suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
              </div>
              <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" placeholder="Name, email or username" value="<?php echo htmlspecialchars($filter_q); ?>">
              </div>
              <div class="form-group mb-0">
                <button type="submit" class="btn btn-outline">Apply</button>
                <a href="users.php" class="btn btn-outline">Reset</a>
              </div>
            </form>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-1">Branch Staff Directory</h3>
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
                        <button type="button" class="btn btn-primary btn-sm edit-user-btn mt-1" data-user="<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>">Edit</button>
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

    <script>
      document.querySelectorAll('.edit-user-btn').forEach((button) => {
        button.addEventListener('click', () => {
          const user = JSON.parse(button.dataset.user);
          document.getElementById('edit_user_id').value = user.user_id || '';
          document.getElementById('edit_full_name').value = user.full_name || '';
          document.getElementById('edit_email').value = user.email || '';
          document.getElementById('edit_phone').value = user.phone || '';
          document.getElementById('edit_role').value = user.role || 'instructor';
          document.getElementById('edit_status').value = user.status || 'active';
          document.getElementById('edit_password').value = '';
          // profile photo preview
          const photoContainer = document.getElementById('edit_profile_photo_preview');
          photoContainer.innerHTML = '';
          if(user.profile_photo){
            const img = document.createElement('img');
            img.src = '../' + user.profile_photo;
            img.style.width = '64px'; img.style.height = '64px'; img.style.objectFit = 'cover'; img.style.borderRadius = '50%'; img.style.border = '2px solid var(--primary)';
            photoContainer.appendChild(img);
          } else {
            photoContainer.innerHTML = '<span class="text-sm text-muted">No photo</span>';
          }
          // id document link
          const idContainer = document.getElementById('edit_id_document_link');
          idContainer.innerHTML = '';
          if(user.id_document){
            const a = document.createElement('a');
            a.href = '../' + user.id_document; a.target = '_blank'; a.className = 'badge badge-primary';
            a.innerText = 'View ID Document';
            idContainer.appendChild(a);
          } else {
            idContainer.innerHTML = '<span class="text-sm text-muted">No ID uploaded</span>';
          }
          document.getElementById('editUserCard').style.display = 'block';
          document.getElementById('editUserCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });

      document.getElementById('cancelEditUser')?.addEventListener('click', () => {
        document.getElementById('editUserCard').style.display = 'none';
      });
    </script>
  </body>
</html>