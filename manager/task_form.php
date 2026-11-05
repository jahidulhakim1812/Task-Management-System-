<?php
require_once __DIR__ . '/../config/config.php';
$user = require_login();
$id = (int)($_GET['id'] ?? 0);
if (!in_array($user['role'], ['admin', 'manager'], true) || ($user['role'] === 'admin' && $id)) {
    http_response_code(403);
    exit('<h2>403 - Access denied</h2><p>You do not have permission to view this page. <a href="' . e(url(home_for($user['role']))) . '">Back to dashboard</a></p>');
}
$t = ['title' => '', 'description' => '', 'project_id' => '', 'assigned_to' => (int)($_GET['emp'] ?? 0) ?: '', 'priority' => 'Medium', 'status' => 'Pending', 'start_date' => date('Y-m-d'), 'deadline' => ''];
if ($id) { $t = find_task($id, $user) ?: redirect('tasks/index.php'); }
$emps = db()->query("SELECT id, full_name FROM users WHERE role='employee' AND is_active=1 ORDER BY full_name")->fetchAll();
$projects = db()->query('SELECT id, name FROM projects ORDER BY name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = ['title' => trim($_POST['title'] ?? ''), 'description' => trim($_POST['description'] ?? ''), 'project_id' => (int)($_POST['project_id'] ?? 0) ?: null,
            'assigned_to' => (int)($_POST['assigned_to'] ?? 0), 'priority' => $_POST['priority'] ?? '', 'status' => $id ? ($_POST['status'] ?? '') : 'Pending',
            'start_date' => $_POST['start_date'] ?? '', 'deadline' => $_POST['deadline'] ?? ''];
    $ok = db()->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'employee' AND is_active = 1"); $ok->execute([$new['assigned_to']]);
    $err = null;
    if ($new['title'] === '') $err = 'Task title is required.';
    elseif (!$ok->fetchColumn() && !($id && $new['assigned_to'] == $t['assigned_to'])) $err = 'Please assign the task to an active employee.';
    elseif (!in_array($new['priority'], PRIORITIES, true) || !in_array($new['status'], STATUSES, true)) $err = 'Invalid priority or status.';
    elseif (!strtotime($new['start_date']) || !strtotime($new['deadline'])) $err = 'Start date and deadline are required.';
    elseif ($new['deadline'] < $new['start_date']) $err = 'Deadline cannot be before the start date.';
    if ($err) { flash($err, 'danger'); $t = array_merge($t, $new); }
    else {
        $cols = [$new['title'], $new['description'], $new['project_id'], $new['assigned_to'], $new['priority'], $new['start_date'], $new['deadline']];
        if ($id) {
            db()->prepare('UPDATE tasks SET title=?, description=?, project_id=?, assigned_to=?, priority=?, start_date=?, deadline=? WHERE id=?')->execute([...$cols, $id]);
            change_task_status($t, $new['status'], $user);
            if ($new['assigned_to'] != $t['assigned_to']) notify($new['assigned_to'], "A task was reassigned to you: \"{$new['title']}\" (deadline {$new['deadline']}).", $id, 'assigned');
            log_activity('task_update', $new['title']); flash('Task updated.');
        } else {
            db()->prepare('INSERT INTO tasks (title,description,project_id,assigned_to,priority,start_date,deadline,created_by) VALUES (?,?,?,?,?,?,?,?)')->execute([...$cols, $user['id']]);
            $id = (int)db()->lastInsertId();
            foreach (array_filter(array_map('trim', explode("\n", $_POST['checklist'] ?? ''))) as $line)
                db()->prepare('INSERT INTO task_checklist (task_id,label) VALUES (?,?)')->execute([$id, cut($line, 0, 200)]);
            notify($new['assigned_to'], "New task assigned: \"{$new['title']}\" - {$new['priority']} priority, deadline {$new['deadline']}.", $id, 'assigned');
            log_activity('task_create', $new['title']); flash('Task created and assigned.');
        }
        redirect('tasks/view.php?id=' . $id);
    }
}
$pageTitle = $id ? 'Edit Task ' . task_code($id) : 'Create Task'; include __DIR__ . '/../includes/header.php';
?>
<div class="card narrow"><form method="post"><?= csrf_field() ?>
  <label>Task title<input name="title" required maxlength="200" value="<?= e($t['title']) ?>" placeholder="e.g. Prepare Monthly Project Report"></label>
  <label>Description<textarea name="description" rows="4"><?= e($t['description']) ?></textarea></label>
  <div class="cols"><label>Assign to<select name="assigned_to" required><option value="">- Select employee -</option><?php foreach ($emps as $em): ?><option value="<?= $em['id'] ?>" <?= $t['assigned_to'] == $em['id'] ? 'selected' : '' ?>><?= e($em['full_name']) ?></option><?php endforeach; ?></select></label>
  <label>Project (optional)<select name="project_id"><option value="">- None -</option><?php foreach ($projects as $pr): ?><option value="<?= $pr['id'] ?>" <?= $t['project_id'] == $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?></select></label></div>
  <div class="cols"><label>Priority<select name="priority"><?php foreach (PRIORITIES as $s): ?><option <?= $t['priority'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></label>
  <?php if ($id): ?><label>Status<select name="status"><?php foreach (STATUSES as $s): ?><option <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></label><?php endif; ?></div>
  <div class="cols"><label>Start date<input type="date" name="start_date" required value="<?= e($t['start_date']) ?>"></label>
  <label>Deadline<input type="date" name="deadline" required value="<?= e($t['deadline']) ?>"></label></div>
  <?php if (!$id): ?><label>Checklist (optional, one item per line)<textarea name="checklist" rows="3" placeholder="Collect data&#10;Write summary&#10;Send to manager"></textarea></label><?php endif; ?>
  <div class="row"><button class="btn primary"><?= $id ? 'Update Task' : 'Create Task' ?></button><a class="btn" href="<?= e(url($id ? 'tasks/view.php?id=' . $id : 'tasks/index.php')) ?>">Cancel</a></div>
</form></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
