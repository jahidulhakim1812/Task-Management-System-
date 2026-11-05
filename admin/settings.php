<?php
require_once __DIR__ . '/../config/config.php';
$user = require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['company_name'] ?? ''); $days = (int)($_POST['warning_days'] ?? 2);
    if ($name === '' || $days < 0 || $days > 30) flash('Enter a company name and a warning period between 0 and 30 days.', 'danger');
    else { save_setting('company_name', cut($name, 0, 100)); save_setting('warning_days', (string)$days); log_activity('settings_update', "company=$name, warning_days=$days"); flash('Settings saved.'); }
    redirect('admin/settings.php');
}
$stats = db()->query('SELECT (SELECT COUNT(*) FROM users) users, (SELECT COUNT(*) FROM tasks) tasks, (SELECT COUNT(*) FROM task_attachments) files, (SELECT COUNT(*) FROM activity_logs) logs')->fetch();
$pageTitle = 'System Settings'; include __DIR__ . '/../includes/header.php';
?>
<div class="two"><div class="card"><h3>General</h3><form method="post"><?= csrf_field() ?>
  <label>Company name<input name="company_name" required maxlength="100" value="<?= e(company_name()) ?>"></label>
  <label>"Deadline approaching" warning (days before deadline)<input type="number" name="warning_days" min="0" max="30" value="<?= e(warning_days()) ?>"></label>
  <button class="btn primary">Save settings</button></form></div>
<div class="card"><h3>System information</h3><div class="table-wrap"><table><tbody>
  <tr><td>Application</td><td>TaskFlow TMS v<?= e(APP_VERSION) ?></td></tr><tr><td>PHP version</td><td><?= e(PHP_VERSION) ?></td></tr><tr><td>Database server</td><td><?= e(db()->getAttribute(PDO::ATTR_SERVER_VERSION)) ?></td></tr>
  <tr><td>Time zone</td><td><?= e(date_default_timezone_get()) ?></td></tr><tr><td>Users / Tasks</td><td><?= (int)$stats['users'] ?> / <?= (int)$stats['tasks'] ?></td></tr><tr><td>Attachments / Log entries</td><td><?= (int)$stats['files'] ?> / <?= (int)$stats['logs'] ?></td></tr>
  <tr><td>Uploads folder writable</td><td><?= is_writable(UPLOAD_DIR) ? badge('Yes', 'completed') : badge('No', 'overdue') ?></td></tr></tbody></table></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
