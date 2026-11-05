<?php
require_once __DIR__ . '/config/config.php';
$u = current_user();
redirect($u ? home_for($u['role']) : 'auth/login.php');
