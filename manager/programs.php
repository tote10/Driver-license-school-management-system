<?php
session_start();
require_once '../config/db.php';

// check if manager is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$message = "";
$branch_id = $_SESSION['branch_id'];
$my_id = $_SESSION['user_id'];

// whitelist for safety
$allowed_cats = ['Automatic', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Level 6'];

// catch message from url
if(isset($_GET['m'])) {
    $m = $_GET['m'];
    if($m == 'created') $message = "<div class='alert alert-success'>Program created!</div>";
    elseif($m == 'deleted') $message = "<div class='alert alert-success'>Program deleted.</div>";
    elseif($m == 'updated') $message = "<div class='alert alert-success'>Program updated.</div>";
    elseif($m == 'busy') $message = "<div class='alert alert-danger'>Cannot delete: students are enrolled here.</div>";
    elseif($m == 'error') $message = "<div class='alert alert-danger'>Something went wrong. Check your data.</div>";
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])){
  
    // creating new program
    if($_POST['action'] == 'create'){
        $name = trim($_POST['name']);
        $cat = trim($_POST['license_category']);
        $t_hrs = intval($_POST['theory_duration_hours']);
        $p_hrs = intval($_POST['practical_duration_hours']);
        $fee = floatval($_POST['fee_amount']);
        $desc = trim($_POST['description'] ?? '');
        
        // whitelist and range check
        if(empty($name) || $fee <= 0 || !in_array($cat, $allowed_cats)){
            header("Location: programs.php?m=error");
            exit();
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO training_programs (branch_id, created_by, name, license_category, theory_duration_hours, practical_duration_hours, fee_amount, description) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$branch_id, $my_id, $name, $cat, $t_hrs, $p_hrs, $fee, $desc]);
                header("Location: programs.php?m=created");
                exit();
            } catch(PDOException $e){
                header("Location: programs.php?m=error");
                exit();
            }
        }
    }

    // deleting (super safe check)
    if($_POST['action'] == 'delete'){
        $pid = intval($_POST['program_id']);
        try {
            // anyone enrolled?
            $chk = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE program_id = ?");
            $chk->execute([$pid]);
            if($chk->fetchColumn() > 0){
                header("Location: programs.php?m=busy");
                exit();
            } else {
                // kill it but only in our branch!
                $stmt = $pdo->prepare("DELETE FROM training_programs WHERE program_id=? AND branch_id=?");
                $stmt->execute([$pid, $branch_id]);
                header("Location: programs.php?m=deleted");
                exit();
            }
        } catch(PDOException $e){
            header("Location: programs.php?m=error");
            exit();
        }
    }

    // update from the big table form
    if(strpos($_POST['action'], 'update_') === 0){
        $pid = intval(str_replace('update_', '', $_POST['action']));
        
        // grab inputs using the dynamic names
        $name = trim($_POST["name_$pid"] ?? '');
        $cat = trim($_POST["cat_$pid"] ?? '');
        $t_hrs = intval($_POST["t_hrs_$pid"] ?? 0);
        $p_hrs = intval($_POST["p_hrs_$pid"] ?? 0);
        $fee = floatval($_POST["fee_$pid"] ?? 0);
        $desc = trim($_POST["desc_$pid"] ?? '');

        // server side check category and data
        if(empty($name) || $fee <= 0 || !in_array($cat, $allowed_cats) || $pid <= 0){
            header("Location: programs.php?m=error");
            exit();
        } else {
            try {
                // tight branch check here too
                $stmt = $pdo->prepare("UPDATE training_programs SET name=?, license_category=?, theory_duration_hours=?, practical_duration_hours=?, fee_amount=?, description=?, updated_at=NOW() WHERE program_id=? AND branch_id=?");
                $stmt->execute([$name, $cat, $t_hrs, $p_hrs, $fee, $desc, $pid, $branch_id]);
                
                header("Location: programs.php?m=updated");
                exit();
            } catch(PDOException $e){
                header("Location: programs.php?m=error");
                exit();
            }
        }
    }
}

// pull current branch programs
try {
    $stmt = $pdo->prepare("SELECT * FROM training_programs WHERE branch_id = ? ORDER BY license_category ASC, name ASC");
    $stmt->execute([$branch_id]);
    $programs = $stmt->fetchAll();
} catch(PDOException $e) {
    $programs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Programs</title>
    <style>
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; font-family: sans-serif; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-family: sans-serif; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f2f2f2; }
        input, select, textarea { padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
    </style>
</head>
<body>
    <nav style="padding: 10px; background: #eee; margin-bottom: 20px; font-family: sans-serif;">
        <a href="dashboard.php">Dashboard</a> | <a href="users.php">Users</a> | <a href="programs.php"><b>Programs</b></a> | <a href="enrollments.php">Enrollments</a>
    </nav>

    <h2>Manage Training Programs</h2>
    <?php if($message) echo $message; ?>

    <!-- 1. CREATE FORM (Clean and separate) -->
    <fieldset style="padding: 20px; font-family: sans-serif;">
        <legend>Create New Program</legend>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <input type="text" name="name" placeholder="Program Name" required>
            
            <select name="license_category" required>
                <?php foreach($allowed_cats as $cat): ?>
                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                <?php endforeach; ?>
            </select>

            <input type="number" name="theory_duration_hours" placeholder="Theory Hrs" required style="width:80px;">
            <input type="number" name="practical_duration_hours" placeholder="Practical Hrs" required style="width:80px;">
            <input type="number" name="fee_amount" step="0.01" placeholder="Fee ($)" required style="width:100px;">
            <br><br>
            <textarea name="description" placeholder="Description" style="width:100%;"></textarea>
            <br><br>
            <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">Save New Program</button>
        </form>
    </fieldset>

    <!-- 2. THE BIG TABLE FORM (Valid and Robust) -->
    <form method="POST">
    <table>
        <tr>
            <th>Program Info</th>
            <th>Hrs (T/P)</th>
            <th>Fee ($)</th>
            <th>Actions</th>
        </tr>
        <?php foreach($programs as $p): $pid = $p['program_id']; ?>
        <tr>
            <td>
                <!-- explicit names so one form works for all rows -->
                <input type="text" name="name_<?php echo $pid; ?>" value="<?php echo htmlspecialchars($p['name']); ?>" required style="width:90%;"><br>
                <small>Category:</small>
                <select name="cat_<?php echo $pid; ?>" style="margin-top: 5px;">
                    <?php foreach($allowed_cats as $acat): ?>
                        <option value="<?php echo $acat; ?>" <?php if($p['license_category']==$acat) echo 'selected'; ?>><?php echo $acat; ?></option>
                    <?php endforeach; ?>
                </select>
                <br>
                <textarea name="desc_<?php echo $pid; ?>" style="width:90%; height:40px; font-size:11px; margin-top:5px;"><?php echo htmlspecialchars($p['description']); ?></textarea>
            </td>
            <td>
                T: <input type="number" name="t_hrs_<?php echo $pid; ?>" value="<?php echo $p['theory_duration_hours']; ?>" style="width:40px;"><br>
                P: <input type="number" name="p_hrs_<?php echo $pid; ?>" value="<?php echo $p['practical_duration_hours']; ?>" style="width:40px;">
            </td>
            <td>
                <input type="number" name="fee_<?php echo $pid; ?>" value="<?php echo $p['fee_amount']; ?>" step="0.01" style="width:70px;">
            </td>
            <td>
                <!-- Each button tells the form which row to update -->
                <button type="submit" name="action" value="update_<?php echo $pid; ?>" style="background: orange; border: none; padding: 8px; cursor: pointer;">Update</button>
                
                <br><br>
                <!-- delete form stays separate inside its own cell -->
                <form method="POST" onsubmit="return confirm('Kill this entry?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="program_id" value="<?php echo $pid; ?>">
                    <button type="submit" style="background: red; color: white; border: none; padding: 8px; cursor: pointer;">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </form>
</body>
</html>