<?php
require_once 'config/db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST"){
    
$username=trim($_POST['username']);
$email=trim($_POST['email']);
$password=trim($_POST['password']);
$confirmPassword=trim($_POST['confirmPassword']);
$nationalID=trim($_POST['nationalID']);
$date_of_birth=trim($_POST['date_of_birth']);
$address=trim($_POST['address']);
$license_category=trim($_POST['license_category']);
$full_name=trim($_POST['fullname']);
if($password !== $confirmPassword){
    die("Passwords do not match");
}
$password_hash=password_hash($password,PASSWORD_DEFAULT);
try{
    $sql_user = "INSERT INTO users (username,email,password_hash,full_name,role,status) VALUES(?,?,?,?,'student','pending');";
    $stmt = $pdo->prepare($sql_user);
    $stmt->execute([
        $username,
        $email,
        $password_hash,
        $full_name,      
    ]);
     $new_user_id = $pdo->lastInsertId();
     $sql_student = "INSERT INTO students (user_id,national_id,date_of_birth,address,license_category,registration_status) VALUES(?,?,?,?,?,'pending');";
     $stmt_student = $pdo->prepare($sql_student);
     $stmt_student-> execute([
        $new_user_id,
        $nationalID,
        $date_of_birth,
        $address,
        $license_category,
     ]);
     $message="Registration successful wait for manager approval";


}catch(PDOException $e){
    $message="Registration failed: " . $e->getMessage();
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
</head>
<body>
    <?php if (!empty($message)) echo "<p><strong>$message</strong></p>"; ?>

    <h2>Registration as a Learner</h2>
    <form action="register.php" method="post">
        <label for="name">Full Name:</label>
        <input type="text" name="fullname" required>
        <label for="username">Username:</label>
        <input type="text" name="username" required>
        <label for="email">Email address:</label>
        <input type="email" name="email" required>
        <label for="password">Password</label>
        <input type="password" name="password" required>
        <label for="confirmPassword">Confirm Password:</label>
        <input type="password" name="confirmPassword" required>
        <label for="nationalID">National ID:</label>
        <input type="text" name="nationalID" required>
        
        <label for="dob">Date of Birth:</label>
        <input type="date" name="date_of_birth" required><br><br>

        <label for="address">Address:</label>
        <textarea name="address" required></textarea><br><br>

        <label for="category">License Category (e.g., Auto, Level 1):</label>
        <select name="license_category" required>
            <option value="Auto">Auto</option>
            <option value="level1">Level 1</option>
            <option value="level2">Level 2</option>
            <option value="level3">Level 3</option>
            <option value="level4">Level 4</option>
            <option value="level5">Level 5</option>
            <option value="level6">Level 6</option>
        </select><br><br>
        <button type="submit">Sign Up</button>

    </form>
    
</body>
</html>