<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$q = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));

$sql = 'SELECT * FROM devices WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (mac LIKE ? OR android_id LIKE ? OR username LIKE ? OR model LIKE ? OR device_key LIKE ? OR ip LIKE ?)';
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}
if ($type === 'tv') {
    $sql .= " AND lower(device_type) LIKE '%tv%'";
} elseif ($type === 'mobile') {
    $sql .= " AND (lower(device_type) LIKE '%mobile%' OR lower(device_type) LIKE '%phone%')";
}
$sql .= ' ORDER BY last_seen DESC LIMIT 500';

$stmt = panel_db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$now = time();

layout_header('Dispositivos', 'devices');
?>
  <h1>Dispositivos conectados</h1>
  <p class="sub">Cada app envia heartbeat com MAC/Android ID, tipo (TV ou celular), usuário e URL do servidor.</p>

  <?php if (!$rows): ?>
  <div class="alert err" style="margin-bottom:16px">
    <strong>Nenhum aparelho na lista.</strong>
    <ul style="margin:8px 0 0 18px;line-height:1.5">
      <li>O app só reporta com o <strong>APK Liberou atualizado</strong> (tem o PanelClient).</li>
      <li>Abra o app até o <strong>dashboard</strong> (tela dos botões), com internet.</li>
      <li>Token da API no servidor deve ser o mesmo do APK (veja Início → API do app).</li>
      <li>No EasyPanel, monte volume em <span class="mono">/var/www/html/data</span> para não apagar a lista em cada redeploy.</li>
    </ul>
  </div>
  <?php endif; ?>

  <form method="get" class="card" style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:end">
    <div style="flex:2;min-width:200px">
      <label>Buscar</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="MAC, usuário, modelo, IP...">
    </div>
    <div style="flex:1;min-width:140px">
      <label>Tipo</label>
      <select name="type">
        <option value="">Todos</option>
        <option value="tv" <?= $type === 'tv' ? 'selected' : '' ?>>TV / Box</option>
        <option value="mobile" <?= $type === 'mobile' ? 'selected' : '' ?>>Celular</option>
      </select>
    </div>
    <button class="btn primary" type="submit">Filtrar</button>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Status</th>
            <th>Tipo</th>
            <th>Modelo</th>
            <th>Usuário</th>
            <th>MAC</th>
            <th>Android ID</th>
            <th>Servidor</th>
            <th>App</th>
            <th>IP</th>
            <th>Primeiro</th>
            <th>Último</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="11">Nenhum dispositivo encontrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r):
            $ts = strtotime((string) $r['last_seen']);
            $isOn = $ts && ($now - $ts) <= 15 * 60;
            $t = strtolower((string) $r['device_type']);
            $badge = str_contains($t, 'tv') ? 'tv' : 'mobile';
        ?>
          <tr>
            <td><span class="badge <?= $isOn ? 'online' : 'offline' ?>"><?= $isOn ? 'Online' : 'Offline' ?></span></td>
            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars((string) $r['device_type']) ?></span></td>
            <td><?= htmlspecialchars(trim(($r['manufacturer'] ?? '') . ' ' . ($r['model'] ?? ''))) ?></td>
            <td><?= htmlspecialchars((string) ($r['username'] ?: '—')) ?></td>
            <td class="mono"><?= htmlspecialchars((string) ($r['mac'] ?: '—')) ?></td>
            <td class="mono"><?= htmlspecialchars((string) ($r['android_id'] ?: '—')) ?></td>
            <td class="mono"><?= htmlspecialchars((string) ($r['server_url'] ?: '—')) ?></td>
            <td><?= htmlspecialchars((string) ($r['app_version'] ?: '—')) ?> / Android <?= htmlspecialchars((string) ($r['android_version'] ?: '?')) ?></td>
            <td class="mono"><?= htmlspecialchars((string) ($r['ip'] ?: '—')) ?></td>
            <td><?= htmlspecialchars((string) $r['first_seen']) ?></td>
            <td><?= htmlspecialchars((string) $r['last_seen']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php layout_footer(); ?>
