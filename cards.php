<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$msg = '';
$err = '';

function handle_card_upload(string $field, string $settingKey): ?string
{
    if (empty($_FILES[$field]['name']) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $errCode = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errCode !== UPLOAD_ERR_OK) {
        $hint = match ($errCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ' (arquivo grande demais — máx. ~25MB)',
            UPLOAD_ERR_PARTIAL => ' (upload incompleto)',
            UPLOAD_ERR_NO_TMP_DIR => ' (pasta temp ausente no servidor)',
            UPLOAD_ERR_CANT_WRITE => ' (servidor sem permissão de escrita)',
            default => " (código $errCode)",
        };
        throw new RuntimeException("Falha no upload de {$field}{$hint}");
    }
    if (!is_dir(PANEL_UPLOADS) && !@mkdir(PANEL_UPLOADS, 0775, true)) {
        throw new RuntimeException('Pasta de uploads não existe e não pôde ser criada: ' . PANEL_UPLOADS);
    }
    if (!is_writable(PANEL_UPLOADS)) {
        throw new RuntimeException('Pasta de uploads sem permissão de escrita: ' . PANEL_UPLOADS);
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
        default => throw new RuntimeException("Formato de imagem não suportado em {$field}"),
    };
    $name = $settingKey . '_' . date('Ymd_His') . '.' . $ext;
    $dest = PANEL_UPLOADS . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException("Não foi possível salvar {$field} em {$dest}");
    }
    setting_set($settingKey, $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $changed = [];
        foreach ([
            'card_live' => 'card_live',
            'card_movies' => 'card_movies',
            'card_series' => 'card_series',
            'card_dashboard_bg' => 'dashboard_bg',
            'card_login_bg' => 'login_bg',
        ] as $field => $key) {
            $n = handle_card_upload($field, $key);
            if ($n) {
                $changed[] = $key;
            }
        }
        // URL manual alternativa
        foreach ([
            'url_live' => 'card_live',
            'url_movies' => 'card_movies',
            'url_series' => 'card_series',
            'url_dashboard_bg' => 'dashboard_bg',
            'url_login_bg' => 'login_bg',
        ] as $field => $key) {
            $url = trim((string) ($_POST[$field] ?? ''));
            if ($url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                setting_set($key, $url);
                $changed[] = $key;
            }
        }
        if (!empty($_POST['clear_login_bg'])) {
            setting_set('login_bg', '');
            $changed[] = 'login_bg (limpo)';
        }
        if (!empty($_POST['clear_dashboard_bg'])) {
            setting_set('dashboard_bg', '');
            $changed[] = 'dashboard_bg (limpo)';
        }
        $msg = $changed
            ? 'Imagens atualizadas: ' . implode(', ', array_unique($changed)) . '. O app baixa no próximo dashboard/sync.'
            : 'Nada alterado. Envie uma imagem ou URL.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$bgVal = setting_get('dashboard_bg', '');
$bgUrl = card_public_url($bgVal);
$loginBgVal = setting_get('login_bg', '');
$loginBgUrl = card_public_url($loginBgVal);

$cards = [
    'live' => ['key' => 'card_live', 'field' => 'card_live', 'url' => 'url_live', 'title' => 'Live TV', 'hint' => 'Card grande ao lado esquerdo'],
    'movies' => ['key' => 'card_movies', 'field' => 'card_movies', 'url' => 'url_movies', 'title' => 'Filmes (On Demand)', 'hint' => 'Card de filmes'],
    'series' => ['key' => 'card_series', 'field' => 'card_series', 'url' => 'url_series', 'title' => 'Séries', 'hint' => 'Card de séries / catch-up'],
];

layout_header('Cards', 'cards');
?>
  <h1>Cards e fundos</h1>
  <p class="sub">Envie PNG/JPG. O app baixa e aplica no próximo abrir (login ou dashboard).</p>

  <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="grid 2">
    <div class="card" style="grid-column:1/-1">
      <h2>Fundo da tela de login</h2>
      <p class="sub">Imagem de fundo da tela de login (activity_login). Ideal 1920×1080, com a arte à esquerda e área limpa à direita para o formulário.</p>
      <?php if ($loginBgUrl): ?>
        <img class="preview" src="<?= htmlspecialchars($loginBgUrl) ?>" alt="Fundo login" style="max-height:280px;object-fit:cover">
        <p class="mono"><?= htmlspecialchars($loginBgUrl) ?></p>
      <?php else: ?>
        <div class="preview" style="display:grid;place-items:center;color:var(--muted)">Usando fundo embutido no APK (raposa Liberou)</div>
      <?php endif; ?>
      <label>Upload do fundo do login</label>
      <input type="file" name="card_login_bg" accept="image/*">
      <label>Ou URL direta (https://...)</label>
      <input type="url" name="url_login_bg" placeholder="https://...">
      <?php if ($loginBgUrl): ?>
        <label style="display:flex;align-items:center;gap:8px;margin-top:12px">
          <input type="checkbox" name="clear_login_bg" value="1"> Voltar ao fundo embutido no APK
        </label>
      <?php endif; ?>
    </div>

    <div class="card" style="grid-column:1/-1">
      <h2>Fundo do dashboard</h2>
      <p class="sub">Imagem de fundo da tela inicial (main_layout). Use 1920×1080 se possível.</p>
      <?php if ($bgUrl): ?>
        <img class="preview" src="<?= htmlspecialchars($bgUrl) ?>" alt="Fundo dashboard" style="max-height:280px;object-fit:cover">
        <p class="mono"><?= htmlspecialchars($bgUrl) ?></p>
      <?php else: ?>
        <div class="preview" style="display:grid;place-items:center;color:var(--muted)">Usando fundo embutido no APK</div>
      <?php endif; ?>
      <label>Upload do fundo</label>
      <input type="file" name="card_dashboard_bg" accept="image/*">
      <label>Ou URL direta (https://...)</label>
      <input type="url" name="url_dashboard_bg" placeholder="https://...">
      <?php if ($bgUrl): ?>
        <label style="display:flex;align-items:center;gap:8px;margin-top:12px">
          <input type="checkbox" name="clear_dashboard_bg" value="1"> Voltar ao fundo embutido no APK
        </label>
      <?php endif; ?>
    </div>

    <?php foreach ($cards as $slug => $meta):
        $val = setting_get($meta['key'], '');
        $url = card_public_url($val);
    ?>
      <div class="card">
        <h2><?= htmlspecialchars($meta['title']) ?></h2>
        <p class="sub"><?= htmlspecialchars($meta['hint']) ?></p>
        <?php if ($url): ?>
          <img class="preview" src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($meta['title']) ?>">
          <p class="mono"><?= htmlspecialchars($url) ?></p>
        <?php else: ?>
          <div class="preview" style="display:grid;place-items:center;color:var(--muted)">Sem imagem</div>
        <?php endif; ?>
        <label>Upload de imagem</label>
        <input type="file" name="<?= htmlspecialchars($meta['field']) ?>" accept="image/*">
        <label>Ou URL direta (https://...)</label>
        <input type="url" name="<?= htmlspecialchars($meta['url']) ?>" placeholder="https://...">
      </div>
    <?php endforeach; ?>
    <div style="grid-column:1/-1">
      <button class="btn primary" type="submit">Salvar imagens</button>
    </div>
  </form>
<?php layout_footer(); ?>
