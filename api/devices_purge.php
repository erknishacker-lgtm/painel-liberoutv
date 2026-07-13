<?php
/**
 * POST /api/devices_purge.php
 * Remove dispositivos de teste conhecidos (token API).
 * Body JSON opcional: { "keys": ["..."], "also_patterns": true }
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_out(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'method'], 405);
}

if (!api_token_ok()) {
    json_out(['ok' => false, 'error' => 'token'], 401);
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

// Chaves de teste criadas em diagnóstico
$keys = $data['keys'] ?? [
    'diag123',
    'real-test-2',
];
if (!is_array($keys)) {
    $keys = [];
}
$keys = array_values(array_filter(array_map('strval', $keys)));

$pdo = panel_db();
$removed = [];

foreach ($keys as $key) {
    $key = trim($key);
    if ($key === '') {
        continue;
    }
    $stmt = $pdo->prepare('DELETE FROM devices WHERE device_key = ? OR android_id = ? OR mac = ?');
    $stmt->execute([$key, $key, $key]);
    if ($stmt->rowCount() > 0) {
        $removed[] = $key;
    }
}

// Padrões óbvios de teste (demo / diag / AA:BB / "test test")
if (($data['also_patterns'] ?? true) !== false) {
    $stmt = $pdo->prepare(<<<SQL
DELETE FROM devices WHERE
  lower(username) IN ('demo', 'diag')
  OR lower(mac) IN ('aa:bb', 'test')
  OR (lower(model) = 'test' AND lower(manufacturer) = 'test')
  OR (lower(model) = 'mibox' AND lower(username) = 'demo')
  OR device_key LIKE 'diag%'
  OR device_key LIKE 'real-test%'
SQL);
    $stmt->execute();
    $n = $stmt->rowCount();
    if ($n > 0) {
        $removed[] = "patterns:$n";
    }
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();

json_out([
    'ok' => true,
    'removed' => $removed,
    'devices_remaining' => $total,
    'server_time' => date('c'),
]);
