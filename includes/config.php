<?php
/**
 * LIBEROU TV — Painel de controle
 * Ajuste PANEL_PUBLIC_URL para a URL pública real do painel (sem barra no final).
 */
declare(strict_types=1);

define('PANEL_ROOT', dirname(__DIR__));
define('PANEL_DATA', PANEL_ROOT . '/data');
// Pasta pública (servida pelo Apache/Nginx junto com assets)
define('PANEL_UPLOADS', PANEL_ROOT . '/assets/cards');
define('PANEL_DB', PANEL_DATA . '/panel.sqlite');

// >>> ALTERE ISSO no servidor (ex: https://seudominio.com/liberou-panel)
define('PANEL_PUBLIC_URL', getenv('LIBEROU_PANEL_URL') ?: 'https://SEU-DOMINIO.com/liberou-panel');

// Token secreto usado pelo app nas APIs (também no APK: PanelClient)
define('API_TOKEN', getenv('LIBEROU_API_TOKEN') ?: 'liberou-panel-token-change-me');

// Login admin padrão (troque no primeiro acesso)
define('ADMIN_USER', 'admin');
// senha: admin123  (hash gerado abaixo; troque em devices/dns se quiser)
define('ADMIN_PASS_HASH', password_hash('admin123', PASSWORD_DEFAULT));

// Sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');
