<?php
/**
 * LIBEROU TV — Painel de controle
 * Defaults apontam para produção. Pode sobrescrever com env no EasyPanel:
 *   LIBEROU_PANEL_URL, LIBEROU_API_TOKEN
 */
declare(strict_types=1);

define('PANEL_ROOT', dirname(__DIR__));
define('PANEL_DATA', PANEL_ROOT . '/data');
// Pasta pública de imagens (servida em /assets/cards/...)
define('PANEL_UPLOADS', PANEL_ROOT . '/assets/cards');
define('PANEL_DB', PANEL_DATA . '/panel.sqlite');

// URL pública real do painel (sem barra no final)
define(
    'PANEL_PUBLIC_URL',
    rtrim((string) (getenv('LIBEROU_PANEL_URL') ?: 'https://painel.liberoutv.online'), '/')
);

// Token secreto — deve ser o mesmo do APK (PanelClient.API_TOKEN)
define(
    'API_TOKEN',
    (string) (getenv('LIBEROU_API_TOKEN') ?: 'Le4lzASyjli5gJhR3D1zMjeKqMjakFLuBD0wHu0i1oZ6OZbKZEq3HcL8Alcmgjk9')
);

define('ADMIN_USER', 'admin');
define('ADMIN_PASS_HASH', password_hash('admin123', PASSWORD_DEFAULT));

// Se o PHP já emitiu warning (ex.: POST > post_max_size), headers já foram
// enviados — session_start() não pode rodar e não deve quebrar a página.
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

// Garante pastas graváveis (Docker/EasyPanel)
define('PANEL_APK_DIR', PANEL_ROOT . '/assets/apk');
foreach ([PANEL_DATA, PANEL_UPLOADS, PANEL_APK_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
