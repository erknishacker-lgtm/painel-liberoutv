<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$msg = '';
$err = '';

$apkDir = defined('PANEL_APK_DIR') ? PANEL_APK_DIR : (PANEL_ROOT . '/assets/apk');
if (!is_dir($apkDir)) {
    @mkdir($apkDir, 0775, true);
}

/**
 * Traduz código de erro do PHP upload em mensagem legível.
 */
function apk_upload_error_message(int $code): string
{
    $uploadMax = ini_get('upload_max_filesize') ?: '?';
    $postMax = ini_get('post_max_size') ?: '?';
    return match ($code) {
        UPLOAD_ERR_INI_SIZE => "APK grande demais para o limite do PHP (upload_max_filesize={$uploadMax}). Aumente no servidor ou use URL externa.",
        UPLOAD_ERR_FORM_SIZE => 'APK grande demais para o limite do formulário.',
        UPLOAD_ERR_PARTIAL => 'Upload incompleto (rede caiu no meio). Tente de novo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Servidor sem pasta temporária (tmp).',
        UPLOAD_ERR_CANT_WRITE => 'Servidor não conseguiu gravar o arquivo temporário.',
        UPLOAD_ERR_EXTENSION => 'Uma extensão PHP bloqueou o upload.',
        default => "Falha no upload do APK (código {$code}). Limites: upload={$uploadMax}, post={$postMax}.",
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Quando o POST inteiro estoura post_max_size, o PHP zera $_POST e $_FILES
        // e emite warning em "Unknown on line 0" (Content-Length exceeds limit).
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            $postMax = ini_get('post_max_size') ?: '?';
            $mb = round($contentLength / 1048576, 1);
            throw new RuntimeException(
                "Arquivo grande demais para o servidor: enviou ~{$mb}MB, mas post_max_size={$postMax}. "
                . 'O APK Liberou tem ~37–40MB — o container ainda está com limite antigo (ex.: 36M). '
                . 'Faça REDEPLOY do painel com o Dockerfile atual (200M) ou use: suba o APK por FTP/SCP em assets/apk/ e cole a URL no campo abaixo.'
            );
        }

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
            $fileErr = (int) ($_FILES['app_apk_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fileErr !== UPLOAD_ERR_OK) {
                throw new RuntimeException(apk_upload_error_message($fileErr));
            }

            if (!is_dir($apkDir) && !@mkdir($apkDir, 0775, true)) {
                throw new RuntimeException('Pasta de APK não existe e não pôde ser criada: ' . $apkDir);
            }
            if (!is_writable($apkDir)) {
                throw new RuntimeException(
                    'Pasta de APK sem permissão de escrita: ' . $apkDir
                    . ' (no Docker/EasyPanel monte o volume e rode chown www-data).'
                );
            }

            $tmp = (string) $_FILES['app_apk_file']['tmp_name'];
            $orig = (string) $_FILES['app_apk_file']['name'];
            $size = (int) ($_FILES['app_apk_file']['size'] ?? 0);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext !== 'apk') {
                throw new RuntimeException('Envie um arquivo .apk (recebido: ' . ($ext !== '' ? $ext : 'sem extensão') . ').');
            }
            if ($size > 0 && $size < 1000) {
                throw new RuntimeException('Arquivo muito pequeno para ser um APK válido.');
            }
            // validação básica: ZIP/APK magic
            $fh = @fopen($tmp, 'rb');
            $magic = $fh ? (string) fread($fh, 2) : '';
            if ($fh) {
                fclose($fh);
            }
            if ($magic !== 'PK') {
                throw new RuntimeException('Arquivo não parece um APK válido (precisa começar como ZIP).');
            }

            $safeVer = preg_replace('/[^0-9A-Za-z._-]/', '', $latest !== '' ? $latest : 'update') ?: 'update';
            $name = 'liberou_' . $safeVer . '_' . date('Ymd_His') . '.apk';
            $dest = $apkDir . '/' . $name;
            if (!@move_uploaded_file($tmp, $dest)) {
                // fallback copy (alguns hosts restringem move_uploaded_file entre mounts)
                if (!@is_uploaded_file($tmp) || !@copy($tmp, $dest)) {
                    throw new RuntimeException(
                        'Não foi possível salvar o APK em ' . $dest
                        . '. Verifique permissão de escrita em assets/apk.'
                    );
                }
                @unlink($tmp);
            }
            @chmod($dest, 0644);

            setting_set('app_apk_file', $name);
            $url = rtrim(PANEL_PUBLIC_URL, '/') . '/assets/apk/' . rawurlencode($name);
        }

        if ($url !== '' && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            throw new RuntimeException('URL do APK deve começar com https:// (ou http://).');
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

$uploadMax = (string) (ini_get('upload_max_filesize') ?: '?');
$postMax = (string) (ini_get('post_max_size') ?: '?');
$apkWritable = is_dir($apkDir) && is_writable($apkDir);

layout_header('Atualização do app', 'app_update');
?>
  <h1>Atualização do app (OTA)</h1>
  <p class="sub">Quando o cliente abrir o app, se a versão for antiga aparece um popup para baixar e instalar o APK novo.</p>

  <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <h2>Status do servidor (upload)</h2>
    <table class="data" style="width:100%">
      <tr><td>Limite por arquivo (upload_max_filesize)</td><td class="mono"><?= htmlspecialchars($uploadMax) ?></td></tr>
      <tr><td>Limite do envio (post_max_size)</td><td class="mono"><?= htmlspecialchars($postMax) ?></td></tr>
      <tr><td>Pasta APK</td><td class="mono"><?= htmlspecialchars($apkDir) ?> — <?= $apkWritable ? 'gravável' : 'SEM PERMISSÃO' ?></td></tr>
    </table>
    <?php if (!$apkWritable): ?>
      <p class="sub" style="color:#f87171;margin-top:8px">A pasta de APK não aceita escrita. No EasyPanel monte o volume <span class="mono">/var/www/html/assets/apk</span> e garanta dono <span class="mono">www-data</span>.</p>
    <?php endif; ?>
    <?php
      $uploadBytes = return_bytes($uploadMax);
      $postBytes = return_bytes($postMax);
      if (($uploadBytes > 0 && $uploadBytes < 45 * 1024 * 1024) || ($postBytes > 0 && $postBytes < 45 * 1024 * 1024)):
    ?>
      <p class="sub" style="color:#fbbf24;margin-top:8px">
        <strong>Atenção:</strong> o servidor ainda limita upload/POST em menos de 45MB
        (seu APK tem ~37–40MB). O Dockerfile do projeto já pede 200M — é preciso
        <strong>rebuild + redeploy</strong> do container no EasyPanel. Enquanto isso,
        use URL pública ou copie o APK para <span class="mono">assets/apk/</span> no servidor.
      </p>
    <?php endif; ?>
  </div>

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
      <label>Upload do APK (máx. servidor: <?= htmlspecialchars($uploadMax) ?>)</label>
      <input type="file" name="app_apk_file" accept=".apk,application/vnd.android.package-archive">

      <label>Ou URL pública do APK (https://…)</label>
      <input type="url" name="app_apk_url" value="<?= htmlspecialchars($url) ?>" placeholder="https://.../LIBEROU.apk">

      <?php if ($url): ?>
        <p class="mono" style="margin-top:12px;word-break:break-all"><?= htmlspecialchars($url) ?></p>
        <p class="sub"><a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener">Testar download do APK</a></p>
      <?php endif; ?>
      <?php if ($file): ?>
        <p class="sub">Arquivo no servidor: <span class="mono"><?= htmlspecialchars($file) ?></span></p>
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
<?php
layout_footer();

/**
 * Converte "128M" / "64K" em bytes (0 se desconhecido).
 */
function return_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '' || $val === '?') {
        return 0;
    }
    $last = strtolower($val[strlen($val) - 1]);
    $num = (float) $val;
    return (int) match ($last) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}
