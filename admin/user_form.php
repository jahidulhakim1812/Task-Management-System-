<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['admin']);
$id = (int)($_GET['id'] ?? 0);
$row = ['full_name' => '', 'email' => '', 'role' => 'employee', 'phone' => '', 'job_title' => '', 'is_active' => 1];
if ($id) { $st = db()->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$id]); $row = $st->fetch() ?: redirect('admin/users.php'); }
$self = $id === $user['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? ''); $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? '') ?: null; $job = trim($_POST['job_title'] ?? '') ?: null;
    $role = $self ? $row['role'] : ($_POST['role'] ?? ''); $active = $self ? 1 : (isset($_POST['is_active']) ? 1 : 0);
    $pass = $_POST['password'] ?? ''; $err = null;
    $dup = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?'); $dup->execute([$email, $id]);
    $weak = fn($p) => strlen($p) < 8 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid name and email.';
    elseif (!isset(ROLES[$role])) $err = 'Select a valid role.';
    elseif ($dup->fetch()) $err = 'This email is already used by another user.';
    elseif ((!$id || $pass !== '') && $weak($pass)) $err = 'Password needs at least 8 characters with a letter and a number.';
    if ($err) { flash($err, 'danger'); $row = array_merge($row, ['full_name' => $name, 'email' => $email, 'role' => $role, 'phone' => $phone, 'job_title' => $job, 'is_active' => $active]); }
    else {
        if ($id) {
            db()->prepare('UPDATE users SET full_name=?, email=?, role=?, phone=?, job_title=?, is_active=? WHERE id=?')->execute([$name, $email, $role, $phone, $job, $active, $id]);
            if ($pass !== '') db()->prepare('UPDATE users SET password_hash=?, failed_logins=0, locked_until=NULL WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            log_activity('user_update', "$name ($role)");
        } else {
            db()->prepare('INSERT INTO users (full_name,email,password_hash,role,phone,job_title,is_active) VALUES (?,?,?,?,?,?,?)')->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $phone, $job, $active]);
            log_activity('user_create', "$name ($role)");
        }
        flash('User saved successfully.'); redirect('admin/users.php');
    }
}
$pageTitle = $id ? 'Edit User' : 'Add User'; include __DIR__ . '/../includes/header.php';
?>
<div class="card narrow"><form method="post"><?= csrf_field() ?>
  <div class="cols"><label>Full name<input name="full_name" required value="<?= e($row['full_name']) ?>"></label>
  <label>Email<input type="email" name="email" required value="<?= e($row['email']) ?>"></label></div>
  <div class="cols"><label>Phone<input name="phone" value="<?= e($row['phone']) ?>"></label><label>Job title<input name="job_title" value="<?= e($row['job_title']) ?>"></label></div>
  <label>Role<select name="role" <?= $self ? 'disabled' : '' ?>><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $row['role'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
  <label>Password <small class="muted"><?= $id ? '(leave blank to keep current)' : '(8+ characters, letter and number)' ?></small><input type="password" name="password" <?= $id ? '' : 'required' ?> minlength="8" autocomplete="new-password"></label>
  <?php if (!$self): ?><label class="check"><input type="checkbox" name="is_active" <?= $row['is_active'] ? 'checked' : '' ?>> Account active</label>
  <?php else: ?><p class="muted small">You cannot change your own role or status.</p><?php endif; ?>
  <div class="row"><button class="btn primary">Save</button><a class="btn" href="<?= e(url('admin/users.php')) ?>">Cancel</a></div>
</form></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
