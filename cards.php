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
        default => throw new RuntimeException("Formato de imagem não suportado em {$field}"),
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
        $changed = [];
        foreach (['card_live' => 'card_live', 'card_movies' => 'card_movies', 'card_series' => 'card_series'] as $field => $key) {
            $n = handle_card_upload($field, $key);
            if ($n) {
                $changed[] = $key;
            }
        }
        // URL manual alternativa
        foreach (['url_live' => 'card_live', 'url_movies' => 'card_movies', 'url_series' => 'card_series'] as $field => $key) {
            $url = trim((string) ($_POST[$field] ?? ''));
            if ($url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                setting_set($key, $url);
                $changed[] = $key;
            }
        }
        $msg = $changed
            ? 'Cards atualizados: ' . implode(', ', array_unique($changed)) . '. O app baixa no próximo dashboard/sync.'
            : 'Nada alterado. Envie uma imagem ou URL.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$cards = [
    'live' => ['key' => 'card_live', 'title' => 'Live TV', 'hint' => 'Card grande ao lado esquerdo'],
    'movies' => ['key' => 'card_movies', 'title' => 'Filmes (On Demand)', 'hint' => 'Card de filmes'],
    'series' => ['key' => 'card_series', 'title' => 'Séries', 'hint' => 'Card de séries / catch-up'],
];

layout_header('Cards', 'cards');
?>
  <h1>Cards dos botões do dashboard</h1>
  <p class="sub">Envie PNG/JPG. O app baixa e aplica como fundo de Live, Filmes e Séries.</p>

  <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="grid 2">
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
        <input type="file" name="card_<?= $slug === 'live' ? 'live' : ($slug === 'movies' ? 'movies' : 'series') ?>" accept="image/*">
        <label>Ou URL direta (https://...)</label>
        <input type="url" name="url_<?= $slug ?>" placeholder="https://...">
      </div>
    <?php endforeach; ?>
    <div style="grid-column:1/-1">
      <button class="btn primary" type="submit">Salvar cards</button>
    </div>
  </form>
<?php layout_footer(); ?>
