<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle Update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile'){
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if(empty($full_name)){
        $message = "<div class='toast show bg-danger'>Full name cannot be empty.</div>";
    } else {
        try {
            if(!empty($password)){
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, password_hash = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $phone, $hash, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $phone, $user_id]);
            }

            // Handle Profile Photo Upload
            if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0){
                $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $target = "../uploads/profiles/" . $user_id . "_" . time() . "." . $ext;
                if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)){
                    $stmt = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'profile_photo', ?, ?)");
                    $stmt->execute([$user_id, "uploads/profiles/".basename($target), $user_id]);
                }
            }

            // Handle ID Document Upload
            if(isset($_FILES['id_document']) && $_FILES['id_document']['error'] == 0){
                $ext = pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION);
                $target = "../uploads/documents/" . $user_id . "_id_" . time() . "." . $ext;
                if(move_uploaded_file($_FILES['id_document']['tmp_name'], $target)){
                    $stmt = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'id_document', ?, ?)");
                    $stmt->execute([$user_id, "uploads/documents/".basename($target), $user_id]);
                }
            }
            $_SESSION['full_name'] = $full_name;
            $message = "<div class='toast show'>Profile updated successfully!</div>";
        } catch(PDOException $e) {
            $message = "<div class='toast show bg-danger'>Error: ".$e->getMessage()."</div>";
        }
    }
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$full_name = ds_display_name('Manager');
$initials = ds_display_initials($full_name, 'Manager');

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>My Profile | Manager Dashboard</title>
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
        <?php $page_title = 'Profile Settings'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="toast-container">
            <?php if($message) echo $message; ?>
          </div>

          <div class="card" style="max-width: 600px; margin: 0 auto;">
              <h3 class="card-subtitle mb-3">Update Your Information</h3>
              <form method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="update_profile">
                  <div class="form-group">
                      <label class="form-label">Username (Cannot be changed)</label>
                      <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                  </div>
                  <div class="form-group">
                      <label class="form-label">Email (Cannot be changed)</label>
                      <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                  </div>
                  <div class="form-group">
                      <label class="form-label">Full Name</label>
                      <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                  </div>
                  <div class="form-group">
                      <label class="form-label">Phone Number</label>
                      <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                  </div>
                  <div class="form-group">
                      <label class="form-label">Profile Photo</label>
                      <?php 
                        $stmt_p = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='profile_photo' ORDER BY uploaded_at DESC LIMIT 1");
                        $stmt_p->execute([$user_id]);
                        if($pfile = $stmt_p->fetchColumn()): 
                      ?>
                        <div class="mb-2"><img src="../<?php echo htmlspecialchars($pfile); ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary);"></div>
                      <?php endif; ?>
                      <input type="file" name="profile_photo" class="form-control" accept="image/png, image/jpeg">
                  </div>
                  <div class="form-group">
                      <label class="form-label">National ID / Document</label>
                      <?php 
                        $stmt_d = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='id_document' ORDER BY uploaded_at DESC LIMIT 1");
                        $stmt_d->execute([$user_id]);
                        if($dfile = $stmt_d->fetchColumn()): 
                      ?>
                        <div class="mb-2"><a href="../<?php echo htmlspecialchars($dfile); ?>" target="_blank" class="badge badge-primary">View Current ID Document</a></div>
                      <?php endif; ?>
                      <input type="file" name="id_document" class="form-control" accept=".pdf, image/png, image/jpeg">
                  </div>
                  <div class="form-group mt-4">
                      <label class="form-label">New Password (leave blank to keep current)</label>
                      <input type="password" name="password" class="form-control" placeholder="••••••••">
                  </div>
                  <button type="submit" class="btn btn-primary mt-2">Save Profile</button>
              </form>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>
