<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    $name = trim($_POST['name']);
    $category = trim($_POST['license_category']);
    $theory_hours = intval($_POST['theory_duration_hours']);
    $practical_hours = intval($_POST['practical_duration_hours']);
    $fee = floatval($_POST['fee_amount']);
    $description = trim($_POST['description']);
    if (empty($name) || empty($category) || empty($theory_hours) || empty($practical_hours) || $fee<=0)
    {
        $message = "Please fill in all required fields.";
    } 
    else{
        try{
            $stmt=$pdo->prepare("INSERT INTO training_programs (branch_id,name,license_category,theory_duration_hours,practical_duration_hours,fee_amount,description) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$_SESSION['branch_id'],$name,$category,$theory_hours,$practical_hours,$fee,$description]);
            $message="Program added successfully";
            
        }
        catch(PDOException $e){
            $message="Program added failed: " . $e->getMessage();
        }
    }
}
try{
    $stmt=$pdo->query("SELECT * FROM training_programs WHERE branch_id={$_SESSION['branch_id']}");
    $programs=$stmt->fetchAll();
}catch(PDOException $e){
    $programs=[];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Programs - Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Manage Programs</h1>
        <?php if($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="POST" action="programs.php">
            <input type="hidden" name="action" value="create">
            <label for="name">Program Name:</label>
            <input type="text" id="name" name="name" required>
            <label for="license_category">License Category:</label>
            <select id="license_category" name="license_category" required>
                <option value="">-- Select License Category --</option>
                <option value="Automatic">Automatic</option>
                <option value="Level 1">Level 1</option>
                <option value="Level 2">Level 2</option>
                <option value="Level 3">Level 3</option>
                <option value="Level 4">Level 4</option>
                <option value="Level 5">Level 5</option>
                <option value="Level 6">Level 6</option>
            </select>

            <label for="theory_duration_hours">Theory Duration (hours):</label>
            <input type="number" id="theory_duration_hours" name="theory_duration_hours" required>
            <label for="practical_duration_hours">Practical Duration (hours):</label>
            <input type="number" id="practical_duration_hours" name="practical_duration_hours" required>
            <label for="fee_amount">Fee Amount:</label>
            <input type="number" id="fee_amount" name="fee_amount" required>
            <label for="description">Description:</label>
            <textarea id="description" name="description"></textarea>
            <button type="submit">Add Program</button>
        </form>
        <h2>Existing Programs</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>License Category</th>
                    <th>Theory Hours</th>
                    <th>Practical Hours</th>
                    <th>Fee</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($programs as $program): ?>
                    <tr>
                        <td><?php echo $program['program_id']; ?></td>
                        <td><?php echo $program['name']; ?></td>
                        <td><?php echo $program['license_category']; ?></td>
                        <td><?php echo $program['theory_duration_hours']; ?></td>
                        <td><?php echo $program['practical_duration_hours']; ?></td>
                        <td><?php echo $program['fee_amount']; ?></td>
                        <td><?php echo $program['description']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>