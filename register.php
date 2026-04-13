<?php
// register.php - FRONTEND UI MOCKUP
session_start();
include 'includes/header.php';
?>

<div style="max-width: 600px; margin: 40px auto;">
    <div class="card">
        <h2 style="margin-bottom: 25px; text-align: center;">Learner Registration</h2>

        <form action="#" onsubmit="event.preventDefault(); alert('Form design submitted!'); window.location.href='login.php';">
            <!-- Professional 2-column Grid layout -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" placeholder="john_doe">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" placeholder="john@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" placeholder="0911234567">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" placeholder="Enter password">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" placeholder="Confirm password">
                </div>
                <div class="form-group">
                    <label>National ID</label>
                    <input type="text" placeholder="e.g., 87654321">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date">
                </div>
                <div class="form-group">
                    <label>Choose Campus/Branch</label>
                    <select>
                        <option>-- Select a Branch --</option>
                        <option>Main Branch (Addis Ababa)</option>
                        <option>Adama Branch</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>License Category</label>
                    <select>
                        <option>Auto</option>
                        <option>Manual</option>
                        <option>Heavy Truck</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Full Address</label>
                    <textarea placeholder="Enter your full address..." rows="3"></textarea>
                </div>
            </div>
            
            <button type="submit" style="width: 100%; margin-top: 15px;">Submit Registration (Demo)</button>
        </form>
        
        <p style="margin-top: 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
            Already have an account? <a href="login.php" style="color: var(--primary-color); text-decoration: none;">Login here</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
