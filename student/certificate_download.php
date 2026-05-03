<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$cert_id = intval($_GET['cert_id'] ?? 0);
$print_mode = isset($_GET['print']) && intval($_GET['print']) === 1;
$download_mode = isset($_GET['download']) && intval($_GET['download']) === 1;
try {
    $stmt = $pdo->prepare("SELECT c.*, u.full_name FROM certificates c JOIN users u ON c.student_user_id = u.user_id WHERE c.certificate_id = ? AND c.student_user_id = ? LIMIT 1");
    $stmt->execute([$cert_id, $student_id]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cert = false;
}

if (!$cert) {
    http_response_code(404);
    echo 'Certificate not found.';
    exit();
}

if ($download_mode) {
    $filename = 'certificate-' . preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$cert['certificate_number']) . '.html';
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Certificate</title>
    <style>
      body { font-family: Georgia, 'Times New Roman', serif; background: #f7f7f7; padding: 24px; }
      .cert { border: 4px solid #1f2937; padding: 40px; border-radius: 10px; background: #fff; max-width: 960px; margin: 0 auto; }
      .cert-head { text-align: center; margin-bottom: 24px; }
      .cert-title { font-size: 2rem; margin: 0 0 8px; }
      .cert-sub { color: #4b5563; margin: 0; }
      .cert-body { font-size: 1.08rem; line-height: 1.7; text-align: center; }
      .cert-footer { margin-top: 36px; display: flex; justify-content: space-between; align-items: flex-end; }
      .muted { color: #6b7280; }
      .actions { margin-top: 28px; text-align: center; }
      .btn { display: inline-block; padding: 10px 16px; border: 1px solid #111827; border-radius: 8px; text-decoration: none; color: #111827; margin: 0 6px; }
      .btn-primary { background: #111827; color: #fff; }
      @media print {
        body { background: #fff; padding: 0; }
        .actions { display: none; }
        .cert { border-width: 3px; box-shadow: none; }
      }
    </style>
  </head>
  <body>
    <div class="cert">
      <div class="cert-head">
        <h1 class="cert-title">Driving School Completion Certificate</h1>
        <p class="cert-sub">Official record of graduation</p>
      </div>

      <div class="cert-body">
        <p>This certifies that</p>
        <p style="font-size:1.45rem; font-weight:700; margin: 6px 0 10px;"><?php echo htmlspecialchars($cert['full_name']); ?></p>
        <p>has successfully completed the required driving school program and is hereby awarded certificate number</p>
        <p style="font-size:1.1rem; font-weight:700; margin: 6px 0 8px;"><?php echo htmlspecialchars($cert['certificate_number']); ?></p>
        <p class="muted">Issued on <?php echo date('Y-m-d', strtotime($cert['issue_date'])); ?></p>
      </div>

      <div class="cert-footer">
        <div>
          <div class="muted">Certificate ID</div>
          <div><?php echo intval($cert['certificate_id']); ?></div>
        </div>
        <div style="text-align:right;">
          <div class="muted">Student ID</div>
          <div><?php echo intval($cert['student_user_id']); ?></div>
        </div>
      </div>

      <div class="actions">
        <a href="#" onclick="window.print();return false;" class="btn">Print</a>
        <a href="certificate_download.php?cert_id=<?php echo intval($cert['certificate_id']); ?>&download=1" class="btn btn-primary">Download</a>
      </div>
    </div>
    <?php if ($print_mode): ?>
      <script>window.addEventListener('load', function(){ window.print(); });</script>
    <?php endif; ?>
  </body>
</html>
