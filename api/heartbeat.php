<?php
/**
 * POST /api/heartbeat.php
 * App reporta dispositivo (MAC / Android ID / tipo / dados de conta).
 * Body JSON ou form-urlencoded.
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
    $data = $_POST;
}

$mac = trim((string) ($data['mac'] ?? ''));
$androidId = trim((string) ($data['android_id'] ?? ''));
$deviceType = trim((string) ($data['device_type'] ?? 'unknown'));
$model = trim((string) ($data['model'] ?? ''));
$manufacturer = trim((string) ($data['manufacturer'] ?? ''));
$androidVersion = trim((string) ($data['android_version'] ?? ''));
$appVersion = trim((string) ($data['app_version'] ?? ''));
$username = trim((string) ($data['username'] ?? ''));
$serverUrl = trim((string) ($data['server_url'] ?? ''));

$deviceKey = $androidId !== '' ? $androidId : ($mac !== '' ? $mac : ('anon-' . md5(($model . $manufacturer . ($_SERVER['REMOTE_ADDR'] ?? '')))));

$now = date('c');
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (str_contains((string) $ip, ',')) {
    $ip = trim(explode(',', (string) $ip)[0]);
}

$pdo = panel_db();
$existing = $pdo->prepare('SELECT id, first_seen FROM devices WHERE device_key = ?');
$existing->execute([$deviceKey]);
$row = $existing->fetch();

if ($row) {
    $stmt = $pdo->prepare(<<<SQL
UPDATE devices SET
  mac = ?, android_id = ?, device_type = ?, model = ?, manufacturer = ?,
  android_version = ?, app_version = ?, username = ?, server_url = ?, ip = ?,
  last_seen = ?, raw_json = ?
WHERE device_key = ?
SQL);
    $stmt->execute([
        $mac, $androidId, $deviceType, $model, $manufacturer,
        $androidVersion, $appVersion, $username, $serverUrl, $ip,
        $now, $raw !== '' ? $raw : json_encode($data, JSON_UNESCAPED_UNICODE),
        $deviceKey,
    ]);
} else {
    $stmt = $pdo->prepare(<<<SQL
INSERT INTO devices (
  device_key, mac, android_id, device_type, model, manufacturer,
  android_version, app_version, username, server_url, ip, first_seen, last_seen, raw_json
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
SQL);
    $stmt->execute([
        $deviceKey, $mac, $androidId, $deviceType, $model, $manufacturer,
        $androidVersion, $appVersion, $username, $serverUrl, $ip, $now, $now,
        $raw !== '' ? $raw : json_encode($data, JSON_UNESCAPED_UNICODE),
    ]);
}

json_out(['ok' => true, 'device_key' => $deviceKey, 'server_time' => $now]);
