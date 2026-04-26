<?php
session_start();
require_once '../config/db.php';
// 1. SECURITY: Admin Only
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}
$message = "";
$edit_branch = null;
// 2. ROUTING: Handling POST Actions
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])){  
    // A. CREATE OR UPDATE BRANCH
    if($_POST['action'] == 'save'){
        $id = intval($_POST['branch_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $phone = trim($_POST['contact_phone'] ?? '');
        $email = trim($_POST['contact_email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if(empty($name) || empty($email)){
            $message = "<div class='alert alert-danger'>Name and Email are required!</div>";
        } else {
            try {
                if($id > 0){
                    // Update
                    $stmt = $pdo->prepare("UPDATE branches SET name=?, location=?, contact_phone=?, contact_email=?, address=? WHERE branch_id=?");
                    $stmt->execute([$name, $location, $phone, $email, $address, $id]);
                    $message = "<div class='alert alert-success'>Branch updated!</div>";
                } else {
                    // Create
                    $stmt = $pdo->prepare("INSERT INTO branches (name, location, contact_phone, contact_email, address) VALUES (?,?,?,?,?)");
                    $stmt->execute([$name, $location, $phone, $email, $address]);
                    $message = "<div class='alert alert-success'>New branch created!</div>";
                }
            } catch(PDOException $e) {
                $message = "<div class='alert alert-danger'>Error saving branch: " . $e->getMessage() . "</div>";
            }
        }
    }
    // B. DELETE BRANCH
    if($_POST['action'] == 'delete'){
        $id = intval($_POST['branch_id'] ?? 0);
        try {
            // Safety: Check if users are assigned to this branch
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
            $chk->execute([$id]);
            if($chk->fetchColumn() > 0){
                $message = "<div class='alert alert-danger'>Cannot delete: Branch has active users/students.</div>";
            }else {
                $stmt = $pdo->prepare("DELETE FROM branches WHERE branch_id = ?");
                $stmt->execute([$id]);
                $message = "<div class='alert alert-success'>Branch deleted successfully.</div>";
            }
        } catch(PDOException $e) {
            $message = "<div class='alert alert-danger'>Delete failed: " . $e->getMessage() . "</div>";
        }
    }
}
// 3. FETCHING DATA
// If editing, get the specific branch row
if(isset($_GET['edit'])){
    $stmt_e = $pdo->prepare("SELECT * FROM branches WHERE branch_id = ?");
    $stmt_e->execute([intval($_GET['edit'])]);
    $edit_branch = $stmt_e->fetch();
}
// Get all branches
$branches = $pdo->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Branches | Super Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        nav { background: #333; color: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        nav a { color: #fff; margin-right: 15px; text-decoration: none; font-weight: bold; }
        .container { display: flex; gap: 30px; }
        .form-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 350px; }
        .list-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-grow: 1; }
        input, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .btn-save { background: #28a745; color: white; border: none; padding: 10px; width: 100%; cursor: pointer; border-radius: 4px; }
        .btn-delete { color: #dc3545; text-decoration: none; font-size: 0.85rem; }
    </style>
</head>
<body>
    <nav>
        <span style="font-size: 1.2rem; margin-right: 30px;">🛡️ Super Admin</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="branches.php" style="border-bottom: 2px solid white;">Branches</a>
        <a href="users.php">Global Users</a>
        <a href="../logout.php" style="float: right; color: #ff6b6b;">Logout</a>
    </nav>
    <h2>Branch Management</h2>
    <?php echo $message; ?>
    <div class="container">
        <!-- Branch Form -->
        <div class="form-section">
            <h3><?php echo $edit_branch ? 'Edit Branch' : 'Create New Branch'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="branch_id" value="<?php echo $edit_branch['branch_id'] ?? 0; ?>">
                
                <label>Branch Name:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($edit_branch['name'] ?? ''); ?>" required>
                
                <label>Location (City):</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($edit_branch['location'] ?? ''); ?>">
                
                <label>Email Contact:</label>
                <input type="email" name="contact_email" value="<?php echo htmlspecialchars($edit_branch['contact_email'] ?? ''); ?>" required>
                
                <label>Phone:</label>
                <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($edit_branch['contact_phone'] ?? ''); ?>">
                
                <label>Full Address:</label>
                <textarea name="address" rows="3"><?php echo htmlspecialchars($edit_branch['address'] ?? ''); ?></textarea>
                
                <button type="submit" class="btn-save"><?php echo $edit_branch ? 'Update Branch' : 'Create Branch'; ?></button>
                <?php if($edit_branch): ?>
                    <a href="branches.php" style="display:block; text-align:center; margin-top:10px; color:#666;">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
        <!-- Branch List -->
        <div class="list-section">
            <h3>Existing School Locations</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($branches as $b): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($b['name']); ?></b></td>
                        <td><?php echo htmlspecialchars($b['location'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="branches.php?edit=<?php echo $b['branch_id']; ?>" style="color: #007bff; text-decoration: none;">Edit</a> | 
                            <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: Delete this branch?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="branch_id" value="<?php echo $b['branch_id']; ?>">
                                <button type="submit" class="btn-delete" style="background:none; border:none; cursor:pointer;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
