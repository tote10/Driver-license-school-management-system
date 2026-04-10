<?php
require_once "config/db.php";
$message='';
$success=false;
if($_SERVER["REQUEST_METHOD"]=="POST"){
    $username=trim($_POST['username']);
    $passwrord=trim($_POST['password']);
    try{
        $get_user=$pdo->prepare("SELECT user_id,username,password_hash,role,full_name,status FROM users WHERE username=?");
        $user=$get_user->execute([$username]);
        if($get_user->rowCount()===0){
            $message="Username Not Found";
        }
        else{
            $user_data=$get_user->fetch();
            if(password_verify($password,$user_data['password_hash'])){
                if($user_data['status']!=='active'){
                    $message="Your account is not active. Please contact the manager.";
                }
                else{
                    
                }

            }
            else{
                $message="Invalid Password";
            }
        }
    
    }catch(PDO Exeception r){
        $message="Error fetching user: " . $e->getMessage();
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