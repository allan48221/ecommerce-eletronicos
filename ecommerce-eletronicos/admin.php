<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}
$is_master = empty($_SESSION['id_tenant']);
$id_tenant = $_SESSION['id_tenant'] ?? null;

$conn->query("UPDATE pedidos SET status = 'expirado', data_acao = NOW()
              WHERE status = 'pendente' AND data_pedido < NOW() - INTERVAL '24 hours'");

$stats = [];
$stats['total_clientes'] = $conn->query("SELECT COUNT(*) FROM clientes WHERE ativo = TRUE")->fetchColumn();
$stats['total_produtos'] = $conn->query("SELECT COUNT(*) FROM produtos WHERE ativo = TRUE")->fetchColumn();
$stats['total_pedidos']  = $conn->query("SELECT COUNT(*) FROM compras")->fetchColumn();
$stats['total_vendas']   = $conn->query("SELECT COALESCE(SUM(valor_total),0) FROM compras")->fetchColumn();
$stats['estoque_baixo']  = $conn->query("SELECT COUNT(*) FROM produtos WHERE estoque < 10 AND ativo = TRUE")->fetchColumn();

$subdominio_logout = '';
if (!empty($id_tenant)) {
    $stmt_sub = $conn->prepare("SELECT subdominio FROM empresas_tenants WHERE id_tenant = ?");
    $stmt_sub->execute([$id_tenant]);
    $subdominio_logout = $stmt_sub->fetchColumn() ?: '';
}
$url_saida = $subdominio_logout
    ? 'logout.php?redirect=index.php&tenant=' . urlencode($subdominio_logout)
    : 'logout.php';

// Verifica se o plano tem acesso a loja
$plano_admin  = mb_strtolower($_SESSION['plano_nome'] ?? '');
// ✅ Correto:
$plano_admin = mb_strtolower($_SESSION['plano_nome'] ?? '');
$tem_loja = empty($id_tenant) || (
    !str_contains($plano_admin, 'enterprise') && 
    !str_contains($plano_admin, 'pro')
);
$url_ver_loja = $subdominio_logout ? 'index.php?tenant=' . urlencode($subdominio_logout) : 'index.php';

if ($is_master) {
    $mais_vistos = $conn->query("
        SELECT p.id_produto, p.nome, p.imagem, p.preco, COUNT(pv.id) as total_views
        FROM produto_views pv JOIN produtos p ON pv.id_produto = p.id_produto
        WHERE p.ativo = TRUE
        GROUP BY p.id_produto, p.nome, p.imagem, p.preco
        ORDER BY total_views DESC LIMIT 3
    ");
} else {
    $mais_vistos = $conn->prepare("
        SELECT p.id_produto, p.nome, p.imagem, p.preco, COUNT(pv.id) as total_views
        FROM produto_views pv JOIN produtos p ON pv.id_produto = p.id_produto
        WHERE p.ativo = TRUE AND p.id_tenant = ?
        GROUP BY p.id_produto, p.nome, p.imagem, p.preco
        ORDER BY total_views DESC LIMIT 3
    ");
    $mais_vistos->execute([$id_tenant]);
}

if ($is_master) {
    $todos_produtos = $conn->query("SELECT id_produto, nome, estoque, preco FROM produtos ORDER BY estoque ASC");
} else {
    $todos_produtos = $conn->prepare("SELECT id_produto, nome, estoque, preco FROM produtos WHERE id_tenant = ? ORDER BY estoque ASC");
    $todos_produtos->execute([$id_tenant]);
}

if ($is_master) {
    $produtos_baixo = $conn->query("SELECT * FROM produtos WHERE estoque < 10 AND ativo = TRUE ORDER BY estoque ASC LIMIT 5");
} else {
    $produtos_baixo = $conn->prepare("SELECT * FROM produtos WHERE estoque < 10 AND ativo = TRUE AND id_tenant = ? ORDER BY estoque ASC LIMIT 5");
    $produtos_baixo->execute([$id_tenant]);
}

$filtro_pedido = $_GET['pedidos'] ?? 'pendente';
if (!in_array($filtro_pedido, ['pendente','confirmado','cancelado','expirado','todos'])) $filtro_pedido = 'pendente';

if ($filtro_pedido === 'todos') {
    $stmt_pedidos = $conn->query("SELECT * FROM pedidos ORDER BY data_pedido DESC");
} else {
    $stmt_pedidos = $conn->prepare("SELECT * FROM pedidos WHERE status = ? ORDER BY data_pedido DESC");
    $stmt_pedidos->execute([$filtro_pedido]);
}
$pedidos = $stmt_pedidos->fetchAll();

$count_pendentes = $conn->query("SELECT COUNT(*) FROM pedidos WHERE status = 'pendente'")->fetchColumn();

$cores_atuais = ['cor_primary'=>'#2563eb','cor_primary_dark'=>'#1e40af','cor_secondary'=>'#10b981','cor_danger'=>'#ef4444','cor_header_grad1'=>'#1e40af','cor_header_grad2'=>'#7c3aed'];
$res_cores = $conn->query("SELECT chave, valor FROM configuracoes");
if ($res_cores) {
    while ($row = $res_cores->fetch()) {
        $cores_atuais[$row['chave']] = $row['valor'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title>Painel Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <?= aplicar_tema($conn) ?>
    <style>
        * { box-sizing: border-box; }
        .admin-header {
            background: #fff; padding: 1.5rem 2rem; border-radius: 1rem;
            margin-bottom: 1.5rem; display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 0.8rem;
        }
        .admin-header h1 { margin-bottom: 0.3rem; font-size: 1.4rem; }
        .admin-header p  { opacity: 0.7; font-size: 0.9rem; margin: 0; }
        .visitantes-card {
            background: linear-gradient(135deg, #065f46 0%, #059669 100%);
            color: white; padding: 1.2rem 1.5rem; border-radius: 1rem;
            display: flex; align-items: center; gap: 1rem;
            margin-bottom: 1.5rem; box-shadow: 0 4px 18px rgba(5,150,105,0.35);
        }
        .vis-icon { font-size: 2rem; }
        .vis-numero { font-size: 2rem; font-weight: 800; line-height: 1; }
        .vis-label  { font-size: 0.82rem; opacity: 0.85; margin-top: 0.2rem; }
        .vis-dot { width:9px;height:9px;background:#6ee7b7;border-radius:50%;display:inline-block;margin-right:4px;animation:piscar 1.5s infinite; }
        @keyframes piscar { 0%,100%{opacity:1}50%{opacity:0.2} }
        .quick-actions { display: grid; grid-template-columns: repeat(2,1fr); gap: 0.8rem; }
        .action-btn {
            padding: 0.85rem; background: white; border: 2px solid var(--primary);
            border-radius: 0.5rem; text-align: center; text-decoration: none;
            color: var(--primary); font-weight: 600; font-size: 0.9rem;
            transition: all 0.2s; position: relative;
        }
        .action-btn:hover { background: var(--primary); color: white; }
        .notif-wrap { position: relative; display: inline-block; font-size: 1.8rem; text-decoration: none; }
        .notif-badge-count {
            position: absolute; top: -6px; right: -10px;
            background: #e74c3c; color: white; border-radius: 50%;
            padding: 1px 5px; font-size: 0.65rem; font-weight: 800;
            animation: pulsar 1.5s infinite; line-height: 1.4;
        }
        @keyframes pulsar { 0%,100%{transform:scale(1)}50%{transform:scale(1.25)} }
        .top-produtos-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 0.8rem; }
        .top-produto-card {
            background: white; border-radius: 0.75rem; padding: 1rem 0.8rem;
            box-shadow: var(--shadow); text-align: center; position: relative;
            border: 2px solid transparent; transition: all 0.2s;
        }
        .top-produto-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .rank-badge {
            position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
            width: 26px; height: 26px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 800; color: white;
        }
        .rank-1{background:#f59e0b} .rank-2{background:#94a3b8} .rank-3{background:#b45309}
        .top-produto-card img { width: 60px; height: 60px; object-fit: contain; margin: 0.5rem auto; display: block; }
        .prod-nome  { font-size: 0.78rem; font-weight: 600; color: var(--dark); line-height: 1.3; }
        .prod-views { font-size: 0.72rem; color: var(--gray); }
        .prod-views span { font-weight: 700; color: var(--primary); }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table th { background: var(--light); padding: 0.7rem 0.6rem; text-align: left; font-weight: 600; color: var(--dark); font-size: 0.82rem; }
        table td { padding: 0.6rem; border-bottom: 1px solid var(--light); }
        table tr:hover { background: var(--light); }
        .estoque-table tr.estoque-critico td { background: #fff1f1 !important; color: #991b1b; font-weight: 700; }
        .badge-critico { background:#fee2e2;color:#991b1b;padding:0.15rem 0.5rem;border-radius:2rem;font-size:0.72rem;font-weight:700;border:1px solid #fca5a5;white-space:nowrap; }
        .badge-ok      { background:#d1fae5;color:#065f46;padding:0.15rem 0.5rem;border-radius:2rem;font-size:0.72rem;font-weight:700;border:1px solid #6ee7b7;white-space:nowrap; }
        .btn-editar-produto {
            display:inline-flex;align-items:center;gap:3px;padding:4px 10px;
            background:#f0f4ff;color:#007bff;border:1.5px solid #007bff;
            border-radius:6px;font-size:0.75rem;font-weight:600;text-decoration:none;
            transition:all .2s;white-space:nowrap;
        }
        .btn-editar-produto:hover { background:#007bff;color:#fff; }
        .pedidos-abas { display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem; }
        .pedidos-aba {
            padding:0.3rem 0.8rem;border-radius:2rem;text-decoration:none;
            font-size:0.78rem;font-weight:600;border:2px solid transparent;
            transition:all 0.2s;opacity:0.55;
        }
        .pedidos-aba.ativa { opacity:1;box-shadow:0 2px 6px rgba(0,0,0,0.12); }
        .aba-pendente  {background:#fff3cd;color:#856404;border-color:#ffc107}
        .aba-confirmado{background:#d1e7dd;color:#0a3622;border-color:#198754}
        .aba-cancelado {background:#f8d7da;color:#842029;border-color:#dc3545}
        .aba-expirado  {background:#e2e3e5;color:#41464b;border-color:#6c757d}
        .aba-todos     {background:#cfe2ff;color:#084298;border-color:#0d6efd}
        .pedido-card {
            background:#f9fafb;border-radius:0.6rem;
            padding:0.7rem 0.9rem;margin-bottom:0.5rem;
            border-left:4px solid #ccc;
        }
        .pedido-card.pendente   {border-left-color:#ffc107}
        .pedido-card.confirmado {border-left-color:#198754}
        .pedido-card.cancelado  {border-left-color:#dc3545}
        .pedido-card.expirado   {border-left-color:#6c757d;opacity:0.75}
        .pedido-topo {
            display:flex;justify-content:space-between;align-items:center;
            flex-wrap:wrap;gap:0.3rem;margin-bottom:0.5rem;
        }
        .pedido-num  {font-weight:700;font-size:0.88rem;color:var(--dark)}
        .pedido-data {font-size:0.7rem;color:#888;margin-top:1px}
        .p-status {
            padding:0.15rem 0.55rem;border-radius:2rem;font-size:0.65rem;
            font-weight:700;text-transform:uppercase;white-space:nowrap;
        }
        .p-status.pendente   {background:#fff3cd;color:#856404}
        .p-status.confirmado {background:#d1e7dd;color:#0a3622}
        .p-status.cancelado  {background:#f8d7da;color:#842029}
        .p-status.expirado   {background:#e2e3e5;color:#41464b}
        .pedido-infos {
            display:grid;grid-template-columns:repeat(4,1fr);
            gap:0.35rem 0.5rem;margin-bottom:0.5rem;
        }
        .pi-label {font-size:0.62rem;color:#aaa;text-transform:uppercase;display:block}
        .pi-val   {font-weight:600;font-size:0.75rem;margin-top:1px}
        .pedido-endereco,.pedido-obs { font-size:0.75rem;color:#555;margin-bottom:0.4rem;line-height:1.4; }
        .pedido-itens { background:white;border-radius:0.4rem;padding:0.4rem 0.6rem;margin-bottom:0.4rem; }
        .pi-title {font-size:0.62rem;color:#aaa;text-transform:uppercase;margin-bottom:0.3rem;display:block}
        .pedido-item-linha {
            display:flex;justify-content:space-between;
            padding:0.18rem 0;border-bottom:1px solid #f0f0f0;font-size:0.75rem;
            flex-direction: column;
        }
        .pedido-item-linha:last-child {border-bottom:none}
        .pedido-totais { display:flex;gap:0.8rem;font-size:0.75rem;margin-bottom:0.5rem;flex-wrap:wrap;align-items:center; }
        .pedido-totais span  {color:#888}
        .pedido-totais strong{color:var(--dark)}
        .p-total-final {font-size:0.85rem;color:#198754 !important;font-weight:700}
        .pedido-acoes {display:flex;gap:0.4rem;flex-wrap:wrap}
        .btn-p-confirmar,.btn-p-cancelar {
            padding:0.4rem 0.9rem;border:none;border-radius:0.4rem;
            font-size:0.78rem;font-weight:700;cursor:pointer;transition:all 0.2s;
        }
        .btn-p-confirmar {background:#198754;color:white}
        .btn-p-confirmar:hover {background:#146c43}
        .btn-p-cancelar  {background:#dc3545;color:white}
        .btn-p-cancelar:hover  {background:#b02a37}
        .btn-p-confirmar:disabled,.btn-p-cancelar:disabled {opacity:0.45;cursor:not-allowed}
        .pedidos-vazio {text-align:center;padding:1.5rem;color:#888}
        .pedidos-vazio span {font-size:2rem;display:block;margin-bottom:0.3rem}
        #p-toast {
            position:fixed;bottom:1rem;right:1rem;padding:0.75rem 1.2rem;
            border-radius:0.6rem;color:white;font-weight:600;font-size:0.85rem;
            display:none;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.2);
        }
        #p-toast.sucesso {background:#198754}
        #p-toast.erro    {background:#dc3545}
        .tema-grid {display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:1rem;margin-bottom:1.2rem}
        .tema-campo {display:flex;flex-direction:column;gap:0.35rem}
        .tema-campo label {font-size:0.85rem;font-weight:600;color:var(--dark)}
        .cor-preview-wrap {display:flex;align-items:center;gap:0.5rem;background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.65rem;cursor:pointer;transition:border-color 0.2s}
        .cor-preview-wrap:hover {border-color:var(--primary)}
        input[type="color"] {width:32px;height:32px;border:none;border-radius:0.3rem;cursor:pointer;padding:0;background:none;flex-shrink:0}
        .hex-value {font-size:0.82rem;font-weight:600;color:var(--dark);font-family:monospace}
        .tema-presets {display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.2rem}
        .tema-preset-btn {padding:0.4rem 0.9rem;border:2px solid transparent;border-radius:2rem;font-size:0.82rem;font-weight:600;cursor:pointer;color:white;transition:all 0.2s}
        .tema-preset-btn:hover {transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2)}
        .btn-salvar-tema {padding:0.75rem 1.8rem;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;border:none;border-radius:0.7rem;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all 0.25s}
        .btn-salvar-tema:hover {transform:translateY(-2px)}
        #tema-feedback {display:none;margin-top:0.8rem;padding:0.75rem 1rem;border-radius:0.5rem;font-weight:600;font-size:0.9rem}
        @media (max-width: 768px) {
            .navbar { display: none !important; }
            .container { padding: 0 0.6rem !important; margin: 0.5rem auto !important; }
            .admin-header { padding: 0.8rem 1rem !important; margin-bottom: 0.8rem !important; border-radius: 0.75rem !important; }
            .admin-header h1 { font-size: 1rem !important; }
            .admin-header p  { font-size: 0.75rem !important; }
            .notif-wrap { font-size: 1.4rem !important; }
            .visitantes-card { padding: 0.75rem 1rem !important; gap: 0.6rem !important; margin-bottom: 0.8rem !important; }
            .vis-icon   { font-size: 1.4rem !important; }
            .vis-numero { font-size: 1.4rem !important; }
            .vis-label  { font-size: 0.7rem !important; }
            .card { padding: 0.8rem !important; border-radius: 0.7rem !important; margin-bottom: 0.8rem !important; }
            .card-title { font-size: 0.82rem !important; margin-bottom: 0.6rem !important; }
            .quick-actions { grid-template-columns: 1fr 1fr !important; gap: 0.5rem !important; }
            .action-btn { padding: 0.6rem 0.4rem !important; font-size: 0.75rem !important; }
            .top-produtos-grid { gap: 0.4rem !important; }
            .top-produto-card { padding: 0.7rem 0.4rem !important; }
            .top-produto-card img { width: 40px !important; height: 40px !important; }
            .prod-nome  { font-size: 0.65rem !important; }
            .prod-views { font-size: 0.6rem !important; }
            .rank-badge { width: 22px !important; height: 22px !important; font-size: 0.6rem !important; }
            table { font-size: 0.7rem !important; }
            table th { padding: 0.4rem 0.35rem !important; font-size: 0.68rem !important; }
            table td { padding: 0.35rem !important; font-size: 0.68rem !important; }
            table th:first-child, table td:first-child { display: none !important; }
            .btn-editar-produto { font-size: 0.65rem !important; padding: 3px 7px !important; }
            .badge-critico, .badge-ok { font-size: 0.6rem !important; padding: 0.1rem 0.35rem !important; }
            .pedidos-abas { gap: 0.25rem !important; margin-bottom: 0.7rem !important; }
            .pedidos-aba  { font-size: 0.68rem !important; padding: 0.22rem 0.55rem !important; }
            .pedido-card  { padding: 0.55rem 0.7rem !important; margin-bottom: 0.4rem !important; }
            .pedido-num   { font-size: 0.78rem !important; }
            .pedido-data  { font-size: 0.62rem !important; }
            .p-status     { font-size: 0.58rem !important; padding: 0.12rem 0.45rem !important; }
            .pedido-infos { grid-template-columns: repeat(2,1fr) !important; gap: 0.25rem !important; margin-bottom: 0.4rem !important; }
            .pi-label { font-size: 0.58rem !important; }
            .pi-val   { font-size: 0.7rem !important; }
            .pedido-endereco, .pedido-obs { font-size: 0.68rem !important; margin-bottom: 0.3rem !important; }
            .pedido-itens { padding: 0.35rem 0.5rem !important; margin-bottom: 0.35rem !important; }
            .pi-title { font-size: 0.58rem !important; }
            .pedido-item-linha { font-size: 0.68rem !important; padding: 0.15rem 0 !important; }
            .pedido-totais { font-size: 0.68rem !important; gap: 0.5rem !important; margin-bottom: 0.4rem !important; }
            .p-total-final { font-size: 0.75rem !important; }
            .btn-p-confirmar, .btn-p-cancelar { font-size: 0.68rem !important; padding: 0.3rem 0.7rem !important; }
            .tema-grid { grid-template-columns: repeat(2,1fr) !important; gap: 0.6rem !important; }
            .tema-preset-btn { font-size: 0.7rem !important; padding: 0.3rem 0.65rem !important; }
            .btn-salvar-tema { width: 100% !important; font-size: 0.85rem !important; padding: 0.65rem !important; }
            .admin-grid { grid-template-columns: 1fr !important; }
        }
        .btn-ver-loja-mobile { display: none; }
        @media (max-width: 768px) {
            .btn-ver-loja-mobile {
                display: flex; align-items: center; justify-content: center;
                gap: 0.4rem; width: 100%; padding: 0.65rem; margin-bottom: 0.8rem;
                background: linear-gradient(135deg, var(--primary), var(--primary-dark, #1e40af));
                color: white; font-weight: 700; font-size: 0.82rem;
                border-radius: 0.6rem; text-decoration: none;
                box-shadow: 0 3px 10px rgba(37,99,235,0.3);
            }
        }
        .pil-adicionais { display: block; }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <a href="<?= $url_ver_loja ?>" class="logo"></a>
            <nav><ul>
                <?php if ($tem_loja): ?>
                <li><a href="<?= $url_ver_loja ?>">Ver Loja</a></li>
                <?php endif; ?>
                <li><a href="<?= $url_saida ?>" class="btn-carrinho" style="background:var(--danger)">Sair</a></li>
            </ul></nav>
        </div>
    </header>

    <div class="container">

    <?php if (!empty($_GET['acesso_negado'])): ?>
    <div data-aviso-plano style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;
                border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        Seu plano nao inclui acesso a essa tela.
    </div>
    <?php endif; ?>

        <div class="admin-header" style="background: white !important;">
            <div>
                <h1>Ola, <?= htmlspecialchars($_SESSION['nome_admin']) ?>!</h1>
                <p>Bem-vindo ao painel administrativo</p>
            </div>
            <?php if ($count_pendentes > 0): ?>
            <a href="#secao-pedidos" class="notif-wrap" title="<?= $count_pendentes ?> pedido(s) pendente(s)">
                <span class="notif-badge-count"><?= $count_pendentes ?></span>
            </a>
            <?php else: ?>
            <span style="font-size:1.8rem" title="Nenhum pedido pendente"></span>
            <?php endif; ?>
        </div>

        <?php if ($tem_loja): ?>
        <a href="<?= $url_ver_loja ?>" class="btn-ver-loja-mobile">Ver Loja</a>
        <?php endif; ?>

        <div class="visitantes-card">
            <div class="vis-icon"></div>
            <div class="vis-info">
                <div class="vis-numero" id="contador-visitantes">--</div>
                <div class="vis-label"><span class="vis-dot"></span>Pessoas que ja acessaram o site</div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Acoes</h2>
            <div class="quick-actions">
                <?php if ($is_master || tela_liberada('novo_produto')): ?>
                <a href="cadastro_produto.php" class="action-btn">Novo Produto</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('pedidos')): ?>
                <a href="#secao-pedidos" class="action-btn"
                   style="<?= $count_pendentes > 0 ? 'border-color:#ffc107;color:#856404;background:#fffbeb' : '' ?>">
                    Pedidos
                    <?php if ($count_pendentes > 0): ?>
                        <span style="background:#e74c3c;color:white;border-radius:50%;
                                     padding:1px 5px;font-size:0.65rem;font-weight:800;margin-left:3px">
                            <?= $count_pendentes ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('categorias')): ?>
                <a href="categorias.php" class="action-btn">Categorias</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('dashboard_vendas')): ?>
                <a href="dashboard_vendas.php" class="action-btn">Dashboard de Vendas</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('venda_presencial')): ?>
                <a href="venda_presencial.php" class="action-btn">Venda Presencial</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('cancelamentos')): ?>
                <a href="cancelamentos.php" class="action-btn">Cancelamentos</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('logs')): ?>
                <a href="logs.php" class="action-btn">Logs</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('usuarios')): ?>
                <a href="cadastrar_vendedor.php" class="action-btn">Usuarios</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('adicionais')): ?>
                <a href="adicionais_admin.php" class="action-btn">Adicionais</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('modo_restaurante')): ?>
                <a href="modo_restaurante.php" class="action-btn">Modo Restaurante</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('historico')): ?>
                <a href="historico_comandas.php" class="action-btn">Historico</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('impressoras')): ?>
                <a href="impressoras.php" class="action-btn">Impressoras</a>
                <?php endif; ?>
                <?php if ($is_master || tela_liberada('empresa')): ?>
                <a href="empresa.php" class="action-btn">Empresa</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Top 3 Mais Vistos</h2>
            <?php
            $top_lista = $mais_vistos ? $mais_vistos->fetchAll() : [];
            if (!empty($top_lista)): ?>
            <div class="top-produtos-grid">
                <?php $rank = 1; foreach ($top_lista as $p): ?>
                <div class="top-produto-card">
                    <?php if (!empty($p['imagem'])): ?>
                       <img src="<?= img_src($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>">
                    <?php else: ?>
                        <div style="width:60px;height:60px;background:#f1f5f9;border-radius:0.5rem;margin:0.5rem auto;display:flex;align-items:center;justify-content:center;font-size:1.5rem">-</div>
                    <?php endif; ?>
                    <div class="prod-nome"><?= htmlspecialchars($p['nome']) ?></div>
                    <div class="prod-views"><span><?= number_format($p['total_views']) ?></span> views</div>
                </div>
                <?php $rank++; endforeach; ?>
            </div>
            <?php else: ?>
                <p style="color:var(--gray);text-align:center;padding:1.5rem 0;font-size:0.85rem">Nenhum dado ainda.</p>
            <?php endif; ?>
        </div>

        <?php
        $labels_status = [
            'pendente'   => 'Pendente',
            'confirmado' => 'Confirmado',
            'cancelado'  => 'Cancelado',
            'expirado'   => 'Expirado',
        ];
        foreach ($pedidos as $p):
            $s = $conn->prepare("SELECT * FROM itens_pedido WHERE id_pedido = ?");
            $s->execute([$p['id_pedido']]);
            $itens_p = $s->fetchAll();
        ?>
        <div class="pedido-card <?= $p['status'] ?>" id="pcard-<?= $p['id_pedido'] ?>">
            <div class="pedido-topo">
                <div>
                    <div class="pedido-num">Pedido #<?= $p['id_pedido'] ?></div>
                    <div class="pedido-data">
                        <?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?>
                        <?php if ($p['status'] === 'pendente'):
                            $diff   = time() - strtotime($p['data_pedido']);
                            $restam = 24 - floor($diff / 3600);
                            $mins   = floor(($diff % 3600) / 60);
                        ?> - <?= $restam ?>h<?= $mins ?>min<?php endif; ?>
                    </div>
                </div>
                <span class="p-status <?= $p['status'] ?>"><?= $labels_status[$p['status']] ?? $p['status'] ?></span>
            </div>
            <div class="pedido-infos">
                <div><span class="pi-label">Cliente</span><span class="pi-val"><?= htmlspecialchars($p['nome_cliente']) ?></span></div>
                <div><span class="pi-label">Telefone</span><span class="pi-val"><?= htmlspecialchars($p['telefone_cliente']) ?></span></div>
                <div><span class="pi-label">Pagamento</span><span class="pi-val"><?= htmlspecialchars($p['forma_pagamento']) ?></span></div>
            </div>
            <?php if ($p['observacoes']): ?>
            <div class="pedido-obs">
                <span class="pi-label">Obs:</span> <?= htmlspecialchars($p['observacoes']) ?>
            </div>
            <?php endif; ?>
            <div class="pedido-itens">
                <span class="pi-title">Produtos</span>
                <?php foreach ($itens_p as $item):
                    $adicionais_item = [];
                    if (!empty($item['adicionais_json'])) {
                        $adicionais_item = json_decode($item['adicionais_json'], true) ?: [];
                    }
                ?>
                <div class="pedido-item-linha">
                    <div class="pil-nome">
                        <?= htmlspecialchars($item['nome_produto']) ?>
                        <span class="pil-qtd">(<?= $item['quantidade'] ?>x)</span>
                        <span class="pil-preco">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></span>
                    </div>
                    <?php if (!empty($adicionais_item)): ?>
                    <div class="pil-adicionais">
                        <?php foreach ($adicionais_item as $ad): ?>
                        <span class="pil-ad-tag">
                            + <?= htmlspecialchars($ad['nome']) ?>
                            <?php if (floatval($ad['preco']) > 0): ?>
                            <span class="pil-ad-preco">R$ <?= number_format($ad['preco'], 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pedido-totais">
                <div>
                    <span>Total: </span>
                    <strong class="p-total-final">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></strong>
                </div>
            </div>
            <?php if ($p['status'] === 'pendente'): ?>
            <div class="pedido-acoes">
                <button class="btn-p-confirmar" onclick="acaoPedido(<?= $p['id_pedido'] ?>, 'confirmar', this)">Confirmar Venda</button>
                <button class="btn-p-cancelar"  onclick="acaoPedido(<?= $p['id_pedido'] ?>, 'cancelar',  this)">Nao Realizada</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="card">
            <h2 class="card-title">Estoque de Produtos</h2>
            <table class="estoque-table">
                <thead><tr>
                    <th>#</th><th>Produto</th><th>Preco</th><th>Estoque</th><th>Status</th><th>Acao</th>
                </tr></thead>
                <tbody>
                <?php
                $lista_produtos = $todos_produtos ? $todos_produtos->fetchAll() : [];
                if (!empty($lista_produtos)):
                    foreach ($lista_produtos as $prod):
                        $critico = $prod['estoque'] <= 3; ?>
                <tr <?= $critico ? 'class="estoque-critico"' : '' ?>>
                    <td><?= $prod['id_produto'] ?></td>
                    <td><?= htmlspecialchars($prod['nome']) ?></td>
                    <td>R$ <?= number_format($prod['preco'],2,',','.') ?></td>
                    <td><?= $critico ? '<strong>'.$prod['estoque'].'</strong>' : $prod['estoque'] ?></td>
                    <td><?= $critico ? '<span class="badge-critico">Critico</span>' : '<span class="badge-ok">OK</span>' ?></td>
                    <td><a href="editar_produto.php?id=<?= $prod['id_produto'] ?>" class="btn-editar-produto">Editar</a></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center;color:var(--gray);padding:1.5rem">Nenhum produto.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-grid">
            <div class="card">
                <h2 class="card-title">Estoque Baixo</h2>
                <?php
                $lista_baixo = $produtos_baixo ? $produtos_baixo->fetchAll() : [];
                if (!empty($lista_baixo)):
                    foreach ($lista_baixo as $pb): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.4rem 0;border-bottom:1px solid var(--light)">
                    <span style="font-size:0.8rem;font-weight:500"><?= htmlspecialchars($pb['nome']) ?></span>
                    <span style="background:#fee2e2;color:#991b1b;padding:0.15rem 0.5rem;border-radius:2rem;font-size:0.72rem;font-weight:700"><?= $pb['estoque'] ?> un.</span>
                </div>
                <?php endforeach; else: ?>
                    <p style="color:var(--gray);font-size:0.85rem">Estoque OK em todos.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Tema do Sistema</h2>
            <p style="color:var(--gray);font-size:0.85rem;margin-bottom:1rem">Personalize as cores. Aplicado em todas as paginas imediatamente.</p>
            <p style="font-weight:600;font-size:0.85rem;margin-bottom:0.5rem;color:var(--dark)">Temas prontos:</p>
            <div class="tema-presets">
                <button class="tema-preset-btn" style="background:linear-gradient(135deg,#2563eb,#1e40af)" onclick="aplicarPreset('#2563eb','#1e40af','#10b981','#ef4444','#1e40af','#7c3aed')">Azul</button>
                <button class="tema-preset-btn" style="background:linear-gradient(135deg,#16a34a,#14532d)" onclick="aplicarPreset('#16a34a','#14532d','#2563eb','#ef4444','#14532d','#166534')">Verde</button>
                <button class="tema-preset-btn" style="background:linear-gradient(135deg,#9333ea,#6b21a8)" onclick="aplicarPreset('#9333ea','#6b21a8','#ec4899','#ef4444','#6b21a8','#4c1d95')">Roxo</button>
                <button class="tema-preset-btn" style="background:linear-gradient(135deg,#dc2626,#991b1b)" onclick="aplicarPreset('#dc2626','#991b1b','#f59e0b','#ef4444','#991b1b','#7f1d1d')">Vermelho</button>
                <button class="tema-preset-btn" style="background:linear-gradient(135deg,#ea580c,#9a3412)" onclick="aplicarPreset('#ea580c','#9a3412','#facc15','#ef4444','#9a3412','#431407')">Laranja</button>
                <button class="tema-preset-btn" style="background:linear-gradient(135deg,#0891b2,#164e63)" onclick="aplicarPreset('#0891b2','#164e63','#10b981','#ef4444','#164e63','#0c4a6e')">Ciano</button>
                <button class="tema-preset-btn" style="background:linear-gradient(135deg,#1f2937,#111827)" onclick="aplicarPreset('#1f2937','#111827','#10b981','#ef4444','#111827','#374151')">Escuro</button>
            </div>
            <p style="font-weight:600;font-size:0.85rem;margin-bottom:0.7rem;color:var(--dark)">Personalizar manualmente:</p>
            <div class="tema-grid">
                <?php
                $campos_tema = [
                    'inp_primary'     =>['hex_primary',     'Cor Principal',       'cor_primary'],
                    'inp_primary_dark'=>['hex_primary_dark','Cor Principal Escura','cor_primary_dark'],
                    'inp_secondary'   =>['hex_secondary',   'Cor Secundaria',      'cor_secondary'],
                    'inp_danger'      =>['hex_danger',      'Cor de Alerta',       'cor_danger'],
                    'inp_grad1'       =>['hex_grad1',       'Header - Cor 1',      'cor_header_grad1'],
                    'inp_grad2'       =>['hex_grad2',       'Header - Cor 2',      'cor_header_grad2'],
                ];
                foreach ($campos_tema as $id => [$hexId, $label, $chave]):
                    $val = htmlspecialchars($cores_atuais[$chave]);
                ?>
                <div class="tema-campo">
                    <label><?= $label ?></label>
                    <div class="cor-preview-wrap" onclick="document.getElementById('<?= $id ?>').click()">
                        <input type="color" id="<?= $id ?>" value="<?= $val ?>" oninput="atualizarHex(this,'<?= $hexId ?>')">
                        <span class="hex-value" id="<?= $hexId ?>"><?= $val ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="tema-campo" style="margin-bottom:1.2rem">
                <label>Cor de Fundo</label>
                <div class="cor-preview-wrap" onclick="document.getElementById('inp_fundo').click()">
                    <input type="color" id="inp_fundo" value="<?= htmlspecialchars($cores_atuais['cor_fundo'] ?? '#f1f5f9') ?>" oninput="atualizarHex(this,'hex_fundo')">
                    <span class="hex-value" id="hex_fundo"><?= htmlspecialchars($cores_atuais['cor_fundo'] ?? '#f1f5f9') ?></span>
                </div>
            </div>
            <button class="btn-salvar-tema" onclick="salvarTema()">Salvar Tema</button>
            <div id="tema-feedback"></div>
        </div>

    </div>
    <div id="p-toast"></div>

    <script>
    function atualizarVisitantes() {
        fetch('get_visitas.php').then(r=>r.json()).then(d=>{
            const el=document.getElementById('contador-visitantes');
            if(el) el.textContent=d.total.toLocaleString('pt-BR');
        }).catch(()=>{});
    }
    atualizarVisitantes();
    setInterval(atualizarVisitantes,10000);

    function acaoPedido(id,acao,btn) {
        const msg=acao==='confirmar'?'Confirmar venda e dar baixa no estoque?':'Marcar como nao realizada? Estoque NAO sera alterado.';
        if(!confirm(msg)) return;
        const card=document.getElementById('pcard-'+id);
        card.querySelectorAll('button').forEach(b=>b.disabled=true);
        fetch('acao_pedido.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id_pedido:id,acao:acao})})
        .then(r=>r.json()).then(res=>{
            if(res.success){mostrarToast(res.msg,'sucesso');setTimeout(()=>location.reload(),1500);}
            else{mostrarToast('Erro: '+res.msg,'erro');card.querySelectorAll('button').forEach(b=>b.disabled=false);}
        }).catch(()=>{mostrarToast('Erro de conexao!','erro');card.querySelectorAll('button').forEach(b=>b.disabled=false);});
    }
    function mostrarToast(msg,tipo) {
        const t=document.getElementById('p-toast');
        t.className=tipo;t.textContent=msg;t.style.display='block';
        setTimeout(()=>{t.style.display='none';},3000);
    }
    setTimeout(()=>location.reload(),60000);

    function atualizarHex(input,spanId){document.getElementById(spanId).textContent=input.value;}
    function aplicarPreset(primary,primaryDark,secondary,danger,grad1,grad2){
        const map={inp_primary:['hex_primary',primary],inp_primary_dark:['hex_primary_dark',primaryDark],inp_secondary:['hex_secondary',secondary],inp_danger:['hex_danger',danger],inp_grad1:['hex_grad1',grad1],inp_grad2:['hex_grad2',grad2]};
        for(const[inputId,[hexId,cor]]of Object.entries(map)){document.getElementById(inputId).value=cor;document.getElementById(hexId).textContent=cor;}
    }
    function salvarTema(){
        const btn=document.querySelector('.btn-salvar-tema');
        const feedback=document.getElementById('tema-feedback');
        btn.disabled=true;btn.textContent='Salvando...';
        const dados=new FormData();
        ['cor_primary:inp_primary','cor_primary_dark:inp_primary_dark','cor_secondary:inp_secondary','cor_danger:inp_danger','cor_header_grad1:inp_grad1','cor_header_grad2:inp_grad2','cor_fundo:inp_fundo'].forEach(p=>{
            const[k,id]=p.split(':');dados.append(k,document.getElementById(id).value);
        });
        fetch('salvar_tema.php',{method:'POST',body:dados}).then(r=>r.json()).then(data=>{
            feedback.style.display='block';
            if(data.success){feedback.style.background='#d1fae5';feedback.style.color='#065f46';feedback.textContent='Salvo! '+data.msg+' Recarregando...';setTimeout(()=>location.reload(),1200);}
            else{feedback.style.background='#fee2e2';feedback.style.color='#991b1b';feedback.textContent='Erro: '+data.msg;btn.disabled=false;btn.textContent='Salvar Tema';}
        }).catch(()=>{feedback.style.display='block';feedback.style.background='#fee2e2';feedback.style.color='#991b1b';feedback.textContent='Erro de conexao.';btn.disabled=false;btn.textContent='Salvar Tema';});
    }
    </script>

    <script>
    (function(){
        let ultimaVerificacao=new Date().toISOString().slice(0,19).replace('T',' ');
        function pedirPermissao(){if(!('Notification'in window))return;if(Notification.permission==='default')Notification.requestPermission();}
        pedirPermissao();
        function dispararNotificacao(pedido){
            if(Notification.permission!=='granted')return;
            const total=parseFloat(pedido.valor_total).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
            const notif=new Notification('Novo Pedido',{body:'Cliente: '+pedido.nome_cliente+'\nTotal: '+total,icon:'favicon.png',tag:'pedido-'+pedido.id_pedido,requireInteraction:true});
            notif.onclick=function(){window.focus();const s=document.getElementById('secao-pedidos');if(s)s.scrollIntoView({behavior:'smooth'});notif.close();};
        }
        function verificarNovos(){
            fetch('checar_pedidos.php?desde='+encodeURIComponent(ultimaVerificacao)).then(r=>r.json()).then(data=>{
                if(data.novos>0){
                    data.pedidos.forEach(p=>dispararNotificacao(p));
                    const badge=document.querySelector('.notif-badge-count');
                    if(badge)badge.textContent=parseInt(badge.textContent||0)+data.novos;
                    tocarSom();
                    setTimeout(()=>location.reload(),2000);
                }
                ultimaVerificacao=new Date().toISOString().slice(0,19).replace('T',' ');
            }).catch(()=>{});
        }
        setTimeout(()=>{verificarNovos();setInterval(verificarNovos,30000);},30000);
        function tocarSom(){
            try{
                const ctx=new(window.AudioContext||window.webkitAudioContext)();
                [0,0.3].forEach(delay=>{
                    const osc=ctx.createOscillator(),gain=ctx.createGain();
                    osc.connect(gain);gain.connect(ctx.destination);
                    osc.frequency.value=880;osc.type='sine';
                    gain.gain.setValueAtTime(0.3,ctx.currentTime+delay);
                    gain.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+delay+0.2);
                    osc.start(ctx.currentTime+delay);osc.stop(ctx.currentTime+delay+0.2);
                });
            }catch(e){}
        }
    })();

    (function(){
        const aviso = document.querySelector('[data-aviso-plano]');
        if (!aviso) return;
        setTimeout(() => {
            aviso.style.transition = 'opacity 0.5s';
            aviso.style.opacity = '0';
            setTimeout(() => aviso.remove(), 500);
        }, 4000);
    })();
    </script>
</body>
</html>
