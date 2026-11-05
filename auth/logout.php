<?php
require_once __DIR__ . '/../config/config.php';
if (current_user()) log_activity('logout', 'User logged out');
$_SESSION = [];
session_destroy();
redirect('auth/login.php');
