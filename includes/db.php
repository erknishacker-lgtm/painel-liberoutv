<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function panel_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(PANEL_DATA)) {
        mkdir(PANEL_DATA, 0775, true);
    }
    if (!is_dir(PANEL_UPLOADS)) {
        mkdir(PANEL_UPLOADS, 0775, true);
    }

    $pdo = new PDO('sqlite:' . PANEL_DB, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
    panel_migrate($pdo);
    return $pdo;
}

function panel_migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS devices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  device_key TEXT NOT NULL UNIQUE,
  mac TEXT,
  android_id TEXT,
  device_type TEXT,
  model TEXT,
  manufacturer TEXT,
  android_version TEXT,
  app_version TEXT,
  username TEXT,
  server_url TEXT,
  ip TEXT,
  first_seen TEXT NOT NULL,
  last_seen TEXT NOT NULL,
  raw_json TEXT
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL
);
SQL);

    // defaults
    $defaults = [
        'login_dns' => 'http://srv.example.com:80/',
        'card_live' => '',
        'card_movies' => '',
        'card_series' => '',
        'force_dns' => '1',
        'panel_name' => 'LIBEROU TV Panel',
        // 3 atalhos de baixo do dashboard
        'shortcut_1_label' => 'Premiere',
        'shortcut_1_cat' => 'PREMIERE',
        'shortcut_1_type' => 'live',
        'shortcut_1_image' => '',
        'shortcut_2_label' => 'Novelas',
        'shortcut_2_cat' => 'TELENOVELAS',
        'shortcut_2_type' => 'series',
        'shortcut_2_image' => '',
        'shortcut_3_label' => 'Desenhos',
        'shortcut_3_cat' => 'ANIMACAO',
        'shortcut_3_type' => 'series',
        'shortcut_3_image' => '',
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value, updated_at) VALUES (?, ?, ?)');
    $now = date('c');
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v, $now]);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')
            ->execute([ADMIN_USER, $hash]);
    }
}

function setting_get(string $key, string $default = ''): string
{
    $stmt = panel_db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['value'] : $default;
}

function setting_set(string $key, string $value): void
{
    $stmt = panel_db()->prepare(
        'INSERT INTO settings (key, value, updated_at) VALUES (?, ?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
    );
    $stmt->execute([$key, $value, date('c')]);
}

function settings_all(): array
{
    $rows = panel_db()->query('SELECT key, value, updated_at FROM settings')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['key']] = $r;
    }
    return $out;
}
