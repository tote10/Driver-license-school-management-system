<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/notifications.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);
$page_title = 'Notifications';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
  mark_notification_read($pdo, intval($_POST['mark_read']), $user_id);
  header('Location: notifications.php');
  exit();
}

$notes = fetch_user_notifications($pdo, $user_id, 150);
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
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
      <main class="page-content">
        <div class="card">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Status</th>
                  <th>Title</th>
                  <th>Message</th>
                  <th>Type</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($notes as $n): ?>
                <tr>
                  <td><span class="badge <?php echo intval($n['is_read']) === 1 ? 'badge-success' : 'badge-warning'; ?>"><?php echo intval($n['is_read']) === 1 ? 'Read' : 'Unread'; ?></span></td>
                  <td><?php echo htmlspecialchars($n['title']); ?></td>
                  <td><?php echo htmlspecialchars($n['message']); ?></td>
                  <td><?php echo htmlspecialchars($n['notification_type'] ?? 'general'); ?></td>
                  <td><?php echo !empty($n['sent_at']) ? date('Y-m-d H:i', strtotime($n['sent_at'])) : '-'; ?></td>
                  <td>
                    <?php if (intval($n['is_read']) === 0): ?>
                      <form method="post" style="margin:0; display:inline;">
                        <button type="submit" name="mark_read" value="<?php echo intval($n['notification_id']); ?>" class="btn btn-outline btn-sm">Mark read</button>
                      </form>
                    <?php else: ?>
                      <span class="text-sm text-muted">Done</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($notes)===0): ?>
                  <tr><td colspan="6" class="text-center text-muted">No notifications.</td></tr>
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
