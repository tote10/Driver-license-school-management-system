<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$full_name = $_SESSION['full_name'] ?? 'Manager';

// Safe initials
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if(count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

// Fetch stats...
try {
    $stmt_rev = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments p JOIN users u ON p.student_user_id = u.user_id WHERE u.branch_id = ? AND p.status='paid'");
    $stmt_rev->execute([$branch_id]);
    $total_revenue = $stmt_rev->fetchColumn();
} catch(PDOException $e) {}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Financial Reports | Manager Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include 'includes/sidebar.php'; ?>

      <div class="main-content">
        <?php $page_title = 'Financial Reports'; include 'includes/topbar.php'; ?>

        <main class="page-content">
          <div class="grid grid-cols-4 mb-4">
              <div class="card">
                  <h3 class="card-subtitle">Branch Revenue</h3>
                  <div class="stat-value text-success">$<?php echo number_format($total_revenue, 2); ?></div>
                  <p class="text-sm text-muted mt-1">Total collections</p>
              </div>
          </div>
          
          <div class="card">
              <h3 class="card-subtitle mb-2">Performance Analytics</h3>
              <div class="placeholder-box">
                  Advanced charts and deeper analytics will appear here.
              </div>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>
