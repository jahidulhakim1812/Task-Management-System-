<?php
/** substr that is safe for UTF-8 when mbstring exists, plain substr otherwise */
function cut(string $s, int $start, ?int $len = null): string { return function_exists('mb_substr') ? mb_substr($s, $start, $len) : substr($s, $start, $len); }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p): void { header('Location: ' . url($p)); exit; }
function db(): PDO { return Database::getConnection(); }
function flash(?string $msg = null, string $type = 'success')
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

// ---------- Settings (stored in `settings` table) ----------
function setting(string $key, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        try { $all = db()->query('SELECT k, v FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $e) { $all = []; }
    }
    return $all[$key] ?? $default;
}
function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$key, $value]);
}
function company_name(): string { return setting('company_name', 'ABC IT Solutions'); }
function warning_days(): int { return max(0, (int)setting('warning_days', '2')); }

// ---------- CSRF ----------
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function check_csrf(bool $json = false): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        if ($json) { header('Content-Type: application/json'); exit(json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.'])); }
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

// ---------- Auth ----------
function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT id, full_name, email, phone, job_title, role, is_active FROM users WHERE id = ? AND is_active = 1');
            $st->execute([$_SESSION['uid']]);
            $u = $st->fetch() ?: null;
            if (!$u) unset($_SESSION['uid']);
        }
    }
    return $u;
}
function home_for(string $role): string { return $role . '/dashboard.php'; }
function require_login(bool $json = false): array
{
    $u = current_user();
    if (!$u) {
        if ($json) { http_response_code(401); header('Content-Type: application/json'); exit(json_encode(['ok' => false, 'error' => 'Please log in again.'])); }
        redirect('auth/login.php');
    }
    check_csrf($json);
    return $u;
}
function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('<h2>403 - Access denied</h2><p>You do not have permission to view this page. <a href="' . e(url(home_for($u['role']))) . '">Back to dashboard</a></p>');
    }
    return $u;
}

// ---------- Logging & notifications ----------
function log_activity(string $action, string $details = '', ?int $uid = null): void
{
    $uid ??= $_SESSION['uid'] ?? null;
    db()->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)')
        ->execute([$uid, $action, cut($details, 0, 250), $_SERVER['REMOTE_ADDR'] ?? null]);
}
function notify(int $uid, string $msg, ?int $taskId = null, string $type = 'info'): void
{
    db()->prepare('INSERT INTO notifications (user_id, task_id, type, message) VALUES (?,?,?,?)')->execute([$uid, $taskId, $type, cut($msg, 0, 250)]);
}
function unread_count(int $uid): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$uid]); return (int)$st->fetchColumn();
}
/** Creates "deadline approaching" / "overdue" notifications once per task/type/user. */
function sync_deadline_notifications(array $u): void
{
    $col = ['employee' => 'assigned_to', 'manager' => 'created_by'][$u['role']] ?? null;
    if (!$col) return;
    $st = db()->prepare("SELECT id, title, deadline FROM tasks WHERE $col = ? AND status <> 'Completed' AND deadline <= ?");
    $st->execute([$u['id'], date('Y-m-d', strtotime('+' . warning_days() . ' days'))]);
    $chk = db()->prepare('SELECT 1 FROM notifications WHERE user_id = ? AND task_id = ? AND type = ?');
    foreach ($st->fetchAll() as $t) {
        $type = $t['deadline'] < date('Y-m-d') ? 'overdue' : 'deadline';
        $chk->execute([$u['id'], $t['id'], $type]);
        if (!$chk->fetchColumn()) {
            notify($u['id'], $type === 'overdue'
                ? "Task \"{$t['title']}\" is overdue (deadline {$t['deadline']})."
                : "Deadline approaching for \"{$t['title']}\" ({$t['deadline']}).", (int)$t['id'], $type);
        }
    }
}

// ---------- Task helpers ----------
function task_code($id): string { return 'TMS-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT); }
function effective_status(array $t): string
{
    return ($t['status'] !== 'Completed' && $t['deadline'] < date('Y-m-d')) ? 'Overdue' : $t['status'];
}
function due_label(array $t): string
{
    if ($t['status'] === 'Completed') return '';
    $d = (int)floor((strtotime($t['deadline']) - strtotime(date('Y-m-d'))) / 86400);
    if ($d < 0) return abs($d) . ' day' . (abs($d) > 1 ? 's' : '') . ' late';
    return $d === 0 ? 'due today' : "in $d day" . ($d > 1 ? 's' : '');
}
/** Counters. Overdue = not completed and past deadline (its own bucket). */
function task_stats(string $where = '1=1', array $params = []): array
{
    $today = date('Y-m-d');
    $st = db()->prepare("SELECT COUNT(*) total,
        COALESCE(SUM(status='Pending' AND deadline >= ?),0) pending,
        COALESCE(SUM(status='In Progress' AND deadline >= ?),0) in_progress,
        COALESCE(SUM(status='Completed'),0) completed,
        COALESCE(SUM(status<>'Completed' AND deadline < ?),0) overdue
        FROM tasks WHERE $where");
    $st->execute(array_merge([$today, $today, $today], $params));
    return array_map('intval', $st->fetch());
}
/** Which statuses may $actor move task $t to? Employees: only the next step on their own task. Managers: any. */
function allowed_statuses(array $actor, array $t): array
{
    if ($actor['role'] === 'manager') return array_values(array_diff(STATUSES, [$t['status']]));
    if ($actor['role'] === 'employee' && (int)$t['assigned_to'] === (int)$actor['id'])
        return array_filter([['Pending' => 'In Progress', 'In Progress' => 'Completed'][$t['status']] ?? null]);
    return [];
}
function change_task_status(array $t, string $new, array $actor): void
{
    if ($t['status'] === $new) return;
    db()->prepare('UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?')->execute([$new, $new === 'Completed' ? date('Y-m-d H:i:s') : null, $t['id']]);
    db()->prepare('INSERT INTO task_history (task_id,user_id,old_status,new_status) VALUES (?,?,?,?)')->execute([$t['id'], $actor['id'], $t['status'], $new]);
    log_activity('task_status', "{$t['title']}: {$t['status']} -> $new", (int)$actor['id']);
    if ($new === 'Completed' && (int)$actor['id'] !== (int)$t['created_by']) notify((int)$t['created_by'], "{$actor['full_name']} completed \"{$t['title']}\".", (int)$t['id'], 'completed');
    if ($actor['role'] === 'manager' && (int)$actor['id'] !== (int)$t['assigned_to']) notify((int)$t['assigned_to'], "\"{$t['title']}\" was moved to $new by {$actor['full_name']}.", (int)$t['id'], 'status');
}
const TASK_SELECT = 'SELECT t.*, e.full_name AS employee_name, c.full_name AS creator_name, p.name AS project_name,
    (SELECT COUNT(*) FROM task_checklist k WHERE k.task_id = t.id) AS ck_total,
    (SELECT COUNT(*) FROM task_checklist k WHERE k.task_id = t.id AND k.is_done = 1) AS ck_done
    FROM tasks t JOIN users e ON e.id = t.assigned_to JOIN users c ON c.id = t.created_by LEFT JOIN projects p ON p.id = t.project_id';
/** Fetches a task by id (null if missing or not visible to $u). */
function find_task(int $id, array $u): ?array
{
    $st = db()->prepare(TASK_SELECT . ' WHERE t.id = ?'); $st->execute([$id]); $t = $st->fetch();
    if (!$t || ($u['role'] === 'employee' && (int)$t['assigned_to'] !== (int)$u['id'])) return null;
    return $t;
}

// ---------- UI helpers ----------
function badge(string $text, string $kind): string
{
    return '<span class="badge b-' . e(strtolower(str_replace(' ', '-', $kind))) . '">' . e($text) . '</span>';
}
function status_badge(array $t): string { $s = effective_status($t); return badge($s, $s); }
function fmt_date(?string $d): string { return $d ? date('d M Y', strtotime($d)) : '-'; }
function fmt_dt(?string $d): string { return $d ? date('d M Y, H:i', strtotime($d)) : '-'; }
function time_ago(string $dt): string
{
    $s = time() - strtotime($dt);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' hr ago';
    if ($s < 2592000) return floor($s / 86400) . ' day' . (floor($s / 86400) > 1 ? 's' : '') . ' ago';
    return fmt_date($dt);
}
function avatar(string $name, string $cls = ''): string
{
    $parts = preg_split('/\s+/', trim($name));
    $second = $parts[1] ?? '';
    $ini = strtoupper(cut($parts[0], 0, 1) . ($second === '' ? '' : (ctype_digit($second) ? (ltrim($second, '0') ?: '0') : cut($second, 0, 1))));
    return '<span class="avatar a' . (crc32($name) % 6) . ' ' . $cls . '">' . e($ini) . '</span>';
}
function icon(string $n, int $s = 18): string
{
    static $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'tasks' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'board' => '<rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="4" height="8" rx="1"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'chart' => '<path d="M18 20V10M12 20V4M6 20v-6"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'menu' => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'paperclip' => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'trash' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'left' => '<path d="M15 18l-6-6 6-6"/>', 'right' => '<path d="M9 18l6-6-6-6"/>', 'down' => '<path d="M6 9l6 6 6-6"/>',
        'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>', 'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'expand' => '<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'userplus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/>',
        'badge' => '<path d="M12 2l2.4 2.1 3.2-.3 1 3 2.8 1.6-1 3 1 3-2.8 1.6-1 3-3.2-.3L12 22l-2.4-2.1-3.2.3-1-3L2.6 15.6l1-3-1-3 2.8-1.6 1-3 3.2.3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
    ];
    return '<svg class="ic" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}
function stat_card(string $label, $value, string $cls = '', string $ic = 'tasks', string $href = ''): string
{
    $in = '<div class="ico">' . icon($ic, 20) . '</div><div><div class="num">' . e($value) . '</div><div class="lbl">' . e($label) . '</div></div>';
    return $href ? '<a class="card stat ' . $cls . '" href="' . e(url($href)) . '">' . $in . '</a>' : '<div class="card stat ' . $cls . '">' . $in . '</div>';
}
function progress_bar(int $pct, string $cls = ''): string
{
    return '<div class="bar ' . $cls . '" title="' . $pct . '%"><i style="width:' . max(0, min(100, $pct)) . '%"></i></div>';
}

// ---------- Pagination ----------
function page_num(): int { return max(1, (int)($_GET['page'] ?? 1)); }
function pager(int $total, int $page, int $per = PER_PAGE): string
{
    $pages = (int)ceil($total / $per); if ($pages <= 1) return '';
    $q = $_GET; $a = fn($p, $l, $c = '') => '<a class="' . $c . '" href="?' . e(http_build_query(array_merge($q, ['page' => $p]))) . '">' . $l . '</a>';
    $h = '<nav class="pager"><span class="muted small">Page ' . $page . ' of ' . $pages . ' &middot; ' . $total . ' records</span><div>';
    if ($page > 1) $h .= $a($page - 1, '&laquo; Prev');
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) $h .= $a($i, $i, $i === $page ? 'on' : '');
    if ($page < $pages) $h .= $a($page + 1, 'Next &raquo;');
    return $h . '</div></nav>';
}

/** Standard task table. */
function task_table(array $rows, bool $showEmployee = true): string
{
    if (!$rows) return '<div class="empty">' . icon('tasks', 36) . '<p>No tasks found.</p></div>';
    $h = '<div class="table-wrap"><table><thead><tr><th>Task</th>' . ($showEmployee ? '<th>Assigned to</th>' : '') .
         '<th>Priority</th><th>Deadline</th><th>Checklist</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $t) {
        $pct = $t['ck_total'] ? (int)round($t['ck_done'] * 100 / $t['ck_total']) : null;
        $due = due_label($t);
        $h .= '<tr class="' . (effective_status($t) === 'Overdue' ? 'row-late' : '') . '"><td><a class="tlink" href="' . e(url('tasks/view.php?id=' . $t['id'])) . '"><span class="code">' . e(task_code($t['id'])) . '</span> ' . e($t['title']) . '</a>' .
              (!empty($t['project_name']) ? '<div class="muted small">' . e($t['project_name']) . '</div>' : '') . '</td>' .
              ($showEmployee ? '<td><span class="who">' . avatar($t['employee_name']) . e($t['employee_name']) . '</span></td>' : '') .
              '<td>' . badge($t['priority'], $t['priority']) . '</td><td>' . e(fmt_date($t['deadline'])) . ($due ? '<div class="muted small">' . e($due) . '</div>' : '') . '</td>' .
              '<td>' . ($pct === null ? '<span class="muted small">-</span>' : progress_bar($pct) . '<span class="small muted">' . $t['ck_done'] . '/' . $t['ck_total'] . '</span>') . '</td><td>' . status_badge($t) . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}

// ---------- Uploads ----------
function fmt_size(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB'; }

// ---------- Dashboard widgets ----------
/** Gradient KPI card (like the dashboard design). $tone: blue|green|orange|purple|red */
function hero_stat(string $label, $value, string $tone, string $ic, string $href = '', ?int $growth = null, array $spark = [], string $note = ''): string
{
    $trend = $growth !== null ? '<div class="trend ' . ($growth >= 0 ? 'up' : 'down') . '">' . ($growth >= 0 ? '&#8593;' : '&#8595;') . ' ' . abs($growth) . '%<small>vs. last month</small></div>' : ($note ? '<div class="trend note">' . e($note) . '</div>' : '');
    $in = '<div class="hs-ico">' . icon($ic, 26) . '</div><div class="hs-body"><div class="hs-lbl">' . e($label) . '</div><div class="hs-num">' . e($value) . '</div>' . $trend . '</div>' .
          ($spark ? '<div class="hs-spark">' . spark($spark) . '</div>' : '') . ($href ? '<span class="hs-go">' . icon('right', 14) . '</span>' : '');
    return $href ? '<a class="hstat t-' . e($tone) . '" href="' . e(url($href)) . '">' . $in . '</a>' : '<div class="hstat t-' . e($tone) . '">' . $in . '</div>';
}
/** Cumulative row count per month (last 6 months) + growth % vs the end of last month. */
function cumulative_series(string $table, string $where = '1=1', array $params = []): array
{
    $first = date('Y-m-01'); $start = date('Y-m-01', strtotime('-5 months'));
    $now = db()->prepare("SELECT COUNT(*) FROM $table WHERE $where"); $now->execute($params); $total = (int)$now->fetchColumn();
    $before = db()->prepare("SELECT COUNT(*) FROM $table WHERE created_at < ? AND $where"); $before->execute(array_merge([$start], $params)); $run = (int)$before->fetchColumn();
    $m = db()->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') k, COUNT(*) n FROM $table WHERE created_at >= ? AND $where GROUP BY k"); $m->execute(array_merge([$start], $params));
    $per = $m->fetchAll(PDO::FETCH_KEY_PAIR); $vals = []; $lastMonthEnd = $run;
    for ($i = 5; $i >= 0; $i--) { $k = date('Y-m', strtotime("$first -$i month")); $run += (int)($per[$k] ?? 0); $vals[] = $run; if ($i === 1) $lastMonthEnd = $run; }
    $growth = $lastMonthEnd > 0 ? (int)round(($total - $lastMonthEnd) * 100 / $lastMonthEnd) : 0;
    return [$vals, $growth, $total];
}
function greeting_word(): string { $h = (int)date('G'); return $h < 12 ? 'Good Morning' : ($h < 17 ? 'Good Afternoon' : 'Good Evening'); }
