<?php
session_start();
require_once 'config/db.php';
if(!isset($_SESSION['user_id']) or $_SESSION['role'] !== 'manager'){
    header("Location: login.php");
    exit();
}
if(isset($_POST['approve_id'])){
    $student_id = trim($_POST['approve_id']);
    try{
        $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE user_id=?");
        $stmt->execute([$student_id]);
        $message="Student account activated and approved!";
    }
}
$message= '';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard</title>
</head>
<body>
   <h2>Welcome,</h2>
   <?=htmlspecialchars($_SESSION['full_name']);?>
   <a href="logout.php">Logout</a>
   <h2>Pending Student Approvals</h2>
   <table border="1" cellpadding="10" cellspacing="0">
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Registered At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($pending_students)):?>
                <tr>
                    <td colspan="4">No pending students</td>
                </tr>
            <?php else:?>
                <?php foreach($pending_students as $student):?>
                    <tr>
                        <td><?= htmlspecialchars($student['full_name'])?></td>
                        <td><?= htmlspecialchars($student['email'])?></td>
                        <td><?= htmlspecialchars($student['created_at'])?></td>
                        <td>
                            
                        </td>
                    </tr>
                <?php endforeach;?>
            <?php endif;?>
        </tbody>
   </table>
</body>
</html>