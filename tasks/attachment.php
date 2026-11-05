<?php
require_once __DIR__ . '/../config/config.php';
$user = require_login();
function back(int $id) { redirect('tasks/view.php?id=' . $id); }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {            // download
    $st = db()->prepare('SELECT * FROM task_attachments WHERE id = ?'); $st->execute([(int)($_GET['id'] ?? 0)]); $a = $st->fetch();
    $path = $a ? UPLOAD_DIR . basename($a['stored_name']) : '';
    if (!$a || !find_task((int)$a['task_id'], $user) || !is_file($path)) { http_response_code(404); exit('File not found.'); }
    header('Content-Type: application/octet-stream'); header('Content-Length: ' . filesize($path));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($a['original_name']));
    readfile($path); exit;
}
$action = $_POST['action'] ?? '';
if ($action === 'upload') {
    $t = find_task((int)($_POST['task_id'] ?? 0), $user);
    if (!$t || !in_array($user['role'], ['manager', 'employee'], true)) { http_response_code(403); exit('Not allowed.'); }
    $f = $_FILES['file'] ?? null; $ext = $f ? strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) : '';
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) flash('Choose a file first.', 'danger');
    elseif ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_UPLOAD_BYTES) flash('Upload failed. Maximum file size is ' . fmt_size(MAX_UPLOAD_BYTES) . '.', 'danger');
    elseif (!in_array($ext, ALLOWED_EXT, true)) flash('This file type is not allowed. Allowed: ' . implode(', ', ALLOWED_EXT) . '.', 'danger');
    else {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $stored)) {
            db()->prepare('INSERT INTO task_attachments (task_id,user_id,original_name,stored_name,file_size) VALUES (?,?,?,?,?)')->execute([$t['id'], $user['id'], cut(basename($f['name']), 0, 250), $stored, $f['size']]);
            log_activity('file_upload', $t['title'] . ': ' . basename($f['name']));
            $to = $user['role'] === 'employee' ? (int)$t['created_by'] : (int)$t['assigned_to'];
            notify($to, "{$user['full_name']} attached a file to \"{$t['title']}\".", (int)$t['id'], 'comment');
            flash('File uploaded.');
        } else flash('Could not save the file. Check that the uploads folder is writable.', 'danger');
    }
    back((int)($_POST['task_id'] ?? 0));
}
if ($action === 'delete') {
    $st = db()->prepare('SELECT * FROM task_attachments WHERE id = ?'); $st->execute([(int)($_POST['id'] ?? 0)]); $a = $st->fetch();
    if (!$a || !find_task((int)$a['task_id'], $user) || !($user['role'] === 'manager' || (int)$a['user_id'] === (int)$user['id'])) { http_response_code(403); exit('Not allowed.'); }
    db()->prepare('DELETE FROM task_attachments WHERE id = ?')->execute([$a['id']]);
    @unlink(UPLOAD_DIR . basename($a['stored_name'])); log_activity('file_delete', $a['original_name']); flash('Attachment deleted.');
    back((int)$a['task_id']);
}
redirect(home_for($user['role']));
