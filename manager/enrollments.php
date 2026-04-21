<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}
$message = "";

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == "approve"){
    $student_id = intval($_POST['student_id'] ?? 0);
    $program_id = intval($_POST['program_id'] ?? 0);
    
    if($student_id <= 0 || $program_id <= 0){
        $message = "<div class='alert alert-danger'>You must select a valid program to approve this student.</div>";
    } else {
        try {
            $pdo->beginTransaction();
            
            // SECURITY FIX: Ensure the student actually exists and belongs to THIS branch before approving!
            $stmt_check = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND branch_id = ? AND status = 'pending'");
            $stmt_check->execute([$student_id, $_SESSION['branch_id']]);
            if ($stmt_check->rowCount() === 0) {
                throw new Exception("Student not found, not pending, or does not belong to your branch.");
            }
            
            // Update User status
            $stmt_user = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            $stmt_user->execute([$student_id]);

            // Update Student registration status
            $stmt_students = $pdo->prepare("UPDATE students SET registration_status='approved' WHERE user_id = ?");
            $stmt_students->execute([$student_id]);
            
            // Insert Enrollment Record (FIX: Added current_progress_status for dashboard)
            $stmt_enroll = $pdo->prepare("INSERT INTO enrollments (student_user_id, program_id, approval_status, approved_by, approved_date, current_progress_status) VALUES (?, ?, 'approved', ?, NOW(), 'enrolled')");
            $stmt_enroll->execute([$student_id, $program_id, $_SESSION['user_id']]);
            
            $pdo->commit();
            $message = "<div class='alert alert-success'>Student approved successfully and enrolled into the program!</div>";
        } catch(Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Approval failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// --- RESTORED REJECT LOGIC ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == "reject"){
    $student_id = intval($_POST['student_id'] ?? 0);
    try {
        $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ? AND branch_id = ?")->execute([$student_id, $_SESSION['branch_id']]);
        $pdo->prepare("UPDATE students SET registration_status = 'rejected' WHERE user_id = ?")->execute([$student_id]);
        $message = "<div class='alert alert-danger'>Student registration rejected and account suspended.</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Rejection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

try {
    // SECURITY FIX: Parameterized prepared statement for branch_id
    $stmt_pending = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.email, u.phone, s.national_id, s.license_category, b.name as branch_name 
        FROM users u
        JOIN students s ON u.user_id = s.user_id
        JOIN branches b ON u.branch_id = b.branch_id
        WHERE u.status = 'pending' AND s.registration_status = 'pending' AND u.branch_id = ?
    ");
    $stmt_pending->execute([$_SESSION['branch_id']]);
    $pending_students = $stmt_pending->fetchAll();
} catch (PDOException $e) {
    $pending_students = [];
    $message = "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

try {
    // SECURITY FIX: Parameterized prepared statement for branch_id
    $stmt_programs = $pdo->prepare("SELECT program_id, name, license_category FROM training_programs WHERE branch_id = ?");
    $stmt_programs->execute([$_SESSION['branch_id']]);
    $programs = $stmt_programs->fetchAll();
} catch(PDOException $e) {
    $programs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Enrollments</title>
    <style>
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <nav style="margin-bottom: 20px; padding: 10px; background: #eee;">
        <b>Manager Module:</b><br><br>
        <a href="dashboard.php">School Overview</a> | 
        <a href="users.php">Manage Users</a> | 
        <a href="programs.php">Training Programs</a> | 
        <a href="enrollments.php" style="font-weight: bold;">Registrations & Enrollments</a> | 
        <a href="../logout.php" style="color: red; margin-left: 15px;">Logout</a>
    </nav>
    
    <h2>Pending Student Registrations</h2>
    
    <?php if ($message) echo $message; ?>
    
    <?php if (count($pending_students) > 0): ?>
        <table>
            <tr>
                <th>Student Name</th>
                <th>National ID</th>
                <th>Requested Category</th>
                <th>Assign Program & Approve</th>
            </tr>
            <?php foreach ($pending_students as $student): ?>
                <tr>
                    <!-- SECURITY FIX: htmlspecialchars on outputs -->
                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['national_id']); ?></td>
                    <td><b><?php echo htmlspecialchars($student['license_category']); ?></b></td>
                    
                    <td>
                        <form method="POST" style="display: flex; gap: 10px; margin: 0;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['user_id']); ?>">
                            
                            <select name="program_id" required style="padding: 5px;">
                                <option value="">-- Assign Program --</option>
                                <?php 
                                foreach ($programs as $program) {
                                    if ($program['license_category'] == $student['license_category']) {
                                        echo "<option value='" . htmlspecialchars($program['program_id']) . "'>" . htmlspecialchars($program['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            <button type="submit" style="background: green; color: white; border: none; padding: 5px 15px; cursor: pointer; border-radius: 3px;">Approve</button>
                        </form>

                        <!-- REJECT BUTTON -->
                        <form method="POST" onsubmit="return confirm('Reject this student?');" style="margin-top: 5px;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['user_id']); ?>">
                            <button type="submit" style="background: #dc3545; color: white; border: none; padding: 5px 15px; cursor: pointer; border-radius: 3px;">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <div class="alert alert-success">No pending students right now. You are all caught up!</div>
    <?php endif; ?>
</body>
</html>