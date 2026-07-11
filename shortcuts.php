<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$msg = '';
$err = '';

function handle_shortcut_upload(string $field, string $settingKey): ?string
{
    if (empty($_FILES[$field]['name']) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Falha no upload de {$field}");
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        throw new RuntimeException("Arquivo de {$field} não é imagem válida");
    }
    $ext = match ($info[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
        default => throw new RuntimeException("Formato não suportado em {$field}"),
    };
    $name = $settingKey . '_' . date('Ymd_His') . '.' . $ext;
    $dest = PANEL_UPLOADS . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException("Não foi possível salvar {$field}");
    }
    setting_set($settingKey, $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        for ($i = 1; $i <= 3; $i++) {
            $label = trim((string) ($_POST["label_$i"] ?? ''));
            $cat = trim((string) ($_POST["cat_$i"] ?? ''));
            $type = trim((string) ($_POST["type_$i"] ?? 'series'));
            if ($type !== 'live' && $type !== 'series') {
                $type = 'series';
            }
            if ($label === '') {
                $label = "Atalho $i";
            }
            if ($cat === '') {
                throw new RuntimeException("Atalho $i: informe o nome da categoria (ex: PREMIERE).");
            }
            setting_set("shortcut_{$i}_label", $label);
            setting_set("shortcut_{$i}_cat", $cat);
            setting_set("shortcut_{$i}_type", $type);

            handle_shortcut_upload("image_$i", "shortcut_{$i}_image");

            $url = trim((string) ($_POST["url_$i"] ?? ''));
            if ($url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                setting_set("shortcut_{$i}_image", $url);
            }
        }
        $msg = 'Atalhos salvos. O app aplica no próximo abrir / dashboard.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$slots = [];
for ($i = 1; $i <= 3; $i++) {
    $img = setting_get("shortcut_{$i}_image", '');
    $slots[$i] = [
        'label' => setting_get("shortcut_{$i}_label", "Atalho $i"),
        'cat' => setting_get("shortcut_{$i}_cat", ''),
        'type' => setting_get("shortcut_{$i}_type", 'series'),
        'image' => $img,
        'url' => card_public_url($img),
    ];
}

$hints = [
    1 => 'Botão da esquerda embaixo (ex: Premiere → categoria LIVE)',
    2 => 'Botão do meio embaixo (ex: Novelas → categoria SÉRIE)',
    3 => 'Botão da direita embaixo (ex: Desenhos → categoria SÉRIE)',
];

layout_header('Atalhos', 'shortcuts');
?>
  <h1>Atalhos de baixo do dashboard</h1>
  <p class="sub">Os 3 botões de baixo: imagem + nome da categoria no app + se é Live ou Série. O nome da categoria precisa bater com a pasta/categoria do servidor IPTV.</p>

  <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="grid 2">
    <?php foreach ($slots as $i => $s): ?>
      <div class="card">
        <h2>Atalho <?= (int) $i ?></h2>
        <p class="sub"><?= htmlspecialchars($hints[$i]) ?></p>
        <?php if ($s['url']): ?>
          <img class="preview" src="<?= htmlspecialchars($s['url']) ?>" alt="Atalho <?= (int) $i ?>">
          <p class="mono"><?= htmlspecialchars($s['url']) ?></p>
        <?php else: ?>
          <div class="preview" style="display:grid;place-items:center;color:var(--muted)">Sem imagem (usa a do APK)</div>
        <?php endif; ?>

        <label>Nome no painel (só visual admin)</label>
        <input type="text" name="label_<?= $i ?>" value="<?= htmlspecialchars($s['label']) ?>">

        <label>Categoria no app (ex: PREMIERE, TELENOVELAS, ANIMACAO)</label>
        <input type="text" name="cat_<?= $i ?>" value="<?= htmlspecialchars($s['cat']) ?>" required placeholder="NOME_DA_CATEGORIA">

        <label>Tipo</label>
        <select name="type_<?= $i ?>">
          <option value="live" <?= $s['type'] === 'live' ? 'selected' : '' ?>>Live (canais ao vivo)</option>
          <option value="series" <?= $s['type'] === 'series' ? 'selected' : '' ?>>Série</option>
        </select>

        <label>Upload da imagem do botão</label>
        <input type="file" name="image_<?= $i ?>" accept="image/*">

        <label>Ou URL da imagem (https://...)</label>
        <input type="url" name="url_<?= $i ?>" placeholder="https://...">
      </div>
    <?php endforeach; ?>
    <div style="grid-column:1/-1">
      <button class="btn primary" type="submit">Salvar atalhos</button>
    </div>
  </form>

  <div class="card" style="margin-top:16px">
    <h2>Dica</h2>
    <p class="sub" style="margin:0">A categoria deve ser o mesmo nome (ou bem parecido) que aparece no app na lista de Live ou Séries. Ex.: se no servidor a pasta é “TELENOVELAS”, coloque <span class="mono">TELENOVELAS</span>. Prefira PNG com fundo transparente nos botões de baixo.</p>
  </div>
<?php layout_footer(); ?>
