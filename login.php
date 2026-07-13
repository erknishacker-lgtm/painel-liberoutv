<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if (auth_attempt($user, $pass)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Usuário ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · LIBEROU Panel</title>
  <meta name="theme-color" content="#0a0a0b">
  <link rel="icon" href="assets/logo-liberou-nav.png" type="image/png">
  <link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
  <div class="login-page">
    <div class="card login-box">
      <img class="login-logo" src="assets/logo-liberou.png" alt="LIBEROU TV" width="800" height="312">
      <p class="login-title">Painel de controle</p>
      <p class="sub">DNS, dispositivos, cards e atalhos do app</p>
      <?php if ($error): ?><div class="alert err" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="on">
        <label for="username">Usuário</label>
        <input id="username" type="text" name="username" value="admin" required autocomplete="username">
        <label for="password">Senha</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button class="btn primary block" type="submit">Entrar</button>
      </form>
      <p class="foot mono" style="border:0;margin-top:16px;padding-top:0">Padrão: admin / admin123 — troque após o primeiro acesso</p>
    </div>
  </div>
</body>
</html>
