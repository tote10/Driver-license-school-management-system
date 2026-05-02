<?php
$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_role = $_SESSION['role'] ?? 'student';
$notifications_widget_file = __DIR__ . '/../../includes/notifications_widget.php';
if (file_exists($notifications_widget_file)) {
  require_once $notifications_widget_file;
}
$student_topbar_name = trim((string)($_SESSION['full_name'] ?? 'Student'));
$student_topbar_parts = explode(' ', $student_topbar_name);
$student_topbar_initials = strtoupper(substr($student_topbar_parts[0] ?? 'S', 0, 1));
if (count($student_topbar_parts) > 1) {
    $student_topbar_initials .= strtoupper(substr(end($student_topbar_parts), 0, 1));
}
?>
<header class="topbar">
  <div class="d-flex align-center">
    <button class="mobile-toggle" id="mobile-sidebar-toggle">☰</button>
    <h1 class="page-title"><?php echo htmlspecialchars($page_title ?? 'Student'); ?></h1>
  </div>

  <div class="topbar-actions d-flex align-center gap-md">
    <?php if (function_exists('render_notifications_widget') && !empty($current_user_id)): ?>
      <?php render_notifications_widget($pdo, $current_user_id, 'notifications.php'); ?>
    <?php endif; ?>
    <div class="topbar-profile" style="cursor: default;">
      <div class="topbar-profile-meta d-flex flex-col text-right">
        <span class="name font-bold text-sm"><?php echo htmlspecialchars($student_topbar_name); ?></span>
        <span class="role"><span class="badge badge-primary">Student</span></span>
      </div>
      <div class="avatar"><?php echo htmlspecialchars($student_topbar_initials); ?></div>
    </div>
    <a href="../logout.php" class="btn btn-outline btn-sm text-danger" style="border-color: var(--danger)">Logout</a>
  </div>
</header>
