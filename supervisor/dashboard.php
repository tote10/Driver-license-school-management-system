<?php
// supervisor/dashboard.php - FRONTEND UI MOCKUP
session_start();
include '../includes/header.php';
?>

<h2 style="margin-bottom: 20px;">Supervisor Matchmaker</h2>

<div class="card">
    <h3 style="margin-bottom: 15px;">Pending Instructor Assignments</h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Student Name</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Course Program</th>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; background: #f1f3f5;">Assign Instructor</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Sarah Jenkins</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Automatic Theory + Driving (Morning)</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; gap: 10px;">
                            <select style="padding: 5px;" required>
                                <option value="">-- Choose Instructor --</option>
                                <option value="1">Mr. David Smith (Auto)</option>
                                <option value="2">Ms. Lisa Wong (Auto/Manual)</option>
                            </select>
                            <button type="button" onclick="alert('Instructor Assigned Successfully!')" style="background: var(--primary-color); color: white; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer;">Assign</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Michael Abebe</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Manual Mastery Course</td>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; gap: 10px;">
                            <select style="padding: 5px;" required>
                                <option value="">-- Choose Instructor --</option>
                                <option value="2">Ms. Lisa Wong (Auto/Manual)</option>
                                <option value="3">Mr. James Bond (Heavy Duty/Manual)</option>
                            </select>
                            <button type="button" onclick="alert('Instructor Assigned Successfully!')" style="background: var(--primary-color); color: white; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer;">Assign</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
