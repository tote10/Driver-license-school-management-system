<aside class="sidebar" id="app-sidebar">
    <div class="sidebar-brand">Driving License School</div>
    <nav class="sidebar-nav mt-3">
        <a href="dashboard.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            Instructor Dashboard
        </a>
        <a href="students.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
            My Students
        </a>
        <a href="records.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'records.php' ? 'active' : ''; ?>">
            Training Records
        </a>
        <a href="schedule.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">
            Weekly Schedule
        </a>
        <a href="profile.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            My Profile
        </a>
    </nav>
</aside>
