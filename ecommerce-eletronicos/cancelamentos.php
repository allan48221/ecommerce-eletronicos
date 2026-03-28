<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$filtro_tipo   = $_GET['tipo']   ?? 'todos';
$filtro_status = $_GET['status'] ?? 'todos';
$busca         = trim($_GET['busca'] ?? '');

if (!in_array($filtro_tipo,   ['todos','online','presencial'])) $filtro_tipo = 'todos';
if (!in_array($filtro_status, ['todos','pendente','confirmado','aprovado'])) $filtro_status = 'todos';

$where  = "WHERE p.status NOT IN ('cancelado','expirado')";
$params = [];

if ($filtro_tipo !== 'todos') {
    $where .= " AND COALESCE(p.tipo,'online') = ?";
    $params[] = $filtro_tipo;
}
if ($filtro_status !== 'todos') {
    $where .= " AND p.status = ?";
    $params[] = $filtro_status;
}
if ($busca !== '') {
    $where .= " AND (CAST(p.id_pedido AS TEXT) ILIKE ? OR p.nome_cliente ILIKE ? OR p.telefone_cliente ILIKE ?)";
    $like = '%'.$busca.'%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql = "SELECT p.id_pedido, p.nome_cliente, p.telefone_cliente,
               p.forma_pagamento, p.valor_total, p.status,
               COALESCE(p.tipo,'online') AS tipo,
               p.data_pedido, p.observacoes
        FROM pedidos p $where
        ORDER BY p.data_pedido DESC LIMIT 100";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$cnt = $conn->query("
    SELECT
        COUNT(*) FILTER (WHERE status NOT IN ('cancelado','expirado')) AS todos,
        COUNT(*) FILTER (WHERE status NOT IN ('cancelado','expirado') AND COALESCE(tipo,'online')='online') AS online,
        COUNT(*) FILTER (WHERE status NOT IN ('cancelado','expirado') AND tipo='presencial') AS presencial
    FROM pedidos
")->fetch();

$ids = array_column($pedidos, 'id_pedido');
$itens_map = [];
if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $si = $conn->prepare("SELECT id_pedido, nome_produto, quantidade, subtotal FROM itens_pedido WHERE id_pedido IN ($ph)");
    $si->execute($ids);
    foreach ($si->fetchAll() as $row) {
        $itens_map[$row['id_pedido']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cancelamentos - Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sora', sans-serif;
            background: #fef2f2;
            min-height: 100vh;
            color: #0f172a;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 24px 80px;
        }

        /* ── Topbar ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            background: #fff;
            color: #475569;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
            font-family: 'Sora', sans-serif;
        }
        .btn-back:hover { background: #f8fafc; }

        /* ── Hero ── */
        .hero {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            border-radius: 20px;
            padding: 28px 30px 24px;
            color: #fff;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            right: -20px; top: -20px;
            width: 150px; height: 150px;
            background: rgba(255,255,255,.07);
            border-radius: 50%;
        }
        .hero h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .hero p  { font-size: 13px; opacity: .75; }

        /* ── Filtros ── */
        .filtros-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 16px 18px;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }
        .filtros-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 160px;
        }
        .filtro-group label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #64748b;
        }
        .filtro-group select,
        .filtro-group input {
            padding: 10px 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: 14px;
            font-family: 'Sora', sans-serif;
            color: #0f172a;
            outline: none;
            background: #fff;
            transition: .2s;
        }
        .filtro-group select:focus,
        .filtro-group input:focus { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
        .btn-filtrar {
            padding: 10px 22px;
            background: var(--primary, #2563eb);
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            cursor: pointer;
            height: 42px;
            white-space: nowrap;
        }
        .btn-limpar {
            padding: 10px 16px;
            background: #f1f5f9;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Sora', sans-serif;
            text-decoration: none;
            height: 42px;
            display: inline-flex;
            align-items: center;
        }

        /* ── Pills ── */
        .pills { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .pill {
            padding: 7px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid transparent;
            transition: .15s;
            font-family: 'Sora', sans-serif;
        }
        .pill-todos      { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .pill-todos.ativo, .pill-todos:hover { background: #e2e8f0; color: #1e293b; }
        .pill-online     { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
        .pill-online.ativo, .pill-online:hover { background: #7c3aed; color: #fff; }
        .pill-presencial { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .pill-presencial.ativo, .pill-presencial:hover { background: #d97706; color: #fff; }

        .contagem { font-size: 13px; color: #ffffff; font-weight: 600; margin-bottom: 14px; }
        .contagem span { color: #dc2626; font-weight: 800; }

        /* ── Grid ── */
        .pedidos-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
        @media (min-width: 900px) {
            .pedidos-grid { grid-template-columns: 1fr 1fr; gap: 20px; }
        }

        /* ── Card pedido ── */
        .pedido-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 6px rgba(0,0,0,.06);
            transition: .2s;
            display: flex;
            flex-direction: column;
        }
        .pedido-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,.1); transform: translateY(-2px); }

        .pedido-head {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            border-bottom: 1px solid #f0f0f0;
            background: #fafafa;
        }
        .pedido-id   { font-weight: 800; font-size: 15px; color: #0f172a; }
        .pedido-data { font-size: 12px; color: #94a3b8; margin-top: 2px; }

        .badge-tipo {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
            margin-left: 6px;
            vertical-align: middle;
        }
        .badge-online     { background: #ede9fe; color: #5b21b6; }
        .badge-presencial { background: #fef3c7; color: #92400e; }

        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .badge-pendente              { background: #fef3c7; color: #854d0e; }
        .badge-confirmado,
        .badge-aprovado              { background: #d1fae5; color: #065f46; }

        .pedido-body {
            padding: 16px 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .pedido-infos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        @media (min-width: 500px) {
            .pedido-infos { grid-template-columns: repeat(3, 1fr); }
        }

        .pi { display: flex; flex-direction: column; gap: 3px; }
        .pi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; }
        .pi-val   { font-size: 14px; font-weight: 600; color: #1e293b; }
        .pi-val.verde { color: #059669; font-weight: 800; }

        .itens-lista {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .itens-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .item-linha {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 5px 0;
            border-bottom: 1px solid #f1f5f9;
            color: #374151;
        }
        .item-linha:last-child { border-bottom: none; }
        .item-nome { font-weight: 600; }
        .item-sub  { color: #9ca3af; font-size: 12px; }

        .btn-cancelar-pedido {
            width: 100%;
            padding: 14px;
            margin-top: auto;
            background: #fff;
            color: #dc2626;
            border: 2px solid #fecaca;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s;
        }
        .btn-cancelar-pedido:hover { background: #fef2f2; border-color: #f87171; }

        .vazio { text-align: center; padding: 64px 16px; color: #94a3b8; }
        .vazio-txt { font-size: 15px; font-weight: 600; margin-top: 12px; }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.aberto { display: flex; }

        .modal {
            background: #fff;
            border-radius: 22px;
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,.25);
            animation: slideUp .25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        .modal-head {
            padding: 22px 24px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .modal-head h3 { font-size: 18px; font-weight: 800; color: #0f172a; }
        .modal-head p  { font-size: 13px; color: #94a3b8; margin-top: 3px; }

        .modal-alerta {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 11px;
            padding: 13px 16px;
            margin: 16px 24px 0;
            font-size: 13px;
            color: #7f1d1d;
            font-weight: 500;
        }

        .modal-body { padding: 18px 24px; }

        .modal-pedido-info {
            background: #f8fafc;
            border-radius: 11px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .modal-pedido-info strong { display: block; font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .modal-pedido-info span   { display: block; color: #64748b; font-size: 13px; margin-top: 3px; }

        .campo-motivo label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .campo-motivo label .req { color: #dc2626; }

        .campo-motivo textarea {
            width: 100%;
            min-height: 110px;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 11px;
            font-size: 14px;
            font-family: 'Sora', sans-serif;
            resize: vertical;
            outline: none;
            transition: .2s;
            color: #0f172a;
            line-height: 1.5;
        }
        .campo-motivo textarea:focus { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
        .campo-motivo textarea.erro  { border-color: #dc2626 !important; animation: shake .3s; }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25%      { transform: translateX(-6px); }
            75%      { transform: translateX(6px); }
        }

        .motivos-rapidos { display: flex; gap: 7px; flex-wrap: wrap; margin-bottom: 12px; }
        .motivo-pill {
            padding: 6px 13px;
            background: #f1f5f9;
            color: #475569;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .15s;
            user-select: none;
            font-family: 'Sora', sans-serif;
        }
        .motivo-pill:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

        .modal-footer {
            padding: 14px 24px 22px;
            display: flex;
            gap: 12px;
        }
        .btn-modal-voltar {
            flex: 1;
            padding: 13px;
            background: #fff;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            cursor: pointer;
            transition: .2s;
        }
        .btn-modal-voltar:hover { background: #f8fafc; }

        .btn-modal-confirmar {
            flex: 2;
            padding: 13px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: #fff;
            border: none;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(220,38,38,.35);
            transition: .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-modal-confirmar:hover:not([disabled]) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(220,38,38,.45); }
        .btn-modal-confirmar[disabled] { opacity: .5; cursor: not-allowed; }

        #toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 13px;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            display: none;
            z-index: 99999;
            box-shadow: 0 6px 24px rgba(0,0,0,.2);
            font-family: 'Sora', sans-serif;
        }
        #toast.sucesso { background: #059669; }
        #toast.erro    { background: #dc2626; }

        /* ══════════════════════════════════
           MOBILE
           ══════════════════════════════════ */
        @media (max-width: 600px) {
            .page { padding: 16px 12px 80px; }

            .hero { border-radius: 16px; padding: 20px 18px 18px; }
            .hero h1 { font-size: 18px; }

            .filtros-row { flex-direction: column; }
            .filtro-group { min-width: 100%; }
            .btn-filtrar,
            .btn-limpar { width: 100%; justify-content: center; height: 44px; }

            .pedidos-grid { gap: 10px; }

            .pedido-head { padding: 12px 14px; }
            .pedido-id   { font-size: 14px; }
            .pedido-body { padding: 14px; gap: 12px; }

            .pedido-infos { grid-template-columns: 1fr 1fr; }
            .pi-val       { font-size: 13px; }

            .item-linha { font-size: 12px; }

            .btn-cancelar-pedido { padding: 13px; font-size: 13px; }

            /* Modal ocupa tela toda no mobile */
            .modal-overlay { align-items: flex-end; padding: 0; }
            .modal {
                border-radius: 22px 22px 0 0;
                max-width: 100%;
                max-height: 92vh;
                overflow-y: auto;
            }
            .modal-head   { padding: 18px 18px 14px; }
            .modal-alerta { margin: 14px 18px 0; }
            .modal-body   { padding: 16px 18px; }
            .modal-footer { padding: 12px 18px 28px; }

            .campo-motivo textarea { font-size: 16px; min-height: 90px; }

            #toast {
                bottom: 16px;
                left: 12px;
                right: 12px;
                text-align: center;
                font-size: 13px;
            }
        }

        @media (max-width: 380px) {
            .hero h1 { font-size: 16px; }
            .pedido-infos { grid-template-columns: 1fr; }
            .pills { gap: 6px; }
            .pill  { font-size: 12px; padding: 6px 12px; }
        }
    </style>
</head>
<body>
<div class="page">

    <div class="topbar">
        <a href="admin.php" class="btn-back">&#8592; Voltar ao Admin</a>
    </div>

    <div class="hero">
        <h1>Cancelamento de Pedidos</h1>
        <p>Cancele pedidos online ou presenciais — estoque devolvido automaticamente</p>
    </div>

    <div class="filtros-card">
        <form method="GET" action="">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
            <div class="filtros-row">
                <div class="filtro-group">
                    <label>Buscar</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="N pedido, cliente ou telefone...">
                </div>
                <div class="filtro-group" style="max-width:180px;">
                    <label>Status</label>
                    <select name="status">
                        <option value="todos"      <?= $filtro_status==='todos'      ?'selected':'' ?>>Todos</option>
                        <option value="pendente"   <?= $filtro_status==='pendente'   ?'selected':'' ?>>Pendente</option>
                        <option value="confirmado" <?= $filtro_status==='confirmado' ?'selected':'' ?>>Confirmado</option>
                        <option value="aprovado"   <?= $filtro_status==='aprovado'   ?'selected':'' ?>>Aprovado</option>
                    </select>
                </div>
                <button type="submit" class="btn-filtrar">Filtrar</button>
                <a href="cancelamentos.php" class="btn-limpar">Limpar</a>
            </div>
        </form>
    </div>

    <div class="pills">
        <a href="?tipo=todos&status=<?= $filtro_status ?>&busca=<?= urlencode($busca) ?>"      class="pill pill-todos       <?= $filtro_tipo==='todos'      ?'ativo':'' ?>">Todos (<?= $cnt['todos'] ?>)</a>
        <a href="?tipo=online&status=<?= $filtro_status ?>&busca=<?= urlencode($busca) ?>"     class="pill pill-online      <?= $filtro_tipo==='online'     ?'ativo':'' ?>">Online (<?= $cnt['online'] ?>)</a>
        <a href="?tipo=presencial&status=<?= $filtro_status ?>&busca=<?= urlencode($busca) ?>" class="pill pill-presencial  <?= $filtro_tipo==='presencial' ?'ativo':'' ?>">Presencial (<?= $cnt['presencial'] ?>)</a>
    </div>

    <div class="contagem">Mostrando <span><?= count($pedidos) ?></span> pedido(s)</div>

    <?php if (empty($pedidos)): ?>
    <div class="vazio">
        <div class="vazio-txt">Nenhum pedido encontrado.</div>
    </div>
    <?php else: ?>
    <div class="pedidos-grid">
        <?php foreach ($pedidos as $p):
            $itens    = $itens_map[$p['id_pedido']] ?? [];
            $tipo     = $p['tipo'];
            $badge_st = in_array($p['status'], ['confirmado','aprovado']) ? 'badge-confirmado' : 'badge-'.$p['status'];
            $modal_json = htmlspecialchars(json_encode([
                'id'      => (int)$p['id_pedido'],
                'cliente' => $p['nome_cliente'],
                'valor'   => 'R$ '.number_format($p['valor_total'],2,',','.'),
                'data'    => date('d/m/Y H:i', strtotime($p['data_pedido'])),
                'tipo'    => $tipo,
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="pedido-card" id="card-<?= $p['id_pedido'] ?>">
            <div class="pedido-head">
                <div>
                    <div class="pedido-id">
                        Pedido #<?= $p['id_pedido'] ?>
                        <span class="badge-tipo <?= $tipo==='presencial'?'badge-presencial':'badge-online' ?>">
                            <?= $tipo==='presencial' ? 'Presencial' : 'Online' ?>
                        </span>
                    </div>
                    <div class="pedido-data"><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></div>
                </div>
                <span class="badge-status <?= $badge_st ?>"><?= ucfirst($p['status']) ?></span>
            </div>

            <div class="pedido-body">
                <div class="pedido-infos">
                    <div class="pi"><span class="pi-label">Cliente</span>   <span class="pi-val"><?= htmlspecialchars($p['nome_cliente']) ?></span></div>
                    <div class="pi"><span class="pi-label">Telefone</span>  <span class="pi-val"><?= htmlspecialchars($p['telefone_cliente'] ?: '—') ?></span></div>
                    <div class="pi"><span class="pi-label">Pagamento</span> <span class="pi-val"><?= htmlspecialchars($p['forma_pagamento']) ?></span></div>
                    <div class="pi"><span class="pi-label">Total</span>     <span class="pi-val verde">R$ <?= number_format($p['valor_total'],2,',','.') ?></span></div>
                    <?php if ($p['observacoes']): ?>
                    <div class="pi" style="grid-column:1/-1;">
                        <span class="pi-label">Obs</span>
                        <span class="pi-val" style="font-size:12px;color:#64748b;"><?= htmlspecialchars($p['observacoes']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($itens)): ?>
                <div class="itens-lista">
                    <div class="itens-title">Produtos</div>
                    <?php foreach ($itens as $item): ?>
                    <div class="item-linha">
                        <span class="item-nome"><?= htmlspecialchars($item['nome_produto']) ?></span>
                        <span class="item-sub"><?= $item['quantidade'] ?>x &middot; R$ <?= number_format($item['subtotal'],2,',','.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <button type="button" class="btn-cancelar-pedido" data-pedido="<?= $modal_json ?>">
                    Cancelar este pedido
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- MODAL -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal" id="modal-box">
        <div class="modal-head">
            <h3>Confirmar Cancelamento</h3>
            <p>Esta acao devolve o estoque automaticamente</p>
        </div>
        <div class="modal-alerta">
            O pedido sera marcado como <strong>cancelado</strong> e o estoque de cada produto sera <strong>devolvido</strong>. Esta acao nao pode ser desfeita.
        </div>
        <div class="modal-body">
            <div class="modal-pedido-info">
                <strong id="modal-titulo">—</strong>
                <span id="modal-cliente">—</span>
                <span id="modal-valor">—</span>
                <span id="modal-data">—</span>
            </div>
            <div class="campo-motivo">
                <label>Motivo do cancelamento <span class="req">*</span></label>
                <div class="motivos-rapidos">
                    <span class="motivo-pill" data-motivo="Cliente desistiu da compra">Desistencia</span>
                    <span class="motivo-pill" data-motivo="Produto sem estoque">Sem estoque</span>
                    <span class="motivo-pill" data-motivo="Pagamento nao aprovado">Pgto recusado</span>
                    <span class="motivo-pill" data-motivo="Cliente solicitou troca">Solicitou troca</span>
                    <span class="motivo-pill" data-motivo="Pedido duplicado">Duplicado</span>
                    <span class="motivo-pill" data-motivo="Erro no pedido">Erro no pedido</span>
                </div>
                <textarea id="motivo-txt" placeholder="Descreva o motivo do cancelamento..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-voltar" id="btn-voltar-modal">Voltar</button>
            <button type="button" class="btn-modal-confirmar" id="btn-confirmar-cancel">
                Confirmar Cancelamento
            </button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
var pedidoAtual = null;

document.addEventListener('click', function(e) {
    var btnCard = e.target.closest('.btn-cancelar-pedido');
    if (btnCard) {
        var dados = JSON.parse(btnCard.getAttribute('data-pedido'));
        abrirModal(dados);
        return;
    }
    var pill = e.target.closest('.motivo-pill');
    if (pill) {
        document.getElementById('motivo-txt').value = pill.getAttribute('data-motivo');
        document.getElementById('motivo-txt').classList.remove('erro');
        return;
    }
    if (e.target.closest('#btn-voltar-modal')) { fecharModal(); return; }
    if (e.target.closest('#btn-confirmar-cancel')) { confirmarCancelamento(); return; }
    if (e.target.id === 'modal-overlay') { fecharModal(); }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharModal();
});

function abrirModal(dados) {
    pedidoAtual = dados.id;
    document.getElementById('modal-titulo').textContent  = 'Pedido #' + dados.id + ' — ' + (dados.tipo === 'presencial' ? 'Presencial' : 'Online');
    document.getElementById('modal-cliente').textContent = 'Cliente: ' + dados.cliente;
    document.getElementById('modal-valor').textContent   = 'Valor: ' + dados.valor;
    document.getElementById('modal-data').textContent    = 'Data: ' + dados.data;
    document.getElementById('motivo-txt').value = '';
    document.getElementById('motivo-txt').classList.remove('erro');
    var btn = document.getElementById('btn-confirmar-cancel');
    btn.disabled = false;
    btn.innerHTML = 'Confirmar Cancelamento';
    document.getElementById('modal-overlay').classList.add('aberto');
    setTimeout(function() { document.getElementById('motivo-txt').focus(); }, 300);
}

function fecharModal() {
    document.getElementById('modal-overlay').classList.remove('aberto');
    pedidoAtual = null;
}

function confirmarCancelamento() {
    var motivo = document.getElementById('motivo-txt').value.trim();
    if (!motivo) {
        var txt = document.getElementById('motivo-txt');
        txt.classList.add('erro');
        txt.focus();
        mostrarToast('Informe o motivo do cancelamento!', 'erro');
        return;
    }
    var btn = document.getElementById('btn-confirmar-cancel');
    btn.disabled = true;
    btn.innerHTML = 'Processando...';
    fetch('processar_cancelamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_pedido: pedidoAtual, motivo: motivo })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            fecharModal();
            mostrarToast(data.msg, 'sucesso');
            var card = document.getElementById('card-' + pedidoAtual);
            if (card) {
                card.style.transition = 'opacity .4s, transform .4s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(function() { card.remove(); }, 450);
            }
        } else {
            mostrarToast(data.msg, 'erro');
            btn.disabled = false;
            btn.innerHTML = 'Confirmar Cancelamento';
        }
    })
    .catch(function() {
        mostrarToast('Erro de conexao!', 'erro');
        btn.disabled = false;
        btn.innerHTML = 'Confirmar Cancelamento';
    });
}

function mostrarToast(msg, tipo) {
    var t = document.getElementById('toast');
    t.className = tipo;
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(function() { t.style.display = 'none'; }, 3500);
}
</script>
</body>
</html>