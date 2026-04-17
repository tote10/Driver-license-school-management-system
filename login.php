<?php
session_start();
require_once 'config/db.php';
$message = "";
$success = false;
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_id = trim($_POST['login_id']);
    $password = $_POST['password'];
    if (empty($login_id) || empty($password)) {
        $message = "Please enter both username and password.";
    }
    else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email=?");
            $stmt->execute([$login_id,$login_id]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                if($user['status'] !== 'active'){
                    $message = "Your account is not active. Please contact the administrator.";
                }
                else{   
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['full_name'] = $user['full_name'];
                header("Location: dashboard.php");
                exit();}
            }
            else {
                $message = "Invalid username or password.";
            }
        }
        catch(PDOException $e){
            $message = "Login failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Driving School</title>
</head>
<body>
    <h2>System Login</h2>
    <?php if ($message): ?>
        <p style="color: <?php echo $success ? 'green' : 'red'; ?>;">
            <b><?php echo htmlspecialchars($message); ?></b>
        </p>
    <?php endif; ?>
    <form method="POST" action="login.php">
        <label for="login_id">Username or Email:</label><br>
        <input type="text" id="login_id" name="login_id" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        <button type="submit">Secure Login</button>
    </form>
    <br>
    <a href="register.php">Don't have an account? Register here.</a>
</body>
</html>
