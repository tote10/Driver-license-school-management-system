<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
try {
    $stmt = $pdo->prepare("SELECT c.*, e.program_id FROM certificates c LEFT JOIN enrollments e ON c.enrollment_id = e.enrollment_id WHERE c.student_user_id = ? ORDER BY c.issue_date DESC");
    $stmt->execute([$student_id]);
    $certs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $certs = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Certificates</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="topbar" style="position: static; border-radius: 18px; margin-bottom: 20px;">
        <div class="d-flex align-center gap-md">
          <div>
            <h1 class="page-title">My Certificates</h1>
            <div class="text-sm text-muted">Download issued certificates</div>
          </div>
        </div>
      </header>
      <main class="page-content" style="padding:0; max-width:none;">
        <div class="card">
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>#</th><th>Certificate No</th><th>Issued</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach($certs as $c): ?>
                <tr>
                  <td><?php echo intval($c['certificate_id']); ?></td>
                  <td><?php echo htmlspecialchars($c['certificate_number']); ?></td>
                  <td><?php echo date('Y-m-d', strtotime($c['issue_date'])); ?></td>
                  <td><a class="btn btn-outline" href="certificate_download.php?cert_id=<?php echo intval($c['certificate_id']); ?>">View / Download</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($certs)===0): ?><tr><td colspan="4" class="text-center text-muted">No certificates issued yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </body>
</html>
