<?php
/** Reusable dashboard building blocks (tiles, panels, quick actions, illustrations). */
function sparkline(array $v, int $w = 150, int $h = 52): string
{
    if (count($v) < 2) return '';
    $min = min($v); $max = max($v); $rng = max(1, $max - $min); $n = count($v) - 1; $pts = [];
    foreach ($v as $i => $val) $pts[] = [4 + ($w - 8) * $i / $n, 6 + ($h - 12) * (1 - ($val - $min) / $rng)];
    $d = 'M' . round($pts[0][0], 1) . ',' . round($pts[0][1], 1);
    for ($i = 1; $i <= $n; $i++) { $mx = ($pts[$i - 1][0] + $pts[$i][0]) / 2; $d .= ' C' . round($mx, 1) . ',' . round($pts[$i - 1][1], 1) . ' ' . round($mx, 1) . ',' . round($pts[$i][1], 1) . ' ' . round($pts[$i][0], 1) . ',' . round($pts[$i][1], 1); }
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true"><path d="' . $d . ' L' . ($w - 4) . ',' . $h . ' L4,' . $h . ' Z" class="sfill"/><path d="' . $d . '" class="sline"/></svg>';
}
/** "up 12% vs. last month" / "up 5 new in last 30 days" */
function trend_text(int $cur, int $prev): string
{
    if ($prev > 0) { $p = round(($cur - $prev) * 100 / $prev); return '<b class="' . ($p >= 0 ? 'up' : 'down') . '">' . ($p >= 0 ? '&uarr; ' : '&darr; ') . abs($p) . '%</b> <span>vs. last month</span>'; }
    return '<b class="up">&uarr; ' . $cur . '</b> <span>new in last 30 days</span>';
}
/** Big gradient KPI tile. $color: blue|green|orange|purple|red */
function tile(string $label, $value, string $color, string $ic, string $href, string $sub, array $spark = []): string
{
    return '<a class="tile s-' . e($color) . '" href="' . e(url($href)) . '"><span class="tgo">' . icon('right', 14) . '</span><span class="ticon">' . icon($ic, 24) . '</span>' .
           '<span class="tbody"><span class="tlabel">' . e($label) . '</span><span class="tnum">' . e($value) . '</span><span class="tdelta">' . $sub . '</span></span>' . sparkline($spark) . '</a>';
}
/** Panel heading: icon + title + subtitle (+ optional right-hand html). */
function ph(string $ic, string $title, string $sub = '', string $right = ''): string
{
    return '<div class="ph"><span class="phi">' . icon($ic, 20) . '</span><div><h3>' . e($title) . '</h3>' . ($sub ? '<p>' . e($sub) . '</p>' : '') . '</div>' . ($right ? '<div class="phr">' . $right . '</div>' : '') . '</div>';
}
function pill_select(string $text): string { return '<span class="pillsel">' . e($text) . ' ' . icon('down', 14) . '</span>'; }
/** Quick action list. $items: [[label, href, icon, primary?], ...] */
function quick_actions(array $items): string
{
    $h = '<div class="card qa"><div class="ph"><span class="phi">' . icon('badge', 20) . '</span><div><h3>Quick Actions</h3></div></div>';
    foreach ($items as [$l, $href, $ic, $primary]) $h .= '<a class="qbtn ' . ($primary ? 'primary' : '') . '" href="' . e(url($href)) . '">' . icon($ic, 18) . '<span>' . e($l) . '</span></a>';
    return $h . '</div>';
}
/** The two decorative cards on the right of the dashboards. */
function promo_cards(string $href = 'tasks/index.php'): string
{
    $clip = '<svg viewBox="0 0 80 80" class="promo-ic" aria-hidden="true"><defs><linearGradient id="pg1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4aa3ff"/><stop offset="1" stop-color="#2557ff"/></linearGradient></defs><rect x="14" y="10" width="46" height="60" rx="7" fill="url(#pg1)" transform="rotate(-8 37 40)"/><rect x="26" y="6" width="22" height="9" rx="4" fill="#9cc9ff" transform="rotate(-8 37 40)"/><g stroke="#fff" stroke-width="3" stroke-linecap="round" fill="none" transform="rotate(-8 37 40)"><path d="M22 28l4 4 7-8M22 44l4 4 7-8M22 60l4 4 7-8"/><path d="M40 29h14M40 45h14M40 61h14" opacity=".7"/></g></svg>';
    $steps = '<svg viewBox="0 0 320 120" class="steps" preserveAspectRatio="xMaxYMax slice" aria-hidden="true"><defs><linearGradient id="pg2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1a2f8f"/><stop offset="1" stop-color="#5a2bb8"/></linearGradient></defs><rect width="320" height="120" fill="url(#pg2)"/><path d="M0 120 L90 70 L150 100 L230 40 L320 95 V120Z" fill="#0b1a55" opacity=".7"/><path d="M150 120 L190 96 L190 84 L222 84 L222 72 L254 72 L254 60 L286 60 V120Z" fill="#101c63"/><path d="M286 60 V26" stroke="#cfe3ff" stroke-width="2"/><path d="M286 26 l24 7 -24 7z" fill="#57a6ff"/><circle cx="60" cy="22" r="1.5" fill="#fff"/><circle cx="110" cy="40" r="1" fill="#fff"/><circle cx="200" cy="18" r="1.5" fill="#fff"/></svg>';
    return '<a class="card promo p1" href="' . e(url($href)) . '">' . $clip . '<div><b>Stay Productive</b><strong>Get Things Done</strong><span>Efficient task management for a better tomorrow.</span></div><span class="tgo">' . icon('right', 16) . '</span></a>' .
           '<div class="card promo p2">' . $steps . '<div class="quote">Small steps<br>every day lead to<br><em>big results</em></div></div>';
}
function greeting(): string { $h = (int)date('G'); return $h < 12 ? 'Good Morning' : ($h < 17 ? 'Good Afternoon' : 'Good Evening'); }
