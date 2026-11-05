<?php
require_once __DIR__ . '/../config/config.php';
$user = require_login();
$id = (int)($_GET['id'] ?? 0);
$t = find_task($id, $user);
if (!$t) { http_response_code(404); exit('Task not found or not assigned to you. <a href="' . e(url(home_for($user['role']))) . '">Back</a>'); }
$isMgr = $user['role'] === 'manager'; $isOwner = $user['role'] === 'employee';
$canWork = $isMgr || $isOwner;                      // may update checklist, comment, upload

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'status' && in_array($_POST['status'] ?? '', allowed_statuses($user, $t), true)) {
        change_task_status($t, $_POST['status'], $user); flash('Task moved to ' . $_POST['status'] . '.');
    } elseif ($a === 'comment' && $canWork && trim($_POST['comment'] ?? '') !== '') {
        db()->prepare('INSERT INTO task_comments (task_id,user_id,comment) VALUES (?,?,?)')->execute([$id, $user['id'], trim($_POST['comment'])]);
        notify($isOwner ? (int)$t['created_by'] : (int)$t['assigned_to'], "{$user['full_name']} commented on \"{$t['title']}\".", $id, 'comment');
        flash('Comment added.');
    } elseif ($a === 'ck_add' && $canWork && trim($_POST['label'] ?? '') !== '') {
        db()->prepare('INSERT INTO task_checklist (task_id,label) VALUES (?,?)')->execute([$id, cut(trim($_POST['label']), 0, 200)]);
    } elseif ($a === 'ck_toggle' && $canWork) {
        db()->prepare('UPDATE task_checklist SET is_done = 1 - is_done WHERE id = ? AND task_id = ?')->execute([(int)($_POST['item'] ?? 0), $id]);
    } elseif ($a === 'ck_del' && $canWork) {
        db()->prepare('DELETE FROM task_checklist WHERE id = ? AND task_id = ?')->execute([(int)($_POST['item'] ?? 0), $id]);
    } elseif ($a === 'delete' && $isMgr) {
        $files = db()->prepare('SELECT stored_name FROM task_attachments WHERE task_id = ?'); $files->execute([$id]);
        foreach ($files as $fl) @unlink(UPLOAD_DIR . basename($fl['stored_name']));
        db()->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]); log_activity('task_delete', $t['title']);
        flash('Task deleted.'); redirect('tasks/index.php');
    }
    redirect('tasks/view.php?id=' . $id);
}
$q = fn($sql) => (function ($sql) use ($id) { $s = db()->prepare($sql); $s->execute([$id]); return $s->fetchAll(); })($sql);
$ck = $q('SELECT * FROM task_checklist WHERE task_id = ? ORDER BY id');
$files = $q('SELECT a.*, u.full_name FROM task_attachments a JOIN users u ON u.id = a.user_id WHERE a.task_id = ? ORDER BY a.id DESC');
$comments = $q('SELECT c.*, u.full_name FROM task_comments c JOIN users u ON u.id = c.user_id WHERE c.task_id = ? ORDER BY c.id');
$hist = $q('SELECT h.*, u.full_name FROM task_history h JOIN users u ON u.id = h.user_id WHERE h.task_id = ? ORDER BY h.id DESC');
$allowed = allowed_statuses($user, $t);
$pct = $t['ck_total'] ? (int)round($t['ck_done'] * 100 / $t['ck_total']) : null;
$pageTitle = task_code($id); include __DIR__ . '/../includes/header.php';
?>
<div class="card">
<div class="row between wrap"><h2><?= e($t['title']) ?></h2><div><?= badge($t['priority'], $t['priority']) ?> <?= status_badge($t) ?></div></div>
<p><?= nl2br(e($t['description'] ?: 'No description.')) ?></p>
<dl class="meta"><div><dt>Assigned to</dt><dd><span class="who"><?= avatar($t['employee_name']) ?><?= e($t['employee_name']) ?></span></dd></div><div><dt>Created by</dt><dd><?= e($t['creator_name']) ?></dd></div>
<div><dt>Project</dt><dd><?= e($t['project_name'] ?? '-') ?></dd></div><div><dt>Start date</dt><dd><?= e(fmt_date($t['start_date'])) ?></dd></div>
<div><dt>Deadline</dt><dd><?= e(fmt_date($t['deadline'])) ?> <span class="muted small"><?= e(due_label($t)) ?></span></dd></div><div><dt>Completed</dt><dd><?= e(fmt_dt($t['completed_at'])) ?></dd></div></dl>
<div class="row wrap">
<?php foreach ($allowed as $s): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($s) ?>"><button class="btn <?= $isOwner ? 'primary' : '' ?>"><?= $isOwner ? ($s === 'In Progress' ? 'Start Task' : 'Mark as Completed') : 'Move to ' . e($s) ?></button></form><?php endforeach; ?>
<?php if ($isMgr): ?><a class="btn" href="<?= e(url('manager/task_form.php?id=' . $id)) ?>"><?= icon('edit', 16) ?> Edit / Reassign</a>
<form method="post" class="inline" data-confirm="Delete this task permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn danger"><?= icon('trash', 16) ?> Delete</button></form><?php endif; ?>
<a class="btn" href="<?= e(url('tasks/index.php')) ?>">Back to list</a></div></div>

<div class="two">
<div>
 <div class="card"><div class="row between"><h3>Checklist</h3><?php if ($pct !== null): ?><span class="muted small"><?= $t['ck_done'] ?>/<?= $t['ck_total'] ?> done</span><?php endif; ?></div>
 <?php if ($pct !== null) echo progress_bar($pct, 'wide'); ?>
 <?php foreach ($ck as $i): ?><div class="ck <?= $i['is_done'] ? 'done' : '' ?>">
   <?php if ($canWork): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ck_toggle"><input type="hidden" name="item" value="<?= $i['id'] ?>"><input type="checkbox" <?= $i['is_done'] ? 'checked' : '' ?> data-autosubmit aria-label="Done"></form>
   <?php else: ?><?= $i['is_done'] ? icon('check', 16) : '' ?><?php endif; ?>
   <span><?= e($i['label']) ?></span>
   <?php if ($canWork): ?><form method="post" data-confirm="Remove this item?"><?= csrf_field() ?><input type="hidden" name="action" value="ck_del"><input type="hidden" name="item" value="<?= $i['id'] ?>"><button class="x" aria-label="Remove"><?= icon('trash', 15) ?></button></form><?php endif; ?></div><?php endforeach; ?>
 <?php if (!$ck): ?><p class="muted small">No checklist items yet.</p><?php endif; ?>
 <?php if ($canWork): ?><form method="post" class="row" style="margin-top:.7rem"><?= csrf_field() ?><input type="hidden" name="action" value="ck_add"><input name="label" placeholder="Add a checklist item" maxlength="200" required style="flex:1;width:100%"><button class="btn sm">Add</button></form><?php endif; ?></div>

 <div class="card"><h3>Attachments</h3>
 <?php foreach ($files as $f): ?><div class="file"><?= icon('paperclip', 18) ?><div><b><?= e($f['original_name']) ?></b><span class="muted small"><?= e(fmt_size((int)$f['file_size'])) ?> &middot; <?= e($f['full_name']) ?> &middot; <?= e(time_ago($f['created_at'])) ?></span></div>
   <a class="btn sm" href="<?= e(url('tasks/attachment.php?id=' . $f['id'])) ?>"><?= icon('download', 14) ?></a>
   <?php if ($isMgr || (int)$f['user_id'] === (int)$user['id']): ?><form method="post" action="<?= e(url('tasks/attachment.php')) ?>" data-confirm="Delete this file?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn sm danger"><?= icon('trash', 14) ?></button></form><?php endif; ?></div><?php endforeach; ?>
 <?php if (!$files): ?><p class="muted small">No files attached.</p><?php endif; ?>
 <?php if ($canWork): ?><form method="post" action="<?= e(url('tasks/attachment.php')) ?>" enctype="multipart/form-data" class="row wrap" style="margin-top:.7rem"><?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="task_id" value="<?= $id ?>"><input type="file" name="file" required style="flex:1"><button class="btn sm">Upload</button></form><p class="muted small">Max <?= e(fmt_size(MAX_UPLOAD_BYTES)) ?>. Allowed: <?= e(implode(', ', ALLOWED_EXT)) ?></p><?php endif; ?></div>
</div>
<div>
 <div class="card"><h3>Comments &amp; progress updates</h3>
 <?php foreach ($comments as $m): ?><div class="cmt"><?= avatar($m['full_name']) ?><div><b><?= e($m['full_name']) ?></b> <span class="muted small"><?= e(time_ago($m['created_at'])) ?></span><p><?= nl2br(e($m['comment'])) ?></p></div></div><?php endforeach; ?>
 <?php if (!$comments): ?><p class="muted small">No comments yet.</p><?php endif; ?>
 <?php if ($canWork): ?><form method="post" style="margin-top:.8rem"><?= csrf_field() ?><input type="hidden" name="action" value="comment"><textarea name="comment" rows="3" placeholder="Write a comment or progress update" required></textarea><br><button class="btn primary sm">Post comment</button></form><?php endif; ?></div>
 <div class="card"><h3>Status history</h3><ul class="tl"><?php foreach ($hist as $x): ?><li><b><?= e($x['old_status']) ?> &rarr; <?= e($x['new_status']) ?></b><div class="muted small"><?= e($x['full_name']) ?> &middot; <?= e(fmt_dt($x['created_at'])) ?></div></li><?php endforeach; ?>
 <li><b>Task created</b><div class="muted small"><?= e($t['creator_name']) ?> &middot; <?= e(fmt_dt($t['created_at'])) ?></div></li></ul></div>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
