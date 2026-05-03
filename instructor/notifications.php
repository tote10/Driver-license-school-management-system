<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/profile_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'instructor') {
    header('Location: ../login.php');
    exit();
}

$instructor_id = intval($_SESSION['user_id']);
$full_name = ds_display_name('Instructor');
$page_title = 'Notifications Board';
$initials = ds_display_initials($full_name, 'Instructor');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    mark_notification_read($pdo, intval($_POST['mark_read']), $instructor_id);
    header('Location: notifications.php');
    exit();
}

$notifications = fetch_user_notifications($pdo, $instructor_id, 150);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Instructor Notifications</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <main class="page-content">
          <div class="card">
            <h3 class="card-subtitle mb-2">Notifications Board</h3>
            <p class="text-sm text-muted mb-3">Recent notifications for your account.</p>
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
                  <?php foreach ($notifications as $note): ?>
                  <tr>
                    <td><span class="badge <?php echo intval($note['is_read']) === 1 ? 'badge-success' : 'badge-warning'; ?>"><?php echo intval($note['is_read']) === 1 ? 'Read' : 'Unread'; ?></span></td>
                    <td><?php echo htmlspecialchars($note['title']); ?></td>
                    <td><?php echo htmlspecialchars($note['message']); ?></td>
                    <td><?php echo htmlspecialchars($note['notification_type'] ?? 'general'); ?></td>
                    <td><?php echo !empty($note['sent_at']) ? date('Y-m-d H:i', strtotime($note['sent_at'])) : '-'; ?></td>
                    <td>
                      <?php if (intval($note['is_read']) === 0): ?>
                        <form method="post" style="margin:0; display:inline;">
                          <button type="submit" name="mark_read" value="<?php echo intval($note['notification_id']); ?>" class="btn btn-outline btn-sm">Mark read</button>
                        </form>
                      <?php else: ?>
                        <span class="text-sm text-muted">Done</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (count($notifications) === 0): ?>
                  <tr><td colspan="6" class="text-center text-muted">No notifications found.</td></tr>
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
