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
    <div class="page-shell">
      <header class="topbar" style="position: static; border-radius: 18px; margin-bottom: 20px;">
        <div class="d-flex align-center gap-md">
          <div>
            <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
            <div class="text-sm text-muted">Manage your personal details</div>
          </div>
        </div>
        <div class="d-flex align-center gap-md">
          <div class="topbar-profile" style="cursor: default;">
            <div class="d-flex flex-col text-right">
              <span class="name font-bold text-sm"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
              <span class="role"><span class="badge badge-primary">Student</span></span>
            </div>
            <div class="avatar"><?php
              $n = $_SESSION['full_name'] ?? '';
              $p = explode(' ', trim($n));
              $init = strtoupper(substr($p[0] ?? '',0,1));
              if(count($p)>1) $init .= strtoupper(substr(end($p),0,1));
              echo htmlspecialchars($init);
            ?></div>
          </div>
          <a href="../logout.php" class="btn btn-outline btn-sm text-danger" style="border-color: var(--danger)">Logout</a>
        </div>
      </header>
      <main class="page-content" style="padding: 0; max-width: none;">
        <div class="card">
          <h3 class="card-subtitle">My Profile</h3>
          <?php if ($message): ?>
            <div class="text-sm" style="color: <?php echo $success ? 'green' : 'red'; ?>; margin-bottom:8px;"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>

          <form method="POST" class="grid grid-cols-2 gap-md">
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
  </body>
</html>
