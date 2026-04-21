<?php
session_start();
require_once '../config/db.php';

// 1. SECURITY: Admin Only
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$message = "";

// 2. ROUTING: Handling Actions
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])){
    
    // A. UPDATE USER (Role or Branch)
    if($_POST['action'] == 'update_user'){
        $uid = intval($_POST['user_id'] ?? 0);
        $new_role = $_POST['role'] ?? '';
        $new_branch = intval($_POST['branch_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';

        if($uid > 0){
            try {
                $stmt = $pdo->prepare("UPDATE users SET role=?, branch_id=?, status=? WHERE user_id=?");
                $stmt->execute([$new_role, ($new_branch > 0 ? $new_branch : null), $new_status, $uid]);
                $message = "<div class='alert alert-success'>User updated successfully!</div>";
            } catch(PDOException $e) {
                $message = "<div class='alert alert-danger'>Update failed: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// 3. FETCHING DATA
// Get Branches for the dropdowns
$branches = $pdo->query("SELECT branch_id, name FROM branches ORDER BY name ASC")->fetchAll();

// Get Users (with branch names)
$stmt_u = $pdo->query("
    SELECT u.*, b.name as branch_name 
    FROM users u 
    LEFT JOIN branches b ON u.branch_id = b.branch_id 
    ORDER BY u.role, u.full_name ASC
");
$users = $stmt_u->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global User Management | Super Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        nav { background: #333; color: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        nav a { color: #fff; margin-right: 15px; text-decoration: none; font-weight: bold; }
        table { width: 100%; background: white; border-collapse: collapse; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        select, button { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .badge { padding: 3px 6px; border-radius: 3px; font-size: 0.75rem; color: white; text-transform: uppercase; }
        .admin { background: #dc3545; }
        .manager { background: #fd7e14; }
        .instructor { background: #007bff; }
        .student { background: #28a745; }
    </style>
</head>
<body>

    <nav>
        <span style="font-size: 1.2rem; margin-right: 30px;">🛡️ Super Admin</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="branches.php">Branches</a>
        <a href="users.php" style="border-bottom: 2px solid white;">Global Users</a>
        <a href="../logout.php" style="float: right; color: #ff6b6b;">Logout</a>
    </nav>

    <h2>Global User Directory</h2>
    <?php echo $message; ?>

    <p>Manage all accounts system-wide. You can re-assign managers to different branches or promote staff members.</p>

    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Global Role</th>
                <th>Current Branch</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <form method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                    
                    <td>
                        <b><?php echo htmlspecialchars($user['full_name']); ?></b><br>
                        <small><?php echo htmlspecialchars($user['email']); ?></small>
                    </td>
                    <td>
                        <select name="role">
                            <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="instructor" <?php echo $user['role'] == 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                            <option value="manager" <?php echo $user['role'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </td>
                    <td>
                        <select name="branch_id">
                            <option value="0">-- No Branch --</option>
                            <?php foreach($branches as $br): ?>
                                <option value="<?php echo $br['branch_id']; ?>" <?php echo $user['branch_id'] == $br['branch_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="status">
                            <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $user['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="suspended" <?php echo $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </td>
                    <td>
                        <button type="submit" style="background: #28a745; color: white; border: none; cursor: pointer;">Save Changes</button>
                    </td>
                </form>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
