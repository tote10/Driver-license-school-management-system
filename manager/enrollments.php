<?php
session_start();
require_once '../config/db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}
$message = "";
 if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['action']) && $_POST['action']=="approve"){
    $student_id=intval($_POST['student_id']);
    $program_id=intval($_POST['program_id']);
    if(empty($program_id)){
        $message="You must select program to approve this student";
    }
    else{
        try{
            $pdo->beginTransaction();
            $stmt_user = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id= ?");
            $stmt_user->execute([$student_id]);

            $stmt_students=$pdo->prepare("UPDATE students SET registration_status='approved' WHERE user_id=?");
            $stmt_students->execute([$student_id]);
            

            $stmt_enroll=$pdo->prepare("INSERT INTO enrollments (student_user_id,program_id,approval_status,approved_by,approved_date) VALUES (?,?,?,?,now())");
            $stmt_enroll->execute([$student_id,$program_id,'approved',$_SESSION['user_id']]);
            $pdo->commit();
            $message="Student approved successfully and enrilled.";
        }
        catch(PDOException $e){
            $pdo->rollBack();
            $message="Approval failed: ".$e->getMessage();
        }
    }
 }
try {
    $stmt_pending=$pdo->query("SELECT u.user_id,u.full_name,u.email,u.phone,s.national_id,s.license_category,b.name as branch_name 
    FROM users u
    JOIN students s ON u.user_id=s.user_id
    JOIN branches b ON u.branch_id=b.branch_id
    WHERE u.status='pending' AND s.registration_status='pending'");
    
    $pending_students=$stmt_pending->fetchAll();
} catch (PDOException $e) {
    $pending_students=[];
}
try{
    $stmt_programs=$pdo->query("SELECT program_id,name,license_category FROM training_programs");
    $programs=$stmt_programs->fetchAll();
}catch(PDOException $e){
    $programs=[];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Enrollments</title>
</head>
<body>
    <nav style="margin-bottom: 20px; padding: 10px; background: #eee;">
        <b>Manager Module:</b><br><br>
        <a href="dashboard.php">School Overview</a> | 
        <a href="users.php">Manage Users</a> | 
        <a href="programs.php">Training Programs</a> | 
        <a href="enrollments.php">Registrations & Enrollments</a> | 
        <a href="../logout.php" style="color: red; margin-left: 15px;">Logout</a>
    </nav>
    <h2>Pending Student Registrations</h2>
    
    <?php if ($message): ?>
        <p style="color: green;"><b><?php echo htmlspecialchars($message); ?></b></p>
    <?php endif; ?>
    <?php if (count($pending_students) > 0): ?>
        <table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%;">
            <tr>
                <th>Student Name</th>
                <th>National ID</th>
                <th>Requested Category</th>
                <th>Assign Program & Approve</th>
            </tr>
            <?php foreach ($pending_students as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['national_id']); ?></td>
                    <td><?php echo htmlspecialchars($student['license_category']); ?></td>
                    
                    <td>
                        <!-- Form to submit the Approval -->
                        <form method="POST" action="enrollments.php" style="display: flex; gap: 10px;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
                            
                            <select name="program_id" required>
                                <option value="">-- Assign Program --</option>
                                <?php 
                                // THE MAGIC FILTER! Only show programs matching this student
                                foreach ($programs as $program) {
                                    if ($program['license_category'] == $student['license_category']) {
                                        echo "<option value='{$program['program_id']}'>" . htmlspecialchars($program['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            
                            <button type="submit" style="background: green; color: white;">Approve Now</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No pending students right now. You are all caught up!</p>
    <?php endif; ?>
</body>
</html>