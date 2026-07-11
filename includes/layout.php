<?php
declare(strict_types=1);

function layout_header(string $title, string $active = ''): void
{
    $links = [
        'index' => ['index.php', 'Início'],
        'dns' => ['dns.php', 'DNS Login'],
        'devices' => ['devices.php', 'Dispositivos'],
        'cards' => ['cards.php', 'Cards'],
        'shortcuts' => ['shortcuts.php', 'Atalhos'],
    ];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> · LIBEROU Panel</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <nav class="nav">
    <div class="brand"><span>LIBEROU</span> TV Panel</div>
    <div class="links">
      <?php foreach ($links as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
      <a href="logout.php">Sair</a>
    </div>
  </nav>
  <div class="wrap">
<?php
}

function layout_footer(): void
{
    ?>
    <p class="foot">LIBEROU TV · painel local/VPS · API em <span class="mono">/api/config.php</span> e <span class="mono">/api/heartbeat.php</span></p>
  </div>
</body>
</html>
<?php
}
