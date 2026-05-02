<?php
require_once __DIR__ . '/notifications.php';

function render_notifications_widget(PDO $pdo, int $user_id, string $view_link = 'notifications.php') {
    $unread = fetch_unread_count($pdo, $user_id);
    $items = fetch_user_notifications($pdo, $user_id, 5);
    $widget_id = 'notifications-widget-' . $user_id;
    $button_id = 'notif-toggle-' . $user_id;
    $dropdown_id = 'notif-dropdown-' . $user_id;
    ?>
    <div class="notifications-widget" id="<?php echo htmlspecialchars($widget_id); ?>" style="position:relative;">
      <button type="button" class="btn btn-ghost notifications-toggle" id="<?php echo htmlspecialchars($button_id); ?>" data-notifications-toggle="<?php echo htmlspecialchars($dropdown_id); ?>" style="position:relative;">
        🔔
        <?php if($unread>0): ?><span class="badge badge-danger" style="position:absolute; top:-6px; right:-6px; font-size:11px;"><?php echo $unread; ?></span><?php endif; ?>
      </button>
      <div id="<?php echo htmlspecialchars($dropdown_id); ?>" class="card notifications-dropdown" style="display:none; position:absolute; right:0; width:320px; max-height:360px; overflow:auto; z-index:999;">
        <div style="padding:8px 12px; border-bottom:1px solid #eee;"><strong>Notifications</strong> <a href="<?php echo htmlspecialchars($view_link); ?>" style="float:right; font-size:12px;">View all</a></div>
        <div style="padding:8px;">
          <?php if(empty($items)): ?>
            <div class="text-muted">No notifications</div>
          <?php else: ?>
            <ul style="list-style:none; margin:0; padding:0;">
              <?php foreach($items as $it): ?>
                <li style="padding:8px; border-bottom:1px solid #f2f2f2;">
                  <div style="font-weight:600;"><?php echo htmlspecialchars($it['title']); ?></div>
                  <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars(mb_strimwidth($it['message'],0,120,'...')); ?></div>
                  <div style="font-size:11px; color:#999; margin-top:6px;"><?php echo date('M d, H:i', strtotime($it['sent_at'] ?? 'now')); ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <script>
      (function(){
        const widget = document.getElementById('<?php echo htmlspecialchars($widget_id); ?>');
        const btn = document.getElementById('<?php echo htmlspecialchars($button_id); ?>');
        const dd = document.getElementById('<?php echo htmlspecialchars($dropdown_id); ?>');
        if(!btn) return;
        btn.addEventListener('click', () => {
          dd.style.display = dd.style.display === 'none' || dd.style.display === '' ? 'block' : 'none';
        });
        document.addEventListener('click', (e)=>{ if(widget && !widget.contains(e.target)) dd.style.display='none'; });
      })();
    </script>
    <?php
}

?>
