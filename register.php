<?php
session_start();
require_once 'config/db.php';
require_once __DIR__ . '/includes/security.php';
$success = false;
$message = "";
try{
    $stmt_branch=$pdo->query("SELECT branch_id,name FROM branches ORDER BY name ASC");
    $branches=$stmt_branch->fetchAll();
}
catch(PDOException $e){
    error_log('Register branches query failed: ' . $e->getMessage());
    $branches=[];
}
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(!csrf_validate_request()){
        $message = "Security validation failed. Please refresh and try again.";
    }

    $full_name=trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $branch_id = trim($_POST['branch_id']);
    $phone=trim($_POST['phone']);
    $gender=trim($_POST['gender']);
    $password  = $_POST['password'];
    $confirm=trim($_POST['confirm_password']);
    $national_id = trim($_POST['national_id']);
    $dob=$_POST['dob'];
    $address=trim($_POST['address']);
    $license_category=trim($_POST['license_category']);
    
    if (empty($message) && (empty($full_name) || empty($username) || empty($email) || empty($phone) || empty($password) || 
        empty($national_id) || empty($dob) || empty($address) || empty($license_category) || empty($branch_id))) {
        $message = "Please fill in all required fields.";
    }
    elseif(empty($message) && $password !== $confirm){
        $message="Passwords do not match";
        $success=false;
    }
    elseif(empty($message)) {
        $check_user = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check_user->execute([$username, $email]);
        $check_student = $pdo->prepare("SELECT user_id FROM students WHERE national_id = ?");
        $check_student->execute([$national_id]);
        if ($check_user->rowCount() > 0) {
            $message = "Error: That Username or Email is already used.";
            $success = false;
        }
        elseif($check_student->rowCount()>0){
            $message="Error: That National ID is already used";
            $success=false;
        }
        else{
            $hashed_password=password_hash($password,PASSWORD_DEFAULT);
            try{
            $pdo->beginTransaction();
            $stmt_user=$pdo->prepare("INSERT INTO users (username,email,password_hash,full_name,phone,gender,branch_id,role,status) VALUES (?,?,?,?,?,?,?,'student','pending')");
            $stmt_user->execute([$username,$email,$hashed_password,$full_name,$phone,$gender,$branch_id]);
            $newUser_id=$pdo->lastInsertId();
            $stmt_student=$pdo->prepare("INSERT INTO students (user_id,national_id,date_of_birth,address,license_category) VALUES (?,?,?,?,?)");
            $stmt_student->execute([$newUser_id,$national_id,$dob,$address,$license_category]);
            $pdo->commit();
            $success=true;
            $message="Registration successful";
            }
            catch(PDOException $e){
            $pdo->rollBack();
            $success=false;
            error_log('Registration transaction failed: ' . $e->getMessage());
            $message="Registration failed. Please try again.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration | Driving School</title>
        <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>" />
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                background: linear-gradient(145deg, #eef6ff 0%, #f9fcf7 100%);
                padding: 20px;
            }
            .register-wrap {
                max-width: 980px;
                margin: 0 auto;
            }
            .register-card {
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
                padding: 24px;
            }
            .register-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 16px;
            }
            .register-grid fieldset {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 14px;
            }
            .register-grid legend {
                font-weight: 700;
                padding: 0 6px;
            }
        </style>
</head>
<body>
        <div class="register-wrap">
            <div class="register-card">
                <h2 class="card-subtitle" style="font-size:1.35rem;">Student Registration</h2>
                <p class="text-sm text-muted">Create your student account to request enrollment approval.</p>
                <?php if ($message): ?>
                    <div class="toast show <?php echo $success ? '' : 'bg-danger'; ?>" style="position: static; margin-bottom: 14px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="register.php" class="d-flex flex-col gap-md">
                <?php csrf_input(); ?>
                <div class="register-grid">
                <fieldset>
            <legend>Account Credentials</legend>
                        <label class="form-label" for="fname">Full Name</label>
                        <input type="text" class="form-control" id="fname" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                        <label class="form-label" for="uname">Username</label>
                        <input type="text" class="form-control" id="uname" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <label class="form-label" for="pass">Create Password</label>
                        <input type="password" class="form-control" id="pass" name="password" required>
                        <label class="form-label" for="conf">Confirm Password</label>
                        <input type="password" class="form-control" id="conf" name="confirm_password" required>
        </fieldset>
        <fieldset>
            <legend>Personal Details</legend>
                        <label class="form-label" for="nat_id">National ID</label>
                        <input type="text" class="form-control" id="nat_id" name="national_id" value="<?php echo htmlspecialchars($_POST['national_id'] ?? ''); ?>" required>
                        <label class="form-label" for="dob">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>" required>
                        <label class="form-label" for="gender">Gender</label>
                        <select id="gender" class="form-control" name="gender" required>
                <option value="">-- Select Gender --</option>
                                <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                        <label class="form-label" for="address">Contact Address</label>
                        <textarea id="address" class="form-control" name="address" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
        </fieldset>
        <fieldset>
            <legend>Enrollment Details</legend>
                        <label class="form-label" for="license">License Category</label>
                        <select id="license" class="form-control" name="license_category" required>
                <option value="">-- Select License Category --</option>
                                <option value="Automatic" <?php echo (($_POST['license_category'] ?? '') === 'Automatic') ? 'selected' : ''; ?>>Automatic</option>
                                <option value="Level 1" <?php echo (($_POST['license_category'] ?? '') === 'Level 1') ? 'selected' : ''; ?>>Level 1</option>
                                <option value="Level 2" <?php echo (($_POST['license_category'] ?? '') === 'Level 2') ? 'selected' : ''; ?>>Level 2</option>
                                <option value="Level 3" <?php echo (($_POST['license_category'] ?? '') === 'Level 3') ? 'selected' : ''; ?>>Level 3</option>
                                <option value="Level 4" <?php echo (($_POST['license_category'] ?? '') === 'Level 4') ? 'selected' : ''; ?>>Level 4</option>
                                <option value="Level 5" <?php echo (($_POST['license_category'] ?? '') === 'Level 5') ? 'selected' : ''; ?>>Level 5</option>
                                <option value="Level 6" <?php echo (($_POST['license_category'] ?? '') === 'Level 6') ? 'selected' : ''; ?>>Level 6</option>
                        </select>
                        <label class="form-label" for="branch">Select Branch</label>
                        <select id="branch" class="form-control" name="branch_id" required>
                <option value="">-- Select Branch --</option>
                <?php foreach($branches as $branch): ?>
                                        <option value="<?php echo $branch['branch_id']; ?>" <?php echo (intval($_POST['branch_id'] ?? 0) === intval($branch['branch_id'])) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endforeach; ?>
                        </select>
        </fieldset>
                </div>
                <div class="d-flex gap-sm align-center">
                    <button type="submit" class="btn btn-primary">Register Now</button>
                    <a href="login.php" class="btn btn-outline">Back to Login</a>
                </div>
        </form>
        </div>
        </div>
        </body>
</html>
