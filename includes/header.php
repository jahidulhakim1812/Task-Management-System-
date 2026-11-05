<?php
$user = $user ?? require_login();
sync_deadline_notifications($user);
$unread = unread_count($user['id']);
$nav = [
  'admin' => [['Overview', [['Dashboard','admin/dashboard.php','dashboard'],['All Tasks','tasks/index.php','tasks'],['Create Task','manager/task_form.php','plus'],['Board','tasks/board.php','board'],['Calendar','tasks/calendar.php','calendar']]],
              ['Administration', [['Users','admin/users.php','user'],['Reports','reports/index.php','chart'],['System Activity','admin/activity.php','activity'],['Settings','admin/settings.php','sliders']]]],
  'manager' => [['Overview', [['Dashboard','manager/dashboard.php','dashboard'],['Tasks','tasks/index.php','tasks'],['Board','tasks/board.php','board'],['Calendar','tasks/calendar.php','calendar']]],
                ['Manage', [['Create Task','manager/task_form.php','plus'],['Projects','manager/projects.php','folder'],['Reports','reports/index.php','chart']]]],
  'employee' => [['My Work', [['Dashboard','employee/dashboard.php','dashboard'],['My Tasks','tasks/index.php','tasks'],['My Board','tasks/board.php','board'],['Calendar','tasks/calendar.php','calendar'],['Task History','employee/history.php','clock']]]],
][$user['role']];
if ($user['role'] === 'employee') { $bc = db()->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'Completed'"); $bc->execute([$user['id']]); $navCount = (int)$bc->fetchColumn(); }
else $navCount = (int)db()->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
$here = $_SERVER['SCRIPT_NAME'];
$flash = flash();
?><!DOCTYPE html>
<html lang="en" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle ?? 'Dashboard') ?> | <?= e(company_name()) ?> TMS</title>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head>
<body>
<div class="scrim" id="scrim"></div>
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url(home_for($user['role']))) ?>"><span class="logo"><?= icon('send', 24) ?></span><span>TaskFlow<small>Task Management System</small></span></a>
  <nav><?php foreach ($nav as $gi => [$group, $items]): ?><?php if ($gi > 0): ?><div class="grp"><?= e($group) ?></div><?php endif; ?>
    <?php foreach ($items as [$label, $path, $ic]): ?><a href="<?= e(url($path)) ?>" class="<?= substr($here, -strlen($path)) === $path ? 'active' : '' ?>"><?= icon($ic, 20) ?><span><?= e($label) ?></span><?php if ($path === 'tasks/index.php'): ?><em class="cnt"><?= $navCount ?></em><?php endif; ?></a><?php endforeach; endforeach; ?></nav>
  <?= art_sidebar() ?>
  <div class="side-foot"><a href="<?= e(url('auth/profile.php')) ?>" class="me"><?= avatar($user['full_name'], 'lg') ?><span><b><?= e($user['full_name']) ?></b><small><?= e(ROLES[$user['role']]) ?></small></span><?= icon('right', 16) ?></a>
    <a class="logout" href="<?= e(url('auth/logout.php')) ?>"><?= icon('logout', 18) ?> Logout</a></div>
</aside>
<button id="menuBtn" class="icon-btn" type="button" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="true" title="Toggle navigation"><?= icon('menu') ?></button>
<main class="main">
  <header class="topbar">
    <form class="search" action="<?= e(url('tasks/index.php')) ?>" method="get"><?= icon('search', 17) ?><input id="gsearch" name="q" placeholder="Search tasks by title or description..." aria-label="Search tasks"><kbd>Ctrl + K</kbd></form>
    <div class="tb-right">
      <div class="datepill"><?= icon('calendar', 22) ?><div><small>Today</small><b id="dateTxt"><?= e(date('D, d M Y')) ?></b></div><span class="sep"></span><?= icon('clock', 18) ?><b id="clockTxt"><?= e(date('h:i A')) ?></b></div>
      <a class="icon-btn bell" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications"><?= icon('bell', 20) ?><?php if ($unread): ?><span class="dotn"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?></a>
      <button id="themeBtn" class="icon-btn" aria-label="Toggle light / dark mode" title="Light / dark mode"><?= icon('sun', 20) ?></button>
      <button id="fsBtn" class="icon-btn" aria-label="Full screen" title="Full screen"><?= icon('expand', 19) ?></button>
      <div class="usermenu" id="userMenu"><button class="um-btn" aria-haspopup="true"><?= avatar($user['full_name'], 'lg') ?><span><b><?= e($user['full_name']) ?></b><small><?= e($user['role'] === 'admin' ? 'Administrator' : ROLES[$user['role']]) ?></small></span><?= icon('down', 16) ?></button>
        <div class="um-list"><a href="<?= e(url('auth/profile.php')) ?>"><?= icon('user', 16) ?> My Profile</a><a href="<?= e(url('notifications.php')) ?>"><?= icon('bell', 16) ?> Notifications</a><a href="<?= e(url('auth/logout.php')) ?>"><?= icon('logout', 16) ?> Logout</a></div></div>
    </div>
  </header>
  <div class="page-content">
  <?php if (!empty($greeting)): ?>
  <div class="greet"><span class="wave">&#128075;</span><div><small><?= e(greeting_word()) ?>,</small><h1><?= e($user['full_name']) ?> <?= icon('badge', 22) ?></h1><p><?= e($greeting) ?></p></div></div>
  <?php else: ?><h1 class="ptitle"><?= e($pageTitle ?? '') ?></h1><?php endif; ?>
  <?php if ($flash): ?><div class="alert a-<?= e($flash[1]) ?>" role="alert"><?= e($flash[0]) ?></div><?php endif; ?>
