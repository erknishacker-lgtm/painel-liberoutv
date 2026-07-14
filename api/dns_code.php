<?php
/**
 * GET /api/dns_code.php?code=BRINDE01
 * Header: X-Api-Token: <token>
 *
 * Resolve código de lista secundária → DNS do provedor.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_out(['ok' => true]);
}

if (!api_token_ok()) {
    json_out(['ok' => false, 'error' => 'unauthorized'], 401);
}

$raw = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$code = strtoupper($raw);
$code = preg_replace('/[^A-Z0-9\-_]/', '', $code) ?? '';

if ($code === '' || strlen($code) < 2) {
    json_out(['ok' => false, 'error' => 'missing_code', 'message' => 'Informe o código'], 400);
}

$pdo = panel_db();
// case-insensitive match + trim
$stmt = $pdo->prepare(
    'SELECT code, dns_url, label, active FROM secondary_dns
     WHERE UPPER(TRIM(code)) = ? LIMIT 1'
);
$stmt->execute([$code]);
$row = $stmt->fetch();

if (!$row) {
    json_out([
        'ok' => false,
        'error' => 'not_found',
        'message' => 'Código inválido',
        'code_received' => $code,
    ], 404);
}

if ((int) $row['active'] !== 1) {
    json_out(['ok' => false, 'error' => 'inactive', 'message' => 'Código desativado'], 403);
}

$dns = (string) $row['dns_url'];
if ($dns !== '' && !str_ends_with($dns, '/')) {
    $dns .= '/';
}

json_out([
    'ok' => true,
    'code' => (string) $row['code'],
    'dns_url' => $dns,
    'label' => (string) ($row['label'] ?? ''),
]);
