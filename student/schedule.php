<?php
session_start();
require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student'){
    header('Location: ../login.php');
    exit();
}

$student_id = intval($_SESSION['user_id']);
$page_title = 'My Schedule';

try {
    $stmt = $pdo->prepare(
        "SELECT sch.*, u.full_name AS instructor_name
         FROM training_schedules sch
         LEFT JOIN users u ON sch.instructor_user_id = u.user_id
         WHERE sch.student_user_id = ?
         ORDER BY sch.scheduled_datetime ASC"
    );
    $stmt->execute([$student_id]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all = [];
}

$now = new DateTime();
$upcoming = $past = [];
foreach ($all as $row) {
    $dt = new DateTime($row['scheduled_datetime']);
    if ($dt >= $now) $upcoming[] = $row; else $past[] = $row;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="topbar" style="position: static; border-radius: 18px; margin-bottom: 20px;">
        <div class="d-flex align-center gap-md">
          <div>
            <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
            <div class="text-sm text-muted">Your scheduled lessons</div>
          </div>
        </div>
        <div class="d-flex align-center gap-md">
          <div class="topbar-profile" style="cursor: default;">
            <div class="d-flex flex-col text-right">
              <span class="name font-bold text-sm"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
              <span class="role"><span class="badge badge-primary">Student</span></span>
            </div>
            <div class="avatar"><?php $n = $_SESSION['full_name'] ?? ''; $p = explode(' ', trim($n)); $init = strtoupper(substr($p[0]??'',0,1)); if(count($p)>1) $init .= strtoupper(substr(end($p),0,1)); echo htmlspecialchars($init); ?></div>
          </div>
          <a href="../logout.php" class="btn btn-outline btn-sm text-danger" style="border-color: var(--danger)">Logout</a>
        </div>
      </header>

      <main class="page-content" style="padding:0; max-width:none;">
        <div class="grid grid-cols-2 gap-md">
          <div class="card">
            <h3 class="card-subtitle mb-2">Upcoming Lessons</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr><th>Instructor</th><th>Lesson</th><th>Date & Time</th><th>Location</th><th>Status</th></tr>
                </thead>
                <tbody>
                  <?php foreach($upcoming as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['instructor_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['lesson_type']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($r['scheduled_datetime'])); ?></td>
                    <td><?php echo htmlspecialchars($r['location'] ?? '-'); ?></td>
                    <td><span class="badge <?php echo htmlspecialchars($r['status'] === 'completed' ? 'badge-success' : 'badge-warning'); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($upcoming)===0): ?><tr><td colspan="5" class="text-center text-muted">No upcoming lessons.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h3 class="card-subtitle mb-2">Past Lessons</h3>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr><th>Instructor</th><th>Lesson</th><th>Date & Time</th><th>Location</th><th>Status</th></tr>
                </thead>
                <tbody>
                  <?php foreach($past as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['instructor_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['lesson_type']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($r['scheduled_datetime'])); ?></td>
                    <td><?php echo htmlspecialchars($r['location'] ?? '-'); ?></td>
                    <td><span class="badge <?php echo htmlspecialchars($r['status'] === 'completed' ? 'badge-success' : 'badge-outline'); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(count($past)===0): ?><tr><td colspan="5" class="text-center text-muted">No past lessons.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>
  </body>
</html>
