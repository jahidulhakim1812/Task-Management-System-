<?php
require_once __DIR__ . '/../config/config.php';
$user = require_login();
$isEmp = $user['role'] === 'employee';
$f = ['q' => trim($_GET['q'] ?? ''), 'status' => $_GET['status'] ?? '', 'priority' => $_GET['priority'] ?? '', 'emp' => (int)($_GET['emp'] ?? 0), 'project' => (int)($_GET['project'] ?? 0), 'sort' => $_GET['sort'] ?? 'deadline'];
$w = ['1=1']; $p = [];
if ($isEmp) { $w[] = 't.assigned_to = ?'; $p[] = $user['id']; }
if ($f['q'] !== '') { $w[] = '(t.title LIKE ? OR t.description LIKE ?)'; $p[] = '%' . $f['q'] . '%'; $p[] = '%' . $f['q'] . '%'; }
if ($f['status'] === 'Overdue') { $w[] = "t.status <> 'Completed' AND t.deadline < ?"; $p[] = date('Y-m-d'); }
elseif ($f['status'] === 'Due Soon') { $w[] = "t.status <> 'Completed' AND t.deadline BETWEEN ? AND ?"; $p[] = date('Y-m-d'); $p[] = date('Y-m-d', strtotime('+7 days')); }
elseif (in_array($f['status'], STATUSES, true)) { $w[] = 't.status = ?'; $p[] = $f['status']; }
if (in_array($f['priority'], PRIORITIES, true)) { $w[] = 't.priority = ?'; $p[] = $f['priority']; }
if ($f['emp'] && !$isEmp) { $w[] = 't.assigned_to = ?'; $p[] = $f['emp']; }
if ($f['project']) { $w[] = 't.project_id = ?'; $p[] = $f['project']; }
$sorts = ['deadline' => 't.deadline ASC, t.id', 'newest' => 't.id DESC', 'priority' => "FIELD(t.priority,'Urgent','High','Medium','Low'), t.deadline", 'title' => 't.title ASC'];
$order = $sorts[$f['sort']] ?? $sorts['deadline'];
$where = implode(' AND ', $w);

if (($_GET['export'] ?? '') === 'csv' && !$isEmp) {
    $st = db()->prepare(TASK_SELECT . " WHERE $where ORDER BY $order"); $st->execute($p);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="tasks_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Code', 'Title', 'Project', 'Assigned to', 'Priority', 'Start', 'Deadline', 'Status', 'Completed on']);
    foreach ($st as $r) fputcsv($o, [task_code($r['id']), $r['title'], $r['project_name'], $r['employee_name'], $r['priority'], $r['start_date'], $r['deadline'], effective_status($r), $r['completed_at']]);
    exit;
}
$page = page_num();
$c = db()->prepare("SELECT COUNT(*) FROM tasks t WHERE $where"); $c->execute($p); $total = (int)$c->fetchColumn();
$st = db()->prepare(TASK_SELECT . " WHERE $where ORDER BY $order LIMIT " . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE)); $st->execute($p);
$rows = $st->fetchAll();
$emps = db()->query("SELECT id, full_name FROM users WHERE role='employee' ORDER BY full_name")->fetchAll();
$projects = db()->query('SELECT id, name FROM projects ORDER BY name')->fetchAll();
$pageTitle = $isEmp ? 'My Tasks' : ($user['role'] === 'admin' ? 'All Tasks' : 'Tasks'); include __DIR__ . '/../includes/header.php';
?>
<div class="card">
<div class="row between wrap"><form class="filters" method="get">
  <input name="q" placeholder="Search title or description" value="<?= e($f['q']) ?>">
  <select name="status" data-autosubmit><option value="">All status</option><?php foreach (array_merge(STATUSES, ['Overdue', 'Due Soon']) as $s): ?><option <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
  <select name="priority" data-autosubmit><option value="">All priority</option><?php foreach (PRIORITIES as $s): ?><option <?= $f['priority'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
  <?php if (!$isEmp): ?><select name="emp" data-autosubmit><option value="">All employees</option><?php foreach ($emps as $em): ?><option value="<?= $em['id'] ?>" <?= $f['emp'] == $em['id'] ? 'selected' : '' ?>><?= e($em['full_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
  <select name="project" data-autosubmit><option value="">All projects</option><?php foreach ($projects as $pr): ?><option value="<?= $pr['id'] ?>" <?= $f['project'] == $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?></select>
  <select name="sort" data-autosubmit><?php foreach (['deadline' => 'Sort: Deadline', 'priority' => 'Sort: Priority', 'newest' => 'Sort: Newest', 'title' => 'Sort: Title'] as $k => $v): ?><option value="<?= $k ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
  <button class="btn">Search</button><a class="btn" href="<?= e(url('tasks/index.php')) ?>">Reset</a></form>
<div class="row"><?php if (!$isEmp): ?><a class="btn" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><?= icon('download', 16) ?> CSV</a><?php endif; ?>
<?php if (in_array($user['role'], ['admin', 'manager'], true)): ?><a class="btn primary" href="<?= e(url('manager/task_form.php')) ?>"><?= icon('plus', 16) ?> Create Task</a><?php endif; ?></div></div>
<?= task_table($rows, !$isEmp) ?><?= pager($total, $page) ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
