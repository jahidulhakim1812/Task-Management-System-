<?php
require_once __DIR__ . '/../config/config.php';
if ($u = current_user()) redirect(home_for($u['role']));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([trim($_POST['email'] ?? '')]);
    $row = $st->fetch();
    if ($row && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
        $error = 'Too many failed attempts. Try again in ' . max(1, ceil((strtotime($row['locked_until']) - time()) / 60)) . ' minute(s), or ask the Admin to unlock your account.';
    } elseif (!$row || !password_verify($_POST['password'] ?? '', $row['password_hash'])) {
        $error = 'Invalid email or password.';
        if ($row) {
            $n = $row['failed_logins'] + 1;
            if ($n >= MAX_LOGIN_ATTEMPTS) {
                db()->prepare('UPDATE users SET failed_logins = 0, locked_until = DATE_ADD(NOW(), INTERVAL ' . LOCK_MINUTES . ' MINUTE) WHERE id = ?')->execute([$row['id']]);
                log_activity('account_locked', 'Too many failed logins: ' . $row['email'], (int)$row['id']);
            } else db()->prepare('UPDATE users SET failed_logins = ? WHERE id = ?')->execute([$n, $row['id']]);
        }
    } elseif (!$row['is_active']) {
        $error = 'Your account is deactivated. Please contact the Admin.';
    } else {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['id'];
        db()->prepare('UPDATE users SET last_login = NOW(), failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$row['id']]);
        log_activity('login', 'User logged in', (int)$row['id']);
        redirect(home_for($row['role']));
    }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | <?= e(company_name()) ?> TMS</title><script src="<?= e(url('assets/js/theme.js')) ?>"></script><link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head>
<body class="auth">
<section class="loginbox">
  <div class="login-scene" aria-hidden="true">
    <span class="scene-orb scene-orb-blue"></span><span class="scene-orb scene-orb-violet"></span><span class="scene-ring"></span>
  </div>
  <div class="login login-card">
  <a class="auth-brand login-brand" href="<?= e(url('index.php')) ?>"><span class="logo"><?= icon('send', 24) ?></span><span>TaskFlow<small><?= e(company_name()) ?> workspace</small></span></a>
  <div class="login-heading"><span class="login-mark"><?= icon('user', 20) ?></span><span class="login-eyebrow">YOUR WORKSPACE AWAITS</span></div>
  <p class="muted login-subtitle">Sign in to pick up where you left off.</p>
  <?php if ($error): ?><div class="alert a-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <label>Email address<input type="email" name="email" required autofocus autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@company.com"></label>
    <label>Password<input type="password" name="password" id="pw" required autocomplete="current-password" placeholder="Enter your password"></label>
    <label class="check"><input type="checkbox" data-toggle-pass="#pw"> Show password</label>
    <button class="btn primary block login-submit">Sign in <span aria-hidden="true"><?= icon('right', 17) ?></span></button>
  </form>
  <?php if (APP_ENV === 'development'): ?><details class="demo muted small"><summary>Demo accounts</summary><div class="demo-list"><span>Admin: admin@abcit.com / Admin@123</span><span>Manager: manager@abcit.com / Manager@123</span><span>Employee: employee01@abcit.com / Employee@123</span></div></details><?php endif; ?>
  <p class="login-secure"><?= icon('check', 15) ?> Secure sign-in protected by your workspace</p>
  </div>
</section>
<script src="<?= e(url('assets/js/main.js')) ?>"></script></body></html>
