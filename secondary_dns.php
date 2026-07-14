<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
auth_check();

$pdo = panel_db();
$msg = '';
$err = '';

function liberou_norm_dns(string $dns): string
{
    $dns = trim($dns);
    if ($dns === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $dns)) {
        $dns = 'http://' . $dns;
    }
    if (!str_ends_with($dns, '/')) {
        $dns .= '/';
    }
    return $dns;
}

function liberou_norm_code(string $code): string
{
    // códigos amigáveis: letras, números, hífen, underscore
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9\-_]/', '', $code) ?? '';
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM secondary_dns WHERE id = ?')->execute([$id]);
            $msg = 'Código removido.';
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE secondary_dns SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END, updated_at = ? WHERE id = ?')
                ->execute([date('c'), $id]);
            $msg = 'Status atualizado.';
        }
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $code = liberou_norm_code((string) ($_POST['code'] ?? ''));
        $dns = liberou_norm_dns((string) ($_POST['dns_url'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($code === '' || strlen($code) < 3) {
            $err = 'Informe um código com pelo menos 3 caracteres (A–Z, 0–9).';
        } elseif ($dns === '') {
            $err = 'Informe a DNS / URL do provedor secundário.';
        } else {
            $now = date('c');
            try {
                if ($id > 0) {
                    $pdo->prepare(
                        'UPDATE secondary_dns SET code = ?, dns_url = ?, label = ?, active = ?, updated_at = ? WHERE id = ?'
                    )->execute([$code, $dns, $label, $active, $now, $id]);
                    $msg = 'Código atualizado.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO secondary_dns (code, dns_url, label, active, created_at, updated_at) VALUES (?,?,?,?,?,?)'
                    )->execute([$code, $dns, $label, $active, $now, $now]);
                    $msg = 'Código cadastrado. O cliente usa este código no app (lista secundária).';
                }
            } catch (Throwable $e) {
                $err = 'Não foi possível salvar (código já existe?).';
            }
        }
    }
}

$rows = $pdo->query('SELECT * FROM secondary_dns ORDER BY code ASC')->fetchAll();
$edit = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM secondary_dns WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

layout_header('DNS secundários', 'secondary_dns');
?>
  <div class="page-head">
    <div class="kicker">Listas secundárias / brinde</div>
    <h1>DNS por código</h1>
    <p class="sub">
      Cadastre provedores secundários aqui. No app, o cliente preenche
      <strong>nome da lista + usuário + senha + código</strong> — a DNS real fica só no painel.
    </p>
  </div>

  <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="grid actions">
    <div class="card" style="max-width:560px">
      <h2><?= $edit ? 'Editar código' : 'Novo código' ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

        <label>Código (o que o cliente digita no app)</label>
        <input type="text" name="code" required maxlength="32"
               value="<?= htmlspecialchars((string) ($edit['code'] ?? '')) ?>"
               placeholder="Ex: BRINDE01" style="text-transform:uppercase">

        <label>DNS / URL do provedor</label>
        <input type="text" name="dns_url" required
               value="<?= htmlspecialchars((string) ($edit['dns_url'] ?? '')) ?>"
               placeholder="http://provedor.com:8080/">

        <label>Nome interno (opcional)</label>
        <input type="text" name="label"
               value="<?= htmlspecialchars((string) ($edit['label'] ?? '')) ?>"
               placeholder="Ex: Revenda João — filmes">

        <label style="display:flex;align-items:center;gap:8px;color:var(--text);margin-bottom:16px">
          <input type="checkbox" name="active" style="width:auto;margin:0"
            <?= !isset($edit['active']) || (int) $edit['active'] === 1 ? 'checked' : '' ?>>
          Ativo (app pode usar)
        </label>

        <div class="action-row">
          <button class="btn primary" type="submit"><?= $edit ? 'Salvar alterações' : 'Cadastrar código' ?></button>
          <?php if ($edit): ?>
            <a class="btn" href="secondary_dns.php">Cancelar edição</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>Como o cliente usa</h2>
      <ol style="margin:0;padding-left:1.2rem;color:var(--muted);line-height:1.7">
        <li>Faz login na <strong>lista principal</strong> (DNS principal).</li>
        <li>Em Listar usuários → Adicionar.</li>
        <li>Preenche: nome da playlist, usuário, senha e o <strong>código</strong>.</li>
        <li>O app consulta o painel, troca o código pela DNS e salva a lista.</li>
      </ol>
      <p class="sub" style="margin-top:14px">O cliente <strong>nunca vê</strong> a URL do provedor secundário.</p>
    </div>
  </div>

  <div class="card section-gap">
    <h2>Códigos cadastrados (<?= count($rows) ?>)</h2>
    <?php if (!$rows): ?>
      <p class="sub">Nenhum código ainda. Cadastre o primeiro ao lado.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>DNS</th>
              <th>Nome</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><span class="mono" style="font-weight:700;color:var(--brand-hot)"><?= htmlspecialchars((string) $r['code']) ?></span></td>
                <td class="mono" style="font-size:12px"><?= htmlspecialchars((string) $r['dns_url']) ?></td>
                <td><?= htmlspecialchars((string) ($r['label'] ?: '—')) ?></td>
                <td>
                  <?php if ((int) $r['active'] === 1): ?>
                    <span style="color:var(--success)">Ativo</span>
                  <?php else: ?>
                    <span style="color:var(--muted-2)">Inativo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="action-row" style="justify-content:flex-end;gap:6px;flex-wrap:wrap">
                    <a class="btn" href="secondary_dns.php?edit=<?= (int) $r['id'] ?>">Editar</a>
                    <form method="post" style="display:inline;margin:0">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="btn" type="submit"><?= (int) $r['active'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                    </form>
                    <form method="post" style="display:inline;margin:0" onsubmit="return confirm('Remover este código?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="btn" type="submit" style="border-color:var(--danger);color:var(--danger)">Excluir</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php layout_footer(); ?>
