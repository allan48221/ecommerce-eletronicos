<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$id_tenant = $_SESSION['id_tenant'] ?? null;

$conn->exec("
    CREATE TABLE IF NOT EXISTS adicionais (
        id_adicional SERIAL PRIMARY KEY,
        nome         VARCHAR(100)  NOT NULL,
        preco        NUMERIC(10,2) NOT NULL DEFAULT 0,
        ativo        BOOLEAN       NOT NULL DEFAULT TRUE,
        criado_em    TIMESTAMP     NOT NULL DEFAULT NOW(),
        id_tenant    INTEGER
    )
");
$conn->exec("
    CREATE TABLE IF NOT EXISTS produto_adicionais (
        id           SERIAL  PRIMARY KEY,
        id_produto   INTEGER NOT NULL,
        id_adicional INTEGER NOT NULL,
        UNIQUE(id_produto, id_adicional)
    )
");

// Adiciona coluna id_tenant se não existir
try {
    $conn->exec("ALTER TABLE adicionais ADD COLUMN IF NOT EXISTS id_tenant INTEGER");
} catch (\Throwable $e) {}

$msg  = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar') {
    $nome  = trim($_POST['nome']  ?? '');
    $preco = floatval(str_replace(',', '.', $_POST['preco'] ?? '0'));
    if ($nome === '') {
        $msg = 'Informe o nome do adicional.'; $tipo = 'erro';
    } else {
        // ✅ INSERT com id_tenant
        $conn->prepare("INSERT INTO adicionais (nome, preco, id_tenant) VALUES (?, ?, ?)")->execute([$nome, $preco, $id_tenant]);
        $msg = 'Adicional criado com sucesso.'; $tipo = 'ok';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'editar') {
    $id_a  = intval($_POST['id_adicional'] ?? 0);
    $nome  = trim($_POST['nome'] ?? '');
    $preco = floatval(str_replace(',', '.', $_POST['preco'] ?? '0'));
    if ($id_a > 0 && $nome !== '') {
        // ✅ UPDATE filtrado por tenant
        $conn->prepare("UPDATE adicionais SET nome=?, preco=? WHERE id_adicional=? AND (id_tenant=? OR id_tenant IS NULL)")->execute([$nome, $preco, $id_a, $id_tenant]);
        $msg = 'Adicional atualizado.'; $tipo = 'ok';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle') {
    $id_a = intval($_POST['id_adicional'] ?? 0);
    if ($id_a > 0) {
        $conn->prepare("UPDATE adicionais SET ativo = NOT ativo WHERE id_adicional=? AND (id_tenant=? OR id_tenant IS NULL)")->execute([$id_a, $id_tenant]);
        $msg = 'Status alterado.'; $tipo = 'ok';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id_a = intval($_POST['id_adicional'] ?? 0);
    if ($id_a > 0) {
        $conn->prepare("DELETE FROM produto_adicionais WHERE id_adicional=?")->execute([$id_a]);
        $conn->prepare("DELETE FROM adicionais WHERE id_adicional=? AND (id_tenant=? OR id_tenant IS NULL)")->execute([$id_a, $id_tenant]);
        $msg = 'Adicional excluido.'; $tipo = 'ok';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'vincular') {
    $id_prod      = intval($_POST['id_produto'] ?? 0);
    $selecionados = $_POST['adicionais'] ?? [];
    if ($id_prod > 0) {
        $conn->prepare("DELETE FROM produto_adicionais WHERE id_produto = ?")->execute([$id_prod]);
        $inseridos = 0;
        foreach ($selecionados as $id_a) {
            $id_a = intval($id_a);
            if ($id_a > 0) {
                try {
                    $conn->prepare("INSERT INTO produto_adicionais (id_produto, id_adicional) VALUES (?, ?)")->execute([$id_prod, $id_a]);
                    $inseridos++;
                } catch (\Throwable $e) {}
            }
        }
        $msg = 'Vinculos salvos: ' . $inseridos . ' adicional(is) vinculado(s) ao produto.'; $tipo = 'ok';
    } else {
        $msg = 'Produto invalido.'; $tipo = 'erro';
    }
}

// ✅ SELECTs filtrados por tenant
$stmt_ad = $conn->prepare("SELECT * FROM adicionais WHERE (id_tenant = ? OR id_tenant IS NULL) ORDER BY criado_em DESC");
$stmt_ad->execute([$id_tenant]);
$adicionais = $stmt_ad->fetchAll();

$stmt_pr = $conn->prepare("SELECT id_produto, nome FROM produtos WHERE ativo=TRUE AND (id_tenant = ? OR id_tenant IS NULL) ORDER BY nome");
$stmt_pr->execute([$id_tenant]);
$produtos = $stmt_pr->fetchAll();

$vinculos = [];
try {
    $rows = $conn->query("SELECT id_produto, id_adicional FROM produto_adicionais")->fetchAll();
    foreach ($rows as $r) {
        $vinculos[$r['id_produto']][] = $r['id_adicional'];
    }
} catch (\Throwable $e) {}

$ativos = array_filter($adicionais, fn($a) => $a['ativo']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Adicionais -- Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sora', sans-serif; background: var(--dash-bg, #f1f5f9); min-height: 100vh; color: #0f172a; }
        .page { max-width: 1000px; margin: 0 auto; padding: 28px 20px 80px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 8px; }
        .btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 50px; font-size: 14px; font-weight: 600; text-decoration: none; transition: .2s; }
        .btn-back:hover { background: #f1f5f9; }
        .hero { background: linear-gradient(135deg, var(--primary, #2563eb), var(--primary-dark, #1e40af)); border-radius: 18px; padding: 28px 30px 24px; color: #fff; margin-bottom: 24px; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; right: -20px; top: -20px; width: 150px; height: 150px; background: rgba(255,255,255,.07); border-radius: 50%; }
        .hero h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .hero p  { font-size: 13px; opacity: .75; }
        .alerta { padding: 13px 16px; border-radius: 11px; font-size: 14px; font-weight: 600; margin-bottom: 18px; border-left: 4px solid; }
        .alerta.ok   { background: #ecfdf5; color: #065f46; border-color: #10b981; }
        .alerta.erro { background: #fef2f2; color: #991b1b; border-color: #dc2626; }
        .grid-2 { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media (min-width: 800px) { .grid-2 { grid-template-columns: 1fr 1fr; } }
        .card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 22px; box-shadow: 0 1px 6px rgba(0,0,0,.05); margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
        .campo { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
        .campo label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; }
        .campo input { padding: 11px 13px; border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 16px; font-family: 'Sora', sans-serif; color: #0f172a; outline: none; transition: .2s; background: #fff; }
        .campo input:focus { border-color: var(--primary, #2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .btn-primary { padding: 12px 22px; background: var(--primary, #2563eb); color: #fff; border: none; border-radius: 9px; font-size: 14px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; transition: .2s; width: 100%; }
        .btn-primary:hover { filter: brightness(1.08); }
        .adicionais-lista { display: flex; flex-direction: column; gap: 8px; }
        .adicional-row { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #f8fafc; border-radius: 11px; border: 1px solid #e2e8f0; }
        .adicional-row.inativo { opacity: .5; }
        .adicional-nome  { font-weight: 700; font-size: 14px; color: #0f172a; flex: 1; min-width: 0; word-break: break-word; }
        .adicional-preco { font-size: 13px; font-weight: 700; color: #059669; white-space: nowrap; }
        .adicional-acoes { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
        .btn-sm { padding: 7px 13px; border-radius: 7px; font-size: 12px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; border: none; transition: .2s; white-space: nowrap; }
        .btn-editar     { background: #dbeafe; color: #1e40af; }
        .btn-toggle-on  { background: #d1fae5; color: #065f46; }
        .btn-toggle-off { background: #fef3c7; color: #92400e; }
        .btn-excluir    { background: #fee2e2; color: #991b1b; }
        .badge-ativo   { display: inline-block; padding: 2px 9px; border-radius: 50px; font-size: 10px; font-weight: 800; background: #d1fae5; color: #065f46; }
        .badge-inativo { display: inline-block; padding: 2px 9px; border-radius: 50px; font-size: 10px; font-weight: 800; background: #f1f5f9; color: #94a3b8; }
        .produto-vinculo-card { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 13px; padding: 16px 18px; margin-bottom: 12px; }
        .produto-vinculo-nome { font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px; }
        .vinculados-count { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 50px; background: #dbeafe; color: #1e40af; }
        .checkboxes-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .check-label { display: flex; align-items: center; gap: 8px; padding: 9px 14px; background: #fff; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: .15s; font-size: 13px; font-weight: 600; color: #374151; user-select: none; }
        .check-label:hover { border-color: var(--primary, #2563eb); background: #f0f4ff; }
        .check-label.marcado { border-color: var(--primary, #2563eb); background: #dbeafe; color: #1e40af; }
        .check-label input[type=checkbox] { width: 18px; height: 18px; accent-color: var(--primary, #2563eb); cursor: pointer; flex-shrink: 0; }
        .check-preco { font-size: 11px; color: #059669; font-weight: 700; margin-left: 2px; }
        .btn-salvar-vinculo { padding: 11px 20px; background: var(--primary, #2563eb); color: #fff; border: none; border-radius: 9px; font-size: 13px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; transition: .2s; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px; }
        .modal-overlay.aberto { display: flex; }
        .modal { background: #fff; border-radius: 18px; width: 100%; max-width: 420px; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,.2); animation: slideUp .2s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal h3 { font-size: 16px; font-weight: 800; margin-bottom: 18px; color: #0f172a; }
        .modal-footer { display: flex; gap: 10px; margin-top: 18px; }
        .btn-cinza { flex: 1; padding: 12px; background: #f1f5f9; color: #475569; border: none; border-radius: 9px; font-size: 14px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; }
        .vazio { text-align: center; padding: 32px; color: #94a3b8; font-size: 14px; font-weight: 600; }
        @media (max-width: 600px) {
            .page { padding: 14px 12px 80px; }
            .hero { border-radius: 14px; padding: 18px 16px 16px; }
            .hero h1 { font-size: 18px; }
            .card { padding: 16px 14px; border-radius: 14px; }
            .adicional-row { flex-direction: column; align-items: flex-start; gap: 10px; }
            .adicional-acoes { width: 100%; }
            .btn-sm { padding: 8px 14px; font-size: 13px; flex: 1; text-align: center; }
            .check-label { width: 100%; font-size: 14px; }
            .checkboxes-grid { flex-direction: column; gap: 6px; }
            .btn-salvar-vinculo { width: 100%; padding: 13px; }
            .modal-overlay { align-items: flex-end; padding: 0; }
            .modal { border-radius: 22px 22px 0 0; max-height: 92vh; overflow-y: auto; padding: 24px 20px 32px; }
            .modal-footer { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="page">

    <div class="topbar">
        <a href="admin.php" class="btn-back">&#8592; Voltar ao Admin</a>
    </div>

    <div class="hero">
        <h1>Gerenciar Adicionais</h1>
        <p>Crie adicionais, defina precos e vincule aos produtos desejados</p>
    </div>

    <?php if ($msg): ?>
    <div class="alerta <?= $tipo ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="card">
            <div class="card-title">Novo adicional</div>
            <form method="POST" action="adicionais_admin.php">
                <input type="hidden" name="acao" value="criar">
                <div class="campo">
                    <label>Nome do adicional</label>
                    <input type="text" name="nome" placeholder="Ex: Bacon extra, Queijo duplo..." required maxlength="100">
                </div>
                <div class="campo">
                    <label>Preco (R$) — coloque 0 para gratis</label>
                    <input type="text" name="preco" placeholder="0,00" value="0" inputmode="decimal">
                </div>
                <button type="submit" class="btn-primary">Criar adicional</button>
            </form>
        </div>

        <div class="card">
            <div class="card-title">Adicionais criados (<?= count($adicionais) ?>)</div>
            <?php if (empty($adicionais)): ?>
                <div class="vazio">Nenhum adicional criado ainda.</div>
            <?php else: ?>
            <div class="adicionais-lista">
                <?php foreach ($adicionais as $a): ?>
                <div class="adicional-row <?= !$a['ativo'] ? 'inativo' : '' ?>">
                    <div style="flex:1;min-width:0;width:100%;">
                        <div class="adicional-nome"><?= htmlspecialchars($a['nome']) ?></div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                            <span class="adicional-preco"><?= $a['preco'] > 0 ? '+ R$ ' . number_format($a['preco'],2,',','.') : 'Gratis' ?></span>
                            <span class="<?= $a['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $a['ativo'] ? 'Ativo' : 'Inativo' ?></span>
                        </div>
                    </div>
                    <div class="adicional-acoes">
                        <button type="button" class="btn-sm btn-editar"
                            onclick="abrirEdicao(<?= $a['id_adicional'] ?>, '<?= addslashes(htmlspecialchars($a['nome'])) ?>', '<?= number_format($a['preco'],2,',','.') ?>')">
                            Editar
                        </button>
                        <form method="POST" action="adicionais_admin.php" style="display:contents;">
                            <input type="hidden" name="acao" value="toggle">
                            <input type="hidden" name="id_adicional" value="<?= $a['id_adicional'] ?>">
                            <button type="submit" class="btn-sm <?= $a['ativo'] ? 'btn-toggle-off' : 'btn-toggle-on' ?>"><?= $a['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                        </form>
                        <form method="POST" action="adicionais_admin.php" style="display:contents;"
                              onsubmit="return confirm('Excluir este adicional?')">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id_adicional" value="<?= $a['id_adicional'] ?>">
                            <button type="submit" class="btn-sm btn-excluir">Excluir</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($adicionais) && !empty($produtos)): ?>
    <div class="card">
        <div class="card-title">Vincular adicionais a produtos</div>
        <p style="font-size:13px;color:#64748b;margin-bottom:20px;">Marque quais adicionais cada produto aceita e clique em Salvar.</p>
        <?php if (empty($ativos)): ?>
            <div class="vazio">Todos os adicionais estao desativados.</div>
        <?php else: ?>
            <?php foreach ($produtos as $prod):
                $vinculados = $vinculos[$prod['id_produto']] ?? [];
                $count_vinc = count($vinculados);
            ?>
            <div class="produto-vinculo-card">
                <div class="produto-vinculo-nome">
                    <span><?= htmlspecialchars($prod['nome']) ?></span>
                    <?php if ($count_vinc > 0): ?>
                    <span class="vinculados-count"><?= $count_vinc ?> adicional(is) vinculado(s)</span>
                    <?php endif; ?>
                </div>
                <form method="POST" action="adicionais_admin.php">
                    <input type="hidden" name="acao" value="vincular">
                    <input type="hidden" name="id_produto" value="<?= $prod['id_produto'] ?>">
                    <div class="checkboxes-grid">
                        <?php foreach ($ativos as $a):
                            $marcado = in_array((int)$a['id_adicional'], array_map('intval', $vinculados));
                        ?>
                        <label class="check-label <?= $marcado ? 'marcado' : '' ?>">
                            <input type="checkbox" name="adicionais[]" value="<?= $a['id_adicional'] ?>" <?= $marcado ? 'checked' : '' ?> onchange="toggleLabel(this)">
                            <?= htmlspecialchars($a['nome']) ?>
                            <span class="check-preco"><?= $a['preco'] > 0 ? '+ R$ ' . number_format($a['preco'],2,',','.') : 'gratis' ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn-salvar-vinculo">Salvar vinculos</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php elseif (empty($adicionais)): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <div style="font-size:15px;color:#94a3b8;font-weight:600;margin-bottom:8px;">Nenhum adicional criado ainda.</div>
    </div>
    <?php endif; ?>

</div>

<div class="modal-overlay" id="modal-overlay">
    <div class="modal">
        <h3>Editar adicional</h3>
        <form method="POST" action="adicionais_admin.php">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id_adicional" id="edit-id">
            <div class="campo">
                <label>Nome</label>
                <input type="text" name="nome" id="edit-nome" required maxlength="100">
            </div>
            <div class="campo">
                <label>Preco (R$)</label>
                <input type="text" name="preco" id="edit-preco" required inputmode="decimal">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cinza" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn-primary" style="flex:2;">Salvar alteracoes</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirEdicao(id, nome, preco) {
    document.getElementById('edit-id').value    = id;
    document.getElementById('edit-nome').value  = nome;
    document.getElementById('edit-preco').value = preco;
    document.getElementById('modal-overlay').classList.add('aberto');
}
function fecharModal() { document.getElementById('modal-overlay').classList.remove('aberto'); }
document.getElementById('modal-overlay').addEventListener('click', function(e) { if (e.target === this) fecharModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') fecharModal(); });
function toggleLabel(cb) { var label = cb.closest('label'); if (label) label.classList.toggle('marcado', cb.checked); }
</script>
</body>
</html>
