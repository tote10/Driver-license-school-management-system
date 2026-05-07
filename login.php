<?php
session_start();
require_once 'config/db.php';
require_once __DIR__ . '/includes/security.php';
$message = "";
$success = false;
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if(!csrf_validate_request()){
        $message = "Security validation failed. Please refresh and try again.";
    }

    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'];
    if (empty($message) && (empty($login_id) || empty($password))) {
        $message = "Please enter both username and password.";
    }
    elseif (empty($message)) {
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
            error_log('Login query failed: ' . $e->getMessage());
            $message = "Login failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Driving School</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>" />
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: radial-gradient(circle at top right, #e6f4ea 0%, #f7faf9 40%, #eef5ff 100%);
            padding: 20px;
        }
        .auth-card {
            width: min(440px, 100%);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            padding: 28px;
        }
        .auth-title {
            margin: 0 0 6px;
            font-size: 1.4rem;
        }
        .auth-subtitle {
            margin: 0 0 18px;
            color: var(--text-muted);
            font-size: 0.92rem;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <h2 class="auth-title">Welcome back</h2>
        <p class="auth-subtitle">Sign in to continue to Driving License School.</p>
        <?php if ($message): ?>
            <div class="toast show <?php echo $success ? '' : 'bg-danger'; ?>" style="position: static; margin-bottom: 14px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="login.php" class="d-flex flex-col gap-md">
            <?php csrf_input(); ?>
            <div class="form-group mb-0">
                <label class="form-label" for="login_id">Username or Email</label>
                <input type="text" class="form-control" id="login_id" name="login_id" value="<?php echo htmlspecialchars($login_id ?? ''); ?>" required>
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Secure Login</button>
        </form>
        <p class="text-sm text-muted mt-3 mb-0">
            No account yet? <a href="register.php">Create one here</a>.
        </p>
    </div>
</body>
</html>
