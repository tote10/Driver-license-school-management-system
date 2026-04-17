<?php
require_once 'config/db.php';
$success = false;
$message = "";
try{
    $stmt_branch=$pdo->query("SELECT branch_id,name FROM branches ORDER BY name ASC");
    $branches=$stmt_branch->fetchAll();
}
catch(PDOException $e){
    $branches=[];
}
if($_SERVER["REQUEST_METHOD"] == "POST"){
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
    
    if (empty($full_name) || empty($username) || empty($email) || empty($phone) || empty($password) || 
        empty($national_id) || empty($dob) || empty($address) || empty($license_category) || empty($branch_id)) {
        $message = "Please fill in all required fields.";
    }
    elseif($password !== $confirm){
        $message="Passwords do not match";
        $success=false;
    }
    else {
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
            $message="Registration failed: " . $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration | Driving School</title>
</head>
<body>
    <h2>Student Registration Form</h2>
    <?php if ($message): ?>
    <p style="color: <?php echo $success ? 'green' : 'red'; ?>;">
        <b><?php echo htmlspecialchars($message); ?></b>
    </p>
<?php endif; ?>
<form method="POST" action="register.php">
        <fieldset>
            <legend>Account Credentials</legend>
            <label for="fname">Full Name:</label><br>
            <input type="text" id="fname" name="full_name" required><br><br>
            <label for="uname">Username:</label><br>
            <input type="text" id="uname" name="username" required><br><br>
            <label for="email">Email Address:</label><br>
            <input type="email" id="email" name="email" required><br><br>
            <label for="pass">Create Password:</label><br>
            <input type="password" id="pass" name="password" required><br><br>
            
            <label for="conf">Confirm Password:</label><br>
            <input type="password" id="conf" name="confirm_password" required><br><br>
        </fieldset>
        <fieldset>
            <legend>Personal Details</legend>
            <label for="nat_id">National ID:</label><br>
            <input type="text" id="nat_id" name="national_id" required><br><br>
            <label for="dob">Date of Birth:</label><br>
            <input type="date" id="dob" name="dob" required><br><br>
            
            <label for="gender">Gender:</label><br>
            <select id="gender" name="gender" required>
                <option value="">-- Select Gender --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select><br><br>
            
            <label for="phone">Phone Number:</label><br>
            <input type="text" id="phone" name="phone" required><br><br>
            <label for="address">Contact Address:</label><br>
            <textarea id="address" name="address" required></textarea><br><br>
        </fieldset>
        <fieldset>
            <legend>Enrollment Details</legend>
            <label for="license">License Category:</label><br>
            <select id="license" name="license_category" required>
                <option value="">-- Select License Category --</option>
                <option value="Automatic">Automatic</option>
                <option value="Level 1">Level 1</option>
                <option value="Level 2">Level 2</option>
                <option value="Level 3">Level 3</option>
                <option value="Level 4">Level 4</option>
                <option value="Level 5">Level 5</option>
                <option value="Level 6">Level 6</option>
            </select><br><br>
            
            <label for="branch">Select Branch:</label><br>
            <select id="branch" name="branch_id" required>
                <option value="">-- Select Branch --</option>
                <?php foreach($branches as $branch): ?>
                    <option value="<?php echo $branch['branch_id']; ?>">
                        <?php echo htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select><br><br>
        </fieldset>
        <button type="submit">Register Now</button>
    </form>
    </body>
</html>