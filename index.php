<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$pdo = panel_db();
$total = (int) $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
$all = $pdo->query('SELECT device_type, last_seen FROM devices')->fetchAll();
$online = 0;
$tv = 0;
$mobile = 0;
$now = time();
foreach ($all as $d) {
    $ts = strtotime((string) $d['last_seen']);
    if ($ts && ($now - $ts) <= 15 * 60) {
        $online++;
    }
    $t = strtolower((string) $d['device_type']);
    if (str_contains($t, 'tv')) {
        $tv++;
    } elseif (str_contains($t, 'mobile') || str_contains($t, 'phone')) {
        $mobile++;
    }
}

$dns = setting_get('login_dns');
$recent = $pdo->query('SELECT * FROM devices ORDER BY last_seen DESC LIMIT 8')->fetchAll();

layout_header('Início', 'index');
?>
  <div class="page-head">
    <div class="kicker">Operação</div>
    <h1>Central LIBEROU</h1>
    <p class="sub">Controle remoto do app: DNS de login, aparelhos conectados, cards e atalhos do dashboard.</p>
  </div>

  <div class="grid stats">
    <div class="card metric">
      <div class="label">Dispositivos</div>
      <div class="value"><?= $total ?></div>
      <div class="hint">Total já vistos</div>
    </div>
    <div class="card metric">
      <div class="label">Online (~15 min)</div>
      <div class="value"><?= $online ?></div>
      <div class="hint">Heartbeats recentes</div>
    </div>
    <div class="card metric">
      <div class="label">TV / Box</div>
      <div class="value"><?= $tv ?></div>
      <div class="hint">Tipo de tela</div>
    </div>
    <div class="card metric">
      <div class="label">Celular</div>
      <div class="value"><?= $mobile ?></div>
      <div class="hint">Tipo de tela</div>
    </div>
  </div>

  <div class="grid actions">
    <div class="card">
      <h2>DNS principal de login</h2>
      <p class="mono" style="margin:0 0 14px"><?= htmlspecialchars($dns !== '' ? $dns : '(não definido)') ?></p>
      <a class="btn primary" href="dns.php">Alterar DNS</a>
    </div>
    <div class="card">
      <h2>Cards e fundo</h2>
      <p class="sub" style="margin:0 0 14px">Live · Filmes · Séries · fundo do dashboard.</p>
      <a class="btn primary" href="cards.php">Atualizar imagens</a>
    </div>
    <div class="card">
      <h2>Atalhos de baixo</h2>
      <p class="sub" style="margin:0 0 14px">Premiere · Novelas · Desenhos — imagem + categoria.</p>
      <a class="btn primary" href="shortcuts.php">Configurar atalhos</a>
    </div>
  </div>

  <div class="card section-gap">
    <div class="action-row" style="justify-content:space-between;margin-bottom:12px">
      <h2 style="margin:0">Últimos dispositivos</h2>
      <a class="btn" href="devices.php">Ver todos</a>
    </div>
    <?php if (!$recent): ?>
      <p class="sub" style="margin:0">Nenhum aparelho reportou ainda. Abra o app com o painel no ar e o APK atualizado.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Modelo</th>
              <th>Usuário</th>
              <th>MAC / ID</th>
              <th>Visto</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recent as $r):
              $t = strtolower((string) $r['device_type']);
              $badge = str_contains($t, 'tv') ? 'tv' : 'mobile';
              $idShow = $r['mac'] ?: $r['android_id'] ?: $r['device_key'];
          ?>
            <tr>
              <td><span class="badge <?= $badge ?>"><?= htmlspecialchars((string) $r['device_type']) ?></span></td>
              <td><?= htmlspecialchars(trim(($r['manufacturer'] ?? '') . ' ' . ($r['model'] ?? ''))) ?></td>
              <td><?= htmlspecialchars((string) ($r['username'] ?: '—')) ?></td>
              <td class="mono"><?= htmlspecialchars((string) $idShow) ?></td>
              <td><?= htmlspecialchars((string) $r['last_seen']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card section-gap">
    <h2>API do app</h2>
    <p class="mono" style="margin:0 0 6px">Config: <?= htmlspecialchars(rtrim(PANEL_PUBLIC_URL, '/') . '/api/config.php') ?></p>
    <p class="mono" style="margin:0 0 10px">Heartbeat: <?= htmlspecialchars(rtrim(PANEL_PUBLIC_URL, '/') . '/api/heartbeat.php') ?></p>
    <p class="sub" style="margin:0">Token API: <span class="mono"><?= htmlspecialchars(API_TOKEN) ?></span> — o mesmo do APK (PanelClient)</p>
  </div>
<?php layout_footer(); ?>
