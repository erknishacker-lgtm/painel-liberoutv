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
$loginBg = setting_get('login_bg', '');

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

$apkRaw = setting_get('app_apk_url', '');
$apkUrl = card_public_url($apkRaw);
// se for path local de upload de APK
if ($apkUrl === '' && $apkRaw !== '') {
    $apkUrl = $apkRaw;
}
if ($apkRaw !== '' && !str_starts_with($apkRaw, 'http') && is_file(PANEL_ROOT . '/assets/apk/' . basename($apkRaw))) {
    $apkUrl = rtrim(PANEL_PUBLIC_URL, '/') . '/assets/apk/' . rawurlencode(basename($apkRaw));
}

json_out([
    'ok' => true,
    'login_dns' => $dns,
    'force_dns' => $force,
    'dashboard_background' => card_public_url($dashboardBg),
    'login_background' => card_public_url($loginBg),
    'cards' => [
        'live' => card_public_url($live),
        'movies' => card_public_url($movies),
        'series' => card_public_url($series),
    ],
    'shortcuts' => $shortcuts,
    // Atualização do app (OTA)
    'app_version_latest' => setting_get('app_version_latest', ''),
    'app_version_min' => setting_get('app_version_min', ''),
    'app_apk_url' => $apkUrl,
    'app_update_message' => setting_get('app_update_message', ''),
    'app_update_force' => setting_get('app_update_force', '0') === '1',
    'server_time' => date('c'),
    'panel' => setting_get('panel_name', 'LIBEROU TV Panel'),
]);
