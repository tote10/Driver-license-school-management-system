<?php?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
</head>
<body>
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
        <button type="submit">Sign Up</button>

    </form>
    
</body>
</html>