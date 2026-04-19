<?php
session_start();
require_once '../config/db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}
$branch_id=$_SESSION['branch_id'];
$message="";
try{
    //tottal students
    $stmt_students = $pdo->prepare("SELECT status, COUNT(*) as total FROM users WHERE role='student' AND branch_id=? GROUP BY status");
    $stmt_students->execute([$branch_id]);
    $student_stats = $stmt_students->fetchAll(PDO::FETCH_KEY_PAIR); 


    //total staff
    $stmt_staff = $pdo->prepare("SELECT role, COUNT(*) as total FROM users WHERE role IN ('instructor', 'supervisor') AND branch_id=? GROUP BY role");
    $stmt_staff->execute([$branch_id]);
    $staff_stats = $stmt_staff->fetchAll(PDO::FETCH_KEY_PAIR);
    
    //total enrollments
    $stmt_enrollments = $pdo->prepare("
        SELECT e.current_progress_status, COUNT(*) as total 
        FROM enrollments e 
        JOIN users u ON e.student_user_id = u.user_id
        WHERE u.branch_id = ? 
        GROUP BY e.current_progress_status
    ");
    $stmt_enrollments->execute([$branch_id]);
    $enrollment_stats = $stmt_enrollments->fetchAll(PDO::FETCH_KEY_PAIR);

    //revenu
    $stmt_revenue=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments p 
    JOIN users u ON p.student_user_id=u.user_id
    WHERE p.status='completed' AND u.branch_id=?");
    $stmt_revenue->execute([$branch_id]);
    $total_revenue=$stmt_revenue->fetchColumn();

    //complaints
    $stmt_comp = $pdo->prepare("
        SELECT COUNT(*) FROM complaints c 
        JOIN users u ON c.reporter_user_id = u.user_id 
        WHERE c.status='open' AND u.branch_id=?
    ");
    $stmt_comp->execute([$branch_id]);
    $open_complaints = $stmt_comp->fetchColumn();


    //todays scedule
    $stmt_sched = $pdo->prepare("SELECT COUNT(*) FROM training_schedules WHERE branch_id=? AND DATE(scheduled_datetime) = CURRENT_DATE()");
    $stmt_sched->execute([$branch_id]);
    $todays_classes = $stmt_sched->fetchColumn();

    //upcoming exams
    $stmt_exams = $pdo->prepare("
        SELECT COUNT(*) FROM exam_records e 
        JOIN users u ON e.student_user_id = u.user_id 
        WHERE e.status='pending_validation' AND u.branch_id=?
    ");
    $stmt_exams->execute([$branch_id]);
    $pending_exams = $stmt_exams->fetchColumn();
}catch(PDOException $e){
    $message="Error: " . $e->getMessage();
}

$active_students = $student_stats['active'] ?? 0;
$pending_students = $student_stats['pending'] ?? 0;
$suspended_students = $student_stats['suspended'] ?? 0;
$total_instructors = $staff_stats['instructor'] ?? 0;
$total_supervisors = $staff_stats['supervisor'] ?? 0;
$total_staff = $total_instructors + $total_supervisors;
$currently_enrolled = $enrollment_stats['enrolled'] ?? 0;
$completed_programs = $enrollment_stats['completed'] ?? 0;
$total_revenue = $total_revenue ?? 0;
$open_complaints = $open_complaints ?? 0;
$todays_classes = $todays_classes ?? 0;
$pending_exams = $pending_exams ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Command Center</title>
</head>
<body>
    <nav style="margin-bottom: 20px; padding: 10px; background: #eee;">
        <b>Local Manager Module:</b><br><br>
        <a href="dashboard.php">Command Center</a> | 
        <a href="users.php">Manage Users</a> | 
        <a href="programs.php">Training Programs</a> | 
        <a href="enrollments.php">Registrations & Enrollments</a> | 
        <a href="../logout.php" style="color: red; margin-left: 15px;">Logout</a>
    </nav>

    <h2 style="margin-bottom: 20px;">Branch Command Center</h2>

    <?php if($message) echo "<p style='color:red;'>$message</p>"; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        
        <!-- Student Box -->
        <fieldset style="padding: 15px; border: 1px solid #ccc;">
            <legend style="font-weight: bold;">Students</legend>
            <ul style="list-style: none; padding: 0;">
                <li style="color: green; font-size: 1.2rem;"><b>Active:</b> <?php echo $active_students; ?></li>
                <li style="color: orange;"><b>Awaiting Approval:</b> <?php echo $pending_students; ?></li>
                <li style="color: red;"><b>Suspended:</b> <?php echo $suspended_students; ?></li>
            </ul>
        </fieldset>

        <!-- Operations Box -->
        <fieldset style="padding: 15px; border: 1px solid #ccc;">
            <legend style="font-weight: bold;">Operations & Staff</legend>
            <ul style="list-style: none; padding: 0;">
                <li style="font-size: 1.2rem;"><b>Total Instructors:</b> <?php echo $total_instructors; ?></li>
                <li><b>Total Supervisors:</b> <?php echo $total_supervisors; ?></li>
                <li style="margin-top: 10px; color: blue;"><b>Students currently learning:</b> <?php echo $currently_enrolled; ?></li>
            </ul>
        </fieldset>

        <!-- Action Required Box (The Alerts!) -->
        <fieldset style="padding: 15px; border: 2px solid red; background: #fff5f5;">
            <legend style="font-weight: bold; color: red;">Action Alerts</legend>
            <ul style="list-style: none; padding: 0;">
                <li><b>Open Complaints:</b> <span style="color: red; font-weight: bold;"><?php echo $open_complaints; ?></span></li>
                <li><b>Exams to Validate:</b> <span style="color: orange; font-weight: bold;"><?php echo $pending_exams; ?></span></li>
                <li><b>Classes Happening Today:</b> <?php echo $todays_classes; ?></li>
            </ul>
        </fieldset>

        <!-- Financial Box -->
        <fieldset style="padding: 15px; border: 1px solid #ccc; background: #eef9ee;">
            <legend style="font-weight: bold;">Financials</legend>
            <div style="font-size: 2.5rem; color: green; font-weight: bold; margin-top: 10px;">
                $<?php echo number_format($total_revenue, 2); ?>
            </div>
            <p style="color: gray; font-size: 0.9rem;">Total Paid Revenue</p>
        </fieldset>

    </div>
</body>
</html>
