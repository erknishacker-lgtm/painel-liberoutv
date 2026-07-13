<?php
/**
 * GET /api/config.php
 * App lê DNS, cards principais e 3 atalhos de baixo.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_out(['ok' => true]);
}

$dns = setting_get('login_dns', '');
$force = setting_get('force_dns', '1') === '1';

$live = setting_get('card_live', '');
$movies = setting_get('card_movies', '');
$series = setting_get('card_series', '');
$dashboardBg = setting_get('dashboard_bg', '');

$shortcuts = [];
for ($i = 1; $i <= 3; $i++) {
    $img = setting_get("shortcut_{$i}_image", '');
    $shortcuts[] = [
        'id' => $i,
        'label' => setting_get("shortcut_{$i}_label", "Atalho $i"),
        'category' => setting_get("shortcut_{$i}_cat", ''),
        'type' => setting_get("shortcut_{$i}_type", 'series'), // live | series
        'image' => card_public_url($img),
    ];
}

json_out([
    'ok' => true,
    'login_dns' => $dns,
    'force_dns' => $force,
    'dashboard_background' => card_public_url($dashboardBg),
    'cards' => [
        'live' => card_public_url($live),
        'movies' => card_public_url($movies),
        'series' => card_public_url($series),
    ],
    'shortcuts' => $shortcuts,
    'server_time' => date('c'),
    'panel' => setting_get('panel_name', 'LIBEROU TV Panel'),
]);
