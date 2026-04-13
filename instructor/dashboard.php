<?php
// instructor/dashboard.php - FRONTEND UI MOCKUP
session_start();
include '../includes/header.php';
?>

<h2 style="margin-bottom: 20px;">Instructor Gradebook</h2>

<div class="card">
    <h3 style="margin-bottom: 15px;">My Assigned Students</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Student Name</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Program Course</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Lessons Completed</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Sarah Jenkins</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Automatic Theory + Driving (Morning)</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">4 / 10</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <button type="button" onclick="alert('Grading Form Details appear here!')" style="background: var(--warning); color: #333; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer;">Record Progress</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
