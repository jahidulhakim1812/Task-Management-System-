<?php
require_once __DIR__ . '/config/config.php';
$err = ''; $installed = false; $connected = false; $done = false;
try {
    $srv = Database::serverConnection(); $connected = true;
    $st = $srv->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = 'users'"); $st->execute([Database::NAME]);
    $installed = (bool)$st->fetchColumn();
} catch (Throwable $e) { $err = $e->getMessage(); }
if ($connected && !$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try { $srv->exec(file_get_contents(__DIR__ . '/database/schema.sql')); $done = true; $installed = true; }
    catch (Throwable $e) { $err = $e->getMessage(); }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install | TMS</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head><body style="display:grid;place-items:center;min-height:100vh">
<div class="card" style="width:min(560px,94vw)"><h2>TaskFlow TMS - Installer</h2>
<?php if ($err): ?><div class="alert a-danger"><?= e($err) ?></div><?php endif; ?>
<?php if (!$connected): ?><p>Cannot reach MySQL. Start <b>MySQL</b> in the XAMPP Control Panel, check the credentials in <code>config/database.php</code>, then refresh.</p>
<?php elseif ($done): ?><div class="alert a-success">Installation complete. Database <b><?= e(Database::NAME) ?></b> was created with demo data.</div><p><a class="btn primary" href="<?= e(url('auth/login.php')) ?>">Go to login</a></p><p class="muted small">For security, delete <code>install.php</code> before putting the system online.</p>
<?php elseif ($installed): ?><p>The system is already installed (database <b><?= e(Database::NAME) ?></b> exists). Nothing was changed.</p><p><a class="btn primary" href="<?= e(url('auth/login.php')) ?>">Go to login</a></p>
<?php else: ?><p>This will create the database <b><?= e(Database::NAME) ?></b>, all tables and demo data on <code><?= e(Database::HOST) ?></code> as user <code><?= e(Database::USER) ?></code>.</p>
<form method="post"><?= csrf_field() ?><button class="btn primary">Install now</button></form><?php endif; ?></div></body></html>
