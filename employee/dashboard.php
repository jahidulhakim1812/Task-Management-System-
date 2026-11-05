<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['employee']);
$s = task_stats('assigned_to = ?', [$user['id']]);
$st = db()->prepare(TASK_SELECT . " WHERE t.assigned_to = ? AND t.status <> 'Completed' ORDER BY t.deadline ASC LIMIT 6"); $st->execute([$user['id']]);
$upcoming = $st->fetchAll();
[$labels, $created, $done] = monthly_trend('assigned_to = ?', [$user['id']]);
[$tS, $tG] = cumulative_series('tasks', 'assigned_to = ?', [$user['id']]);
$pct = $s['total'] ? (int)round($s['completed'] * 100 / $s['total']) : 0;
$greeting = "Here's what's on your plate today.";
$pageTitle = 'My Dashboard'; include __DIR__ . '/../includes/header.php';
?>
<div class="grid">
  <?= hero_stat('My Tasks', $s['total'], 'blue', 'tasks', 'tasks/index.php', $tG, $tS) ?>
  <?= hero_stat('Pending', $s['pending'], 'orange', 'clock', 'tasks/index.php?status=Pending', null, [], pct_of($s['pending'], $s['total'])) ?>
  <?= hero_stat('In Progress', $s['in_progress'], 'purple', 'activity', 'tasks/index.php?status=In+Progress', null, [], pct_of($s['in_progress'], $s['total'])) ?>
  <?= hero_stat('Completed', $s['completed'], 'green', 'check', 'tasks/index.php?status=Completed', null, [], pct_of($s['completed'], $s['total'])) ?>
  <?= hero_stat('Overdue', $s['overdue'], 'red', 'alert', 'tasks/index.php?status=Overdue', null, [], pct_of($s['overdue'], $s['total'])) ?>
</div>
<div class="dash">
  <div class="card s11"><?= card_head('chart', 'My Task Overview', 'Completion rate: ' . $pct . '%') ?><?= status_donut($s) ?></div>
  <div class="card s13"><?= card_head('activity', 'Assigned vs Completed (6 Months)', '', 'Last 6 months') ?><?= chart_lines($labels, [['Assigned', 'c-created', $created], ['Completed', 'c-done', $done]]) ?></div>
</div>
<div class="split"><div>
  <div class="card"><div class="row between"><?= card_head('clock', 'Upcoming Deadlines', 'Your next open tasks') ?><a class="btn sm" href="<?= e(url('tasks/index.php')) ?>">All my tasks</a></div><?= task_table($upcoming, false) ?></div>
</div><div class="rcol">
  <?= quick_actions([['My Tasks', 'tasks/index.php', 'tasks'], ['My Board', 'tasks/board.php', 'board'], ['Calendar', 'tasks/calendar.php', 'calendar'], ['Task History', 'employee/history.php', 'clock']]) ?>
  <?= promo_cards() ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
