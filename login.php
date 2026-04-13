<?php
// login.php - FRONTEND UI MOCKUP
// No database connection needed for UI review
session_start();
include 'includes/header.php';
?>

<div style="max-width: 400px; margin: 60px auto;">
    <div class="card">
        <h2 style="margin-bottom: 20px; text-align: center;">Welcome Back</h2>
        
        <form action="#">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password">
            </div>
            
            <p style="text-align:center; margin: 10px 0; color: var(--text-muted);">
                <em>(UI Demo - Just click Login)</em>
            </p>
            
            <button type="button" onclick="window.location.href='manager/dashboard.php';" style="width: 100%; margin-top: 10px;">Login (Mockup)</button>
        </form>
        
        <p style="margin-top: 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
            Don't have an account? <a href="register.php" style="color: var(--primary-color); text-decoration: none;">Register here</a>
        </p>

        <!-- UI Mockup shortcuts for easy review -->
        <div style="margin-top: 30px; font-size: 0.85rem; text-align: center; border-top: 1px solid var(--border-color); padding-top: 15px;">
            <p style="margin-bottom:10px;"><strong>Quick View Dashboard Mockups:</strong></p>
            <a href="manager/dashboard.php">Manager</a> |
            <a href="supervisor/dashboard.php">Supervisor</a> |
            <a href="instructor/dashboard.php">Instructor</a> |
            <a href="student/dashboard.php">Student</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
