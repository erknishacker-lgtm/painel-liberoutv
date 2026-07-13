<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$msg = '';
$err = '';

$apkDir = PANEL_ROOT . '/assets/apk';
if (!is_dir($apkDir)) {
    @mkdir($apkDir, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $latest = trim((string) ($_POST['app_version_latest'] ?? ''));
        $min = trim((string) ($_POST['app_version_min'] ?? ''));
        $message = trim((string) ($_POST['app_update_message'] ?? ''));
        $force = !empty($_POST['app_update_force']) ? '1' : '0';
        $url = trim((string) ($_POST['app_apk_url'] ?? ''));

        if ($latest === '' && $min === '') {
            throw new RuntimeException('Informe ao menos a versão mais recente ou a versão mínima.');
        }

        // Upload de APK
        if (!empty($_FILES['app_apk_file']['name'])
            && (int) ($_FILES['app_apk_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) $_FILES['app_apk_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Falha no upload do APK (arquivo grande demais ou erro de rede).');
            }
            $tmp = $_FILES['app_apk_file']['tmp_name'];
            $orig = (string) $_FILES['app_apk_file']['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext !== 'apk') {
                throw new RuntimeException('Envie um arquivo .apk');
            }
            // validação básica: ZIP/APK magic
            $fh = fopen($tmp, 'rb');
            $magic = $fh ? fread($fh, 2) : '';
            if ($fh) {
                fclose($fh);
            }
            if ($magic !== 'PK') {
                throw new RuntimeException('Arquivo não parece um APK válido.');
            }
            $name = 'liberou_' . preg_replace('/[^0-9A-Za-z._-]/', '', $latest ?: 'update') . '_' . date('Ymd_His') . '.apk';
            $dest = $apkDir . '/' . $name;
            if (!move_uploaded_file($tmp, $dest)) {
                throw new RuntimeException('Não foi possível salvar o APK em assets/apk.');
            }
            setting_set('app_apk_file', $name);
            $url = rtrim(PANEL_PUBLIC_URL, '/') . '/assets/apk/' . rawurlencode($name);
        }

        if ($url !== '' && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            throw new RuntimeException('URL do APK deve começar com https://');
        }
        if ($url === '' && setting_get('app_apk_file', '') !== '') {
            $url = rtrim(PANEL_PUBLIC_URL, '/') . '/assets/apk/' . rawurlencode(setting_get('app_apk_file', ''));
        }
        if ($url === '') {
            throw new RuntimeException('Envie um APK ou informe a URL pública do arquivo.');
        }

        setting_set('app_version_latest', $latest);
        setting_set('app_version_min', $min);
        setting_set('app_update_message', $message);
        setting_set('app_update_force', $force);
        setting_set('app_apk_url', $url);

        $msg = 'Atualização configurada. Apps antigos verão o popup ao abrir (Splash/login/dashboard).';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$latest = setting_get('app_version_latest', '');
$min = setting_get('app_version_min', '');
$message = setting_get('app_update_message', '');
$force = setting_get('app_update_force', '0') === '1';
$url = setting_get('app_apk_url', '');
$file = setting_get('app_apk_file', '');
if ($url === '' && $file !== '') {
    $url = rtrim(PANEL_PUBLIC_URL, '/') . '/assets/apk/' . rawurlencode($file);
}

layout_header('Atualização do app', 'app_update');
?>
  <h1>Atualização do app (OTA)</h1>
  <p class="sub">Quando o cliente abrir o app, se a versão for antiga aparece um popup para baixar e instalar o APK novo.</p>

  <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="grid 2">
    <div class="card" style="grid-column:1/-1">
      <h2>Como funciona</h2>
      <ol class="sub" style="margin:0;padding-left:18px;line-height:1.6">
        <li>Você sobe o APK novo (ou cola a URL) e a versão (ex: <span class="mono">1.1.5</span>).</li>
        <li>Cliente com versão menor vê o popup ao abrir.</li>
        <li>Se marcar <strong>obrigatória</strong> (ou preencher versão mínima), ele não consegue “pular”.</li>
        <li>O Android ainda pede confirmar a instalação (regra do sistema).</li>
      </ol>
    </div>

    <div class="card">
      <h2>Versões</h2>
      <label>Versão mais recente (ex: 1.1.5)</label>
      <input type="text" name="app_version_latest" value="<?= htmlspecialchars($latest) ?>" placeholder="1.1.5" required>

      <label>Versão mínima obrigatória (opcional)</label>
      <input type="text" name="app_version_min" value="<?= htmlspecialchars($min) ?>" placeholder="1.1.4">
      <p class="sub">Quem estiver <em>abaixo</em> desta versão é forçado a atualizar.</p>

      <label style="display:flex;align-items:center;gap:8px;margin-top:12px">
        <input type="checkbox" name="app_update_force" value="1" <?= $force ? 'checked' : '' ?>>
        Forçar atualização (sem botão “Depois”)
      </label>
    </div>

    <div class="card">
      <h2>Arquivo APK</h2>
      <label>Upload do APK</label>
      <input type="file" name="app_apk_file" accept=".apk,application/vnd.android.package-archive">

      <label>Ou URL pública do APK (https://…)</label>
      <input type="url" name="app_apk_url" value="<?= htmlspecialchars($url) ?>" placeholder="https://.../LIBEROU.apk">

      <?php if ($url): ?>
        <p class="mono" style="margin-top:12px;word-break:break-all"><?= htmlspecialchars($url) ?></p>
      <?php endif; ?>
    </div>

    <div class="card" style="grid-column:1/-1">
      <h2>Mensagem do popup</h2>
      <label>Texto (opcional)</label>
      <textarea name="app_update_message" rows="3" placeholder="Nova versão com correções. Toque em Atualizar agora."><?= htmlspecialchars($message) ?></textarea>
      <div style="margin-top:16px">
        <button class="btn primary" type="submit">Salvar atualização</button>
      </div>
    </div>
  </form>

  <div class="card" style="margin-top:16px">
    <h2>API (o app lê isto)</h2>
    <p class="mono" style="margin:0">app_version_latest, app_version_min, app_apk_url, app_update_force, app_update_message em <span class="mono">/api/config.php</span></p>
  </div>
<?php layout_footer(); ?>
