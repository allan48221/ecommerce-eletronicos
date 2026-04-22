<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$filtro_tipo = $_GET['tipo']   ?? 'todos';
$filtro_data = $_GET['data']   ?? '';
$busca       = trim($_GET['busca'] ?? '');

$tipos_validos = [
    'todos',
    'pedido_criado','pedido_confirmado','pedido_cancelado','venda_presencial',
    'comanda_lancada','comanda_atualizada','comanda_fechada','comanda_cancelada',
    'item_removido',
];
if (!in_array($filtro_tipo, $tipos_validos)) $filtro_tipo = 'todos';

// ── TENANT ──
$id_tenant = $_SESSION['id_tenant'] ?? null;
$is_master = empty($_SESSION['id_tenant']);

$logs = [];

// ── PEDIDOS CRIADOS ──
if (in_array($filtro_tipo, ['todos', 'pedido_criado'])) {
    $where  = "WHERE COALESCE(p.tipo,'online') != 'presencial'";
    $params = [];
    if (!$is_master) { $where .= " AND p.id_tenant = ?"; $params[] = $id_tenant; }
    if ($filtro_data) { $where .= " AND DATE(p.data_pedido) = ?"; $params[] = $filtro_data; }
    if ($busca) { $where .= " AND (CAST(p.id_pedido AS TEXT) ILIKE ? OR p.nome_cliente ILIKE ?)"; $like = '%'.$busca.'%'; $params[] = $like; $params[] = $like; }
    $stmt = $conn->prepare("SELECT p.id_pedido, p.nome_cliente, p.valor_total, p.forma_pagamento, p.status, p.data_pedido AS momento FROM pedidos p $where ORDER BY p.data_pedido DESC LIMIT 200");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $logs[] = ['tipo'=>'pedido_criado','momento'=>$r['momento'],'titulo'=>'Pedido #'.$r['id_pedido'].' criado','detalhe'=>'Cliente: '.$r['nome_cliente'].' · R$ '.number_format($r['valor_total'],2,',','.').' · '.$r['forma_pagamento'],'valor'=>$r['valor_total'],'ref_id'=>$r['id_pedido'],'admin'=>null,'extra'=>$r['status']];
    }
}

// ── PEDIDOS CONFIRMADOS ──
if (in_array($filtro_tipo, ['todos', 'pedido_confirmado'])) {
    $where  = "WHERE p.status = 'confirmado' AND p.data_acao IS NOT NULL";
    $params = [];
    if (!$is_master) { $where .= " AND p.id_tenant = ?"; $params[] = $id_tenant; }
    if ($filtro_data) { $where .= " AND DATE(p.data_acao) = ?"; $params[] = $filtro_data; }
    if ($busca) { $where .= " AND (CAST(p.id_pedido AS TEXT) ILIKE ? OR p.nome_cliente ILIKE ?)"; $like = '%'.$busca.'%'; $params[] = $like; $params[] = $like; }
    $stmt = $conn->prepare("SELECT p.id_pedido, p.nome_cliente, p.valor_total, p.forma_pagamento, p.data_acao AS momento FROM pedidos p $where ORDER BY p.data_acao DESC LIMIT 200");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $logs[] = ['tipo'=>'pedido_confirmado','momento'=>$r['momento'],'titulo'=>'Pedido #'.$r['id_pedido'].' confirmado','detalhe'=>'Cliente: '.$r['nome_cliente'].' · R$ '.number_format($r['valor_total'],2,',','.').' · '.$r['forma_pagamento'],'valor'=>$r['valor_total'],'ref_id'=>$r['id_pedido'],'admin'=>null,'extra'=>null];
    }
}

// ── PEDIDOS CANCELADOS ──
if (in_array($filtro_tipo, ['todos', 'pedido_cancelado'])) {
    $where  = "WHERE 1=1";
    $params = [];
    if (!$is_master) { $where .= " AND p.id_tenant = ?"; $params[] = $id_tenant; }
    if ($filtro_data) { $where .= " AND DATE(c.data_cancelamento) = ?"; $params[] = $filtro_data; }
    if ($busca) { $where .= " AND (CAST(c.id_pedido AS TEXT) ILIKE ? OR p.nome_cliente ILIKE ? OR c.motivo ILIKE ?)"; $like = '%'.$busca.'%'; $params[] = $like; $params[] = $like; $params[] = $like; }
    $stmt = $conn->prepare("SELECT c.id_pedido, c.motivo, c.data_cancelamento AS momento, p.nome_cliente, p.valor_total, p.forma_pagamento, COALESCE(a1.nome, a2.nome, 'Admin') AS nome_admin FROM cancelamentos c JOIN pedidos p ON p.id_pedido = c.id_pedido LEFT JOIN administradores a1 ON a1.id_admin = c.id_admin LEFT JOIN admins a2 ON a2.id_admin = c.id_admin $where ORDER BY c.data_cancelamento DESC LIMIT 200");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $logs[] = ['tipo'=>'pedido_cancelado','momento'=>$r['momento'],'titulo'=>'Pedido #'.$r['id_pedido'].' cancelado','detalhe'=>'Cliente: '.$r['nome_cliente'].' · Motivo: '.$r['motivo'],'valor'=>$r['valor_total'],'ref_id'=>$r['id_pedido'],'admin'=>$r['nome_admin'],'extra'=>null];
    }
}

// ── VENDAS PRESENCIAIS ──
if (in_array($filtro_tipo, ['todos', 'venda_presencial'])) {
    $where  = "WHERE 1=1";
    $params = [];
    if (!$is_master) { $where .= " AND p.id_tenant = ?"; $params[] = $id_tenant; }
    if ($filtro_data) { $where .= " AND DATE(vp.criado_em) = ?"; $params[] = $filtro_data; }
    if ($busca) { $where .= " AND (CAST(vp.id_pedido AS TEXT) ILIKE ? OR p.nome_cliente ILIKE ? OR pr.nome ILIKE ?)"; $like = '%'.$busca.'%'; $params[] = $like; $params[] = $like; $params[] = $like; }
    $stmt = $conn->prepare("
        SELECT vp.id_pedido, vp.quantidade, vp.criado_em AS momento,
               vp.id_admin, vp.id_vendedor,
               p.nome_cliente, p.valor_total, p.forma_pagamento,
               pr.nome AS nome_produto
        FROM vendas_presenciais vp
        JOIN pedidos  p  ON p.id_pedido  = vp.id_pedido
        JOIN produtos pr ON pr.id_produto = vp.id_produto
        $where
        ORDER BY vp.criado_em DESC LIMIT 200
    ");
    $stmt->execute($params);

    $nomes_admins     = [];
    $nomes_vendedores = [];
    if ($is_master) {
        try { foreach ($conn->query("SELECT id_admin, nome FROM admins")->fetchAll() as $row) { $nomes_admins[$row['id_admin']] = $row['nome']; } } catch (\Throwable $e) {}
        try { foreach ($conn->query("SELECT id_vendedor, nome FROM vendedores")->fetchAll() as $row) { $nomes_vendedores[$row['id_vendedor']] = $row['nome']; } } catch (\Throwable $e) {}
    } else {
        try { $r2 = $conn->prepare("SELECT id_admin, nome FROM admins WHERE id_tenant = ?"); $r2->execute([$id_tenant]); foreach ($r2->fetchAll() as $row) { $nomes_admins[$row['id_admin']] = $row['nome']; } } catch (\Throwable $e) {}
        try { $r2 = $conn->prepare("SELECT id_vendedor, nome FROM vendedores WHERE id_tenant = ?"); $r2->execute([$id_tenant]); foreach ($r2->fetchAll() as $row) { $nomes_vendedores[$row['id_vendedor']] = $row['nome']; } } catch (\Throwable $e) {}
    }

    foreach ($stmt->fetchAll() as $r) {
        $quem = null;
        if (!empty($r['id_vendedor']) && isset($nomes_vendedores[$r['id_vendedor']])) {
            $quem = $nomes_vendedores[$r['id_vendedor']] . ' (Vendedor)';
        } elseif (!empty($r['id_admin']) && isset($nomes_admins[$r['id_admin']])) {
            $quem = $nomes_admins[$r['id_admin']] . ' (Admin)';
        } elseif (!empty($r['id_admin'])) {
            $quem = 'Admin #' . $r['id_admin'];
        }
        $logs[] = ['tipo'=>'venda_presencial','momento'=>$r['momento'],'titulo'=>'Venda presencial — Pedido #'.$r['id_pedido'],'detalhe'=>'Produto: '.$r['nome_produto'].' ('.$r['quantidade'].'x) · '.$r['forma_pagamento'],'valor'=>$r['valor_total'],'ref_id'=>$r['id_pedido'],'admin'=>$quem,'extra'=>null];
    }
}

// ── COMANDAS ──
$tipos_comanda = ['comanda_lancada', 'comanda_fechada', 'comanda_cancelada', 'item_removido'];
if ($filtro_tipo === 'todos' || in_array($filtro_tipo, ['comanda_lancada','comanda_fechada','comanda_cancelada'])) {
    $where  = "WHERE 1=1";
    $params = [];
    if (!$is_master) { $where .= " AND c.id_tenant = ?"; $params[] = $id_tenant; }
    if ($filtro_tipo === 'comanda_lancada')       $where .= " AND c.status = 'aberta'";
    elseif ($filtro_tipo === 'comanda_fechada')   $where .= " AND c.status = 'fechada'";
    elseif ($filtro_tipo === 'comanda_cancelada') $where .= " AND c.status = 'cancelada'";
    if ($filtro_data) { $where .= " AND DATE(COALESCE(c.fechado_em, c.criado_em)) = ?"; $params[] = $filtro_data; }
    if ($busca) { $where .= " AND (c.numero_comanda ILIKE ? OR c.lancado_por ILIKE ?)"; $like = '%'.$busca.'%'; $params[] = $like; $params[] = $like; }

    try {
        $stmt = $conn->prepare("
            SELECT c.id_comanda, c.numero_comanda, c.status,
                   c.valor_total, c.lancado_por,
                   c.criado_em, c.fechado_em,
                   COUNT(ci.id_item) AS total_itens
            FROM comandas c
            LEFT JOIN comanda_itens ci ON ci.id_comanda = c.id_comanda
            $where
            GROUP BY c.id_comanda
            ORDER BY COALESCE(c.fechado_em, c.criado_em) DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            if ($r['status'] === 'fechada') {
                $tipo_log = 'comanda_fechada';
                $titulo   = 'Comanda #' . $r['numero_comanda'] . ' fechada';
                $momento  = $r['fechado_em'];
            } elseif ($r['status'] === 'cancelada') {
                $tipo_log = 'comanda_cancelada';
                $titulo   = 'Comanda #' . $r['numero_comanda'] . ' cancelada';
                $momento  = $r['fechado_em'];
            } else {
                $tipo_log = 'comanda_lancada';
                $titulo   = 'Comanda #' . $r['numero_comanda'] . ' lancada';
                $momento  = $r['criado_em'];
            }
            $logs[] = [
                'tipo'    => $tipo_log,
                'momento' => $momento ?? $r['criado_em'],
                'titulo'  => $titulo,
                'detalhe' => $r['total_itens'] . ' item(ns) · R$ ' . number_format($r['valor_total'], 2, ',', '.'),
                'valor'   => $r['valor_total'],
                'ref_id'  => $r['id_comanda'],
                'admin'   => $r['lancado_por'],
                'extra'   => null,
            ];
        }
    } catch (\Throwable $e) {}
}

// ── ITENS REMOVIDOS ──
if (in_array($filtro_tipo, ['todos', 'comanda_cancelada', 'item_removido'])) {
    $where  = "WHERE 1=1";
    $params = [];
    if (!$is_master) { $where .= " AND id_tenant = ?"; $params[] = $id_tenant; }
    if ($filtro_data) { $where .= " AND DATE(removido_em) = ?"; $params[] = $filtro_data; }
    if ($busca) { $where .= " AND (numero_comanda ILIKE ? OR nome_produto ILIKE ? OR removido_por ILIKE ?)"; $like = '%'.$busca.'%'; $params[] = $like; $params[] = $like; $params[] = $like; }
    try {
        $stmt = $conn->prepare("SELECT * FROM comanda_itens_removidos $where ORDER BY removido_em DESC LIMIT 200");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $logs[] = [
                'tipo'    => 'item_removido',
                'momento' => $r['removido_em'],
                'titulo'  => 'Item removido da Comanda #' . $r['numero_comanda'],
                'detalhe' => $r['nome_produto'] . ' · R$ ' . number_format($r['subtotal'], 2, ',', '.'),
                'valor'   => $r['subtotal'],
                'ref_id'  => $r['id_comanda'],
                'admin'   => $r['removido_por'],
                'extra'   => null,
            ];
        }
    } catch (\Throwable $e) {}
}

usort($logs, fn($a, $b) => strtotime($b['momento']) - strtotime($a['momento']));

$total_acoes      = count($logs);
$total_criados    = count(array_filter($logs, fn($l) => $l['tipo'] === 'pedido_criado'));
$total_cancelados = count(array_filter($logs, fn($l) => $l['tipo'] === 'pedido_cancelado'));
$total_presencial = count(array_filter($logs, fn($l) => $l['tipo'] === 'venda_presencial'));
$total_comandas   = count(array_filter($logs, fn($l) => in_array($l['tipo'], $tipos_comanda)));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Logs do Sistema</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sora', sans-serif; background: var(--dash-bg, #f1f5f9); min-height: 100vh; color: #0f172a; }
        .page { max-width: 1100px; margin: 0 auto; padding: 28px 20px 80px; }

        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 8px; }
        .btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 50px; font-size: 14px; font-weight: 600; text-decoration: none; transition: .2s; font-family: 'Sora', sans-serif; }
        .btn-back:hover { background: #f1f5f9; }

        .hero { background: linear-gradient(135deg, var(--primary,#2563eb) 0%, var(--primary-dark,#1e40af) 100%); border-radius: 18px; padding: 28px 30px 24px; color: #fff; margin-bottom: 24px; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; right: -20px; top: -20px; width: 160px; height: 160px; background: rgba(255,255,255,.07); border-radius: 50%; }
        .hero h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .hero p  { font-size: 13px; opacity: .75; margin-top: 4px; }

        .resumo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px; }
        @media (min-width: 700px) { .resumo-grid { grid-template-columns: repeat(5, 1fr); } }
        .resumo-card { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 16px 18px; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
        .resumo-card-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 6px; }
        .resumo-card-valor { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1; }
        .resumo-card-sub   { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .resumo-card.azul     .resumo-card-valor { color: var(--primary,#2563eb); }
        .resumo-card.verde    .resumo-card-valor { color: #059669; }
        .resumo-card.vermelho .resumo-card-valor { color: #dc2626; }
        .resumo-card.laranja  .resumo-card-valor { color: #d97706; }
        .resumo-card.roxo     .resumo-card-valor { color: #7c3aed; }

        .filtros-card { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 16px 18px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
        .filtros-row  { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .filtro-group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 150px; }
        .filtro-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; }
        .filtro-group input,
        .filtro-group select { padding: 10px 13px; border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 14px; font-family: 'Sora', sans-serif; color: #0f172a; outline: none; background: #fff; transition: .2s; }
        .filtro-group input:focus,
        .filtro-group select:focus { border-color: var(--primary,#2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .btn-filtrar { padding: 10px 22px; background: var(--primary,#2563eb); color: #fff; border: none; border-radius: 9px; font-size: 14px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; height: 42px; white-space: nowrap; }
        .btn-limpar  { padding: 10px 16px; background: #f1f5f9; color: #64748b; border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 14px; font-weight: 600; font-family: 'Sora', sans-serif; text-decoration: none; height: 42px; display: inline-flex; align-items: center; }

        .pills { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .pill  { padding: 7px 16px; border-radius: 50px; font-size: 13px; font-weight: 700; text-decoration: none; border: 2px solid transparent; transition: .15s; white-space: nowrap; font-family: 'Sora', sans-serif; }

        .pill-todos      { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .pill-todos.ativo, .pill-todos:hover { background: #1e293b; color: #fff; border-color: #1e293b; }
        .pill-criado     { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
        .pill-criado.ativo, .pill-criado:hover { background: #2563eb; color: #fff; }
        .pill-confirmado { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .pill-confirmado.ativo, .pill-confirmado:hover { background: #059669; color: #fff; }
        .pill-cancelado  { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .pill-cancelado.ativo, .pill-cancelado:hover { background: #dc2626; color: #fff; }
        .pill-presencial { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .pill-presencial.ativo, .pill-presencial:hover { background: #d97706; color: #fff; }
        .pill-comanda    { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
        .pill-comanda.ativo, .pill-comanda:hover { background: #7c3aed; color: #fff; border-color: #7c3aed; }
        .pill-laranja    { background: #ffedd5; color: #9a3412; border-color: #fed7aa; }
        .pill-laranja.ativo, .pill-laranja:hover { background: #f97316; color: #fff; border-color: #f97316; }

        .contagem { font-size: 13px; color: #ffffff; font-weight: 600; margin-bottom: 16px; }
        .contagem span { color: var(--primary,#2563eb); font-weight: 800; }

        .timeline { position: relative; padding-left: 28px; }
        .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; border-radius: 2px; }

        .log-item { position: relative; margin-bottom: 14px; }
        .log-dot  { position: absolute; left: -23px; top: 16px; width: 14px; height: 14px; border-radius: 50%; border: 2.5px solid #fff; box-shadow: 0 0 0 2px currentColor; flex-shrink: 0; }

        .log-item.pedido_criado      .log-dot { background: #2563eb; color: #2563eb; }
        .log-item.pedido_confirmado  .log-dot { background: #059669; color: #059669; }
        .log-item.pedido_cancelado   .log-dot { background: #dc2626; color: #dc2626; }
        .log-item.venda_presencial   .log-dot { background: #d97706; color: #d97706; }
        .log-item.comanda_lancada    .log-dot { background: #7c3aed; color: #7c3aed; }
        .log-item.comanda_atualizada .log-dot { background: #7c3aed; color: #7c3aed; }
        .log-item.comanda_fechada    .log-dot { background: #059669; color: #059669; }
        .log-item.comanda_cancelada  .log-dot { background: #dc2626; color: #dc2626; }
        .log-item.item_removido      .log-dot { background: #f97316; color: #f97316; }

        .log-card { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; border-left-width: 4px; padding: 14px 16px; box-shadow: 0 1px 4px rgba(0,0,0,.05); transition: .2s; }
        .log-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.09); transform: translateX(3px); }

        .log-item.pedido_criado      .log-card { border-left-color: #2563eb; }
        .log-item.pedido_confirmado  .log-card { border-left-color: #059669; }
        .log-item.pedido_cancelado   .log-card { border-left-color: #dc2626; }
        .log-item.venda_presencial   .log-card { border-left-color: #d97706; }
        .log-item.comanda_lancada    .log-card { border-left-color: #7c3aed; }
        .log-item.comanda_atualizada .log-card { border-left-color: #a78bfa; }
        .log-item.comanda_fechada    .log-card { border-left-color: #059669; }
        .log-item.comanda_cancelada  .log-card { border-left-color: #dc2626; }
        .log-item.item_removido      .log-card { border-left-color: #f97316; }

        .log-topo    { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
        .log-badge   { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }

        .badge-pedido_criado      { background: #dbeafe; color: #1e40af; }
        .badge-pedido_confirmado  { background: #d1fae5; color: #065f46; }
        .badge-pedido_cancelado   { background: #fee2e2; color: #991b1b; }
        .badge-venda_presencial   { background: #fef3c7; color: #92400e; }
        .badge-comanda_lancada    { background: #ede9fe; color: #5b21b6; }
        .badge-comanda_atualizada { background: #ede9fe; color: #5b21b6; }
        .badge-comanda_fechada    { background: #d1fae5; color: #065f46; }
        .badge-comanda_cancelada  { background: #fee2e2; color: #991b1b; }
        .badge-item_removido      { background: #ffedd5; color: #9a3412; }

        .log-horario { font-size: 12px; color: #94a3b8; font-weight: 600; white-space: nowrap; display: flex; align-items: center; gap: 4px; }
        .log-titulo  { font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .log-detalhe { font-size: 13px; color: #64748b; line-height: 1.5; }

        .log-rodape  { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
        .log-valor   { font-size: 14px; font-weight: 800; color: #059669; }

        .log-quem { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .log-quem.vendedor     { background: #fef3c7; color: #92400e; }
        .log-quem.admin        { background: #dbeafe; color: #1e40af; }
        .log-quem.atendente    { background: #ede9fe; color: #5b21b6; }
        .log-quem.caixa        { background: #d1fae5; color: #065f46; }
        .log-quem.desconhecido { background: #f1f5f9; color: #64748b; }

        .data-sep       { display: flex; align-items: center; gap: 10px; margin: 20px 0 14px; }
        .data-sep-linha { flex: 1; height: 1px; background: #e2e8f0; }
        .data-sep-label { font-size: 12px; font-weight: 700; color: #ffffff; white-space: nowrap; background: var(--dash-bg,#f1f5f9); padding: 0 8px; }

        .vazio     { text-align: center; padding: 64px 16px; color: #94a3b8; }
        .vazio-txt { font-size: 15px; font-weight: 600; margin-top: 12px; }

        @media (max-width: 600px) {
            .page { padding: 14px 12px 80px; }
            .hero { border-radius: 14px; padding: 18px 16px 16px; }
            .hero h1 { font-size: 17px; }
            .resumo-grid { gap: 8px; margin-bottom: 14px; grid-template-columns: repeat(2, 1fr); }
            .resumo-card { padding: 12px 14px; border-radius: 12px; }
            .resumo-card-valor { font-size: 22px; }
            .filtros-card { padding: 14px; border-radius: 12px; }
            .filtros-row  { flex-direction: column; gap: 10px; }
            .filtro-group { min-width: 100%; }
            .filtro-group input, .filtro-group select { font-size: 16px; padding: 12px 13px; }
            .btn-filtrar, .btn-limpar { width: 100%; justify-content: center; height: 46px; }
            .pills { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; gap: 6px; scrollbar-width: none; }
            .pills::-webkit-scrollbar { display: none; }
            .pill { font-size: 12px; padding: 6px 14px; flex-shrink: 0; }
            .timeline { padding-left: 18px; }
            .timeline::before { left: 5px; }
            .log-dot { left: -15px; width: 12px; height: 12px; top: 14px; }
            .log-card { padding: 12px 13px; border-radius: 12px; }
            .log-card:hover { transform: none; }
            .log-badge { font-size: 10px; padding: 3px 10px; }
            .log-titulo  { font-size: 13px; }
            .log-detalhe { font-size: 12px; }
            .log-valor   { font-size: 13px; }
            .data-sep { margin: 16px 0 10px; margin-left: -18px; }
        }
    </style>
</head>
<body>
<div class="page">

    <div class="topbar">
        <a href="admin.php" class="btn-back">&#8592; Voltar ao Admin</a>
    </div>

    <div class="hero">
        <h1>Logs do Sistema</h1>
        <p>Histórico completo — pedidos, vendas presenciais e movimentações de comanda</p>
    </div>

    <div class="resumo-grid">
        <div class="resumo-card azul">
            <div class="resumo-card-label">Total de ações</div>
            <div class="resumo-card-valor"><?= $total_acoes ?></div>
            <div class="resumo-card-sub">registros encontrados</div>
        </div>
        <div class="resumo-card verde">
            <div class="resumo-card-label">Pedidos criados</div>
            <div class="resumo-card-valor"><?= $total_criados ?></div>
            <div class="resumo-card-sub">online / WhatsApp</div>
        </div>
        <div class="resumo-card vermelho">
            <div class="resumo-card-label">Cancelamentos</div>
            <div class="resumo-card-valor"><?= $total_cancelados ?></div>
            <div class="resumo-card-sub">com motivo registrado</div>
        </div>
        <div class="resumo-card laranja">
            <div class="resumo-card-label">Vendas presenciais</div>
            <div class="resumo-card-valor"><?= $total_presencial ?></div>
            <div class="resumo-card-sub">realizadas na loja</div>
        </div>
        <div class="resumo-card roxo">
            <div class="resumo-card-label">Comandas</div>
            <div class="resumo-card-valor"><?= $total_comandas ?></div>
            <div class="resumo-card-sub">lancadas / fechadas / itens</div>
        </div>
    </div>

    <div class="filtros-card">
        <form method="GET" action="">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
            <div class="filtros-row">
                <div class="filtro-group">
                    <label>Buscar</label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Nº pedido, comanda, atendente...">
                </div>
                <div class="filtro-group" style="max-width:200px;">
                    <label>Data especifica</label>
                    <input type="date" name="data" value="<?= htmlspecialchars($filtro_data) ?>">
                </div>
                <button type="submit" class="btn-filtrar">Filtrar</button>
                <a href="logs.php" class="btn-limpar">Limpar</a>
            </div>
        </form>
    </div>

    <div class="pills">
        <?php
        $qs = '&busca='.urlencode($busca).'&data='.urlencode($filtro_data);
        $tipos_pills = [
            'todos'              => ['label' => 'Todos',             'class' => 'pill-todos'],
            'pedido_criado'      => ['label' => 'Pedidos criados',   'class' => 'pill-criado'],
            'pedido_confirmado'  => ['label' => 'Confirmados',       'class' => 'pill-confirmado'],
            'pedido_cancelado'   => ['label' => 'Cancelados',        'class' => 'pill-cancelado'],
            'venda_presencial'   => ['label' => 'Presenciais',       'class' => 'pill-presencial'],
            'comanda_lancada'    => ['label' => 'Comanda lancada',   'class' => 'pill-comanda'],
            'comanda_fechada'    => ['label' => 'Comanda fechada',   'class' => 'pill-comanda'],
            'comanda_cancelada'  => ['label' => 'Comanda cancelada', 'class' => 'pill-cancelado'],
            'item_removido'      => ['label' => 'Item removido',     'class' => 'pill-laranja'],
        ];
        foreach ($tipos_pills as $key => $info): ?>
        <a href="?tipo=<?= $key ?><?= $qs ?>" class="pill <?= $info['class'] ?> <?= $filtro_tipo===$key?'ativo':'' ?>">
            <?= $info['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="contagem">Mostrando <span><?= count($logs) ?></span> registro(s)</div>

    <?php if (empty($logs)): ?>
    <div class="vazio">
        <div class="vazio-txt">Nenhum registro encontrado.</div>
    </div>
    <?php else: ?>

    <?php
    $badges = [
        'pedido_criado'      => 'Pedido criado',
        'pedido_confirmado'  => 'Confirmado',
        'pedido_cancelado'   => 'Cancelado',
        'venda_presencial'   => 'Venda presencial',
        'comanda_lancada'    => 'Comanda lancada',
        'comanda_atualizada' => 'Comanda atualizada',
        'comanda_fechada'    => 'Comanda fechada',
        'comanda_cancelada'  => 'Comanda cancelada',
        'item_removido'      => 'Item removido',
    ];
    $data_atual = '';
    ?>

    <div class="timeline">
    <?php foreach ($logs as $log):
        $data_log    = date('Y-m-d', strtotime($log['momento']));
        $badge_label = $badges[$log['tipo']] ?? $log['tipo'];

        if ($data_log !== $data_atual):
            $data_atual = $data_log;
            $label_data = date('d/m/Y', strtotime($data_log));
            if ($data_log === date('Y-m-d'))                           $label_data = 'Hoje — '.$label_data;
            elseif ($data_log === date('Y-m-d', strtotime('-1 day'))) $label_data = 'Ontem — '.$label_data;
    ?>
        <div class="data-sep" style="margin-left:-28px;">
            <div class="data-sep-linha"></div>
            <div class="data-sep-label"><?= $label_data ?></div>
            <div class="data-sep-linha"></div>
        </div>
    <?php endif; ?>

        <div class="log-item <?= $log['tipo'] ?>">
            <div class="log-dot"></div>
            <div class="log-card">
                <div class="log-topo">
                    <span class="log-badge badge-<?= $log['tipo'] ?>"><?= $badge_label ?></span>
                    <span class="log-horario"><?= date('H:i:s', strtotime($log['momento'])) ?></span>
                </div>
                <div class="log-titulo"><?= htmlspecialchars($log['titulo']) ?></div>
                <div class="log-detalhe"><?= htmlspecialchars($log['detalhe']) ?></div>

                <div class="log-rodape">
                    <?php if (floatval($log['valor']) > 0): ?>
                    <span class="log-valor">R$ <?= number_format($log['valor'],2,',','.') ?></span>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>

                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">

                        <?php if ($log['extra'] && $log['tipo'] === 'pedido_criado'): ?>
                        <span style="font-size:11px;font-weight:700;padding:2px 9px;border-radius:50px;background:#f1f5f9;color:#475569;">
                            Status: <?= ucfirst($log['extra']) ?>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($log['admin'])): ?>
                        <?php
                            $quem_str   = $log['admin'];
                            $is_vend    = str_contains($quem_str, '(Vendedor)');
                            $is_adm     = str_contains($quem_str, '(Admin)');
                            $is_atend   = str_contains($quem_str, '(Atendente)');
                            $is_caixa   = str_contains($quem_str, '(Caixa)');
                            $quem_nome  = str_replace([' (Vendedor)',' (Admin)',' (Atendente)',' (Caixa)'], '', $quem_str);
                            $quem_class = $is_vend  ? 'vendedor'
                                        : ($is_adm  ? 'admin'
                                        : ($is_atend? 'atendente'
                                        : ($is_caixa? 'caixa'
                                        : 'desconhecido')));
                            $quem_label = $is_vend  ? 'Vendedor'
                                        : ($is_adm  ? 'Admin'
                                        : ($is_atend? 'Atendente'
                                        : ($is_caixa? 'Caixa'
                                        : '')));
                        ?>
                        <span class="log-quem <?= $quem_class ?>">
                            <?= htmlspecialchars($quem_nome) ?>
                            <?php if ($quem_label): ?>
                            <span style="opacity:.7;font-weight:600;">(<?= $quem_label ?>)</span>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($log['ref_id']): ?>
                        <span style="font-size:11px;color:#cbd5e1;">Ref #<?= $log['ref_id'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>
</body>
</html>
