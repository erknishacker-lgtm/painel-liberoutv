<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function auth_check(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function auth_attempt(string $user, string $pass): bool
{
    $stmt = panel_db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$user]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($pass, $row['password_hash'])) {
        return false;
    }
    $_SESSION['admin_id'] = (int) $row['id'];
    $_SESSION['admin_user'] = $row['username'];
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function api_token_ok(): bool
{
    $hdr = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    $q = $_GET['token'] ?? '';
    $token = $hdr !== '' ? $hdr : $q;
    return hash_equals(API_TOKEN, (string) $token);
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Token');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function card_public_url(string $filename): string
{
    if ($filename === '') {
        return '';
    }
    if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
        return $filename;
    }
    return rtrim(PANEL_PUBLIC_URL, '/') . '/assets/cards/' . ltrim($filename, '/');
}
