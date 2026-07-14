<?php
declare(strict_types=1);

function layout_header(string $title, string $active = ''): void
{
    $links = [
        'index' => ['index.php', 'Início'],
        'dns' => ['dns.php', 'DNS Login'],
        'secondary_dns' => ['secondary_dns.php', 'DNS secundários'],
        'devices' => ['devices.php', 'Dispositivos'],
        'cards' => ['cards.php', 'Cards'],
        'shortcuts' => ['shortcuts.php', 'Atalhos'],
        'app_update' => ['app_update.php', 'Atualizar app'],
    ];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> · LIBEROU Panel</title>
  <meta name="theme-color" content="#0a0a0b">
  <link rel="icon" href="assets/logo-liberou-nav.png" type="image/png">
  <link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
  <nav class="nav" aria-label="Principal">
    <a class="brand" href="index.php">
      <img class="brand-logo" src="assets/logo-liberou-nav.png" alt="LIBEROU TV" width="160" height="62">
      <div class="brand-text">
        <strong>Painel</strong>
        <span>Controle remoto do app</span>
      </div>
    </a>
    <div class="links">
      <?php foreach ($links as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
      <a href="logout.php" class="nav-exit">Sair</a>
    </div>
  </nav>
  <main class="wrap">
<?php
}

function layout_footer(): void
{
    ?>
    <p class="foot">LIBEROU TV · painel operacional · API <span class="mono">/api/config.php</span> · <span class="mono">/api/heartbeat.php</span></p>
  </main>
</body>
</html>
<?php
}
