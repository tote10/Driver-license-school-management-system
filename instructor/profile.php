<?php
require_once __DIR__ . '/includes/common.php';

$page_title = 'My Profile';
$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $phone = trim((string)($_POST['phone'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $years_experience = intval($_POST['years_experience'] ?? 0);

    try {
        $pdo->beginTransaction();
        $stmtUser = $pdo->prepare("UPDATE users SET phone = ? WHERE user_id = ? AND branch_id = ? AND role = 'instructor'");
        $stmtUser->execute([$phone, $instructor_id, $branch_id]);

        $stmtInstructor = $pdo->prepare("UPDATE instructors SET specialization = ?, years_experience = ? WHERE user_id = ?");
        $stmtInstructor->execute([$specialization !== '' ? $specialization : 'General', $years_experience, $instructor_id]);

        $pdo->commit();
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
            <form method="post">
              <input type="hidden" name="action" value="update_profile">
              <div class="grid grid-cols-2 gap-md">
                <div class="form-group">
                  <label class="form-label">Full Name</label>
                  <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['full_name'] ?? $full_name); ?>" disabled>
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
