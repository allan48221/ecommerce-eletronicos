<?php
require_once 'config/database.php';
require_once 'config/tema.php'; 

// Verifica se é admin
if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_msg = '';

// ── EXCLUIR ──
if (isset($_GET['excluir'])) {
    $id_excluir = intval($_GET['excluir']);

    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM produtos WHERE id_categoria = ?");
    $stmt_check->execute([$id_excluir]);
    $total_produtos = $stmt_check->fetchColumn();

    if ($total_produtos > 0) {
        $mensagem = "Não é possível excluir: existem $total_produtos produto(s) vinculado(s) a essa categoria.";
        $tipo_msg = 'erro';
    } else {
        $stmt_del = $conn->prepare("DELETE FROM categorias WHERE id_categoria = ?");
        $stmt_del->execute([$id_excluir]);
        $mensagem = 'Categoria excluída com sucesso!';
        $tipo_msg = 'sucesso';
    }
}

// ── SALVAR EDIÇÃO ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id_edit   = intval($_POST['id_categoria']);
    $nome      = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);

    if ($nome === '') {
        $mensagem = 'O nome da categoria não pode ser vazio.';
        $tipo_msg = 'erro';
    } else {
        $stmt_upd = $conn->prepare("UPDATE categorias SET nome = ?, descricao = ? WHERE id_categoria = ?");
        $stmt_upd->execute([$nome, $descricao, $id_edit]);
        $mensagem = 'Categoria atualizada com sucesso!';
        $tipo_msg = 'sucesso';
    }
}

// ── SALVAR NOVA CATEGORIA ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'nova') {
    $nome      = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);

    if ($nome === '') {
        $mensagem = 'O nome da categoria não pode ser vazio.';
        $tipo_msg = 'erro';
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO categorias (nome, descricao) VALUES (?, ?)");
        $stmt_ins->execute([$nome, $descricao]);
        $mensagem = 'Categoria cadastrada com sucesso!';
        $tipo_msg = 'sucesso';
    }
}

// ── BUSCAR CATEGORIAS ──
$sql_cats = "SELECT c.*, COUNT(p.id_produto) as total_produtos
             FROM categorias c
             LEFT JOIN produtos p ON p.id_categoria = c.id_categoria
             GROUP BY c.id_categoria
             ORDER BY c.nome ASC";
$categorias = $conn->query($sql_cats)->fetchAll();

// ── CATEGORIA PARA EDITAR ──
$categoria_editar = null;
if (isset($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $stmt_ed   = $conn->prepare("SELECT * FROM categorias WHERE id_categoria = ?");
    $stmt_ed->execute([$id_editar]);
    $categoria_editar = $stmt_ed->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title>Categorias - TechStore</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?> 
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <style>
        /* ══════════════════════════════════════
           VARIÁVEIS & BASE MOBILE-FIRST
        ══════════════════════════════════════ */
        :root {
            --tap-min: 36px;
            --radius:  0.6rem;
            --shadow-card: 0 2px 12px rgba(0,0,0,0.07);
        }

        @media (max-width: 768px) {
            html { font-size: 13px; }
        }

        /* ══════════════════════════════════════
           LAYOUT PRINCIPAL
        ══════════════════════════════════════ */
        .container {
            padding: 1rem !important;
            margin-top: 1rem !important;
        }

        @media (min-width: 769px) {
            .container { padding: 1.5rem !important; margin-top: 2rem !important; }
        }

        .card {
            border-radius: var(--radius);
            padding: 1rem;
            background: #fff;
            box-shadow: var(--shadow-card);
        }

        @media (min-width: 769px) {
            .card { padding: 1.75rem; }
        }

        /* ══════════════════════════════════════
           CABEÇALHO DA PÁGINA
        ══════════════════════════════════════ */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .page-header h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0;
        }

        @media (min-width: 769px) {
            .page-header h2 { font-size: 1.5rem; }
        }

        .page-header-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            width: 100%;
        }

        @media (min-width: 480px) {
            .page-header-actions { width: auto; }
        }

        /* ══════════════════════════════════════
           BOTÕES DE AÇÃO  (touch-friendly)
        ══════════════════════════════════════ */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            min-height: var(--tap-min);
            padding: 0 0.9rem;
            border-radius: 0.5rem;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
            white-space: nowrap;
        }

        @media (max-width: 479px) {
            .page-header-actions .action-btn { flex: 1; }
        }

        .action-btn.editar  { background: #fef3c7; color: #92400e; }
        .action-btn.editar:active  { background: #fde68a; }

        .action-btn.excluir { background: #fee2e2; color: #991b1b; }
        .action-btn.excluir:active { background: #fecaca; }

        .action-btn.nova-cat {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark, #1d4ed8));
            color: white;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }
        .action-btn.nova-cat:active { opacity: 0.9; }

        .action-btn.voltar {
            background: #f1f5f9;
            color: var(--gray);
        }
        .action-btn.voltar:active { background: #e2e8f0; }

        /* ── hover só em desktop ── */
        @media (hover: hover) {
            .action-btn.editar:hover  { background: #fde68a; transform: translateY(-1px); }
            .action-btn.excluir:hover { background: #fecaca; transform: translateY(-1px); }
            .action-btn.nova-cat:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37,99,235,0.45); }
            .action-btn.voltar:hover  { background: #e2e8f0; color: var(--dark); }
        }

        /* ══════════════════════════════════════
           CARDS DE CATEGORIA  (mobile)
           substitui a tabela em telas pequenas
        ══════════════════════════════════════ */
        .cat-list { display: flex; flex-direction: column; gap: 0.75rem; }

        .cat-card {
            background: #f8faff;
            border: 1.5px solid #e5e7eb;
            border-radius: var(--radius);
            padding: 0.75rem 0.9rem;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            transition: border-color 0.2s;
        }
        .cat-card:active { border-color: var(--primary); }

        .cat-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .cat-card-name {
            font-size: 0.97rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.3;
        }

        .cat-card-desc {
            font-size: 0.85rem;
            color: var(--gray);
            line-height: 1.4;
            margin: 0;
        }

        .cat-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.25rem;
        }

        .badge-qtd {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #eff6ff;
            color: var(--primary);
            border-radius: 2rem;
            padding: 0.3rem 0.85rem;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .cat-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* ── Botões de ação dentro do card ── */
        .cat-actions .action-btn {
            flex: 1;
            min-width: 80px;
            min-height: 38px;
            font-size: 0.85rem;
        }

        /* ══════════════════════════════════════
           TABELA — apenas desktop
        ══════════════════════════════════════ */
        .table-wrapper { display: none; }

        @media (min-width: 769px) {
            .cat-list      { display: none; }
            .table-wrapper {
                display: block;
                overflow-x: auto;
                border-radius: 0.75rem;
                border: 1px solid #e5e7eb;
            }
        }

        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }

        table thead {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark, #1d4ed8));
            color: white;
        }

        table thead th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
        table tbody tr:last-child { border-bottom: none; }
        table tbody tr:hover { background: #f8faff; }

        table tbody td {
            padding: 0.9rem 1.25rem;
            vertical-align: middle;
            color: var(--dark);
        }

        .tbl-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .tbl-actions .action-btn {
            flex: none;
            min-height: 40px;
            padding: 0 1rem;
            font-size: 0.85rem;
        }

        /* ══════════════════════════════════════
           FORMULÁRIO
        ══════════════════════════════════════ */
        .form-card {
            background: white;
            border-radius: var(--radius);
            border: 2px solid var(--primary);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            animation: slideDown 0.3s ease-out;
        }

        @media (min-width: 769px) {
            .form-card { padding: 1.75rem; }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-card h3 {
            margin: 0 0 1.1rem;
            color: var(--primary);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.9rem;
        }

        @media (min-width: 600px) {
            .form-row { grid-template-columns: 1fr 2fr; align-items: end; }
        }

        .form-group { display: flex; flex-direction: column; gap: 0.45rem; }

        .form-group label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input,
        .form-group textarea {
            padding: 0.6rem 0.85rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
            outline: none;
            min-height: 42px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .form-group textarea { resize: vertical; min-height: 80px; }

        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            margin-top: 1.1rem;
        }

        @media (min-width: 480px) {
            .form-actions { flex-direction: row; flex-wrap: wrap; }
        }

        .btn-salvar {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 1.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 3px 10px rgba(16,185,129,0.3);
            width: 100%;
        }

        @media (min-width: 480px) { .btn-salvar { width: auto; } }

        @media (hover: hover) {
            .btn-salvar:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(16,185,129,0.45); }
        }

        .btn-cancelar {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 1.2rem;
            background: #f1f5f9;
            color: var(--gray);
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
            width: 100%;
        }

        @media (min-width: 480px) { .btn-cancelar { width: auto; } }
        @media (hover: hover) { .btn-cancelar:hover { background: #e2e8f0; } }

        /* ══════════════════════════════════════
           ALERTAS
        ══════════════════════════════════════ */
        .alert-custom {
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 1rem;
            margin-bottom: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            animation: slideDown 0.3s ease-out;
        }
        .alert-custom.sucesso { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-custom.erro    { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* ══════════════════════════════════════
           EMPTY STATE
        ══════════════════════════════════════ */
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--gray); }
        .empty-state .empty-icon { font-size: 3rem; margin-bottom: 0.75rem; }
        .empty-state p { font-size: 1rem; }

        /* ══════════════════════════════════════
           MODAL DE CONFIRMAÇÃO
        ══════════════════════════════════════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: flex-end;   /* bottom-sheet em mobile */
            justify-content: center;
            padding: 0;
        }

        @media (min-width: 480px) {
            .modal-overlay { align-items: center; padding: 1rem; }
        }

        .modal-overlay.ativo { display: flex; }

        .modal-box {
            background: white;
            border-radius: 1.25rem 1.25rem 0 0;
            padding: 1.75rem 1.5rem 2rem;
            width: 100%;
            text-align: center;
            box-shadow: 0 -8px 40px rgba(0,0,0,0.18);
            animation: slideUp 0.3s ease-out;
        }

        @media (min-width: 480px) {
            .modal-box {
                border-radius: 1.25rem;
                max-width: 420px;
                animation: slideDown 0.3s ease-out;
            }
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to   { transform: translateY(0); }
        }

        /* Alça visual (handle) para bottom-sheet */
        .modal-handle {
            width: 44px;
            height: 5px;
            background: #d1d5db;
            border-radius: 3px;
            margin: 0 auto 1.25rem;
            display: block;
        }

        @media (min-width: 480px) { .modal-handle { display: none; } }

        .modal-box .modal-icon { font-size: 2.5rem; margin-bottom: 0.6rem; }
        .modal-box h3 { font-size: 1.2rem; margin: 0 0 0.5rem; color: var(--dark); }
        .modal-box p  { color: var(--gray); font-size: 1rem; margin: 0 0 1.5rem; line-height: 1.5; }

        .modal-btns {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        @media (min-width: 480px) {
            .modal-btns { flex-direction: row; justify-content: center; }
        }

        .btn-confirmar-excluir {
            display: flex; align-items: center; justify-content: center;
            min-height: 42px;
            padding: 0 1.25rem;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 0.55rem;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }
        @media (hover: hover) { .btn-confirmar-excluir:hover { background: #dc2626; } }

        .btn-cancelar-modal {
            display: flex; align-items: center; justify-content: center;
            min-height: 42px;
            padding: 0 1.25rem;
            background: #f1f5f9;
            color: var(--gray);
            border: none;
            border-radius: 0.55rem;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            order: 2;
        }

        @media (min-width: 480px) { .btn-cancelar-modal { order: 0; } }

        /* ══════════════════════════════════════
           CONTADOR TOTAL
        ══════════════════════════════════════ */
        .total-count {
            color: var(--gray);
            font-size: 0.92rem;
            margin-top: 0.75rem;
            text-align: right;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR MOBILE -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <h3>Menu</h3>
            <button class="close-sidebar" onclick="toggleSidebar()">×</button>
        </div>
        <ul>
            <li><a href="index.php"> Home</a></li>
            <li><a href="admin.php"> Admin</a></li>
            <li><a href="cadastro_produto.php"> Cadastrar Produto</a></li>
            <li><a href="categorias.php"> Categorias</a></li>
            <li><a href="logout.php"> Sair</a></li>
        </ul>
    </aside>
    <div id="overlay" onclick="toggleSidebar()"></div>

    <div class="container" style="margin-top: 2rem;">

        <!-- MENSAGEM DE FEEDBACK -->
        <?php if ($mensagem): ?>
            <div class="alert-custom <?= $tipo_msg ?>">
                <?= $tipo_msg === 'sucesso' ? '✅' : '❌' ?> <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <div class="card">

            <!-- CABEÇALHO -->
            <div class="page-header">
                <h2> Gerenciar Categorias</h2>
                <div class="page-header-actions">
                    <a href="admin.php" class="action-btn voltar">← Voltar</a>
                    <a href="categorias.php?nova=1" class="action-btn nova-cat">+ Nova Categoria</a>
                </div>
            </div>

            <!-- FORMULÁRIO: NOVA CATEGORIA -->
            <?php if (isset($_GET['nova']) && !$categoria_editar): ?>
            <div class="form-card">
                <h3> Nova Categoria</h3>
                <form method="POST" action="categorias.php">
                    <input type="hidden" name="acao" value="nova">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome *</label>
                            <input type="text" id="nome" name="nome"
                                   placeholder="Ex: Smartphones"
                                   required maxlength="100"
                                   autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <input type="text" id="descricao" name="descricao"
                                   placeholder="Descrição opcional..."
                                   maxlength="255"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-salvar">✔ Salvar Categoria</button>
                        <a href="categorias.php" class="btn-cancelar">Cancelar</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- FORMULÁRIO: EDITAR CATEGORIA -->
            <?php if ($categoria_editar): ?>
            <div class="form-card">
                <h3>✏️ Editar: <?= htmlspecialchars($categoria_editar['nome']) ?></h3>
                <form method="POST" action="categorias.php">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id_categoria" value="<?= $categoria_editar['id_categoria'] ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome_edit">Nome *</label>
                            <input type="text" id="nome_edit" name="nome"
                                   value="<?= htmlspecialchars($categoria_editar['nome']) ?>"
                                   required maxlength="100">
                        </div>
                        <div class="form-group">
                            <label for="desc_edit">Descrição</label>
                            <input type="text" id="desc_edit" name="descricao"
                                   value="<?= htmlspecialchars($categoria_editar['descricao'] ?? '') ?>"
                                   maxlength="255">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-salvar">✔ Salvar Alterações</button>
                        <a href="categorias.php" class="btn-cancelar">Cancelar</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ════════════════════════════════
                 LISTA MOBILE (cards)
            ════════════════════════════════ -->
            <?php if (count($categorias) > 0): ?>

            <div class="cat-list">
                <?php foreach ($categorias as $cat): ?>
                <div class="cat-card">
                    <div class="cat-card-top">
                        <span class="cat-card-name"><?= htmlspecialchars($cat['nome']) ?></span>
                        <span class="badge-qtd"> <?= $cat['total_produtos'] ?></span>
                    </div>
                    <?php if ($cat['descricao']): ?>
                    <p class="cat-card-desc"><?= htmlspecialchars($cat['descricao']) ?></p>
                    <?php endif; ?>
                    <div class="cat-actions">
                        <a href="categorias.php?editar=<?= $cat['id_categoria'] ?>"
                           class="action-btn editar"> Editar</a>
                        <button
                            class="action-btn excluir"
                            onclick="confirmarExclusao(<?= $cat['id_categoria'] ?>, '<?= htmlspecialchars(addslashes($cat['nome'])) ?>', <?= $cat['total_produtos'] ?>)">
                             Excluir
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ════════════════════════════════
                 TABELA DESKTOP
            ════════════════════════════════ -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th style="text-align:center">Produtos</th>
                            <th style="text-align:center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat): ?>
                        <tr>
                            <td style="color: var(--gray); font-size: 0.85rem;"><?= $cat['id_categoria'] ?></td>
                            <td><strong><?= htmlspecialchars($cat['nome']) ?></strong></td>
                            <td style="color: var(--gray);">
                                <?= $cat['descricao'] ? htmlspecialchars($cat['descricao']) : '<span style="opacity:0.4">—</span>' ?>
                            </td>
                            <td style="text-align:center">
                                <span class="badge-qtd"><?= $cat['total_produtos'] ?></span>
                            </td>
                            <td style="text-align:center">
                                <div class="tbl-actions">
                                    <a href="categorias.php?editar=<?= $cat['id_categoria'] ?>"
                                       class="action-btn editar"> Editar</a>
                                    <button
                                        class="action-btn excluir"
                                        onclick="confirmarExclusao(<?= $cat['id_categoria'] ?>, '<?= htmlspecialchars(addslashes($cat['nome'])) ?>', <?= $cat['total_produtos'] ?>)">
                                         Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="total-count">
                Total: <strong><?= count($categorias) ?></strong> categoria(s)
            </p>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"></div>
                <p>Nenhuma categoria cadastrada ainda.</p>
            </div>
            <?php endif; ?>

        </div><!-- .card -->
    </div><!-- .container -->

    <!-- MODAL DE CONFIRMAÇÃO (bottom-sheet em mobile) -->
    <div class="modal-overlay" id="modal-excluir">
        <div class="modal-box">
            <span class="modal-handle"></span>
            <div class="modal-icon"></div>
            <h3>Excluir Categoria</h3>
            <p id="modal-msg">Tem certeza que deseja excluir esta categoria?</p>
            <div class="modal-btns">
                <button class="btn-cancelar-modal" onclick="fecharModal()">Cancelar</button>
                <a id="btn-confirmar-excluir" href="#" class="btn-confirmar-excluir">Sim, excluir</a>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
        function confirmarExclusao(id, nome, totalProdutos) {
            const modal = document.getElementById('modal-excluir');
            const msg   = document.getElementById('modal-msg');
            const btn   = document.getElementById('btn-confirmar-excluir');

            if (totalProdutos > 0) {
                msg.innerHTML = `A categoria <strong>${nome}</strong> possui <strong>${totalProdutos} produto(s)</strong> vinculado(s) e não pode ser excluída.`;
                btn.style.display = 'none';
            } else {
                msg.innerHTML = `Tem certeza que deseja excluir a categoria <strong>${nome}</strong>? Esta ação não pode ser desfeita.`;
                btn.style.display = '';
                btn.href = `categorias.php?excluir=${id}`;
            }

            modal.classList.add('ativo');
        }

        function fecharModal() {
            document.getElementById('modal-excluir').classList.remove('ativo');
        }

        // Fecha o modal clicando fora (ou deslizando o fundo)
        document.getElementById('modal-excluir').addEventListener('click', function(e) {
            if (e.target === this) fecharModal();
        });

        // Auto-esconde alertas após 4 segundos
        const alerta = document.querySelector('.alert-custom');
        if (alerta) {
            setTimeout(() => {
                alerta.style.transition = 'opacity 0.5s';
                alerta.style.opacity = '0';
                setTimeout(() => alerta.remove(), 500);
            }, 4000);
        }
    </script>
</body>
</html>