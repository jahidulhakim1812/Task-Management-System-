<?php
require_once __DIR__ . '/../config/config.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'profile') {
        $name = trim($_POST['full_name'] ?? '');
        if ($name === '') flash('Name is required.', 'danger');
        else {
            db()->prepare('UPDATE users SET full_name=?, phone=?, job_title=? WHERE id=?')->execute([$name, trim($_POST['phone'] ?? '') ?: null, trim($_POST['job_title'] ?? '') ?: null, $user['id']]);
            log_activity('profile_update', 'Profile updated'); flash('Profile updated.');
        }
    } else {
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?'); $st->execute([$user['id']]);
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $st->fetchColumn())) flash('Current password is incorrect.', 'danger');
        elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) flash('New password needs at least 8 characters with a letter and a number.', 'danger');
        elseif ($new !== ($_POST['confirm_password'] ?? '')) flash('New passwords do not match.', 'danger');
        else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            log_activity('password_change', 'User changed own password'); flash('Password updated successfully.');
        }
    }
    redirect('auth/profile.php');
}
$pageTitle = 'My Profile'; include __DIR__ . '/../includes/header.php';
?>
<div class="two">
<div class="card"><div class="row"><?= avatar($user['full_name'], 'big') ?><div><h3 style="margin:0"><?= e($user['full_name']) ?></h3><span class="muted"><?= e($user['email']) ?></span> <?= badge(ROLES[$user['role']], 'role') ?></div></div><br>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
  <label>Full name<input name="full_name" required value="<?= e($user['full_name']) ?>"></label>
  <div class="cols"><label>Phone<input name="phone" value="<?= e($user['phone']) ?>"></label><label>Job title<input name="job_title" value="<?= e($user['job_title']) ?>"></label></div>
  <button class="btn primary">Save profile</button></form></div>
<div class="card"><h3>Change password</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
  <label>Current password<input type="password" name="current_password" required></label>
  <label>New password <small class="muted">(8+ characters, letter and number)</small><input type="password" name="new_password" required minlength="8"></label>
  <label>Confirm new password<input type="password" name="confirm_password" required></label>
  <button class="btn primary">Update password</button></form></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
