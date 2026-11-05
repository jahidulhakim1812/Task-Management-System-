<?php
require_once __DIR__ . '/../config/config.php';
$user = require_login();
$isEmp = $user['role'] === 'employee';
$w = ['1=1']; $p = [];
if ($isEmp) { $w[] = 't.assigned_to = ?'; $p[] = $user['id']; }
$empF = (int)($_GET['emp'] ?? 0); if ($empF && !$isEmp) { $w[] = 't.assigned_to = ?'; $p[] = $empF; }
$st = db()->prepare(TASK_SELECT . ' WHERE ' . implode(' AND ', $w) . " ORDER BY FIELD(t.priority,'Urgent','High','Medium','Low'), t.deadline LIMIT 300"); $st->execute($p);
$cols = array_fill_keys(STATUSES, []);
foreach ($st as $t) $cols[$t['status']][] = $t;
$emps = db()->query("SELECT id, full_name FROM users WHERE role='employee' ORDER BY full_name")->fetchAll();
$pageTitle = $isEmp ? 'My Board' : 'Task Board'; include __DIR__ . '/../includes/header.php';
?>
<div class="row between wrap" style="margin-bottom:1rem">
  <span class="muted"><?= $user['role'] === 'admin' ? 'Read-only view.' : ($isEmp ? 'Drag a task to the next column to update its status.' : 'Drag tasks between columns to change their status.') ?></span>
  <?php if (!$isEmp): ?><form method="get"><select name="emp" data-autosubmit><option value="">All employees</option><?php foreach ($emps as $em): ?><option value="<?= $em['id'] ?>" <?= $empF == $em['id'] ? 'selected' : '' ?>><?= e($em['full_name']) ?></option><?php endforeach; ?></select></form><?php endif; ?></div>
<div class="kboard" id="board" data-move="<?= e(url('tasks/move.php')) ?>">
<?php foreach ($cols as $status => $items): ?>
  <section class="kcol" data-status="<?= e($status) ?>"><div class="khead"><span><?= e($status) ?></span><span class="kcount"><?= count($items) ?></span></div><div class="kbody">
  <?php foreach ($items as $t): $allowed = allowed_statuses($user, $t); ?>
    <article class="kcard <?= effective_status($t) === 'Overdue' ? 'late' : '' ?>" data-id="<?= $t['id'] ?>" data-status="<?= e($t['status']) ?>" data-allowed="<?= e(implode('|', $allowed)) ?>" <?= $allowed ? 'draggable="true"' : '' ?>>
      <div class="row between"><?= status_badge($t) ?><?= badge($t['priority'], $t['priority']) ?></div>
      <h4><a href="<?= e(url('tasks/view.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a></h4>
      <div class="muted small"><span class="code"><?= e(task_code($t['id'])) ?></span> <?= e($t['project_name'] ?? '') ?></div>
      <?php if ($t['ck_total']): ?><div style="margin-top:.4rem"><?= progress_bar((int)round($t['ck_done'] * 100 / $t['ck_total'])) ?><span class="small muted"><?= $t['ck_done'] ?>/<?= $t['ck_total'] ?></span></div><?php endif; ?>
      <div class="kfoot"><span class="small muted"><?= icon('clock', 13) ?> <?= e(fmt_date($t['deadline'])) ?><?= due_label($t) ? ' &middot; ' . e(due_label($t)) : '' ?></span><?= $isEmp ? '' : avatar($t['employee_name']) ?></div>
    </article>
  <?php endforeach; ?></div></section>
<?php endforeach; ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
