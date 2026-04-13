<?php
// student/dashboard.php - FRONTEND UI MOCKUP
session_start();
include '../includes/header.php';
?>

<h2 style="margin-bottom: 20px;">My Learning Dashboard</h2>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">My Program</h3>
        <div style="font-size: 1.2rem; font-weight: bold; color: var(--primary-color);">Automatic Theory + Driving</div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Current Status</h3>
        <div style="font-size: 1.2rem; font-weight: bold; color: var(--success);">Approved & Active</div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">My Instructor</h3>
        <div style="font-size: 1.2rem; font-weight: bold; color: var(--text-main);">Mr. David Smith</div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Average Score</h3>
        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">88.5%</div>
    </div>
</div>

<div class="card">
    <h3>Recent Notifications</h3>
    <ul style="margin-top: 15px; color: var(--text-main); font-size: 0.95rem; list-style: none;">
        <li style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
           <span style="font-size: 1.2rem;">📅</span> You have a scheduled lesson tomorrow at 9:00 AM.
        </li>
        <li style="margin-bottom: 10px;">
           <span style="font-size: 1.2rem;">✅</span> Your theory exam has been graded. Result: <span style="color: var(--success); font-weight: bold;">Passed</span>.
        </li>
    </ul>
</div>

<?php include '../includes/footer.php'; ?>
