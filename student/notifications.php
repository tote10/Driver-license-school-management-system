<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE recipient_user_id = ? ORDER BY sent_at DESC LIMIT 100");
    $stmt->execute([$user_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notes = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Notifications</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="topbar" style="position: static; border-radius: 18px; margin-bottom: 20px;">
        <div class="d-flex align-center gap-md">
          <div>
            <h1 class="page-title">Notifications</h1>
            <div class="text-sm text-muted">Recent updates for your account</div>
          </div>
        </div>
      </header>
      <main class="page-content" style="padding:0; max-width:none;">
        <div class="card">
          <ul style="list-style:none; padding:0; margin:0;">
            <?php foreach($notes as $n): ?>
            <li style="padding:12px; border-bottom:1px solid #eee;">
              <div class="d-flex justify-between">
                <div>
                  <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                  <div class="text-sm text-muted"><?php echo htmlspecialchars($n['message']); ?></div>
                </div>
                <div class="text-sm text-muted"><?php echo date('Y-m-d H:i', strtotime($n['sent_at'])); ?></div>
              </div>
            </li>
            <?php endforeach; ?>
            <?php if(count($notes)===0): ?><li class="text-center text-muted" style="padding:16px">No notifications.</li><?php endif; ?>
          </ul>
        </div>
      </main>
    </div>
  </body>
</html>
