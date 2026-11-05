<?php
require_once __DIR__ . '/config/config.php';
$user = require_login();
if (isset($_GET['read'])) {
    $st = db()->prepare('SELECT task_id FROM notifications WHERE id = ? AND user_id = ?'); $st->execute([(int)$_GET['read'], $user['id']]);
    if ($row = $st->fetch()) {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([(int)$_GET['read']]);
        redirect($row['task_id'] ? 'tasks/view.php?id=' . $row['task_id'] : 'notifications.php');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
    flash('All notifications marked as read.'); redirect('notifications.php');
}
$page = page_num();
$c = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?'); $c->execute([$user['id']]); $total = (int)$c->fetchColumn();
$st = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$st->execute([$user['id']]); $rows = $st->fetchAll();
$icons = ['assigned' => 'tasks', 'completed' => 'check', 'overdue' => 'alert', 'deadline' => 'clock', 'comment' => 'list', 'status' => 'activity'];
$pageTitle = 'Notifications'; include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="row between"><h3>Your notifications</h3><form method="post"><?= csrf_field() ?><button class="btn sm">Mark all as read</button></form></div>
<?php if (!$rows): ?><div class="empty"><?= icon('bell', 36) ?><p>No notifications yet.</p></div><?php endif; ?>
<ul class="list"><?php foreach ($rows as $n): ?>
  <li class="<?= $n['is_read'] ? '' : 'unread' ?>"><?= icon($icons[$n['type']] ?? 'bell', 16) ?> <a href="?read=<?= (int)$n['id'] ?>"><?= e($n['message']) ?></a> <span class="muted small"><?= e(time_ago($n['created_at'])) ?></span></li>
<?php endforeach; ?></ul><?= pager($total, $page) ?></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
