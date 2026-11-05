<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['employee']);
$page = page_num();
$c = db()->prepare('SELECT COUNT(*) FROM task_history h JOIN tasks t ON t.id = h.task_id WHERE t.assigned_to = ?'); $c->execute([$user['id']]); $total = (int)$c->fetchColumn();
$st = db()->prepare('SELECT h.*, t.title, t.id tid FROM task_history h JOIN tasks t ON t.id = h.task_id WHERE t.assigned_to = ? ORDER BY h.id DESC LIMIT ' . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE)); $st->execute([$user['id']]);
$rows = $st->fetchAll();
$st = db()->prepare("SELECT id, title, completed_at FROM tasks WHERE assigned_to = ? AND status = 'Completed' ORDER BY completed_at DESC LIMIT 8"); $st->execute([$user['id']]);
$done = $st->fetchAll();
$pageTitle = 'My Task History'; include __DIR__ . '/../includes/header.php';
?>
<div class="two"><div class="card"><h3>Recently completed</h3><ul class="list"><?php foreach ($done as $d): ?><li><?= icon('check', 16) ?> <a href="<?= e(url('tasks/view.php?id=' . $d['id'])) ?>"><?= e($d['title']) ?></a> <span class="muted small"><?= e(fmt_date($d['completed_at'])) ?></span></li><?php endforeach; if (!$done) echo '<li class="muted">Nothing completed yet.</li>'; ?></ul></div>
<div class="card"><h3>Status updates</h3><div class="table-wrap"><table><thead><tr><th>When</th><th>Task</th><th>Change</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(fmt_dt($r['created_at'])) ?></td><td><a href="<?= e(url('tasks/view.php?id=' . $r['tid'])) ?>"><?= e($r['title']) ?></a></td><td><?= e($r['old_status']) ?> &rarr; <?= e($r['new_status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?= pager($total, $page) ?></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
