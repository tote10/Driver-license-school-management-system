<?php

require_once __DIR__ . '/profile_helpers.php';

if (!function_exists('ds_dashboard_role_config')) {
    function ds_dashboard_role_config(string $role): array {
        $role = strtolower(trim($role));
        return match ($role) {
            'manager' => [
                'label' => 'Manager',
                'badge_class' => 'badge-warning',
                'sidebar_title' => 'Driving License School',
                'sidebar_items' => [
                    ['dashboard.php', 'School Overview'],
                    ['users.php', 'Manage Users'],
                    ['programs.php', 'Training Programs'],
                    ['enrollments.php', 'Registrations & Enrollments'],
                    ['schedules.php', 'Lesson Schedules'],
                    ['exams.php', 'Exam Schedule'],
                    ['reports.php', 'Reports'],
                    ['notifications.php', 'Notifications Board'],
                    ['certificates.php', 'Certificates & Graduation'],
                    ['audit_logs.php', 'Audit Logs'],
                    ['profile.php', 'My Profile'],
                ],
                'profile_link' => 'profile.php',
                'logout_link' => '../logout.php',
                'topbar_class' => 'd-flex align-center gap-md',
            ],
            'supervisor' => [
                'label' => 'Supervisor',
                'badge_class' => 'badge-warning',
                'sidebar_title' => 'Driving License School',
                'sidebar_items' => [
                    ['dashboard.php', 'Supervisor Dashboard'],
                    ['assignments.php', 'Instructor Assignments'],
                    ['reviews.php', 'Training Reviews'],
                    ['schedules.php', 'Schedules'],
                    ['notifications.php', 'Notifications Board'],
                    ['complaints.php', 'Complaints'],
                    ['profile.php', 'My Profile'],
                ],
                'profile_link' => 'profile.php',
                'logout_link' => '../logout.php',
                'topbar_class' => 'd-flex align-center gap-md',
            ],
            'instructor' => [
                'label' => 'Instructor',
                'badge_class' => 'badge-warning',
                'sidebar_title' => 'Driving License School',
                'sidebar_items' => [
                    ['dashboard.php', 'Instructor Dashboard'],
                    ['students.php', 'My Students'],
                    ['records.php', 'Training Records'],
                    ['schedule.php', 'Weekly Schedule'],
                    ['notifications.php', 'Notifications Board'],
                    ['profile.php', 'My Profile'],
                ],
                'profile_link' => 'profile.php',
                'logout_link' => '../logout.php',
                'topbar_class' => 'd-flex align-center gap-md',
            ],
            'student' => [
                'label' => 'Student',
                'badge_class' => 'badge-primary',
                'sidebar_title' => 'Driving License School',
                'sidebar_items' => [
                    ['dashboard.php', 'Dashboard'],
                    ['profile.php', 'My Profile'],
                    ['schedule.php', 'My Schedule'],
                    ['notifications.php', 'Notifications Board'],
                    ['exams.php', 'Exam Results'],
                    ['certificate.php', 'Certificates'],
                    ['complaints.php', 'Complaints'],
                ],
                'profile_link' => 'profile.php',
                'logout_link' => '../logout.php',
                'topbar_class' => 'topbar-actions d-flex align-center gap-md',
            ],
            default => [
                'label' => 'User',
                'badge_class' => 'badge-warning',
                'sidebar_title' => 'Driving License School',
                'sidebar_items' => [],
                'profile_link' => 'profile.php',
                'logout_link' => '../logout.php',
                'topbar_class' => 'd-flex align-center gap-md',
            ],
        };
    }
}

if (!function_exists('ds_render_dashboard_sidebar')) {
    function ds_render_dashboard_sidebar(string $role): void {
        $config = ds_dashboard_role_config($role);
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        ?>
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-brand"><?php echo htmlspecialchars($config['sidebar_title']); ?></div>
            <nav class="sidebar-nav mt-3">
                <?php foreach ($config['sidebar_items'] as $item): ?>
                    <?php [$href, $label] = $item; ?>
                    <a href="<?php echo htmlspecialchars($href); ?>" class="sidebar-item <?php echo $currentPage === basename($href) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <?php
    }
}

if (!function_exists('ds_render_dashboard_topbar')) {
    function ds_render_dashboard_topbar(PDO $pdo, string $role, string $pageTitle = 'Dashboard'): void {
        $config = ds_dashboard_role_config($role);
        $currentUserId = intval($_SESSION['user_id'] ?? 0);
        $fullName = ds_display_name($config['label']);
        $initials = ds_display_initials($fullName, $config['label']);
        $photoUrl = ds_profile_photo_url($pdo, $currentUserId);
        $notificationsWidgetFile = __DIR__ . '/notifications_widget.php';
        if (file_exists($notificationsWidgetFile)) {
            require_once $notificationsWidgetFile;
        }
        ?>
        <header class="topbar">
            <div class="d-flex align-center">
                <button class="mobile-toggle" id="mobile-sidebar-toggle">☰</button>
                <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            </div>

            <div class="<?php echo htmlspecialchars($config['topbar_class']); ?>">
                <?php if (function_exists('render_notifications_widget') && !empty($currentUserId)): ?>
                    <?php render_notifications_widget($pdo, $currentUserId, 'notifications.php'); ?>
                <?php endif; ?>
                <div class="topbar-profile" style="cursor: pointer;" onclick="window.location.href='<?php echo htmlspecialchars($config['profile_link']); ?>'">
                    <div class="d-flex flex-col text-right">
                        <span class="name font-bold text-sm"><?php echo htmlspecialchars($fullName); ?></span>
                        <span class="role"><span class="badge <?php echo htmlspecialchars($config['badge_class']); ?>"><?php echo htmlspecialchars($config['label']); ?></span></span>
                    </div>
                    <div class="avatar">
                        <?php if ($photoUrl !== ''): ?>
                            <img src="../<?php echo htmlspecialchars($photoUrl); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?php echo htmlspecialchars($config['logout_link']); ?>" class="btn btn-outline btn-sm text-danger" style="border-color: var(--danger)">Logout</a>
            </div>
        </header>
        <?php
    }
}
