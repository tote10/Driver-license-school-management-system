<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header('Location: ../login.php');
    exit();
}

$supervisor_id = intval($_SESSION['user_id']);
$branch_id = intval($_SESSION['branch_id'] ?? 0);
$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile'){
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = $_POST['password'] ?? '';
    try{
        if($full_name === '') throw new Exception('Full name is required.');
        if($password !== ''){
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, password_hash = ? WHERE user_id = ? AND role = 'supervisor'");
            $stmt->execute([$full_name, $phone, $hash, $supervisor_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ? AND role = 'supervisor'");
            $stmt->execute([$full_name, $phone, $supervisor_id]);
        }

        // profile photo
        if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0){
            $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $target = "../uploads/profiles/" . $supervisor_id . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)){
                $stmtDoc = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'profile_photo', ?, ?)");
                $stmtDoc->execute([$supervisor_id, "uploads/profiles/".basename($target), $supervisor_id]);
            }
        }
        // id document
        if(isset($_FILES['id_document']) && $_FILES['id_document']['error'] == 0){
            $ext = pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION);
            $target = "../uploads/documents/" . $supervisor_id . "_id_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['id_document']['tmp_name'], $target)){
                $stmtDoc = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'id_document', ?, ?)");
                $stmtDoc->execute([$supervisor_id, "uploads/documents/".basename($target), $supervisor_id]);
            }
        }

        $_SESSION['full_name'] = $full_name;
        $message = "<div class='toast show'>Profile updated successfully.</div>";
    } catch(Exception $e){
        $message = "<div class='toast show bg-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// fetch profile
$profile = [];
try{
    $stmt = $pdo->prepare("SELECT user_id, username, email, full_name, phone FROM users WHERE user_id = ? AND role = 'supervisor' LIMIT 1");
    $stmt->execute([$supervisor_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmtP = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='profile_photo' ORDER BY uploaded_at DESC LIMIT 1");
    $stmtP->execute([$supervisor_id]);
    $profile_photo = $stmtP->fetchColumn();
    $stmtD = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='id_document' ORDER BY uploaded_at DESC LIMIT 1");
    $stmtD->execute([$supervisor_id]);
    $id_document = $stmtD->fetchColumn();
} catch(PDOException $e){}

?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
  </head>
  <body>
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php $page_title = 'My Profile'; include __DIR__ . '/includes/topbar.php'; ?>
        <main class="page-content">
          <div class="toast-container"><?php if($message) echo $message; ?></div>
          <div class="card" style="max-width:700px; margin:0 auto;">
            <h3 class="card-subtitle">Supervisor Profile</h3>
            <form method="post" enctype="multipart/form-data" class="mt-2">
              <input type="hidden" name="action" value="update_profile">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Profile Photo</label>
                <?php if(!empty($profile_photo)): ?><div class="mb-2"><img src="../<?php echo htmlspecialchars($profile_photo); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;"></div><?php endif; ?>
                <input type="file" name="profile_photo" accept="image/png,image/jpeg">
              </div>
              <div class="form-group">
                <label class="form-label">National ID / Document</label>
                <?php if(!empty($id_document)): ?><div class="mb-2"><a href="../<?php echo htmlspecialchars($id_document); ?>" target="_blank" class="badge badge-primary">View Current ID Document</a></div><?php endif; ?>
                <input type="file" name="id_document" accept=".pdf,image/png,image/jpeg">
              </div>
              <div class="form-group">
                <label class="form-label">New Password (leave blank to keep current)</label>
                <input type="password" name="password" class="form-control">
              </div>
              <button class="btn btn-primary mt-2">Save Profile</button>
            </form>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>
