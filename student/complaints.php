<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_type = trim($_POST['subject_type'] ?? 'general');
    $description = trim($_POST['description'] ?? '');
    if ($description === '') {
        $message = 'Please describe your complaint.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO complaints (reporter_user_id, subject_type, description, status) VALUES (?, ?, ?, 'open')");
        $stmt->execute([$student_id, $subject_type, $description]);
        $message = 'Complaint submitted.';
    }
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Submit Complaint</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="topbar" style="position: static; border-radius: 18px; margin-bottom: 20px;">
        <div class="d-flex align-center gap-md">
          <div>
            <h1 class="page-title">Submit Complaint</h1>
            <div class="text-sm text-muted">Report issues to management</div>
          </div>
        </div>
      </header>
      <main class="page-content" style="padding:0; max-width:none;">
        <div class="card">
          <?php if ($message): ?><div class="text-sm" style="color: green; margin-bottom:8px"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
          <form method="POST" class="grid grid-cols-2 gap-md">
            <div>
              <label for="subject_type">Subject</label>
              <select id="subject_type" name="subject_type">
                <option value="instructor">Instructor</option>
                <option value="scheduling">Scheduling</option>
                <option value="facilities">Facilities</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div style="grid-column: 1 / -1;">
              <label for="description">Description</label>
              <textarea id="description" name="description" required></textarea>
            </div>
            <div style="grid-column: 1 / -1; text-align: right;">
              <button class="btn btn-primary" type="submit">Submit Complaint</button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </body>
</html>
