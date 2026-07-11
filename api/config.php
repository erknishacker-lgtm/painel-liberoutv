<?php
/**
 * GET /api/config.php
 * App lê DNS principal + URLs dos cards.
 * Header opcional: X-Api-Token
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_out(['ok' => true]);
}

// Token opcional em leitura (pode abrir se quiser; por padrão exige)
// Comente a checagem se preferir config pública:
if (!api_token_ok()) {
    // permite leitura sem token se force_open=1 no settings? mantém aberto para o app com token embutido
    // json_out(['ok' => false, 'error' => 'token'], 401);
}

$dns = setting_get('login_dns', '');
$force = setting_get('force_dns', '1') === '1';

$live = setting_get('card_live', '');
$movies = setting_get('card_movies', '');
$series = setting_get('card_series', '');

json_out([
    'ok' => true,
    'login_dns' => $dns,
    'force_dns' => $force,
    'cards' => [
        'live' => card_public_url($live),
        'movies' => card_public_url($movies),
        'series' => card_public_url($series),
    ],
    'server_time' => date('c'),
    'panel' => setting_get('panel_name', 'LIBEROU TV Panel'),
]);
