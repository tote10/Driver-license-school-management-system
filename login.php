<?php
session_start();
require_once "config/db.php";
$message='';
$success=false;
if($_SERVER["REQUEST_METHOD"]=="POST"){
    $username=trim($_POST['username'] ?? '');
    $password=trim($_POST['password'] ?? '');
    if(empty($username) || empty($password)){
        $message="Please fill in all fields";
    }
    else{
    try{
        $stmt_user=$pdo->prepare("SELECT user_id,username,password_hash,role,full_name,status FROM users WHERE username=?");
        $stmt_user->execute([$username]);
        $user_data=$stmt_user->fetch();
        if($stmt_user->rowCount()===0){
            $message="Username Not Found";
        }
        
        
        elseif(!password_verify($password,$user_data['password_hash'])){
            $message="Invalid Password";
        }
        elseif($user_data['status']!=='active'){
            $message="Your account is not active. Please contact the manager.";
        }
        else{
            $_SESSION['user_id']=$user_data['user_id'];
            $_SESSION['username']=$user_data['username'];
            $_SESSION['role']=$user_data['role'];
            $_SESSION['full_name']=$user_data['full_name'];
            $success=true;
            header("refresh:2;url=dashboard.php");
                }

            }catch(PDOException $e){
                $message="Error fetching user: " . $e->getMessage();
            }
        }
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <?php if (!empty($message)) echo "<strong>" .htmlspecialchars($message) . "</strong>"; ?>

    <h2>Login Page</h2>
    <form action="login.php" method="POST">
        <label for="username">Enter Username:</label>
        <input type="text" name="username">
        <label for="password">Enter Password:</label>
        <input type="password" name="password">
        <button type="submit">Login</button>
    </form>
</body>
</html>