<?php
session_start();
require_once '../config/db.php';

// security: manager only
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$message = "";

// --- ACTIONS: UPDATE USER ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update'){
    $target_id = intval($_POST['user_id'] ?? 0);
    $new_name = trim($_POST['full_name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_status = $_POST['status'] ?? 'active';

    if($target_id > 0 && !empty($new_name)){
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, status = ? WHERE user_id = ? AND branch_id = ?");
            $stmt->execute([$new_name, $new_phone, $new_status, $target_id, $branch_id]);
            $message = "<div style='padding:10px; background:#d4edda; color:#155724; margin-bottom:15px; border-radius:4px;'>User updated successfully!</div>";
        } catch(PDOException $e) {
            $message = "<div style='padding:10px; background:#f8d7da; color:#721c24; margin-bottom:15px; border-radius:4px;'>Update failed: " . $e->getMessage() . "</div>";
        }
    }
}

// --- DATA FETCHing ---
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.role, u.phone, u.status,
               s.national_id, i.instructor_license_number
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id
        LEFT JOIN instructors i ON u.user_id = i.user_id
        WHERE u.branch_id = ? AND u.user_id != ?
        ORDER BY u.role, u.full_name
    ");
    $stmt->execute([$branch_id, $_SESSION['user_id']]);
    $branch_users = $stmt->fetchAll();
} catch(PDOException $e) {
    $branch_users = [];
    $message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Branch Users</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-family: sans-serif; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f2f2f2; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; color: white; }
        .instructor { background: #007bff; }
        .student { background: #28a745; }
        .supervisor { background: #17a2b8; }
    </style>
</head>
<body style="padding: 20px; font-family: sans-serif;">
    <nav style="margin-bottom: 20px; padding: 10px; background: #eee;">
        <a href="dashboard.php">Overview</a> | 
        <a href="users.php"><b>Manage Users</b></a> | 
        <a href="programs.php">Programs</a> | 
        <a href="enrollments.php">Enrollments</a>
    </nav>

    <h2>Branch Staff & Students</h2>
    <?php echo $message; ?>

    <table>
        <tr>
            <th>User Info</th>
            <th>Contact</th>
            <th>Role</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($branch_users as $user): ?>
        <tr>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                
                <td>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required style="width: 90%; padding: 5px;"><br>
                    <small>
                        <?php 
                        if($user['role'] == 'student') echo "ID: " . htmlspecialchars($user['national_id']);
                        if($user['role'] == 'instructor') echo "Lic: " . htmlspecialchars($user['instructor_license_number']);
                        ?>
                    </small>
                </td>
                <td>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" style="width: 90%; padding: 5px;">
                </td>
                <td>
                    <span class="badge <?php echo $user['role']; ?>"><?php echo strtoupper($user['role']); ?></span>
                </td>
                <td>
                    <select name="status" style="padding: 5px;">
                        <option value="active" <?php if($user['status'] == 'active') echo 'selected'; ?>>Active</option>
                        <option value="suspended" <?php if($user['status'] == 'suspended') echo 'selected'; ?>>Suspended</option>
                        <option value="pending" <?php if($user['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                    </select>
                </td>
                <td>
                    <button type="submit" style="background:#007bff; color:white; border:none; padding: 6px 12px; cursor:pointer; border-radius:3px;">Save</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if(count($branch_users) == 0): ?>
        <p>No other users found in this branch.</p>
    <?php endif; ?>

</body>
</html>