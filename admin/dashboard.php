<?php
session_start();
require_once '../config/db.php';

// 1. SECURITY LOCK: Super Admin Only
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$message = "";

try {
    // A. Global Branch Stats
    $total_branches = $pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();

    // B. Global Student Stats
    $stmt_students = $pdo->query("SELECT status, COUNT(*) as total FROM users WHERE role='student' GROUP BY status");
    $student_stats = $stmt_students->fetchAll(PDO::FETCH_KEY_PAIR);
    $active_students = $student_stats['active'] ?? 0;
    $pending_students = $student_stats['pending'] ?? 0;

    // C. Global Staff Stats
    $total_staff = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('instructor', 'manager', 'supervisor')")->fetchColumn();

    // D. Global Revenue
    $total_revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'paid'")->fetchColumn() ?? 0;

    // E. List all branches for a quick table view
    $stmt_br = $pdo->query("
        SELECT b.*, 
        (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.branch_id AND u.role='student') as student_count
        FROM branches b
        ORDER BY b.name ASC
    ");
    $branches = $stmt_br->fetchAll();

} catch (PDOException $e) {
    $message = "System Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Command Center</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        nav { background: #333; color: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        nav a { color: #fff; margin-right: 15px; text-decoration: none; font-weight: bold; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { margin: 0; color: #666; font-size: 0.9rem; text-transform: uppercase; }
        .stat-card .value { font-size: 2rem; font-weight: bold; margin-top: 10px; color: #333; }
        .stat-card.revenue .value { color: #28a745; }
        table { width: 100%; background: white; border-collapse: collapse; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #555; }
        .btn { padding: 6px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body>

    <nav>
        <span style="font-size: 1.2rem; margin-right: 30px;">🛡️ Super Admin</span>
        <a href="dashboard.php" style="border-bottom: 2px solid white;">Dashboard</a>
        <a href="branches.php">Branches</a>
        <a href="users.php">Global Users</a>
        <a href="../logout.php" style="float: right; color: #ff6b6b;">Logout</a>
    </nav>

    <h2>System-Wide Performance Overview</h2>
    <?php if($message) echo "<p style='color:red;'>$message</p>"; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Branches</h3>
            <div class="value"><?php echo $total_branches; ?></div>
        </div>
        <div class="stat-card">
            <h3>System Students</h3>
            <div class="value"><?php echo $active_students + $pending_students; ?></div>
            <small style="color: green;">Active: <?php echo $active_students; ?></small> | 
            <small style="color: orange;">Pending: <?php echo $pending_students; ?></small>
        </div>
        <div class="stat-card">
            <h3>Total Staff</h3>
            <div class="value"><?php echo $total_staff; ?></div>
        </div>
        <div class="stat-card revenue">
            <h3>Global Revenue</h3>
            <div class="value">$<?php echo number_format($total_revenue, 2); ?></div>
        </div>
    </div>

    <h3>Branch Directory</h3>
    <table>
        <thead>
            <tr>
                <th>Branch Name</th>
                <th>Location</th>
                <th>Contact Info</th>
                <th>Students</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($branches as $b): ?>
            <tr>
                <td><b><?php echo htmlspecialchars($b['name']); ?></b></td>
                <td><?php echo htmlspecialchars($b['location'] ?? 'N/A'); ?></td>
                <td>
                    <small><?php echo htmlspecialchars($b['contact_email']); ?></small><br>
                    <small><?php echo htmlspecialchars($b['contact_phone']); ?></small>
                </td>
                <td><span style="font-weight:bold;"><?php echo $b['student_count']; ?></span></td>
                <td>
                    <a href="branches.php?edit=<?php echo $b['branch_id']; ?>" class="btn">Manage</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
