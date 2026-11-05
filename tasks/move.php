<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
$user = require_login(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['ok' => false, 'error' => 'POST only'])); }
$t = find_task((int)($_POST['id'] ?? 0), $user);
$new = $_POST['status'] ?? '';
if (!$t) exit(json_encode(['ok' => false, 'error' => 'Task not found.']));
if (!in_array($new, allowed_statuses($user, $t), true)) exit(json_encode(['ok' => false, 'error' => 'You cannot move this task to "' . $new . '".']));
change_task_status($t, $new, $user);
$t['status'] = $new;
echo json_encode(['ok' => true, 'label' => effective_status($t), 'allowed' => array_values(allowed_statuses($user, $t))]);
