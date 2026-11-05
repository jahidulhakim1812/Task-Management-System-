<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['admin']);
[$uS, $uG, $uN] = cumulative_series('users'); [$mS, $mG, $mN] = cumulative_series('users', "role='manager'");
[$eS, $eG, $eN] = cumulative_series('users', "role='employee'"); [$tS, $tG, $tN] = cumulative_series('tasks');
$s = task_stats();
[$labels, $created, $done] = monthly_trend();
$load = db()->query("SELECT u.full_name, COUNT(t.id) n FROM users u LEFT JOIN tasks t ON t.assigned_to=u.id AND t.status<>'Completed' WHERE u.role='employee' AND u.is_active=1 GROUP BY u.id ORDER BY n DESC, u.full_name LIMIT 8")->fetchAll();
$logs = db()->query('SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 6')->fetchAll();
$dotc = ['login' => 'c-progress', 'logout' => 'c-progress', 'report_view' => 'c-created', 'task_create' => 'c-pending', 'task_status' => 'c-completed', 'task_update' => 'c-pending'];
$greeting = "Here's what's happening with your tasks today.";
$pageTitle = 'Admin Dashboard'; include __DIR__ . '/../includes/header.php';
?>
<div class="grid">
  <?= hero_stat('Total Users', $uN, 'blue', 'users', 'admin/users.php', $uG, $uS) ?><?= hero_stat('Managers', $mN, 'green', 'user', 'admin/users.php?role=manager', $mG, $mS) ?>
  <?= hero_stat('Employees', $eN, 'orange', 'users', 'admin/users.php?role=employee', $eG, $eS) ?><?= hero_stat('Total Tasks', $tN, 'purple', 'tasks', 'tasks/index.php', $tG, $tS) ?>
</div>
<div class="dash">
  <div class="card s11"><?= card_head('chart', 'Task Overview', 'Current task status breakdown', 'All time') ?><?= status_donut($s) ?></div>
  <div class="card s13"><?= card_head('activity', 'Tasks Created vs Completed (6 Months)', '', 'Last 6 months') ?><?= chart_lines($labels, [['Created', 'c-created', $created], ['Completed', 'c-done', $done]]) ?></div>
</div>
<div class="split"><div>
  <div class="two"><div class="card"><?= card_head('list', 'Workload by Priority', 'Open tasks by priority level') ?><?= priority_bars('1=1', [], true) ?></div>
  <div class="card"><?= card_head('users', 'Open Tasks per Employee', 'Current open tasks for each employee') ?><?= chart_bars(array_map(fn($r) => [$r['full_name'], (int)$r['n'], 'c-progress', avatar($r['full_name'])], $load)) ?></div></div>
  <div class="card"><div class="row between"><?= card_head('activity', 'Recent System Activity', 'Latest actions and updates from your system') ?><a class="btn sm" href="<?= e(url('admin/activity.php')) ?>">View all</a></div>
  <div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?><tr><td><i class="tdot <?= e($dotc[$l['action']] ?? 'c-progress') ?>"></i><?= e(time_ago($l['created_at'])) ?></td><td><span class="who"><?= avatar($l['full_name'] ?? 'System') ?><?= e($l['full_name'] ?? 'System') ?></span></td><td><?= badge($l['action'], 'role') ?></td><td><?= e($l['details']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
</div><div class="rcol">
  <?= quick_actions([['Create New Task', 'manager/task_form.php', 'plus'], ['Add New User', 'admin/user_form.php', 'userplus'], ['Manage Users', 'admin/users.php', 'user'], ['View Reports', 'reports/index.php', 'chart'], ['System Settings', 'admin/settings.php', 'sliders']]) ?>
  <?= promo_cards() ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
