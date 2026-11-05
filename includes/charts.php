<?php
/** Dependency-free SVG/CSS charts and illustrations (work offline in XAMPP, no CDN needed). */

function svg_id(): string { static $n = 0; return 'g' . (++$n); }
/** Smooth cubic path through points (Catmull-Rom -> Bezier), clamped to [$top,$bottom]. */
function smooth_path(array $pts, float $top = 0, float $bottom = 9999): string
{
    $n = count($pts); $d = 'M' . round($pts[0][0], 1) . ',' . round($pts[0][1], 1);
    for ($i = 0; $i < $n - 1; $i++) {
        [$p0, $p1, $p2, $p3] = [$pts[max(0, $i - 1)], $pts[$i], $pts[$i + 1], $pts[min($n - 1, $i + 2)]];
        $c1y = min($bottom, max($top, $p1[1] + ($p2[1] - $p0[1]) / 6)); $c2y = min($bottom, max($top, $p2[1] - ($p3[1] - $p1[1]) / 6));
        $d .= ' C' . round($p1[0] + ($p2[0] - $p0[0]) / 6, 1) . ',' . round($c1y, 1) . ' ' . round($p2[0] - ($p3[0] - $p1[0]) / 6, 1) . ',' . round($c2y, 1) . ' ' . round($p2[0], 1) . ',' . round($p2[1], 1);
    }
    return $d;
}
/** Small trend line with soft fill, used inside KPI cards. */
function spark(array $vals): string
{
    $W = 120; $H = 46; $n = max(1, count($vals) - 1); $min = min($vals); $max = max($vals); $rng = max(1, $max - $min); $id = svg_id();
    $pts = []; foreach ($vals as $i => $v) $pts[] = [$W * $i / $n, 6 + ($H - 12) * (1 - ($v - $min) / $rng)];
    $line = smooth_path($pts, 2, $H - 2);
    return '<svg viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none"><defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".35"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>' .
           '<path d="' . $line . ' L' . $W . ',' . $H . ' L0,' . $H . ' Z" fill="url(#' . $id . ')"/><path d="' . $line . '" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';
}

/** Ring chart with % labels on the ring and a legend table (name / count / %). Items: [label, value, cssClass] */
function chart_donut(array $items, string $sub = 'Total Tasks'): string
{
    $total = (int)array_sum(array_column($items, 1));
    $svg = '<svg viewBox="0 0 42 42" class="donut" role="img"><circle cx="21" cy="21" r="14.5" class="donut-bg"/>';
    $off = 25; $cum = 0; $lab = '';
    foreach ($items as [$l, $v, $c]) {
        if ($total > 0 && $v > 0) {
            $pc = $v * 100 / $total;
            $svg .= '<circle cx="21" cy="21" r="14.5" class="donut-seg ' . e($c) . '" pathLength="100" stroke-dasharray="' . round(max(0, $pc - 0.8), 2) . ' ' . round(100 - max(0, $pc - 0.8), 2) . '" stroke-dashoffset="' . round($off, 2) . '"/>';
            if ($pc >= 7) { $a = ($cum + $pc / 2) / 100 * 2 * M_PI; $lab .= '<text x="' . round(21 + 14.5 * sin($a), 2) . '" y="' . round(21.9 - 14.5 * cos($a), 2) . '" class="donut-pct">' . round($pc) . '%</text>'; }
            $off -= $pc; $cum += $pc;
        }
    }
    $svg .= $lab . '<circle cx="21" cy="21" r="9.2" class="donut-hole"/><text x="21" y="22.4" class="donut-num">' . $total . '</text><text x="21" y="26.6" class="donut-sub">' . e($sub) . '</text></svg>';
    $leg = '<ul class="legend tbl">';
    foreach ($items as [$l, $v, $c]) $leg .= '<li><i class="dot ' . e($c) . '"></i><span>' . e($l) . '</span><b>' . (int)$v . '</b><em>' . ($total ? round($v * 100 / $total) : 0) . '%</em></li>';
    return '<div class="donut-box">' . $svg . $leg . '</ul></div>';
}
/** Horizontal bars. Items: [label, value, cssClass, optional avatar html]. $pct adds a % column, $dots a colour dot. */
function chart_bars(array $items, bool $pct = false, bool $dots = false): string
{
    if (!$items) return '<p class="muted">No data.</p>';
    $max = max(1, max(array_column($items, 1))); $sum = max(1, array_sum(array_column($items, 1)));
    $h = '<div class="hbars' . ($pct ? ' withpct' : '') . '">';
    foreach ($items as $it) {
        [$l, $v, $c] = $it;
        $h .= '<div class="hbar ' . e($c) . '"><span class="hl" title="' . e($l) . '">' . ($dots ? '<i class="dot ' . e($c) . '"></i>' : '') . ($it[3] ?? '') . e($l) . '</span><div class="track"><i class="' . e($c) . '" style="width:' . round($v * 100 / $max) . '%"></i></div><b>' . (int)$v . '</b>' . ($pct ? '<em>' . round($v * 100 / $sum) . '%</em>' : '') . '</div>';
    }
    return $h . '</div>';
}
/** Smooth area/line chart. $series: [[name, cssClass, [values...]], ...] */
function chart_lines(array $labels, array $series): string
{
    $W = 560; $H = 215; $l = 34; $r = 14; $t = 14; $b = 30;
    $max = 1; foreach ($series as $s) $max = max($max, max($s[2] ?: [0]));
    $step = $max <= 8 ? 2 : (int)ceil($max / 4 / 2) * 2; $max = $step * 4; $n = max(1, count($labels) - 1);
    $x = fn($i) => $l + ($W - $l - $r) * $i / $n; $y = fn($v) => $t + ($H - $t - $b) * (1 - $v / $max);
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="linechart" role="img"><defs>';
    $ids = []; foreach ($series as $k => $s) { $ids[$k] = svg_id(); $svg .= '<linearGradient id="' . $ids[$k] . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="' . e($s[1]) . ' stop" stop-opacity=".38"/><stop offset="1" class="' . e($s[1]) . ' stop" stop-opacity="0"/></linearGradient>'; }
    $svg .= '</defs>';
    for ($g = 0; $g <= 4; $g++) { $v = $step * $g; $svg .= '<line x1="' . $l . '" x2="' . ($W - $r) . '" y1="' . $y($v) . '" y2="' . $y($v) . '" class="grid"/><text x="' . ($l - 8) . '" y="' . ($y($v) + 3.5) . '" class="axis" text-anchor="end">' . $v . '</text>'; }
    foreach ($labels as $i => $lab) $svg .= '<text x="' . $x($i) . '" y="' . ($H - 8) . '" class="axis" text-anchor="middle">' . e($lab) . '</text>';
    $leg = '<ul class="legend inline">';
    foreach (array_reverse($series, true) as $k => [$name, $cls, $vals]) {
        $pts = []; foreach ($vals as $i => $v) $pts[] = [$x($i), $y($v)];
        $line = smooth_path($pts, $t, $H - $b);
        $svg .= '<path d="' . $line . ' L' . $x(count($vals) - 1) . ',' . ($H - $b) . ' L' . $x(0) . ',' . ($H - $b) . ' Z" fill="url(#' . $ids[$k] . ')"/><path d="' . $line . '" class="line ' . e($cls) . '"/>';
        foreach ($vals as $i => $v) $svg .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($v), 1) . '" r="3.6" class="pt ' . e($cls) . '"><title>' . e($name) . ': ' . $v . '</title></circle>';
    }
    foreach ($series as [$name, $cls]) $leg .= '<li><i class="dot ' . e($cls) . '"></i>' . e($name) . '</li>';
    return $svg . '</svg>' . $leg . '</ul>';
}
/** Last 6 months: tasks created vs completed. Optional extra WHERE (on tasks). */
function monthly_trend(string $where = '1=1', array $params = []): array
{
    $labels = []; $keys = [];
    for ($i = 5; $i >= 0; $i--) { $ts = strtotime(date('Y-m-01') . " -$i month"); $keys[] = date('Y-m', $ts); $labels[] = date('M', $ts); }
    $from = $keys[0] . '-01';
    $c = db()->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n FROM tasks WHERE created_at >= ? AND $where GROUP BY m"); $c->execute(array_merge([$from], $params));
    $d = db()->prepare("SELECT DATE_FORMAT(completed_at,'%Y-%m') m, COUNT(*) n FROM tasks WHERE completed_at >= ? AND $where GROUP BY m"); $d->execute(array_merge([$from], $params));
    $cr = $c->fetchAll(PDO::FETCH_KEY_PAIR); $co = $d->fetchAll(PDO::FETCH_KEY_PAIR);
    return [$labels, array_map(fn($k) => (int)($cr[$k] ?? 0), $keys), array_map(fn($k) => (int)($co[$k] ?? 0), $keys)];
}
function status_donut(array $s): string
{
    return chart_donut([['Completed', $s['completed'], 'c-completed'], ['In Progress', $s['in_progress'], 'c-progress'], ['Pending', $s['pending'], 'c-pending'], ['Overdue', $s['overdue'], 'c-overdue']]);
}
function priority_bars(string $where = '1=1', array $params = [], bool $pct = false): string
{
    $st = db()->prepare("SELECT priority, COUNT(*) n FROM tasks WHERE status <> 'Completed' AND $where GROUP BY priority"); $st->execute($params);
    $m = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    return chart_bars(array_map(fn($p) => [$p, (int)($m[$p] ?? 0), 'c-' . strtolower($p)], array_reverse(PRIORITIES)), $pct, $pct);
}

// ---------- Decorative illustrations (inline SVG) ----------
function art_clipboard(): string
{
    return '<svg viewBox="0 0 120 100" class="art" aria-hidden="true"><defs><linearGradient id="ac1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#60a5fa"/><stop offset="1" stop-color="#2563eb"/></linearGradient></defs>' .
        '<rect x="52" y="14" width="52" height="72" rx="9" fill="#1e3a8a" opacity=".7" transform="rotate(8 78 50)"/><rect x="30" y="10" width="56" height="78" rx="9" fill="url(#ac1)"/><rect x="46" y="4" width="24" height="12" rx="5" fill="#93c5fd"/>' .
        '<g stroke="#fff" stroke-width="3" stroke-linecap="round" fill="none"><path d="M40 34l4 4 7-8M40 52l4 4 7-8M40 70l4 4 7-8"/></g><g stroke="#dbeafe" stroke-width="3" stroke-linecap="round"><path d="M58 34h20M58 52h20M58 70h20"/></g></svg>';
}
function art_mountain(): string
{
    return '<svg viewBox="0 0 320 130" class="art wide" preserveAspectRatio="xMidYMax slice" aria-hidden="true"><defs><linearGradient id="am1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset="1" stop-color="#0b1b4d"/></linearGradient><linearGradient id="am2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b82f6"/><stop offset="1" stop-color="#1d4ed8"/></linearGradient></defs>' .
        '<polygon points="120,130 215,32 330,130" fill="url(#am1)"/><polygon points="40,130 150,52 270,130" fill="url(#am2)" opacity=".85"/><polygon points="215,32 232,52 220,48 208,56 200,46" fill="#e0e7ff" opacity=".8"/>' .
        '<path d="M150 118h24v-10h24v-10h24v-10h24" stroke="#93c5fd" stroke-width="3" fill="none" opacity=".7"/><path d="M239 88v-30" stroke="#e2e8f0" stroke-width="2"/><polygon points="239,58 262,64 239,72" fill="#60a5fa"/><circle cx="236" cy="82" r="3.5" fill="#fff"/></svg>';
}
function art_rocket(): string
{
    return '<svg viewBox="0 0 40 40" class="rocket" aria-hidden="true"><path d="M30 6c-8 1-14 7-16 14l6 6c7-2 13-8 14-16z" fill="#60a5fa"/><circle cx="24" cy="16" r="3" fill="#0b1b4d"/><path d="M14 20l-6 2 4 4M20 26l-2 6-4-4" fill="#3b82f6"/><path d="M9 31c-3 1-4 4-4 6 2 0 5-1 6-4z" fill="#f59e0b"/></svg>';
}
function art_sidebar(): string
{
    return '<div class="side-art"><svg viewBox="0 0 220 120" preserveAspectRatio="xMidYMax slice" aria-hidden="true"><defs><linearGradient id="sa1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0b1b4d" stop-opacity="0"/><stop offset="1" stop-color="#1d4ed8" stop-opacity=".55"/></linearGradient></defs><rect width="220" height="120" fill="url(#sa1)"/>' .
        '<polygon points="0,120 50,55 95,90 140,40 220,100 220,120" fill="#12307a"/><polygon points="0,120 70,80 130,105 190,70 220,95 220,120" fill="#0a1a52"/>' .
        '<g fill="#fff" opacity=".75"><circle cx="28" cy="16" r="1"/><circle cx="70" cy="28" r="1.2"/><circle cx="150" cy="14" r="1"/><circle cx="190" cy="30" r="1.2"/><circle cx="120" cy="8" r=".9"/></g></svg>' .
        '<div class="tag">Better<br>Tasks<br>Bigger<br><b>Success</b> ' . art_rocket() . '</div></div>';
}

// ---------- Dashboard building blocks ----------
function card_head(string $ic, string $title, string $sub = '', string $tag = ''): string
{
    return '<div class="ch"><span class="ci">' . icon($ic, 19) . '</span><div><h3>' . e($title) . '</h3>' . ($sub ? '<p>' . e($sub) . '</p>' : '') . '</div>' . ($tag ? '<span class="tag">' . e($tag) . '</span>' : '') . '</div>';
}
function quick_actions(array $items): string
{
    $h = '<div class="card">' . card_head('send', 'Quick Actions') . '<div class="qa">';
    foreach ($items as $i => [$l, $p, $ic]) $h .= '<a class="qbtn' . ($i === 0 ? ' primary' : '') . '" href="' . e(url($p)) . '">' . icon($ic, 19) . e($l) . '</a>';
    return $h . '</div></div>';
}
function promo_cards(): string
{
    return '<div class="card promo">' . art_clipboard() . '<div><b>Stay Productive</b><b class="hl">Get Things Done</b><p>Efficient task management<br>for a better tomorrow.</p></div><a class="go" href="' . e(url('tasks/index.php')) . '" aria-label="Tasks">' . icon('right', 16) . '</a></div>' .
           '<div class="card quote">' . art_mountain() . '<p>Small steps<br>every day lead to<br>big results</p></div>';
}
function pct_of(int $n, int $total): string { return ($total ? round($n * 100 / $total) : 0) . '% of all tasks'; }
