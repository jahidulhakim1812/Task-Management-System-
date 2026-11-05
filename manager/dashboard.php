<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['manager']);
$s = task_stats();
[$labels, $created, $done] = monthly_trend();
[$tS, $tG, $tN] = cumulative_series('tasks');
$prog = db()->query("SELECT u.full_name, COUNT(t.id) total, COALESCE(SUM(t.status='Completed'),0) done, COALESCE(SUM(t.status<>'Completed' AND t.deadline<CURDATE()),0) late FROM users u LEFT JOIN tasks t ON t.assigned_to = u.id WHERE u.role='employee' AND u.is_active=1 GROUP BY u.id ORDER BY u.full_name")->fetchAll();
$late = db()->query(TASK_SELECT . " WHERE t.status <> 'Completed' AND t.deadline < CURDATE() ORDER BY t.deadline LIMIT 6")->fetchAll();
$soon = db()->query(TASK_SELECT . " WHERE t.status <> 'Completed' AND t.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY t.deadline, t.priority LIMIT 6")->fetchAll();
$greeting = "Here's what's happening with your team's tasks today.";
$pageTitle = 'Manager Dashboard'; include __DIR__ . '/../includes/header.php';
?>
<div class="grid">
  <?= hero_stat('Total Tasks', $s['total'], 'blue', 'tasks', 'tasks/index.php', $tG, $tS) ?>
  <?= hero_stat('Pending', $s['pending'], 'orange', 'clock', 'tasks/index.php?status=Pending', null, [], pct_of($s['pending'], $s['total'])) ?>
  <?= hero_stat('In Progress', $s['in_progress'], 'purple', 'activity', 'tasks/index.php?status=In+Progress', null, [], pct_of($s['in_progress'], $s['total'])) ?>
  <?= hero_stat('Completed', $s['completed'], 'green', 'check', 'tasks/index.php?status=Completed', null, [], pct_of($s['completed'], $s['total'])) ?>
  <?= hero_stat('Overdue', $s['overdue'], 'red', 'alert', 'tasks/index.php?status=Overdue', null, [], pct_of($s['overdue'], $s['total'])) ?>
</div>
<div class="dash">
  <div class="card s11"><?= card_head('chart', 'Task Overview', 'Current task status breakdown') ?><?= status_donut($s) ?></div>
  <div class="card s13"><?= card_head('activity', 'Tasks Created vs Completed (6 Months)', '', 'Last 6 months') ?><?= chart_lines($labels, [['Created', 'c-created', $created], ['Completed', 'c-done', $done]]) ?></div>
</div>
<div class="split"><div>
  <div class="card"><?= card_head('alert', 'Needs Attention - Overdue', 'Tasks past their deadline') ?><?= task_table($late) ?></div>
  <div class="card"><?= card_head('clock', 'Due in the Next 7 Days', 'Upcoming deadlines') ?><?= task_table($soon) ?></div>
  <div class="two"><div class="card"><?= card_head('users', 'Employee Task Progress', 'Completion per employee') ?><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Tasks</th><th>Late</th><th>Progress</th></tr></thead><tbody>
  <?php foreach ($prog as $p): $pc = $p['total'] ? (int)round($p['done'] * 100 / $p['total']) : 0; ?>
  <tr><td><span class="who"><?= avatar($p['full_name']) ?><?= e($p['full_name']) ?></span></td><td><?= (int)$p['total'] ?></td><td><?= (int)$p['late'] ? badge((int)$p['late'], 'Overdue') : '-' ?></td><td><?= progress_bar($pc) ?><small><?= $pc ?>%</small></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
  <div class="card"><?= card_head('list', 'Workload by Priority', 'Open tasks by priority level') ?><?= priority_bars('1=1', [], true) ?></div></div>
</div><div class="rcol">
  <?= quick_actions([['Create New Task', 'manager/task_form.php', 'plus'], ['Open Task Board', 'tasks/board.php', 'board'], ['View Reports', 'reports/index.php', 'chart'], ['Manage Projects', 'manager/projects.php', 'folder']]) ?>
  <?= promo_cards() ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
