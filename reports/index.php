<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['admin', 'manager']);
$types = ['overall' => 'Overall task progress', 'employee' => 'Employee-wise task report', 'project' => 'Project / task-wise report',
          'completed' => 'Completed task report', 'pending' => 'Pending task report', 'overdue' => 'Overdue task report'];
$type = isset($types[$_GET['type'] ?? '']) ? $_GET['type'] : 'overall';
$today = date('Y-m-d');
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : ''; $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$empF = (int)($_GET['emp'] ?? 0); $projF = (int)($_GET['project'] ?? 0);
$tf = ''; $tp = [];                                  // extra task filters (deadline range, employee, project)
if ($from) { $tf .= ' AND t.deadline >= ?'; $tp[] = $from; } if ($to) { $tf .= ' AND t.deadline <= ?'; $tp[] = $to; }
if ($empF) { $tf .= ' AND t.assigned_to = ?'; $tp[] = $empF; } if ($projF) { $tf .= ' AND t.project_id = ?'; $tp[] = $projF; }
$run = function (string $sql) use ($tp) { $s = db()->prepare($sql); $s->execute($tp); return $s->fetchAll(); };
$sumCols = "COUNT(t.id) total, COALESCE(SUM(t.status='Pending' AND t.deadline >= '$today'),0) pending, COALESCE(SUM(t.status='In Progress' AND t.deadline >= '$today'),0) in_progress, COALESCE(SUM(t.status='Completed'),0) completed, COALESCE(SUM(t.status<>'Completed' AND t.deadline < '$today'),0) overdue";
$pct = fn($r) => ($r['total'] ? round($r['completed'] * 100 / $r['total']) : 0) . '%';
$sum = ['Total', 'Pending', 'In Progress', 'Completed', 'Overdue', 'Completion'];
$line = fn($lab, $r) => [$r[$lab], $r['total'], $r['pending'], $r['in_progress'], $r['completed'], $r['overdue'], $pct($r)];
$stats = task_stats('1=1' . str_replace('t.', '', $tf), $tp);
if ($type === 'overall') {
    $head = ['Priority', ...$sum];
    $rows = array_map(fn($r) => $line('priority', $r), $run("SELECT t.priority, $sumCols FROM tasks t WHERE 1=1 $tf GROUP BY t.priority ORDER BY FIELD(t.priority,'Urgent','High','Medium','Low')"));
    $all = $run("SELECT $sumCols FROM tasks t WHERE 1=1 $tf")[0]; $all['label'] = 'ALL TASKS'; $rows[] = $line('label', $all);
} elseif ($type === 'employee') {
    $head = ['Employee', ...$sum];
    $rows = array_map(fn($r) => $line('full_name', $r), $run("SELECT u.full_name, $sumCols FROM users u LEFT JOIN tasks t ON t.assigned_to = u.id $tf WHERE u.role='employee' GROUP BY u.id ORDER BY u.full_name"));
} elseif ($type === 'project') {
    $head = ['Project', ...$sum];
    $rows = array_map(fn($r) => $line('name', $r), $run("SELECT COALESCE(p.name,'(No project)') name, $sumCols FROM tasks t LEFT JOIN projects p ON p.id = t.project_id WHERE 1=1 $tf GROUP BY p.id ORDER BY name"));
} else {
    $cond = ['completed' => "t.status = 'Completed'", 'pending' => "t.status <> 'Completed' AND t.deadline >= '$today'", 'overdue' => "t.status <> 'Completed' AND t.deadline < '$today'"][$type];
    $head = ['Code', 'Task', 'Employee', 'Project', 'Priority', 'Deadline', 'Status', 'Completed on'];
    $rows = array_map(fn($r) => [task_code($r['id']), $r['title'], $r['employee_name'], $r['project_name'] ?? '-', $r['priority'], fmt_date($r['deadline']), effective_status($r), fmt_date($r['completed_at'])],
        $run(TASK_SELECT . " WHERE $cond $tf ORDER BY t.deadline"));
}
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="tms_' . $type . '_report_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, $head); foreach ($rows as $r) fputcsv($o, $r); exit;
}
log_activity('report_view', $types[$type]);
$emps = db()->query("SELECT id, full_name FROM users WHERE role='employee' ORDER BY full_name")->fetchAll();
$projects = db()->query('SELECT id, name FROM projects ORDER BY name')->fetchAll();
$pageTitle = 'Reports'; include __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="row between wrap"><form class="filters" method="get">
  <select name="type" data-autosubmit><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <label class="check small">From <input type="date" name="from" value="<?= e($from) ?>"></label><label class="check small">To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <select name="emp"><option value="">All employees</option><?php foreach ($emps as $em): ?><option value="<?= $em['id'] ?>" <?= $empF == $em['id'] ? 'selected' : '' ?>><?= e($em['full_name']) ?></option><?php endforeach; ?></select>
  <select name="project"><option value="">All projects</option><?php foreach ($projects as $pr): ?><option value="<?= $pr['id'] ?>" <?= $projF == $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?></select>
  <button class="btn">Apply</button></form>
<div class="row"><a class="btn" href="?<?= e(http_build_query(array_merge($_GET, ['type' => $type, 'export' => 'csv']))) ?>"><?= icon('download', 16) ?> CSV</a><button class="btn" data-print>Print</button></div></div>
<h3><?= e($types[$type]) ?></h3>
<p class="muted small"><?= e(company_name()) ?> &middot; generated <?= e(date('d M Y, H:i')) ?> by <?= e($user['full_name']) ?><?= $from || $to ? ' &middot; deadlines ' . e($from ?: 'start') . ' to ' . e($to ?: 'today+') : '' ?></p>
<?php if ($type === 'overall'): ?><div class="two"><div><?= status_donut($stats) ?></div><div><?= priority_bars('1=1' . str_replace('t.', '', $tf), $tp) ?></div></div><br><?php endif; ?>
<div class="table-wrap"><table><thead><tr><?php foreach ($head as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="<?= count($head) ?>" class="muted">No data for this report.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
