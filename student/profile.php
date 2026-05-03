<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$branch_id = intval($_SESSION['branch_id'] ?? 0);
$page_title = 'My Profile';
$message = '';
$success = false;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($full_name === '' || $phone === '') {
            $message = 'Full name and phone are required.';
        } else {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ? AND role = 'student'");
            $stmt->execute([$full_name, $phone, $student_id]);

            $stmt2 = $pdo->prepare("UPDATE students SET address = ? WHERE user_id = ?");
            $stmt2->execute([$address, $student_id]);

          // Handle profile photo upload
          if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0){
            $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $target = "../uploads/profiles/" . $student_id . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)){
              $stmtDoc = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'profile_photo', ?, ?)");
              $stmtDoc->execute([$student_id, "uploads/profiles/".basename($target), $student_id]);
            }
          }

          // Handle ID document upload
          if(isset($_FILES['id_document']) && $_FILES['id_document']['error'] == 0){
            $ext = pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION);
            $target = "../uploads/documents/" . $student_id . "_id_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['id_document']['tmp_name'], $target)){
              $stmtDoc = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'id_document', ?, ?)");
              $stmtDoc->execute([$student_id, "uploads/documents/".basename($target), $student_id]);
            }
          }

            $pdo->commit();
            $message = 'Profile updated successfully.';
            $success = true;
            $_SESSION['full_name'] = $full_name;
        }
    }

    $stmtProfile = $pdo->prepare(
        "SELECT u.user_id, u.username, u.email, u.full_name, u.phone, u.branch_id, u.status,
                s.national_id, s.date_of_birth, s.address, s.license_category
         FROM users u
         JOIN students s ON u.user_id = s.user_id
         WHERE u.user_id = ? AND u.role = 'student'
         LIMIT 1"
    );
    $stmtProfile->execute([$student_id]);
    $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC) ?: [];
    // fetch latest documents
    $stmtP = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='profile_photo' ORDER BY uploaded_at DESC LIMIT 1");
    $stmtP->execute([$student_id]);
    $profile_photo = $stmtP->fetchColumn();
    $stmtD = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='id_document' ORDER BY uploaded_at DESC LIMIT 1");
    $stmtD->execute([$student_id]);
    $id_document = $stmtD->fetchColumn();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $message = 'Unable to load or save profile.';
}
?>
<!doctype html>
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
        <?php include __DIR__ . '/includes/topbar.php'; ?>
      <main class="page-content">
        <div class="card">
          <h3 class="card-subtitle">My Profile</h3>
          <?php if ($message): ?>
            <div class="text-sm" style="color: <?php echo $success ? 'green' : 'red'; ?>; margin-bottom:8px;"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data" class="grid grid-cols-2 gap-md">
            <div>
              <label>Username</label>
              <div class="text-sm text-muted"><?php echo htmlspecialchars($profile['username'] ?? ''); ?></div>
            </div>

            <div>
              <label>Email</label>
              <div class="text-sm text-muted"><?php echo htmlspecialchars($profile['email'] ?? ''); ?></div>
            </div>

            <div>
              <label for="full_name">Full Name</label>
              <input id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required />
            </div>

            <div>
              <label for="phone">Phone</label>
              <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required />
            </div>

            <div>
              <label>National ID</label>
              <div class="text-sm text-muted"><?php echo htmlspecialchars($profile['national_id'] ?? ''); ?></div>
            </div>

            <div>
              <label>Profile Photo</label>
              <?php if(!empty($profile_photo)): ?>
                <div class="mb-2"><img src="../<?php echo htmlspecialchars($profile_photo); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover; border:2px solid var(--primary);"></div>
              <?php endif; ?>
              <input type="file" name="profile_photo" accept="image/png, image/jpeg">
            </div>

            <div>
              <label>ID Document</label>
              <?php if(!empty($id_document)): ?>
                <div class="mb-2"><a href="../<?php echo htmlspecialchars($id_document); ?>" target="_blank" class="badge badge-primary">View Current ID Document</a></div>
              <?php endif; ?>
              <input type="file" name="id_document" accept=".pdf, image/png, image/jpeg">
            </div>

            <div>
              <label>Date of Birth</label>
              <div class="text-sm text-muted"><?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?></div>
            </div>

            <div style="grid-column: 1 / -1;">
              <label for="address">Address</label>
              <textarea id="address" name="address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
            </div>

            <div>
              <label>License Category</label>
              <div class="text-sm text-muted"><?php echo htmlspecialchars($profile['license_category'] ?? ''); ?></div>
            </div>

            <div style="grid-column: 1 / -1; text-align: right;">
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </main>
      </div>
    </div>
  </body>
</html>
