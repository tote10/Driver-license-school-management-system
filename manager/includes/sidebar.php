<!-- 1:1 Mockup-Synced Manager Sidebar -->
<aside class="sidebar" id="app-sidebar">
    <div class="sidebar-brand">Driving License School</div>
    <nav class="sidebar-nav mt-3">
        <a href="dashboard.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            School Overview
        </a>
        <a href="users.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
            Manage Users
        </a>
        <a href="programs.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'programs.php' ? 'active' : ''; ?>">
            Training Programs
        </a>
        <a href="enrollments.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : ''; ?>">
            Registrations & Enrollments
        </a>
        <a href="exams.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'exams.php' ? 'active' : ''; ?>">
            Exam Schedule
        </a>
        <a href="reports.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            Financial Reports
        </a>
        <a href="certificates.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'active' : ''; ?>">
            Certificates & Graduation
        </a>
        <a href="#" class="sidebar-item">
            System Logs
        </a>
    </nav>
</aside>
