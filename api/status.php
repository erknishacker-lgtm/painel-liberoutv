<?php
/**
 * GET /api/status.php
 * Diagnóstico rápido (token obrigatório).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_out(['ok' => true]);
}

if (!api_token_ok()) {
    json_out(['ok' => false, 'error' => 'token'], 401);
}

$pdo = panel_db();
$devices = (int) $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
$online = 0;
$now = time();
foreach ($pdo->query('SELECT last_seen FROM devices') as $d) {
    $ts = strtotime((string) $d['last_seen']);
    if ($ts && ($now - $ts) <= 15 * 60) {
        $online++;
    }
}

$uploadsOk = is_dir(PANEL_UPLOADS) && is_writable(PANEL_UPLOADS);
$dataOk = is_dir(PANEL_DATA) && is_writable(PANEL_DATA);
$dbOk = is_file(PANEL_DB) ? is_writable(PANEL_DB) : $dataOk;

$cardFiles = 0;
if (is_dir(PANEL_UPLOADS)) {
    foreach (scandir(PANEL_UPLOADS) ?: [] as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        if (is_file(PANEL_UPLOADS . '/' . $f)) {
            $cardFiles++;
        }
    }
}

json_out([
    'ok' => true,
    'panel_url' => PANEL_PUBLIC_URL,
    'db_writable' => $dbOk,
    'data_writable' => $dataOk,
    'uploads_writable' => $uploadsOk,
    'upload_files' => $cardFiles,
    'devices_total' => $devices,
    'devices_online_15m' => $online,
    'settings' => [
        'login_dns' => setting_get('login_dns', ''),
        'card_live' => setting_get('card_live', '') !== '',
        'card_movies' => setting_get('card_movies', '') !== '',
        'card_series' => setting_get('card_series', '') !== '',
        'shortcut_1' => setting_get('shortcut_1_image', '') !== '',
        'shortcut_2' => setting_get('shortcut_2_image', '') !== '',
        'shortcut_3' => setting_get('shortcut_3_image', '') !== '',
    ],
    'server_time' => date('c'),
]);
