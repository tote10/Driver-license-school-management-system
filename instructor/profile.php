<?php
require_once __DIR__ . '/includes/common.php';

$page_title = 'My Profile';
$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $years_experience = intval($_POST['years_experience'] ?? 0);

    try {
        $pdo->beginTransaction();
        $stmtUser = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ? AND branch_id = ? AND role = 'instructor'");
        $stmtUser->execute([$full_name, $phone, $instructor_id, $branch_id]);

        $stmtInstructor = $pdo->prepare("UPDATE instructors SET specialization = ?, years_experience = ? WHERE user_id = ?");
        $stmtInstructor->execute([$specialization !== '' ? $specialization : 'General', $years_experience, $instructor_id]);

    // Handle profile photo upload
    if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0){
      $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
      $target = "../uploads/profiles/" . $instructor_id . "_" . time() . "." . $ext;
      if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)){
        $stmtDoc = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'profile_photo', ?, ?)");
        $stmtDoc->execute([$instructor_id, "uploads/profiles/".basename($target), $instructor_id]);
      }
    }

    // Handle ID document upload
    if(isset($_FILES['id_document']) && $_FILES['id_document']['error'] == 0){
      $ext = pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION);
      $target = "../uploads/documents/" . $instructor_id . "_id_" . time() . "." . $ext;
      if(move_uploaded_file($_FILES['id_document']['tmp_name'], $target)){
        $stmtDoc = $pdo->prepare("INSERT INTO documents (entity_id, entity_type, document_type, file_url, uploaded_by) VALUES (?, 'user', 'id_document', ?, ?)");
        $stmtDoc->execute([$instructor_id, "uploads/documents/".basename($target), $instructor_id]);
      }
    }

        $pdo->commit();
        $_SESSION['full_name'] = $full_name;
        $message = "<div class='toast show'>Profile updated successfully.</div>";
    } catch(Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "<div class='toast show bg-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$profile = [];
try {
    $stmt = $pdo->prepare(
        "SELECT u.full_name, u.email, u.phone,
                COALESCE(i.specialization, 'General') AS specialization,
                COALESCE(i.years_experience, 0) AS years_experience
         FROM users u
         LEFT JOIN instructors i ON u.user_id = i.user_id
         WHERE u.user_id = ? AND u.branch_id = ? AND u.role = 'instructor'
          "
    );
    $stmt->execute([$instructor_id, $branch_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    // fetch latest documents for display
    $stmtP = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='profile_photo' ORDER BY uploaded_at DESC LIMIT 1");
    $stmtP->execute([$instructor_id]);
    $profile_photo = $stmtP->fetchColumn();
    $stmtD = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type='user' AND document_type='id_document' ORDER BY uploaded_at DESC LIMIT 1");
    $stmtD->execute([$instructor_id]);
    $id_document = $stmtD->fetchColumn();
} catch(PDOException $e) {}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        <main class="page-content">
          <div class="toast-container"><?php if($message) echo $message; ?></div>
          <div class="card">
            <h3 class="card-subtitle mb-2">My Profile</h3>
            <p class="text-sm text-muted mb-3">Update your phone number and specialization if your branch allows it.</p>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="action" value="update_profile">
              <div class="grid grid-cols-2 gap-md">
                <div>
                  <label class="form-label">Profile Photo</label>
                  <?php if(!empty($profile_photo)): ?>
                    <div class="mb-2"><img src="../<?php echo htmlspecialchars($profile_photo); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover; border:2px solid var(--primary);"></div>
                  <?php endif; ?>
                  <input type="file" name="profile_photo" accept="image/png, image/jpeg">
                </div>

                <div>
                  <label class="form-label">ID Document</label>
                  <?php if(!empty($id_document)): ?>
                    <div class="mb-2"><a href="../<?php echo htmlspecialchars($id_document); ?>" target="_blank" class="badge badge-primary">View Current ID Document</a></div>
                  <?php endif; ?>
                  <input type="file" name="id_document" accept=".pdf, image/png, image/jpeg">
                </div>
              </div>

              <input type="hidden" name="action" value="update_profile">
              <div class="grid grid-cols-2 gap-md">
                <div class="form-group">
                  <label class="form-label">Full Name</label>
                  <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($profile['full_name'] ?? $full_name); ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                  <label class="form-label">Phone</label>
                  <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Specialization</label>
                  <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($profile['specialization'] ?? 'General'); ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Years of Experience</label>
                  <input type="number" name="years_experience" min="0" class="form-control" value="<?php echo intval($profile['years_experience'] ?? 0); ?>">
                </div>
              </div>
              <button type="submit" class="btn btn-primary mt-2">Save Changes</button>
            </form>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>
