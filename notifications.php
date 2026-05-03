<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notifications.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);
$role = $_SESSION['role'];
$page_title = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
  mark_notification_read($pdo, intval($_POST['mark_read']), $user_id);
  header('Location: notifications.php');
  exit();
}

if (isset($_GET['read'])) {
  mark_notification_read($pdo, intval($_GET['read']), $user_id);
  header('Location: notifications.php');
  exit();
}

$notifications = fetch_user_notifications($pdo, $user_id, 100);

$topbar_include = match ($role) {
    'manager' => 'manager/includes/topbar.php',
    'supervisor' => 'supervisor/includes/topbar.php',
    'instructor' => 'instructor/includes/topbar.php',
    'student' => 'student/includes/topbar.php',
    default => 'includes/header.php',
};

$sidebar_include = match ($role) {
    'manager' => 'manager/includes/sidebar.php',
    'supervisor' => 'supervisor/includes/sidebar.php',
    'instructor' => 'instructor/includes/sidebar.php',
    'student' => 'student/includes/sidebar.php',
    default => null,
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>" />
</head>
<body>
  <div class="app-wrapper">
    <?php if ($sidebar_include && file_exists(__DIR__ . '/' . $sidebar_include)) { include __DIR__ . '/' . $sidebar_include; } ?>
    <div class="main-content">
      <?php if (file_exists(__DIR__ . '/' . $topbar_include)) { include __DIR__ . '/' . $topbar_include; } ?>
      <main class="page-content">
        <div class="card">
          <div class="d-flex justify-between align-center mb-3">
            <div>
              <h2 class="card-title mb-1">Notifications</h2>
              <p class="text-sm text-muted mb-0">Latest in-app notifications for your account.</p>
            </div>
          </div>
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
                <?php if (!empty($notifications)): ?>
                  <?php foreach ($notifications as $note): ?>
                    <tr>
                      <td>
                        <span class="badge <?php echo intval($note['is_read'] ?? 0) === 1 ? 'badge-success' : 'badge-warning'; ?>">
                          <?php echo intval($note['is_read'] ?? 0) === 1 ? 'Read' : 'Unread'; ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($note['title']); ?></td>
                      <td><?php echo htmlspecialchars($note['message']); ?></td>
                      <td><?php echo htmlspecialchars($note['notification_type'] ?? 'general'); ?></td>
                      <td><?php echo !empty($note['sent_at']) ? date('Y-m-d H:i', strtotime($note['sent_at'])) : '-'; ?></td>
                      <td>
                        <?php if (intval($note['is_read'] ?? 0) === 0): ?>
                          <form method="post" style="margin:0; display:inline;">
                            <button type="submit" name="mark_read" value="<?php echo intval($note['notification_id']); ?>" class="btn btn-outline btn-sm">Mark read</button>
                          </form>
                        <?php else: ?>
                          <span class="text-sm text-muted">Done</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted">No notifications found.</td>
                  </tr>
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
