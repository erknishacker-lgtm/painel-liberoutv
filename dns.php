<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dns = trim((string) ($_POST['login_dns'] ?? ''));
    $force = isset($_POST['force_dns']) ? '1' : '0';
    if ($dns === '') {
        $err = 'Informe a DNS / URL principal de login.';
    } else {
        // normaliza: garante barra final se for host simples
        if (!preg_match('#^https?://#i', $dns)) {
            $dns = 'http://' . $dns;
        }
        setting_set('login_dns', $dns);
        setting_set('force_dns', $force);
        $msg = 'DNS salva. Os apps aplicam no próximo abrir / sync.';
    }
}

$dns = setting_get('login_dns');
$force = setting_get('force_dns', '1') === '1';

layout_header('DNS Login', 'dns');
?>
  <h1>DNS principal de login</h1>
  <p class="sub">É a “porta de entrada” do portal IPTV. O app grava isso nas preferências de login (serverUrlMAG / loginPrefsserverurl).</p>

  <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card" style="max-width:720px">
    <form method="post">
      <label>URL / DNS do servidor (ex: http://dns.seudominio.com:8080/)</label>
      <input type="text" name="login_dns" value="<?= htmlspecialchars($dns) ?>" placeholder="http://exemplo.com:80/" required>
      <label style="display:flex;align-items:center;gap:8px;color:var(--text);margin-bottom:16px">
        <input type="checkbox" name="force_dns" <?= $force ? 'checked' : '' ?> style="width:auto;margin:0">
        Forçar esta DNS no app (sobrescreve a local)
      </label>
      <button class="btn primary" type="submit">Salvar DNS</button>
    </form>
  </div>
<?php layout_footer(); ?>
