<header class="topbar">
  <div class="d-flex align-center">
    <button class="mobile-toggle" id="mobile-sidebar-toggle">☰</button>
    <h1 class="page-title"><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></h1>
  </div>

  <div class="d-flex align-center gap-md">
    <div class="topbar-profile" style="cursor: default;">
      <div class="d-flex flex-col text-right">
        <span class="name font-bold text-sm"><?php echo htmlspecialchars($full_name); ?></span>
        <span class="role"><span class="badge badge-warning">Instructor</span></span>
      </div>
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    </div>
    <a href="../logout.php" class="btn btn-outline btn-sm text-danger" style="border-color: var(--danger)">Logout</a>
  </div>
</header>
