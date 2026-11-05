<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    $st = db()->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$id]); $t = $st->fetch();
    if (!$t) flash('User not found.', 'danger');
    elseif ($act === 'unlock') {
        db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$id]); log_activity('user_unlock', $t['full_name']); flash($t['full_name'] . ' unlocked.');
    } elseif ($id === $user['id']) flash('You cannot deactivate or delete your own account.', 'danger');
    elseif ($act === 'toggle') {
        db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        log_activity($t['is_active'] ? 'user_deactivate' : 'user_activate', $t['full_name']);
        flash($t['full_name'] . ($t['is_active'] ? ' deactivated.' : ' activated.'));
    } elseif ($act === 'delete') {
        try {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            log_activity('user_delete', $t['full_name'] . ' (' . $t['email'] . ')'); flash('User deleted.');
        } catch (PDOException $ex) { flash('This user has tasks or history linked to them and cannot be deleted. Deactivate the account instead.', 'danger'); }
    }
    redirect('admin/users.php');
}
$q = trim($_GET['q'] ?? ''); $role = $_GET['role'] ?? '';
$where = ['1=1']; $p = [];
if ($q !== '') { $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
if (isset(ROLES[$role])) { $where[] = 'u.role = ?'; $p[] = $role; }
$w = implode(' AND ', $where); $page = page_num();
$c = db()->prepare("SELECT COUNT(*) FROM users u WHERE $w"); $c->execute($p); $total = (int)$c->fetchColumn();
$st = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status <> 'Completed') open_tasks FROM users u WHERE $w ORDER BY FIELD(u.role,'admin','manager','employee'), u.full_name LIMIT " . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$st->execute($p); $rows = $st->fetchAll();
$pageTitle = 'User Management'; include __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="row between wrap">
  <form class="filters" method="get"><input name="q" placeholder="Search name or email" value="<?= e($q) ?>">
    <select name="role" data-autosubmit><option value="">All roles</option><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $role === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    <button class="btn">Search</button></form>
  <a class="btn primary" href="<?= e(url('admin/user_form.php')) ?>"><?= icon('plus', 16) ?> Add User</a></div>
<div class="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Open tasks</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): $locked = $r['locked_until'] && strtotime($r['locked_until']) > time(); ?><tr>
  <td><span class="who"><?= avatar($r['full_name']) ?><span><b><?= e($r['full_name']) ?></b><br><span class="muted small"><?= e($r['email']) ?><?= $r['job_title'] ? ' &middot; ' . e($r['job_title']) : '' ?></span></span></span></td>
  <td><?= badge(ROLES[$r['role']], 'role') ?></td><td><?= $r['role'] === 'employee' ? (int)$r['open_tasks'] : '-' ?></td>
  <td><?= $r['is_active'] ? badge('Active', 'completed') : badge('Inactive', 'overdue') ?> <?= $locked ? badge('Locked', 'urgent') : '' ?></td>
  <td><?= $r['last_login'] ? e(time_ago($r['last_login'])) : '-' ?></td>
  <td class="actions"><a class="btn sm" href="<?= e(url('admin/user_form.php?id=' . $r['id'])) ?>">Edit</a>
  <?php if ($locked): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="unlock"><button class="btn sm">Unlock</button></form><?php endif; ?>
  <?php if ($r['id'] != $user['id']): ?>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn sm"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
    <form method="post" class="inline" data-confirm="Delete <?= e($r['full_name']) ?> permanently?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn sm danger">Delete</button></form>
  <?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?= pager($total, $page) ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
