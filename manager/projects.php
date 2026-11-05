<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['manager']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? ''; $name = trim($_POST['name'] ?? ''); $pid = (int)($_POST['id'] ?? 0);
    if ($a === 'delete' && $pid) {   // tasks are kept; their project link is cleared (ON DELETE SET NULL)
        db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$pid]); log_activity('project_delete', "Project #$pid"); flash('Project deleted. Its tasks were kept.');
    } elseif ($name === '') flash('Project name is required.', 'danger');
    elseif ($a === 'update' && $pid) { db()->prepare('UPDATE projects SET name=?, description=? WHERE id=?')->execute([$name, trim($_POST['description'] ?? ''), $pid]); flash('Project updated.'); }
    elseif ($a === 'create') { db()->prepare('INSERT INTO projects (name,description,created_by) VALUES (?,?,?)')->execute([$name, trim($_POST['description'] ?? ''), $user['id']]); log_activity('project_create', $name); flash('Project created.'); }
    redirect('manager/projects.php');
}
$edit = null;
if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM projects WHERE id = ?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; }
$rows = db()->query("SELECT p.*, COUNT(t.id) tasks, COALESCE(SUM(t.status='Completed'),0) done FROM projects p LEFT JOIN tasks t ON t.project_id = p.id GROUP BY p.id ORDER BY p.name")->fetchAll();
$pageTitle = 'Projects'; include __DIR__ . '/../includes/header.php';
?>
<div class="card narrow"><h3><?= $edit ? 'Edit project' : 'New project / work' ?></h3><form method="post"><?= csrf_field() ?>
  <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <label>Name<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
  <label>Description<textarea name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></label>
  <div class="row"><button class="btn primary">Save</button><?php if ($edit): ?><a class="btn" href="<?= e(url('manager/projects.php')) ?>">Cancel</a><?php endif; ?></div></form></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Project</th><th>Tasks</th><th>Completed</th><th>Progress</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><strong><?= e($r['name']) ?></strong><div class="muted small"><?= e($r['description']) ?></div></td><td><a href="<?= e(url('tasks/index.php?project=' . $r['id'])) ?>"><?= $r['tasks'] ?></a></td><td><?= $r['done'] ?></td><td><?= progress_bar($r['tasks'] ? (int)round($r['done'] * 100 / $r['tasks']) : 0) ?></td>
<td class="actions"><a class="btn sm" href="?edit=<?= $r['id'] ?>">Edit</a>
<form method="post" class="inline" data-confirm="Delete this project? Its tasks will be kept."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm danger">Delete</button></form></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">No projects yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
