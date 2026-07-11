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
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="login-page">
    <div class="card login-box">
      <div class="brand" style="font-weight:800;margin-bottom:8px"><span style="color:var(--accent)">LIBEROU</span> TV Panel</div>
      <p class="sub">Central de controle do app (DNS, dispositivos e cards)</p>
      <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <label>Usuário</label>
        <input type="text" name="username" value="admin" required autocomplete="username">
        <label>Senha</label>
        <input type="password" name="password" required autocomplete="current-password">
        <button class="btn primary" type="submit" style="width:100%">Entrar</button>
      </form>
      <p class="foot mono">Padrão: admin / admin123 — troque depois de instalar</p>
    </div>
  </div>
</body>
</html>
