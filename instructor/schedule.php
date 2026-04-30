<?php
require_once __DIR__ . '/includes/common.php';

$week_offset = (int)($_GET['week'] ?? 0);
$week_start = new DateTime('monday this week');
if ($week_offset !== 0) {
    $week_start->modify(sprintf('%+d week', $week_offset));
}
$week_start->setTime(0, 0, 0);
$week_end = (clone $week_start)->modify('+6 days')->setTime(23, 59, 59);

$page_title = 'Weekly Schedule';
$grouped_days = [];
$load_error = null;

for ($i = 0; $i < 7; $i++) {
    $day = (clone $week_start)->modify('+' . $i . ' day');
    $grouped_days[$day->format('Y-m-d')] = [
        'label' => $day->format('D, M j'),
        'items' => [],
    ];
}

try {
    $stmt = $pdo->prepare(
        "SELECT sch.schedule_id, sch.lesson_type, sch.scheduled_datetime, sch.duration_minutes, sch.location, sch.status,
                u.full_name AS student_name
         FROM training_schedules sch
         JOIN users u ON sch.student_user_id = u.user_id
         WHERE sch.instructor_user_id = ?
           AND sch.scheduled_datetime BETWEEN ? AND ?
         ORDER BY sch.scheduled_datetime ASC"
    );
    $stmt->execute([
        $instructor_id,
        $week_start->format('Y-m-d H:i:s'),
        $week_end->format('Y-m-d H:i:s'),
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $schedule) {
        $day_key = (new DateTime($schedule['scheduled_datetime']))->format('Y-m-d');
        if (!isset($grouped_days[$day_key])) {
            $grouped_days[$day_key] = [
                'label' => (new DateTime($schedule['scheduled_datetime']))->format('D, M j'),
                'items' => [],
            ];
        }
        $grouped_days[$day_key]['items'][] = $schedule;
    }
} catch (PDOException $e) {
    error_log('Instructor schedule load failed: ' . $e->getMessage());
    $load_error = 'Unable to load schedule right now.';
}

$prev_week = $week_offset - 1;
$next_week = $week_offset + 1;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Weekly Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>" />
    <script src="../assets/js/app.js" defer></script>
  </head>
  <body>
    <div class="app-wrapper">
      <?php include __DIR__ . '/includes/sidebar.php'; ?>
      <div class="main-content">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        <main class="page-content">
          <div class="card mb-4">
            <div class="d-flex flex-wrap justify-between align-center gap-md mb-2">
              <div>
                <h3 class="card-subtitle mb-2">Weekly Schedule</h3>
                <p class="text-sm text-muted mb-0">
                  <?php echo $week_start->format('Y-m-d'); ?> to <?php echo $week_end->format('Y-m-d'); ?>
                </p>
              </div>
              <div class="d-flex gap-sm flex-wrap">
                <a href="?week=<?php echo $prev_week; ?>" class="btn btn-outline btn-sm">Previous Week</a>
                <a href="schedule.php" class="btn btn-outline btn-sm">This Week</a>
                <a href="?week=<?php echo $next_week; ?>" class="btn btn-outline btn-sm">Next Week</a>
              </div>
            </div>

            <?php if ($load_error !== null): ?>
            <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($load_error); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-md">
              <?php foreach ($grouped_days as $day): ?>
              <div class="card" style="margin-bottom: 0;">
                <div class="d-flex justify-between align-center mb-2">
                  <h3 class="card-subtitle mb-0"><?php echo htmlspecialchars($day['label']); ?></h3>
                  <span class="badge badge-outline"><?php echo count($day['items']); ?> lesson<?php echo count($day['items']) === 1 ? '' : 's'; ?></span>
                </div>

                <?php if (count($day['items']) > 0): ?>
                <div class="d-flex flex-col gap-sm">
                  <?php foreach ($day['items'] as $schedule): ?>
                  <div class="card" style="margin-bottom: 0; padding: 14px; box-shadow: none; border-style: dashed;">
                    <div class="d-flex justify-between gap-sm align-center flex-wrap">
                      <div>
                        <div class="font-bold"><?php echo htmlspecialchars($schedule['student_name']); ?></div>
                        <div class="text-sm text-muted"><?php echo htmlspecialchars($schedule['location'] ?? 'No location'); ?></div>
                      </div>
                      <div class="d-flex gap-sm flex-wrap align-center">
                        <span class="badge <?php echo instructor_badge_class($schedule['status']); ?>"><?php echo htmlspecialchars($schedule['lesson_type']); ?></span>
                        <span class="badge badge-outline"><?php echo date('H:i', strtotime($schedule['scheduled_datetime'])); ?></span>
                        <span class="badge badge-outline"><?php echo (int)$schedule['duration_minutes']; ?> min</span>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-sm text-muted">No lessons scheduled.</div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>