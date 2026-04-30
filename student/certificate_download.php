<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$cert_id = intval($_GET['cert_id'] ?? 0);
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
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Certificate</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:40px;} .cert{border:4px solid #222;padding:40px;border-radius:8px}</style>
  </head>
  <body>
    <div class="cert">
      <h1 style="text-align:center;">Driving School Certificate</h1>
      <p style="text-align:center;font-weight:600;">This certifies that <span style="font-size:1.2rem"><?php echo htmlspecialchars($cert['full_name']); ?></span></p>
      <p style="text-align:center;">Has successfully completed the required training program and has been issued certificate number <strong><?php echo htmlspecialchars($cert['certificate_number']); ?></strong> on <?php echo date('Y-m-d', strtotime($cert['issue_date'])); ?>.</p>
      <div style="margin-top:40px;text-align:center;"><a href="#" onclick="window.print();return false;" class="btn btn-outline">Print / Download</a></div>
    </div>
  </body>
</html>
