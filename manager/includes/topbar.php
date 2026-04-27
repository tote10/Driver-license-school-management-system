        <header class="topbar">
          <div class="d-flex align-center">
            <button class="mobile-toggle" id="mobile-sidebar-toggle">☰</button>
            <h1 class="page-title"><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></h1>
          </div>

          <div class="d-flex align-center gap-md">
            <!-- TOPBAR PROFILE -->
            <div class="topbar-profile" style="cursor: pointer;" onclick="window.location.href='profile.php'">
              <div class="d-flex flex-col text-right">
                <span class="name font-bold text-sm"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="role"><span class="badge badge-warning">Manager</span></span>
              </div>
              <div class="avatar">
                <?php 
                  $stmt_dp = $pdo->prepare("SELECT file_url FROM documents WHERE entity_id = ? AND entity_type = 'user' AND document_type = 'profile_photo' ORDER BY uploaded_at DESC LIMIT 1");
                  $stmt_dp->execute([$_SESSION['user_id']]);
                  $dp = $stmt_dp->fetchColumn();
                  if($dp): 
                ?>
                  <img src="../<?php echo htmlspecialchars($dp); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                  <?php echo $initials; ?>
                <?php endif; ?>
              </div>
            </div>
            <a href="../logout.php" class="btn btn-outline btn-sm text-danger" style="border-color: var(--danger)">Logout</a>
          </div>
        </header>
