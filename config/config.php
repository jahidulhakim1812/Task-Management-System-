<?php
/** Global configuration - Task Management System v2 */
define('APP_SHORT', 'TMS');
define('APP_VERSION', '2.1');

// Auto-detects the folder (e.g. /tms under XAMPP htdocs). To force: define('BASE_URL', '/tms') before including this file.
if (!defined('BASE_URL')) {
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app  = realpath(__DIR__ . '/..') ?: '';
    $base = ($root && strpos($app, $root) === 0) ? str_replace('\\', '/', substr($app, strlen($root))) : '';
    define('BASE_URL', rtrim($base, '/'));
}

define('APP_ENV', 'development'); // 'production' hides PHP errors
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
error_reporting(E_ALL);

date_default_timezone_set('Asia/Dhaka');
define('DB_TIME_ZONE', '+06:00');

define('ROLES', ['admin' => 'Admin', 'manager' => 'Manager', 'employee' => 'Employee']);
define('PRIORITIES', ['Low', 'Medium', 'High', 'Urgent']);
define('STATUSES', ['Pending', 'In Progress', 'Completed']);
define('PER_PAGE', 10);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCK_MINUTES', 15);

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
define('ALLOWED_EXT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'zip']);

// ---- Security headers ----
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; script-src 'self'; form-action 'self'; frame-ancestors 'none'");

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/charts.php';
