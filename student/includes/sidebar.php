<aside class="sidebar" id="app-sidebar">
    <div class="sidebar-brand">Driving License School</div>
    <nav class="sidebar-nav mt-3">
        <a href="dashboard.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <a href="profile.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">My Profile</a>
        <a href="schedule.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">My Schedule</a>
        <a href="exams.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'exams.php' ? 'active' : ''; ?>">Exam Results</a>
        <a href="certificate.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'certificate.php' ? 'active' : ''; ?>">Certificates</a>
          <a href="complaints.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'complaints.php' ? 'active' : ''; ?>">Complaints</a>
    </nav>
    <div class="sidebar-footer">
      <a href="../logout.php">Logout</a>
    </div>
</aside>
