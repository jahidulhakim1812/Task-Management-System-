<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['admin']);
$act = trim($_GET['action'] ?? ''); $uid = (int)($_GET['user'] ?? 0);
$w = ['1=1']; $p = [];
if ($act !== '') { $w[] = 'l.action = ?'; $p[] = $act; }
if ($uid) { $w[] = 'l.user_id = ?'; $p[] = $uid; }
$where = implode(' AND ', $w);
if (($_GET['export'] ?? '') === 'csv') {
    $st = db()->prepare("SELECT l.created_at, u.full_name, l.action, l.details, l.ip_address FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $where ORDER BY l.id DESC"); $st->execute($p);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="activity_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Time', 'User', 'Action', 'Details', 'IP']); foreach ($st as $r) fputcsv($o, $r); exit;
}
$page = page_num(); $c = db()->prepare("SELECT COUNT(*) FROM activity_logs l WHERE $where"); $c->execute($p); $total = (int)$c->fetchColumn();
$st = db()->prepare("SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $where ORDER BY l.id DESC LIMIT " . (PER_PAGE * 2) . ' OFFSET ' . (($page - 1) * PER_PAGE * 2)); $st->execute($p); $rows = $st->fetchAll();
$actions = db()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$users = db()->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();
$pageTitle = 'System Activity'; include __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="row between wrap"><form class="filters" method="get">
  <select name="action" data-autosubmit><option value="">All actions</option><?php foreach ($actions as $a): ?><option <?= $act === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select>
  <select name="user" data-autosubmit><option value="">All users</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $uid == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></form>
  <a class="btn" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><?= icon('download', 16) ?> CSV</a></div>
<div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $l): ?><tr><td><?= e(fmt_dt($l['created_at'])) ?></td><td><?= e($l['full_name'] ?? 'System') ?></td><td><?= badge($l['action'], 'role') ?></td><td><?= e($l['details']) ?></td><td class="muted small"><?= e($l['ip_address']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?= pager($total, $page, PER_PAGE * 2) ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
