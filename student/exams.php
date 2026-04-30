<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$page_title = 'My Exams';

try {
    $stmt = $pdo->prepare(
        "SELECT exam_id, exam_type, scheduled_date, taken_date, score, passed, status, comments
         FROM exam_records
         WHERE student_user_id = ?
         ORDER BY COALESCE(taken_date, scheduled_date) DESC"
    );
    $stmt->execute([$student_id]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $exams = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Exams</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="topbar" style="position: static; border-radius: 18px; margin-bottom: 20px;">
        <div class="d-flex align-center gap-md">
          <div>
            <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
            <div class="text-sm text-muted">Your exam schedule and results</div>
          </div>
        </div>
        <div class="d-flex align-center gap-md">
          <div class="topbar-profile" style="cursor: default;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></div>
        </div>
      </header>

      <main class="page-content" style="padding:0; max-width:none;">
        <div class="card">
          <h3 class="card-subtitle mb-2">Exam History</h3>
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Type</th><th>Status</th><th>Score</th><th>Scheduled</th><th>Taken</th><th>Comments</th></tr></thead>
              <tbody>
                <?php foreach($exams as $ex): ?>
                <tr>
                  <td><?php echo htmlspecialchars($ex['exam_type']); ?></td>
                  <td><span class="badge <?php echo ($ex['status']==='passed' || $ex['passed']==1) ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars($ex['status']); ?></span></td>
                  <td><?php echo $ex['score'] !== null ? intval($ex['score']) : '-'; ?></td>
                  <td><?php echo !empty($ex['scheduled_date']) ? date('Y-m-d H:i', strtotime($ex['scheduled_date'])) : '-'; ?></td>
                  <td><?php echo !empty($ex['taken_date']) ? date('Y-m-d H:i', strtotime($ex['taken_date'])) : '-'; ?></td>
                  <td><?php echo htmlspecialchars($ex['comments'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($exams)===0): ?><tr><td colspan="6" class="text-center text-muted">No exam records.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </body>
</html>
