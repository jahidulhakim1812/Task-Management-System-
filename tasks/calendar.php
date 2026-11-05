<?php
require_once __DIR__ . '/../config/config.php';
$user = require_login();
$ym = preg_match('/^\d{4}-\d{2}$/', $_GET['ym'] ?? '') ? $_GET['ym'] : date('Y-m');
$first = strtotime("$ym-01"); $last = strtotime(date('Y-m-t', $first));
$gridStart = strtotime('-' . date('w', $first) . ' days', $first); $gridEnd = strtotime('+' . (6 - date('w', $last)) . ' days', $last);
$w = ['t.deadline BETWEEN ? AND ?']; $p = [date('Y-m-d', $gridStart), date('Y-m-d', $gridEnd)];
if ($user['role'] === 'employee') { $w[] = 't.assigned_to = ?'; $p[] = $user['id']; }
$st = db()->prepare(TASK_SELECT . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY t.deadline'); $st->execute($p);
$by = []; foreach ($st as $t) $by[$t['deadline']][] = $t;
$pageTitle = 'Calendar'; include __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="row between"><h3 style="margin:0"><?= e(date('F Y', $first)) ?> <small class="muted">- task deadlines</small></h3>
<div class="row"><a class="btn sm" href="?ym=<?= date('Y-m', strtotime('-1 month', $first)) ?>"><?= icon('left', 14) ?></a><a class="btn sm" href="?ym=<?= date('Y-m') ?>">Today</a><a class="btn sm" href="?ym=<?= date('Y-m', strtotime('+1 month', $first)) ?>"><?= icon('right', 14) ?></a></div></div><br>
<div class="cal"><?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?><div class="dow"><?= $d ?></div><?php endforeach; ?>
<?php for ($d = $gridStart; $d <= $gridEnd; $d = strtotime('+1 day', $d)): $key = date('Y-m-d', $d); $items = $by[$key] ?? []; ?>
  <div class="day <?= date('Y-m', $d) !== $ym ? 'off' : '' ?> <?= $key === date('Y-m-d') ? 'today' : '' ?>"><span class="dn"><?= date('j', $d) ?></span>
  <?php foreach (array_slice($items, 0, 3) as $t): $es = effective_status($t); ?>
    <a class="chip c-<?= $es === 'Overdue' ? 'overdue' : ($es === 'Completed' ? 'completed' : strtolower($t['priority'])) ?>" href="<?= e(url('tasks/view.php?id=' . $t['id'])) ?>" title="<?= e($t['title'] . ' - ' . $t['employee_name'] . ' (' . $es . ')') ?>"><?= e($t['title']) ?></a>
  <?php endforeach; if (count($items) > 3): ?><span class="small muted">+<?= count($items) - 3 ?> more</span><?php endif; ?></div>
<?php endfor; ?></div>
<div class="legend inline" style="margin-top:.8rem"><li><i class="dot c-overdue"></i>Overdue</li><li><i class="dot c-completed"></i>Completed</li><li><i class="dot c-urgent"></i>Urgent</li><li><i class="dot c-high"></i>High</li><li><i class="dot c-medium"></i>Medium</li><li><i class="dot c-low"></i>Low</li></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
