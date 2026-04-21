<?php
session_start();
require_once '../config/db.php';

// check if manager is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager'){
    header("Location: ../login.php");
    exit();
}

$branch_id = $_SESSION['branch_id'];
$manager_id = $_SESSION['user_id'];
$message = "";

// --- ACTIONS: SCHEDULE OR RECORD ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])){
    
    // 1. Schedule Exam
    if($_POST['action'] == 'schedule'){
        $sid = intval($_POST['student_id'] ?? 0);
        $type = $_POST['exam_type'] ?? '';
        $date = $_POST['scheduled_date'] ?? '';

        if($sid > 0 && !empty($type) && !empty($date)){
            try {
                $stmt = $pdo->prepare("INSERT INTO exam_records (student_user_id, exam_type, scheduled_date, status) VALUES (?, ?, ?, 'scheduled')");
                $stmt->execute([$sid, $type, $date]);
                $message = "<div style='padding:10px; background:#d4edda; color:#155724; border-radius:4px;'>Exam scheduled successfully!</div>";
            } catch(PDOException $e) {
                $message = "<div style='padding:10px; background:#f8d7da; color:#721c24; border-radius:4px;'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }

    // 2. Record Result
    if(strpos($_POST['action'], 'result_') === 0){
        $eid = intval(str_replace('result_', '', $_POST['action']));
        $score = intval($_POST["score_$eid"] ?? 0);
        $passed = isset($_POST["passed_$eid"]) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE exam_records SET score = ?, passed = ?, status = 'completed', taken_date = NOW(), approved_by = ? WHERE exam_id = ?");
            $stmt->execute([$score, $passed, $manager_id, $eid]);
            $message = "<div style='padding:10px; background:#d4edda; color:#155724; border-radius:4px;'>Result recorded!</div>";
        } catch(PDOException $e) {
            $message = "<div style='padding:10px; background:#f8d7da; color:#721c24; border-radius:4px;'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// --- DATA FETCHING ---
// 1. Fetch Enrolled Students for this branch
$students = [];
try {
    $stmt_students = $pdo->prepare("
        SELECT u.user_id, u.full_name 
        FROM users u 
        JOIN enrollments e ON u.user_id = e.student_user_id 
        WHERE u.branch_id = ? AND e.current_progress_status = 'enrolled'
    ");
    $stmt_students->execute([$branch_id]);
    $students = $stmt_students->fetchAll();
} catch(PDOException $e) {}

// 2. Fetch Scheduled Exams for this branch
$exams = [];
try {
    $stmt_exams = $pdo->prepare("
        SELECT er.*, u.full_name 
        FROM exam_records er 
        JOIN users u ON er.student_user_id = u.user_id 
        WHERE u.branch_id = ? AND er.status = 'scheduled'
        ORDER BY er.scheduled_date ASC
    ");
    $stmt_exams->execute([$branch_id]);
    $exams = $stmt_exams->fetchAll();
} catch(PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Management</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-family: sans-serif; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f2f2f2; }
        .card { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; font-family: sans-serif; }
    </style>
</head>
<body style="padding: 20px; font-family: sans-serif;">
    <nav style="margin-bottom: 20px; padding: 10px; background: #eee;">
        <a href="dashboard.php">Overview</a> | 
        <a href="users.php">Manage Users</a> | 
        <a href="programs.php">Programs</a> | 
        <a href="enrollments.php">Enrollments</a> |
        <a href="exams.php"><b>Exams</b></a>
    </nav>

    <h2>Exam Management</h2>
    <?php echo $message; ?>

    <!-- Schedule Form -->
    <div class="card">
        <h3>Schedule New Exam</h3>
        <form method="POST">
            <input type="hidden" name="action" value="schedule">
            <select name="student_id" required style="padding: 5px;">
                <option value="">-- Select Student --</option>
                <?php foreach($students as $s): ?>
                    <option value="<?php echo $s['user_id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="exam_type" required style="padding: 5px;">
                <option value="theory">Theory</option>
                <option value="practical">Practical</option>
            </select>

            <input type="datetime-local" name="scheduled_date" required style="padding: 5px;">
            
            <button type="submit" style="padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">Schedule</button>
        </form>
    </div>

    <!-- Scheduled Exams -->
    <h3>Pending Results</h3>
    <?php if(count($exams) > 0): ?>
        <form method="POST">
            <table>
                <tr>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Record Result</th>
                </tr>
                <?php foreach($exams as $ex): $eid = $ex['exam_id']; ?>
                <tr>
                    <td><?php echo htmlspecialchars($ex['full_name']); ?></td>
                    <td><?php echo strtoupper($ex['exam_type']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($ex['scheduled_date'])); ?></td>
                    <td>
                        <input type="number" name="score_<?php echo $eid; ?>" placeholder="Score" style="width: 60px; padding: 5px;">
                        <label><input type="checkbox" name="passed_<?php echo $eid; ?>"> Passed?</label>
                        <button type="submit" name="action" value="result_<?php echo $eid; ?>" style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-left: 10px;">Save</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </form>
    <?php else: ?>
        <p>No exams currently scheduled.</p>
    <?php endif; ?>

</body>
</html>
