<?php
// manager/dashboard.php - FRONTEND UI MOCKUP
session_start();
include '../includes/header.php';
?>

<h2 style="margin-bottom: 20px;">Manager Command Center</h2>

<!-- Stats Overview Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Total Students</h3>
        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">145</div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Pending Approvals</h3>
        <div style="font-size: 2rem; font-weight: bold; color: var(--warning);">2</div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Active Programs</h3>
        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">5</div>
    </div>
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Monthly Revenue</h3>
        <div style="font-size: 2rem; font-weight: bold; color: var(--success);">$12,400</div>
    </div>
</div>

<!-- Pending Workflow List -->
<div class="card">
    <h3 style="margin-bottom: 15px;">Pending Student Approvals</h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Full Name</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">National ID</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Requested Category</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Sarah Jenkins</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">NAT-998811</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Auto Level 1</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; gap: 10px;">
                            <select style="padding: 5px;" required>
                                <option value="">-- Assign Program --</option>
                                <option value="1">Automatic Theory + Driving (Morning)</option>
                                <option value="2">Automatic Theory + Driving (Evening)</option>
                            </select>
                            <button type="button" onclick="alert('Student Approved & Enrolled!')" style="background: var(--success); color: white; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer;">Approve</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Michael Abebe</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">NAT-556677</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Manual</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; gap: 10px;">
                            <select style="padding: 5px;" required>
                                <option value="">-- Assign Program --</option>
                                <option value="3">Manual Mastery Course</option>
                            </select>
                            <button type="button" onclick="alert('Student Approved & Enrolled!')" style="background: var(--success); color: white; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer;">Approve</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
