<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$id_tenant = $_SESSION['id_tenant'] ?? null;

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-d');
$di = $data_inicio . ' 00:00:00';
$df = $data_fim    . ' 23:59:59';

// ✅ Helpers para montar params com tenant
function params_base($di, $df, $id_tenant) {
    return $id_tenant ? [$di, $df, $id_tenant] : [$di, $df];
}
function tf($alias = '')  {
    global $id_tenant;
    $col = $alias ? "$alias.id_tenant" : "id_tenant";
    return $id_tenant ? "AND $col = ?" : "";
}

// CARDS DE RESUMO
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(valor_total), 0) AS total_vendido,
        COUNT(*)                       AS total_pedidos,
        COALESCE(AVG(valor_total), 0) AS ticket_medio,
        COALESCE(SUM(valor_frete), 0) AS total_frete
    FROM pedidos
    WHERE data_pedido BETWEEN ? AND ?
    AND status != 'cancelado'
    " . tf());
$stmt->execute(params_base($di, $df, $id_tenant));
$resumo = $stmt->fetch();

// COMPARATIVO
$dias   = (strtotime($data_fim) - strtotime($data_inicio)) / 86400 + 1;
$di_ant = date('Y-m-d', strtotime($data_inicio . " -$dias days")) . ' 00:00:00';
$df_ant = date('Y-m-d', strtotime($data_inicio . ' -1 day'))      . ' 23:59:59';

$stmt_ant = $conn->prepare("
    SELECT COALESCE(SUM(valor_total),0) AS total_ant, COUNT(*) AS pedidos_ant
    FROM pedidos
    WHERE data_pedido BETWEEN ? AND ?
    AND status != 'cancelado'
    " . tf());
$stmt_ant->execute(params_base($di_ant, $df_ant, $id_tenant));
$anterior = $stmt_ant->fetch();

// FATURAMENTO HOJE
$hoje_di = date('Y-m-d') . ' 00:00:00';
$hoje_df = date('Y-m-d') . ' 23:59:59';

$stmt_hoje = $conn->prepare("
    SELECT 
        COALESCE(SUM(valor_total), 0) AS total_hoje,
        COUNT(*) AS pedidos_hoje
    FROM pedidos
    WHERE data_pedido BETWEEN ? AND ?
    AND status != 'cancelado'
    " . tf());
$stmt_hoje->execute(params_base($hoje_di, $hoje_df, $id_tenant));
$faturamento_hoje = $stmt_hoje->fetch();

// PRODUTOS MAIS VENDIDOS
$stmt_prod = $conn->prepare("
    SELECT 
        p.nome, p.imagem, c.nome AS categoria,
        SUM(ip.quantidade)           AS qtd_vendida,
        SUM(ip.subtotal)             AS receita,
        COUNT(DISTINCT ip.id_pedido) AS em_pedidos
    FROM itens_pedido ip
    JOIN pedidos pd ON pd.id_pedido = ip.id_pedido
    JOIN produtos p  ON p.id_produto = ip.id_produto
    LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
    WHERE pd.data_pedido BETWEEN ? AND ?
    AND pd.status != 'cancelado'
    " . tf('pd') . "
    GROUP BY ip.id_produto, p.nome, p.imagem, c.nome
    ORDER BY qtd_vendida DESC
    LIMIT 10
");
$stmt_prod->execute(params_base($di, $df, $id_tenant));
$top_produtos = $stmt_prod->fetchAll();

// CATEGORIAS MAIS VENDIDAS
$stmt_cat = $conn->prepare("
    SELECT 
        COALESCE(c.nome, 'Sem categoria') AS categoria,
        SUM(ip.quantidade) AS qtd,
        SUM(ip.subtotal)   AS receita
    FROM itens_pedido ip
    JOIN pedidos pd ON pd.id_pedido = ip.id_pedido
    JOIN produtos p  ON p.id_produto = ip.id_produto
    LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
    WHERE pd.data_pedido BETWEEN ? AND ?
    AND pd.status != 'cancelado'
    " . tf('pd') . "
    GROUP BY c.id_categoria, c.nome
    ORDER BY receita DESC
    LIMIT 6
");
$stmt_cat->execute(params_base($di, $df, $id_tenant));
$categorias = $stmt_cat->fetchAll();

// ULTIMOS PEDIDOS
$stmt_ult = $conn->prepare("
    SELECT id_pedido, nome_cliente, forma_pagamento, valor_total, status,
           COALESCE(tipo, 'online') AS tipo, data_pedido
    FROM pedidos
    WHERE data_pedido BETWEEN ? AND ?
    " . tf() . "
    ORDER BY data_pedido DESC
    LIMIT 8
");
$stmt_ult->execute(params_base($di, $df, $id_tenant));
$ultimos_pedidos = $stmt_ult->fetchAll();

// GRAFICO
$stmt_graf = $conn->prepare("
    SELECT DATE(data_pedido) AS dia, COUNT(*) AS pedidos, SUM(valor_total) AS total
    FROM pedidos
    WHERE data_pedido BETWEEN ? AND ?
    AND status != 'cancelado'
    " . tf() . "
    GROUP BY DATE(data_pedido)
    ORDER BY dia ASC
");
$stmt_graf->execute(params_base($di, $df, $id_tenant));
$grafico_dados = $stmt_graf->fetchAll();

// FORMAS DE PAGAMENTO
$stmt_pag = $conn->prepare("
    SELECT forma_pagamento, COUNT(*) AS qtd, SUM(valor_total) AS total
    FROM pedidos
    WHERE data_pedido BETWEEN ? AND ?
    AND status != 'cancelado'
    " . tf() . "
    GROUP BY forma_pagamento
    ORDER BY qtd DESC
");
$stmt_pag->execute(params_base($di, $df, $id_tenant));
$pagamentos = $stmt_pag->fetchAll();

// CANCELADOS
$stmt_canc = $conn->prepare("
    SELECT COUNT(*) FROM pedidos
    WHERE data_pedido BETWEEN ? AND ?
    AND status = 'cancelado'
    " . tf());
$stmt_canc->execute(params_base($di, $df, $id_tenant));
$total_cancelados = $stmt_canc->fetchColumn();

// VENDAS POR ATENDENTE
$stmt_atend = $conn->prepare("
    SELECT 
        ci.lancado_por AS operador,
        COUNT(DISTINCT ci.id_comanda) AS total_comandas,
        SUM(ci.subtotal)              AS total_vendido,
        COUNT(ci.id_item)             AS total_itens
    FROM comanda_itens ci
    JOIN comandas c ON c.id_comanda = ci.id_comanda
    JOIN pedidos p ON p.observacoes LIKE CONCAT('%Comanda #', c.numero_comanda, '%')
    WHERE p.data_pedido BETWEEN ? AND ?
    AND p.status != 'cancelado'
    " . tf('p') . "
    AND ci.lancado_por IS NOT NULL
    GROUP BY ci.lancado_por
    ORDER BY total_vendido DESC
");
$stmt_atend->execute(params_base($di, $df, $id_tenant));
$vendas_atendentes = $stmt_atend->fetchAll();

// PRODUTOS POR ATENDENTE
$stmt_atend_prods = $conn->prepare("
    SELECT 
        ci.lancado_por AS operador,
        ci.nome_produto,
        SUM(ci.quantidade) AS qtd,
        SUM(ci.subtotal)   AS total
    FROM comanda_itens ci
    JOIN comandas c ON c.id_comanda = ci.id_comanda
    JOIN pedidos p ON p.observacoes LIKE CONCAT('%Comanda #', c.numero_comanda, '%')
    WHERE p.data_pedido BETWEEN ? AND ?
    AND p.status != 'cancelado'
    " . tf('p') . "
    AND ci.lancado_por IS NOT NULL
    GROUP BY ci.lancado_por, ci.nome_produto
    ORDER BY ci.lancado_por, qtd DESC
");
$stmt_atend_prods->execute(params_base($di, $df, $id_tenant));
$prods_por_atendente = [];
foreach ($stmt_atend_prods->fetchAll() as $row) {
    $prods_por_atendente[$row['operador']][] = $row;
}

function variacao($atual, $anterior) {
    if ($anterior == 0) return $atual > 0 ? 100 : 0;
    return round((($atual - $anterior) / $anterior) * 100, 1);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title>Dashboard de Vendas</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        :root { --dash-bg: #f1f5f9; --card-bg: #ffffff; --primary: #2563eb; --primary-dark: #1d4ed8; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --purple: #8b5cf6; --dark: #1e293b; --gray: #64748b; --border: #e2e8f0; --shadow: 0 2px 12px rgba(37,99,235,0.07); }
        body { background: var(--dash-bg); }
        @media (max-width: 768px) { html { font-size: 13px; } }
        .dash-wrapper { max-width: 1400px; margin: 0 auto; padding: 1rem 0.75rem 3rem; }
        @media (min-width: 769px) { .dash-wrapper { padding: 2rem 1.5rem 4rem; } }
        .dash-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1rem; }
        .btn-voltar { background: #f1f5f9; color: #64748b; padding: 0.45rem 1rem; border-radius: 0.45rem; text-decoration: none; font-weight: 600; font-size: 0.82rem; white-space: nowrap; }
        .filtro-card { background: var(--card-bg); border-radius: 0.75rem; padding: 0.9rem 1rem; box-shadow: var(--shadow); display: flex; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; border-left: 3px solid var(--primary); }
        .filtro-card label { font-size: 0.75rem; font-weight: 600; color: var(--gray); }
        .filtro-card input[type="date"] { padding: 0.4rem 0.7rem; border: 1.5px solid var(--border); border-radius: 0.4rem; font-size: 0.82rem; color: var(--dark); outline: none; width: 100%; }
        .filtro-form { display: flex; align-items: flex-end; gap: 0.5rem; flex-wrap: wrap; flex: 1; }
        .filtro-form-group { display: flex; flex-direction: column; gap: 0.2rem; }
        .btn-filtrar { padding: 0.45rem 1rem; background: var(--primary); color: white; border: none; border-radius: 0.4rem; font-weight: 700; font-size: 0.82rem; cursor: pointer; white-space: nowrap; height: 34px; }
        .periodo-shortcuts { display: flex; gap: 0.4rem; flex-wrap: wrap; width: 100%; }
        @media (min-width: 769px) { .periodo-shortcuts { width: auto; } }
        .btn-periodo { padding: 0.3rem 0.7rem; background: #edf2f8; color: var(--primary); border: 1.5px solid #bfdbfe; border-radius: 0.4rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.15s; text-decoration: none; }
        .btn-periodo:hover, .btn-periodo.ativo { background: var(--primary); color: white; border-color: var(--primary); }
        .cards-resumo { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; margin-bottom: 1rem; }
        @media (max-width: 400px) { .cards-resumo { grid-template-columns: 1fr 1fr; } }
        .card-resumo { background: var(--card-bg); border-radius: 0.75rem; padding: 0.85rem 0.9rem; box-shadow: var(--shadow); position: relative; overflow: hidden; }
        @media (min-width: 769px) { .card-resumo { padding: 1.5rem; border-radius: 1rem; } }
        .card-resumo::before { content: ''; position: absolute; top: 0; right: 0; width: 55px; height: 55px; border-radius: 0 0.75rem 0 100%; opacity: 0.12; }
        .card-resumo.azul::before { background: var(--primary); } .card-resumo.verde::before { background: var(--success); } .card-resumo.roxo::before { background: var(--purple); }
        .card-resumo-label { font-size: 0.68rem; font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.3rem; }
        .card-resumo-valor { font-size: 1.1rem; font-weight: 800; color: var(--dark); line-height: 1.1; margin-bottom: 0.3rem; word-break: break-all; }
        @media (min-width: 769px) { .card-resumo-label { font-size: 0.8rem; } .card-resumo-valor { font-size: 1.75rem; } }
        .card-resumo-var { font-size: 0.68rem; font-weight: 600; display: flex; align-items: center; gap: 0.2rem; }
        .card-resumo-var.up { color: var(--success); } .card-resumo-var.down { color: var(--danger); } .card-resumo-var.neutro { color: var(--gray); }
        .dash-grid-2 { display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin-bottom: 0.75rem; }
        .dash-grid-3 { display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin-bottom: 0.75rem; }
        @media (min-width: 769px) { .dash-grid-2 { grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; } .dash-grid-3 { grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; } }
        .dash-card { background: var(--card-bg); border-radius: 0.75rem; padding: 0.85rem 0.9rem; box-shadow: var(--shadow); margin-bottom: 0.75rem; }
        @media (min-width: 769px) { .dash-card { border-radius: 1rem; padding: 1.5rem; margin-bottom: 0; } }
        .dash-card-title { font-size: 0.85rem; font-weight: 700; color: var(--dark); margin-bottom: 0.9rem; display: flex; align-items: center; gap: 0.4rem; padding-bottom: 0.6rem; border-bottom: 2px solid var(--dash-bg); }
        .ranking-table { width: 100%; border-collapse: collapse; }
        .ranking-table th { text-align: left; font-size: 0.68rem; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 0.4px; padding: 0.4rem 0.5rem; }
        .ranking-table td { padding: 0.5rem; font-size: 0.78rem; color: var(--dark); border-bottom: 1px solid var(--dash-bg); vertical-align: middle; }
        .ranking-table tr:last-child td { border-bottom: none; }
        @media (max-width: 768px) { .col-cat { display: none; } }
        .prod-img-mini { width: 28px; height: 28px; object-fit: cover; border-radius: 0.3rem; border: 1px solid var(--border); flex-shrink: 0; }
        .prod-info-mini { display: flex; align-items: center; gap: 0.45rem; }
        .badge-status { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 2rem; font-size: 0.68rem; font-weight: 600; white-space: nowrap; }
        .badge-pendente { background: #fef3c7; color: #854d0e; } .badge-aprovado { background: #d1fae5; color: #065f46; } .badge-cancelado { background: #fee2e2; color: #991b1b; }
        .badge-tipo { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 2rem; font-size: 0.62rem; font-weight: 700; white-space: nowrap; margin-left: 0.3rem; }
        .badge-presencial { background: #fef3c7; color: #92400e; } .badge-online { background: #ede9fe; color: #5b21b6; }
        .cat-bar-item { margin-bottom: 0.75rem; }
        .cat-bar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
        .cat-bar-nome { font-size: 0.78rem; font-weight: 600; color: var(--dark); } .cat-bar-valor { font-size: 0.72rem; color: var(--primary); font-weight: 700; }
        .cat-bar-bg { height: 6px; background: var(--dash-bg); border-radius: 99px; overflow: hidden; }
        .cat-bar-fill { height: 100%; border-radius: 99px; transition: width 1s ease-out; }
        .cat-bar-sub { font-size: 0.68rem; color: var(--gray); margin-top: 0.15rem; }
        .pag-item { display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0; border-bottom: 1px solid var(--dash-bg); }
        .pag-item:last-child { border-bottom: none; }
        .pag-icone { font-size: 1.1rem; margin-right: 0.45rem; } .pag-nome { font-size: 0.8rem; font-weight: 600; color: var(--dark); } .pag-qtd { font-size: 0.7rem; color: var(--gray); } .pag-valor { font-size: 0.82rem; font-weight: 700; color: var(--success); }
        .pedido-row { display: grid; grid-template-columns: auto 1fr auto; grid-template-rows: auto auto; gap: 0.1rem 0.5rem; padding: 0.6rem 0; border-bottom: 1px solid var(--dash-bg); align-items: center; }
        .pedido-row:last-child { border-bottom: none; }
        .pedido-id { font-weight: 700; color: var(--primary); font-size: 0.78rem; grid-column: 1; grid-row: 1; }
        .pedido-nome { font-size: 0.78rem; color: var(--dark); font-weight: 600; grid-column: 2; grid-row: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pedido-val { font-weight: 700; color: var(--success); font-size: 0.82rem; grid-column: 3; grid-row: 1; text-align: right; }
        .pedido-sub { font-size: 0.68rem; color: var(--gray); grid-column: 1 / 3; grid-row: 2; }
        .pedido-badge { grid-column: 3; grid-row: 2; text-align: right; }
        .pedido-pag, .pedido-data-full { display: none; }
        @media (min-width: 769px) { .pedido-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; padding: 0.75rem 0; } .pedido-sub { display: none; } .pedido-pag { display: inline; font-size: 0.82rem; color: var(--gray); } .pedido-data-full { display: inline; font-size: 0.78rem; color: var(--gray); } }
        .empty-dash { text-align: center; padding: 1.5rem; color: var(--gray); font-size: 0.82rem; }
        @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
    </style>
</head>
<body>
    <aside id="sidebar">
        <div class="sidebar-header"><h3>Menu</h3><button class="close-sidebar" onclick="toggleSidebar()">x</button></div>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="admin.php">Admin</a></li>
            <li><a href="cadastro_produto.php">Cadastrar Produto</a></li>
            <li><a href="categorias.php">Categorias</a></li>
            <li><a href="dashboard_vendas.php">Dashboard</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </aside>
    <div id="overlay" onclick="toggleSidebar()"></div>

    <div class="dash-wrapper">
        <div class="dash-header">
            <a href="admin.php" class="btn-voltar">← Voltar</a>
        </div>

        <div class="filtro-card">
            <form method="GET" action="" class="filtro-form">
                <div class="filtro-form-group"><label>De</label><input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>"></div>
                <div class="filtro-form-group"><label>Ate</label><input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>"></div>
                <button type="submit" class="btn-filtrar">Filtrar</button>
            </form>
            <div class="periodo-shortcuts">
                <?php $hoje = date('Y-m-d'); $semana = date('Y-m-d', strtotime('-6 days')); $mes_ini = date('Y-m-01'); $mes_3 = date('Y-m-d', strtotime('-89 days')); $ano_ini = date('Y-01-01'); ?>
                <a href="?data_inicio=<?= $hoje ?>&data_fim=<?= $hoje ?>" class="btn-periodo <?= ($data_inicio===$hoje && $data_fim===$hoje)?'ativo':'' ?>">Hoje</a>
                <a href="?data_inicio=<?= $semana ?>&data_fim=<?= $hoje ?>" class="btn-periodo">7d</a>
                <a href="?data_inicio=<?= $mes_ini ?>&data_fim=<?= $hoje ?>" class="btn-periodo <?= ($data_inicio===$mes_ini && $data_fim===$hoje)?'ativo':'' ?>">Mes</a>
                <a href="?data_inicio=<?= $mes_3 ?>&data_fim=<?= $hoje ?>" class="btn-periodo">90d</a>
                <a href="?data_inicio=<?= $ano_ini ?>&data_fim=<?= $hoje ?>" class="btn-periodo">Ano</a>
            </div>
        </div>

        <?php $var_vendas = variacao($resumo['total_vendido'], $anterior['total_ant']); $var_pedidos = variacao($resumo['total_pedidos'], $anterior['pedidos_ant']); ?>
        <div class="cards-resumo">
            <div class="card-resumo azul">
                <div class="card-resumo-label">Total Vendido</div>
                <div class="card-resumo-valor">R$ <?= number_format($resumo['total_vendido'], 2, ',', '.') ?></div>
                <div class="card-resumo-var <?= $var_vendas >= 0 ? 'up' : 'down' ?>"><?= $var_vendas >= 0 ? '↑' : '↓' ?> <?= abs($var_vendas) ?>%</div>
            </div>
            <div class="card-resumo verde">
                <div class="card-resumo-label">Pedidos</div>
                <div class="card-resumo-valor"><?= number_format($resumo['total_pedidos'], 0, ',', '.') ?></div>
                <div class="card-resumo-var <?= $var_pedidos >= 0 ? 'up' : 'down' ?>"><?= $var_pedidos >= 0 ? '↑' : '↓' ?> <?= abs($var_pedidos) ?>%</div>
            </div>
            <div class="card-resumo roxo">
                <div class="card-resumo-label">Faturamento Hoje</div>
                <div class="card-resumo-valor">R$ <?= number_format($faturamento_hoje['total_hoje'], 2, ',', '.') ?></div>
                <div class="card-resumo-var neutro"><?= $faturamento_hoje['pedidos_hoje'] ?> pedido(s) hoje</div>
            </div>
        </div>

        <div class="dash-grid-3">
            <div class="dash-card">
                <div class="dash-card-title"> Vendas por Dia</div>
                <?php if (count($grafico_dados) > 0): ?><canvas id="grafico-vendas" height="120"></canvas>
                <?php else: ?><div class="empty-dash">Nenhuma venda no periodo.</div><?php endif; ?>
            </div>
            <div class="dash-card">
                <div class="dash-card-title"> Formas de Pagamento</div>
                <?php if (count($pagamentos) > 0): foreach ($pagamentos as $pag): ?>
                    <div class="pag-item">
                        <div style="display:flex;align-items:center;">
                            <span class="pag-icone"></span>
                            <div><div class="pag-nome"><?= htmlspecialchars($pag['forma_pagamento']) ?></div><div class="pag-qtd"><?= $pag['qtd'] ?> pedido(s)</div></div>
                        </div>
                        <div class="pag-valor">R$ <?= number_format($pag['total'], 2, ',', '.') ?></div>
                    </div>
                <?php endforeach; else: ?><div class="empty-dash">Sem dados.</div><?php endif; ?>
            </div>
        </div>

        <div class="dash-grid-3">
            <div class="dash-card">
                <div class="dash-card-title"> Produtos Mais Vendidos</div>
                <?php if (count($top_produtos) > 0): ?>
                <table class="ranking-table">
                    <thead><tr><th>Produto</th><th class="col-cat">Categoria</th><th style="text-align:center">Qtd</th><th style="text-align:right">Receita</th></tr></thead>
                    <tbody>
                        <?php foreach ($top_produtos as $prod): ?>
                        <tr>
                           <td><span style="font-weight:600;"><?= htmlspecialchars($prod['nome']) ?></span></td>
                            <td class="col-cat" style="color:var(--gray);"><?= htmlspecialchars($prod['categoria'] ?? '-') ?></td>
                            <td style="text-align:center;font-weight:700;color:var(--primary);"><?= $prod['qtd_vendida'] ?></td>
                            <td style="text-align:right;font-weight:700;color:var(--success);">R$ <?= number_format($prod['receita'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><div class="empty-dash">Nenhuma venda no periodo.</div><?php endif; ?>
            </div>
            <div class="dash-card">
                <div class="dash-card-title"> Categorias</div>
                <?php if (count($categorias) > 0):
                    $max_cat = max(array_column($categorias, 'receita'));
                    $cores = ['var(--primary)','var(--success)','var(--purple)','var(--warning)','var(--danger)','#06b6d4'];
                    foreach ($categorias as $ci => $cat):
                        $pct = $max_cat > 0 ? ($cat['receita'] / $max_cat) * 100 : 0;
                ?>
                    <div class="cat-bar-item">
                        <div class="cat-bar-header"><span class="cat-bar-nome"><?= htmlspecialchars($cat['categoria']) ?></span><span class="cat-bar-valor">R$ <?= number_format($cat['receita'], 2, ',', '.') ?></span></div>
                        <div class="cat-bar-bg"><div class="cat-bar-fill" style="width:<?= round($pct) ?>%;background:<?= $cores[$ci % count($cores)] ?>;"></div></div>
                        <div class="cat-bar-sub"><?= $cat['qtd'] ?> unid.</div>
                    </div>
                <?php endforeach; else: ?><div class="empty-dash">Sem dados.</div><?php endif; ?>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-title"> Ultimos Pedidos</div>
            <?php if (count($ultimos_pedidos) > 0):
                $status_class = ['pendente'=>'badge-pendente','aprovado'=>'badge-aprovado','enviado'=>'badge-aprovado','entregue'=>'badge-aprovado','cancelado'=>'badge-cancelado'];
                foreach ($ultimos_pedidos as $ped):
                    $cls = $status_class[$ped['status']] ?? 'badge-pendente';
                    $tipo = $ped['tipo'] ?? 'online';
            ?>
            <div class="pedido-row">
                <span class="pedido-id">#<?= $ped['id_pedido'] ?><span class="badge-tipo <?= $tipo==='presencial'?'badge-presencial':'badge-online' ?>"><?= $tipo==='presencial'?'Loja':'Online' ?></span></span>
                <span class="pedido-nome"><?= htmlspecialchars($ped['nome_cliente']) ?></span>
                <span class="pedido-val">R$ <?= number_format($ped['valor_total'], 2, ',', '.') ?></span>
                <span class="pedido-sub"><?= htmlspecialchars($ped['forma_pagamento']) ?> - <?= date('d/m H:i', strtotime($ped['data_pedido'])) ?></span>
                <span class="pedido-badge"><span class="badge-status <?= $cls ?>"><?= ucfirst($ped['status']) ?></span></span>
                <span class="pedido-pag"><?= htmlspecialchars($ped['forma_pagamento']) ?></span>
                <span class="pedido-data-full"><?= date('d/m/Y H:i', strtotime($ped['data_pedido'])) ?></span>
            </div>
            <?php endforeach; else: ?><div class="empty-dash">Nenhum pedido no periodo.</div><?php endif; ?>
        </div>

        <?php if (!empty($vendas_atendentes)): ?>
        <div class="dash-card" style="margin-top:0.75rem;margin-bottom:0;">
            <div class="dash-card-title"> Vendas por Atendente / Caixa</div>
            <div style="overflow-x:auto;">
                <table class="ranking-table">
                    <thead><tr><th>Operador</th><th style="text-align:center;">Comandas</th><th style="text-align:center;">Itens</th><th style="text-align:right;">Total Vendido</th><th style="text-align:center;">Detalhes</th></tr></thead>
                    <tbody>
                        <?php $total_geral_atend = array_sum(array_column($vendas_atendentes, 'total_vendido'));
                        foreach ($vendas_atendentes as $atend):
                            $pct = $total_geral_atend > 0 ? round(($atend['total_vendido'] / $total_geral_atend) * 100, 1) : 0;
                            $prods_json = htmlspecialchars(json_encode($prods_por_atendente[$atend['operador']] ?? []), ENT_QUOTES);
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:0.75rem;flex-shrink:0;"><?= strtoupper(substr($atend['operador'], 0, 2)) ?></div>
                                    <div><div style="font-weight:700;font-size:0.85rem;"><?= htmlspecialchars($atend['operador']) ?></div><div style="font-size:0.7rem;color:var(--gray);"><?= $pct ?>% do total</div></div>
                                </div>
                            </td>
                            <td style="text-align:center;font-weight:700;color:var(--primary);"><?= $atend['total_comandas'] ?></td>
                            <td style="text-align:center;font-weight:700;color:var(--purple);"><?= $atend['total_itens'] ?></td>
                            <td style="text-align:right;font-weight:800;color:var(--success);">R$ <?= number_format($atend['total_vendido'], 2, ',', '.') ?></td>
                            <td style="text-align:center;">
                                <button onclick="abrirModalAtendente('<?= htmlspecialchars(addslashes($atend['operador'])) ?>', <?= $pct ?>, '<?= number_format($atend['total_vendido'],2,',','.') ?>', <?= $atend['total_itens'] ?>, <?= $atend['total_comandas'] ?>, '<?= $prods_json ?>')" style="padding:0.3rem 0.8rem;background:var(--primary);color:#fff;border:none;border-radius:0.4rem;font-size:0.75rem;font-weight:700;cursor:pointer;">Ver</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div id="modal-atendente" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
            <div style="background:#fff;border-radius:1rem;padding:1.5rem;width:100%;max-width:520px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div id="modal-avatar" style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1rem;"></div>
                        <div><div id="modal-nome" style="font-size:1rem;font-weight:800;color:var(--dark);"></div><div id="modal-sub" style="font-size:0.75rem;color:var(--gray);"></div></div>
                    </div>
                    <button onclick="fecharModalAtendente()" style="background:none;border:none;font-size:1.25rem;cursor:pointer;color:var(--gray);">✕</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.6rem;margin-bottom:1.25rem;">
                    <div style="background:#eff6ff;border-radius:0.6rem;padding:0.75rem;text-align:center;"><div style="font-size:1.2rem;font-weight:800;color:var(--primary);" id="modal-total"></div><div style="font-size:0.65rem;color:var(--gray);font-weight:600;text-transform:uppercase;">Total</div></div>
                    <div style="background:#f0fdf4;border-radius:0.6rem;padding:0.75rem;text-align:center;"><div style="font-size:1.2rem;font-weight:800;color:var(--success);" id="modal-itens"></div><div style="font-size:0.65rem;color:var(--gray);font-weight:600;text-transform:uppercase;">Itens</div></div>
                    <div style="background:#faf5ff;border-radius:0.6rem;padding:0.75rem;text-align:center;"><div style="font-size:1.2rem;font-weight:800;color:var(--purple);" id="modal-cmds"></div><div style="font-size:0.65rem;color:var(--gray);font-weight:600;text-transform:uppercase;">Comandas</div></div>
                </div>
                <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);margin-bottom:0.75rem;">Produtos Lancados</div>
                <div id="modal-produtos"></div>
            </div>
        </div>

    </div>

    <script src="js/main.js"></script>
    <script>
    <?php if (count($grafico_dados) > 0): ?>
    const labels  = <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['dia'])), $grafico_dados)) ?>;
    const totais  = <?= json_encode(array_map(fn($r) => round($r['total'], 2), $grafico_dados)) ?>;
    const pedidos = <?= json_encode(array_map(fn($r) => intval($r['pedidos']), $grafico_dados)) ?>;
    const isMobile = window.innerWidth < 769;
    const ctx = document.getElementById('grafico-vendas').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [
            { label: 'Receita (R$)', data: totais, backgroundColor: 'rgba(37,99,235,0.15)', borderColor: '#2563eb', borderWidth: 2, borderRadius: 4, yAxisID: 'y', order: 2 },
            { label: 'Pedidos', data: pedidos, type: 'line', borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', borderWidth: 2, pointBackgroundColor: '#10b981', pointRadius: isMobile ? 2 : 4, tension: 0.4, yAxisID: 'y1', order: 1 }
        ]},
        options: {
            responsive: true, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { font: { size: isMobile ? 10 : 12 }, boxWidth: 12 } }, tooltip: { callbacks: { label: ctx => ctx.datasetIndex === 0 ? ' R$ ' + ctx.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : ' ' + ctx.raw + ' pedido(s)' } } },
            scales: {
                x: { ticks: { font: { size: isMobile ? 9 : 11 }, maxRotation: 45 } },
                y: { position: 'left', grid: { color: '#e2e8f0' }, ticks: { font: { size: isMobile ? 9 : 11 }, callback: v => isMobile ? 'R$' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v) : 'R$ ' + v.toLocaleString('pt-BR') } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { stepSize: 1, font: { size: isMobile ? 9 : 11 } } }
            }
        }
    });
    <?php endif; ?>

    function abrirModalAtendente(nome, pct, total, itens, cmds, prodsJson) {
        const prods = typeof prodsJson === 'string' ? JSON.parse(prodsJson) : prodsJson;
        document.getElementById('modal-avatar').textContent = nome.substring(0,2).toUpperCase();
        document.getElementById('modal-nome').textContent = nome;
        document.getElementById('modal-sub').textContent = pct + '% do faturamento total';
        document.getElementById('modal-total').textContent = 'R$ ' + total;
        document.getElementById('modal-itens').textContent = itens;
        document.getElementById('modal-cmds').textContent = cmds;
        const maxQtd = prods.length > 0 ? Math.max(...prods.map(p => parseInt(p.qtd))) : 1;
        const totalVendido = prods.reduce((s, p) => s + parseFloat(p.total), 0);
        const cores = ['#2563eb','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4'];
        document.getElementById('modal-produtos').innerHTML = prods.length === 0
            ? '<div style="text-align:center;color:#94a3b8;padding:1rem;">Nenhum produto encontrado.</div>'
            : prods.map((p, i) => {
                const pctQtd = Math.round((p.qtd / maxQtd) * 100);
                const pctValor = totalVendido > 0 ? ((p.total / totalVendido) * 100).toFixed(1) : 0;
                const cor = cores[i % cores.length];
                return `<div style="margin-bottom:0.9rem;"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.2rem;"><span style="font-size:0.82rem;font-weight:600;color:#1e293b;">${p.nome_produto}</span><span style="font-size:0.75rem;font-weight:700;color:${cor};">${pctValor}% · R$ ${parseFloat(p.total).toLocaleString('pt-BR',{minimumFractionDigits:2})}</span></div><div style="height:7px;background:#f1f5f9;border-radius:99px;overflow:hidden;"><div style="width:${pctQtd}%;height:100%;background:${cor};border-radius:99px;"></div></div><div style="font-size:0.68rem;color:#94a3b8;margin-top:0.15rem;">${p.qtd} unidade(s)</div></div>`;
            }).join('');
        document.getElementById('modal-atendente').style.display = 'flex';
    }
    function fecharModalAtendente() { document.getElementById('modal-atendente').style.display = 'none'; }
    document.getElementById('modal-atendente').addEventListener('click', function(e) { if (e.target === this) fecharModalAtendente(); });
    </script>
</body>
</html>
