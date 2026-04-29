<aside class="sidebar" id="app-sidebar">
    <div class="sidebar-brand">Driving License School</div>
    <nav class="sidebar-nav mt-3">
        <a href="dashboard.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            Supervisor Dashboard
        </a>
        <a href="assignments.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'active' : ''; ?>">
            Instructor Assignments
        </a>
        <a href="reviews.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>">
            Training Reviews
        </a>
        <a href="progress.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'progress.php' ? 'active' : ''; ?>">
            Student Progress
        </a>
        <a href="schedules.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedules.php' ? 'active' : ''; ?>">
            Schedules
        </a>
        <a href="complaints.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'complaints.php' ? 'active' : ''; ?>">
            Complaints
        </a>
    </nav>
</aside>
