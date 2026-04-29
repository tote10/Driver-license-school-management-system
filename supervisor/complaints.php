<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor'){
    header("Location: ../login.php");
    exit();
}

$branch_id = intval($_SESSION['branch_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? 'Supervisor';
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

$complaints = [];
try {
    $stmt = $pdo->prepare(
        "SELECT c.complaint_id, c.subject_type, c.description, c.status, c.resolution, c.reported_date,
                reporter.full_name AS reporter_name
         FROM complaints c
         JOIN users reporter ON c.reporter_user_id = reporter.user_id
         WHERE reporter.branch_id = ?
         ORDER BY c.reported_date DESC"
    );
    $stmt->execute([$branch_id]);
    $complaints = $stmt->fetchAll();
} catch(PDOException $e) {}

$page_title = 'Complaints';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Complaints</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <main class="page-content">
          <div class="card mb-4">
            <h3 class="card-subtitle mb-2">Complaints</h3>
            <p class="text-sm text-muted mb-3">Review complaints from students and forward serious cases to the manager when needed.</p>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Reporter</th>
                    <th>Subject</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Resolution</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($complaints as $complaint): ?>
                  <tr>
                    <td class="font-bold"><?php echo htmlspecialchars($complaint['reporter_name']); ?></td>
                    <td><?php echo htmlspecialchars($complaint['subject_type']); ?></td>
                    <td><?php echo htmlspecialchars($complaint['description']); ?></td>
                    <td><?php echo htmlspecialchars($complaint['status']); ?></td>
                    <td><?php echo htmlspecialchars($complaint['resolution'] ?? '-'); ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($complaints) === 0): ?>
                  <tr><td colspan="5" class="text-center text-muted">No complaints found in this branch.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>
