<?php
require_once 'config/database.php';
require_once 'config/tema.php';

// -- Apenas admin tem acesso --
if (!isset($_SESSION['id_admin']) || !is_numeric($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$nome_admin = $_SESSION['nome_admin'] ?? 'Administrador';

// ── AJAX: detalhe de comanda ──────────────────────────────────────
if (isset($_GET['detalhe_comanda_hist'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id'] ?? 0);
    try {
        $sc = $conn->prepare("SELECT * FROM comandas WHERE id_comanda = ? LIMIT 1");
        $sc->execute([$id]);
        $comanda = $sc->fetch(PDO::FETCH_ASSOC);
        if (!$comanda) { echo json_encode(null); exit; }
        $si = $conn->prepare("SELECT * FROM comanda_itens WHERE id_comanda = ? ORDER BY criado_em");
        $si->execute([$id]);
        $itens = $si->fetchAll(PDO::FETCH_ASSOC);
        foreach ($itens as &$it) { $it['adicionais'] = $it['adicionais'] ? json_decode($it['adicionais'], true) : []; }
        $comanda['itens'] = $itens;
        echo json_encode($comanda);
    } catch (\Throwable $e) { echo json_encode(null); }
    exit;
}

// ── AJAX: dados de uma sessão específica ──────────────────────────
if (isset($_GET['ajax_sessao_hist'])) {
    header('Content-Type: application/json');
    $id_sessao = intval($_GET['id_sessao'] ?? 0);
    if (!$id_sessao) { echo json_encode(null); exit; }
    try {
        $s = $conn->prepare("SELECT * FROM caixa_sessoes WHERE id_sessao = ? LIMIT 1");
        $s->execute([$id_sessao]);
        $sessao = $s->fetch(PDO::FETCH_ASSOC);
        if (!$sessao) { echo json_encode(null); exit; }

        $sm = $conn->prepare("SELECT * FROM caixa_movimentacoes WHERE id_sessao = ? ORDER BY criado_em");
        $sm->execute([$id_sessao]);
        $sessao['movimentacoes'] = $sm->fetchAll(PDO::FETCH_ASSOC);

        $ate = $sessao['fechado_em'] ? "'{$sessao['fechado_em']}'" : "NOW()";
        $sv = $conn->query("SELECT forma_pagamento, COUNT(*) AS qtd, SUM(valor_total) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao['aberto_em']}' AND data_pedido <= $ate GROUP BY forma_pagamento");
        $sessao['vendas_por_pagamento'] = $sv->fetchAll(PDO::FETCH_ASSOC);

        $st = $conn->query("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao['aberto_em']}' AND data_pedido <= $ate");
        $t = $st->fetch(PDO::FETCH_ASSOC);
        $sessao['total_vendido'] = $t['total'];
        $sessao['qtd_pedidos']   = $t['qtd'];

        echo json_encode($sessao);
    } catch (\Throwable $e) { echo json_encode(['erro' => $e->getMessage()]); }
    exit;
}

// ── Filtros ──────────────────────────────────────────────────────
$hoje_dia = intval(date('d'));
$hoje_mes = intval(date('m'));
$hoje_ano = intval(date('Y'));

$hist_dia    = isset($_GET['hist_dia']) && is_numeric($_GET['hist_dia']) ? intval($_GET['hist_dia']) : $hoje_dia;
$hist_mes    = isset($_GET['hist_mes']) && is_numeric($_GET['hist_mes']) ? intval($_GET['hist_mes']) : $hoje_mes;
$hist_ano    = isset($_GET['hist_ano']) && is_numeric($_GET['hist_ano']) ? intval($_GET['hist_ano']) : $hoje_ano;
$hist_status = isset($_GET['hist_status']) && in_array($_GET['hist_status'], ['', 'fechada', 'cancelada']) ? $_GET['hist_status'] : '';

$filtro_alterado = ($hist_dia != $hoje_dia || $hist_mes != $hoje_mes || $hist_ano != $hoje_ano || $hist_status !== '');

// ── Query ─────────────────────────────────────────────────────────
$where = ["c.status IN ('fechada','cancelada')"];
if ($hist_dia) $where[] = "EXTRACT(DAY   FROM c.fechado_em AT TIME ZONE 'America/Belem') = $hist_dia";
if ($hist_mes) $where[] = "EXTRACT(MONTH FROM c.fechado_em AT TIME ZONE 'America/Belem') = $hist_mes";
if ($hist_ano) $where[] = "EXTRACT(YEAR  FROM c.fechado_em AT TIME ZONE 'America/Belem') = $hist_ano";
if ($hist_status) $where[] = "c.status = " . $conn->quote($hist_status);

try {
    $stmt = $conn->query("
        SELECT c.*,
               COUNT(ci.id_item)          AS total_itens,
               COALESCE(SUM(ci.subtotal), c.valor_total) AS valor_confirmado
        FROM comandas c
        LEFT JOIN comanda_itens ci ON ci.id_comanda = c.id_comanda
        WHERE " . implode(' AND ', $where) . "
        GROUP BY c.id_comanda
        ORDER BY c.fechado_em DESC
        LIMIT 200
    ");
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $historico = [];
}

// ── Sessões de caixa filtradas pelo mesmo período ─────────────────
$sess_where = ['1=1'];
if ($hist_dia) $sess_where[] = "EXTRACT(DAY   FROM aberto_em AT TIME ZONE 'America/Belem') = $hist_dia";
if ($hist_mes) $sess_where[] = "EXTRACT(MONTH FROM aberto_em AT TIME ZONE 'America/Belem') = $hist_mes";
if ($hist_ano) $sess_where[] = "EXTRACT(YEAR  FROM aberto_em AT TIME ZONE 'America/Belem') = $hist_ano";

try {
    $stmt_sess = $conn->query("SELECT * FROM caixa_sessoes WHERE " . implode(' AND ', $sess_where) . " ORDER BY aberto_em DESC");
    $sessoes_hist = $stmt_sess->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $sessoes_hist = []; }

// ── DEBUG TEMPORÁRIO ──────────────────────────────────────────────
$_debug_sql = implode(' AND ', $sess_where);
$_debug_total = count($sessoes_hist);
try {
    $_debug_todas = $conn->query("SELECT id_sessao, aberto_por, aberto_em, status FROM caixa_sessoes ORDER BY aberto_em DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $_debug_todas = []; }
// ── FIM DEBUG ─────────────────────────────────────────────────────

// ── Totalizadores ─────────────────────────────────────────────────
$total_fechadas   = 0;
$total_canceladas = 0;
$soma_fechadas    = 0.0;

foreach ($historico as $h) {
    if ($h['status'] === 'fechada')   { $total_fechadas++;   $soma_fechadas += floatval($h['valor_total']); }
    if ($h['status'] === 'cancelada') { $total_canceladas++; }
}

// ── Label do periodo ──────────────────────────────────────────────
$meses_nomes = ['1'=>'Janeiro','2'=>'Fevereiro','3'=>'Março','4'=>'Abril','5'=>'Maio','6'=>'Junho','7'=>'Julho','8'=>'Agosto','9'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$label_periodo = '';
if ($hist_dia) $label_periodo .= $hist_dia . ' de ';
if ($hist_mes) $label_periodo .= ($meses_nomes[$hist_mes] ?? $hist_mes) . ' ';
if ($hist_ano) $label_periodo .= $hist_ano;
$label_periodo = trim($label_periodo) ?: 'Período sem filtro';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Histórico de Comandas — Admin</title>
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; background: var(--dash-bg, #f1f5f9); min-height: 100vh; color: #0f172a; }

.hc-page { max-width: 1100px; margin: 0 auto; padding: 28px 20px 80px; }

/* topbar */
.hc-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 10px; }
.hc-btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 50px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Sora', sans-serif; transition: .15s; }
.hc-btn-back:hover { border-color: var(--primary,#2563eb); color: var(--primary,#2563eb); }

/* hero */
.hc-hero { background: linear-gradient(135deg, var(--primary,#2563eb) 0%, var(--primary-dark,#1e40af) 100%); border-radius: 18px; padding: 26px 28px; color: #fff; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.hc-hero h1 { font-size: 22px; font-weight: 800; }
.hc-hero p  { font-size: 13px; opacity: .8; margin-top: 4px; }
.hc-hero-stats { display: flex; gap: 28px; flex-wrap: wrap; }
.hc-hero-stat-num { font-size: 26px; font-weight: 800; line-height: 1; }
.hc-hero-stat-lbl { font-size: 11px; opacity: .75; margin-top: 3px; }

/* cards resumo */
.hc-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px,1fr)); gap: 12px; margin-bottom: 24px; }
.hc-card { background: #fff; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 16px 18px; }
.hc-card-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 6px; }
.hc-card-val { font-size: 22px; font-weight: 800; }
.hc-card-val.verde    { color: #059669; }
.hc-card-val.vermelho { color: #dc2626; }
.hc-card-val.azul     { color: var(--primary,#2563eb); }
.hc-card-sub { font-size: 11px; color: #94a3b8; margin-top: 3px; }

/* painel filtros */
.hc-filtros { background: #fff; border-radius: 16px; border: 1.5px solid #e2e8f0; padding: 18px 20px; margin-bottom: 20px; }
.hc-filtros-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #64748b; margin-bottom: 14px; display: flex; align-items: center; gap: 7px; }
.hc-filtros-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.hc-field { display: flex; flex-direction: column; gap: 4px; }
.hc-field label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; }
.hc-field input,
.hc-field select { padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 13px; font-family: 'Sora', sans-serif; outline: none; background: #fff; transition: border-color .15s; }
.hc-field input:focus,
.hc-field select:focus { border-color: var(--primary,#2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
.hc-field input[type=number] { width: 80px; }
.hc-field select { cursor: pointer; min-width: 130px; }
.hc-btn-filtrar { padding: 9px 22px; background: var(--primary,#2563eb); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Sora', sans-serif; white-space: nowrap; transition: opacity .15s; }
.hc-btn-filtrar:hover { opacity: .88; }
.hc-btn-hoje { padding: 9px 16px; background: #f1f5f9; color: #64748b; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 12px; font-weight: 700; text-decoration: none; white-space: nowrap; font-family: 'Sora', sans-serif; transition: .15s; }
.hc-btn-hoje:hover { background: #e2e8f0; }

.hc-table tr.clicavel { cursor: pointer; transition: background .12s; }
.hc-table tr.clicavel:hover td { background: #f0f9ff; }
/* section title */
.hc-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #64748b; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.hc-badge-count { background: var(--primary,#2563eb); color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 20px; }

/* tabela */
.hc-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 16px; overflow: hidden; border: 1.5px solid #e2e8f0; }
.hc-table th { padding: 12px 16px; background: #f8fafc; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; text-align: left; border-bottom: 1px solid #f1f5f9; }
.hc-table td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.hc-table tr:last-child td { border-bottom: none; }
.hc-table tr.clicavel { cursor: pointer; transition: background .12s; }
.hc-table tr.clicavel:hover td { background: #f0f9ff; }

/* badges status */
.hc-badge-fechada   { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #059669; }
.hc-badge-cancelada { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #ef4444; }

/* vazio */
.hc-vazio { text-align: center; padding: 52px 20px; color: #94a3b8; background: #fff; border-radius: 16px; border: 1.5px solid #e2e8f0; }
.hc-vazio-icon { font-size: 32px; margin-bottom: 10px; }
.hc-vazio-txt  { font-size: 15px; font-weight: 600; }
.hc-vazio-sub  { font-size: 13px; margin-top: 5px; }

/* modal historico (igual ao caixa.php) */
.hc-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.hc-modal-overlay.show { display: flex; }
.hc-modal { background: #fff; border-radius: 20px; padding: 28px; width: 100%; max-width: 540px; box-shadow: 0 20px 60px rgba(0,0,0,.2); max-height: 90vh; overflow-y: auto; }
.hc-hist-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
.hc-hist-modal-header h3 { font-size: 18px; font-weight: 800; }
.hc-hist-status-badge { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
.hc-hist-status-badge.fechada   { background: #dcfce7; color: #059669; }
.hc-hist-status-badge.cancelada { background: #fee2e2; color: #ef4444; }
.hc-hist-meta-block { background: #f8fafc; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 14px; }
.hc-hist-meta-block .mi { font-size: 12px; color: #64748b; }
.hc-hist-meta-block .mi strong { color: #0f172a; font-weight: 700; display: block; }
.hc-hist-itens-wrap { background: #f8fafc; border-radius: 12px; padding: 10px 14px; margin-bottom: 16px; max-height: 280px; overflow-y: auto; }
.hc-hist-item-linha { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.hc-hist-item-linha:last-child { border-bottom: none; }
.hc-hist-item-qty  { min-width: 26px; height: 26px; background: #64748b; color: #fff; border-radius: 6px; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
.hc-hist-item-info { flex: 1; min-width: 0; }
.hc-hist-item-nome { font-size: 13px; font-weight: 700; }
.hc-hist-item-ads  { font-size: 11px; color: #2563eb; font-weight: 600; margin-top: 2px; }
.hc-hist-item-obs  { font-size: 11px; color: #94a3b8; font-style: italic; margin-top: 2px; }
.hc-hist-item-sub  { font-size: 13px; font-weight: 800; color: #059669; white-space: nowrap; flex-shrink: 0; align-self: center; }
.hc-hist-total-row { display: flex; justify-content: space-between; font-size: 15px; font-weight: 800; padding-top: 10px; border-top: 2px solid #e2e8f0; margin-bottom: 4px; }

/* periodo chip */
.hc-periodo-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #eff6ff; color: var(--primary,#2563eb); border: 1.5px solid #bfdbfe; border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 16px; }

@media (max-width: 600px) {
    .hc-page { padding: 16px 12px 60px; }
    .hc-hero { border-radius: 14px; padding: 18px; }
    .hc-hero h1 { font-size: 18px; }
    .hc-cards { grid-template-columns: repeat(2,1fr); }
    .hc-filtros-row { gap: 8px; }
    .hc-table th:nth-child(3),
    .hc-table td:nth-child(3) { display: none; }
}
</style>
</head>
<body>

<div class="hc-page">

    <!-- Topbar -->
    <div class="hc-topbar">
        <a href="admin.php" class="hc-btn-back">&#8592; Voltar ao Painel</a>
        <div style="font-size:13px;color:#64748b;font-weight:600;">
            👤 <?= htmlspecialchars($nome_admin) ?>
        </div>
    </div>

    <!-- Hero -->
    <div class="hc-hero">
        <div>
            <h1>Histórico de Comandas</h1>
            <p>Visualização completa — somente administradores</p>
        </div>
        <div class="hc-hero-stats">
            <div style="text-align:center;">
                <div class="hc-hero-stat-num"><?= count($historico) ?></div>
                <div class="hc-hero-stat-lbl">Comandas no período</div>
            </div>
            <div style="text-align:center;">
                <div class="hc-hero-stat-num" style="color:#86efac;">R$ <?= number_format($soma_fechadas,2,',','.') ?></div>
                <div class="hc-hero-stat-lbl">Total faturado</div>
            </div>
        </div>
    </div>

    <!-- Cards resumo -->
    <div style="background:#1e293b;color:#e2e8f0;border-radius:12px;padding:16px;margin-bottom:20px;font-family:monospace;font-size:12px;">
        <div style="color:#94a3b8;margin-bottom:8px;font-weight:700;">🔍 DEBUG — remova depois</div>
        <div><strong style="color:#fbbf24;">SQL WHERE:</strong> <?= htmlspecialchars($_debug_sql) ?></div>
        <div><strong style="color:#fbbf24;">Sessões encontradas:</strong> <?= $_debug_total ?></div>
        <div style="margin-top:8px;color:#94a3b8;">Últimas 5 sessões no banco:</div>
        <?php foreach ($_debug_todas as $d): ?>
        <div style="padding:4px 0;border-bottom:1px solid #334155;">
            ID: <?= $d['id_sessao'] ?> |
            Por: <?= htmlspecialchars($d['aberto_por']) ?> |
            Aberto em: <?= $d['aberto_em'] ?> |
            Status: <?= $d['status'] ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($_debug_todas)): ?>
        <div style="color:#ef4444;">Nenhuma sessão encontrada na tabela caixa_sessoes!</div>
        <?php endif; ?>
    </div>

    <!-- Cards resumo -->
    <div class="hc-cards">
        <div class="hc-card">
            <div class="hc-card-label">Fechadas</div>
            <div class="hc-card-val verde"><?= $total_fechadas ?></div>
            <div class="hc-card-sub"><?= count($historico) > 0 ? number_format(($total_fechadas/count($historico))*100,0).'% do total' : '—' ?></div>
        </div>
        <div class="hc-card">
            <div class="hc-card-label">Canceladas</div>
            <div class="hc-card-val vermelho"><?= $total_canceladas ?></div>
            <div class="hc-card-sub"><?= count($historico) > 0 ? number_format(($total_canceladas/count($historico))*100,0).'% do total' : '—' ?></div>
        </div>
        <div class="hc-card">
            <div class="hc-card-label">Total faturado</div>
            <div class="hc-card-val azul" style="font-size:17px;">R$ <?= number_format($soma_fechadas,2,',','.') ?></div>
            <div class="hc-card-sub">Apenas fechadas</div>
        </div>
        <div class="hc-card">
            <div class="hc-card-label">Ticket médio</div>
            <div class="hc-card-val" style="font-size:17px;">R$ <?= $total_fechadas > 0 ? number_format($soma_fechadas/$total_fechadas,2,',','.') : '0,00' ?></div>
            <div class="hc-card-sub">Por comanda fechada</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="hc-filtros">
        <div class="hc-filtros-title">
            🔍 Filtrar por período
        </div>
        <form method="GET" class="hc-filtros-row">

            <!-- Dia -->
            <div class="hc-field">
                <label>Dia</label>
                <input type="number" name="hist_dia" min="1" max="31"
                    value="<?= $hist_dia ?: '' ?>" placeholder="Todos">
            </div>

            <!-- Mês -->
            <div class="hc-field">
                <label>Mês</label>
                <select name="hist_mes">
                    <option value="">Todos</option>
                    <?php foreach ($meses_nomes as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $hist_mes == $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ano -->
            <div class="hc-field">
                <label>Ano</label>
                <input type="number" name="hist_ano" min="2020" max="2099"
                    value="<?= $hist_ano ?: '' ?>" placeholder="<?= date('Y') ?>"
                    style="width:90px;">
            </div>

            <!-- Status -->
            <div class="hc-field">
                <label>Status</label>
                <select name="hist_status">
                    <option value="" <?= $hist_status==='' ? 'selected' : '' ?>>Todos</option>
                    <option value="fechada"   <?= $hist_status==='fechada'   ? 'selected' : '' ?>>Fechadas</option>
                    <option value="cancelada" <?= $hist_status==='cancelada' ? 'selected' : '' ?>>Canceladas</option>
                </select>
            </div>

            <button type="submit" class="hc-btn-filtrar">Filtrar</button>

            <?php if ($filtro_alterado): ?>
            <a href="historico_comandas.php" class="hc-btn-hoje">✕ Hoje</a>
            <?php endif; ?>

        </form>
    </div>

    <!-- Chip do período ativo -->
    <div class="hc-periodo-chip">
        📅 <?= htmlspecialchars($label_periodo) ?>
        <?php if ($hist_status): ?>
        · <?= $hist_status === 'fechada' ? 'Apenas fechadas' : 'Apenas canceladas' ?>
        <?php endif; ?>
    </div>

    <!-- Titulo + tabela -->
    <div class="hc-section-title">
        Comandas
        <span class="hc-badge-count"><?= count($historico) ?></span>
    </div>

    <?php if (!empty($historico)): ?>
    <table class="hc-table">
        <thead>
            <tr>
                <th>Comanda</th>
                <th>Mesa / Obs</th>
                <th>Lançado por</th>
                <th>Itens</th>
                <th>Total</th>
                <th>Status</th>
                <th>Fechado em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historico as $h): ?>
            <tr class="clicavel" onclick="abrirDetalhe(<?= $h['id_comanda'] ?>)" title="Clique para ver detalhes">
                <td>
                    <strong>#<?= htmlspecialchars($h['numero_comanda']) ?></strong>
                    <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
                        <?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?>
                    </div>
                </td>
                <td>
                    <?php if (!empty($h['observacao'])): ?>
                    <span style="background:#dbeafe;color:#1e40af;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;">
                        <?= htmlspecialchars($h['observacao']) ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($h['lancado_por'] ?? '—') ?></td>
                <td><?= $h['total_itens'] ?></td>
                <td><strong>R$ <?= number_format($h['valor_total'],2,',','.') ?></strong></td>
                <td><span class="hc-badge-<?= $h['status'] ?>"><?= ucfirst($h['status']) ?></span></td>
                <td><?= $h['fechado_em'] ? date('d/m H:i', strtotime($h['fechado_em'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="hc-vazio">
        <div class="hc-vazio-icon">📋</div>
        <div class="hc-vazio-txt">Nenhuma comanda encontrada</div>
        <div class="hc-vazio-sub">Tente ajustar os filtros de data ou status</div>
    </div>
    <?php endif; ?>

    <!-- Histórico de Sessões de Caixa -->
    <div class="hc-section-title" style="margin-top: 32px;">
        Sessões de Caixa no Período
        <span class="hc-badge-count" style="background:#64748b;"><?= count($sessoes_hist) ?></span>
    </div>

    <?php if (!empty($sessoes_hist)): ?>
    <table class="hc-table">
        <thead>
            <tr>
                <th>Operador</th>
                <th>Abertura</th>
                <th>Fechamento</th>
                <th>Val. Inicial</th>
                <th>Val. Contado</th>
                <th>Diferença</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sessoes_hist as $s): ?>
            <tr class="clicavel hc-sess-row" data-id="<?= $s['id_sessao'] ?>" title="Clique para ver detalhes e imprimir">
                <td>
                    <strong><?= htmlspecialchars($s['aberto_por']) ?></strong>
                    <?php if ($s['fechado_por']): ?>
                    <div style="font-size:10px;color:#94a3b8;margin-top:2px;">Fechou: <?= htmlspecialchars($s['fechado_por']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($s['aberto_em'])) ?></td>
                <td><?= $s['fechado_em'] ? date('d/m/Y H:i', strtotime($s['fechado_em'])) : '—' ?></td>
                <td>R$ <?= number_format($s['valor_inicial'], 2, ',', '.') ?></td>
                <td><?= $s['valor_contado'] !== null ? 'R$ ' . number_format($s['valor_contado'], 2, ',', '.') : '—' ?></td>
                <td>
                    <?php if ($s['diferenca'] !== null):
                        $dif = floatval($s['diferenca']);
                        $cor = $dif > 0 ? '#059669' : ($dif < 0 ? '#dc2626' : '#64748b');
                        $sinal = $dif > 0 ? '+' : '';
                    ?>
                    <strong style="color:<?= $cor ?>;"><?= $sinal ?>R$ <?= number_format($dif, 2, ',', '.') ?></strong>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if ($s['status'] === 'aberto'): ?>
                    <span class="hc-badge-fechada">Aberto</span>
                    <?php else: ?>
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#e0e7ff;color:#3730a3;">Fechado</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="hc-vazio">
        <div class="hc-vazio-icon">🗓️</div>
        <div class="hc-vazio-txt">Nenhuma sessão de caixa no período</div>
    </div>
    <?php endif; ?>

</div><!-- /hc-page -->

<!-- MODAL: Detalhe sessão de caixa -->
<div class="hc-modal-overlay" id="hc-modal-sessao">
    <div class="hc-modal" style="max-width:520px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h3 id="hc-sess-titulo">Sessão</h3>
            <span id="hc-sess-status-badge"></span>
        </div>
        <div class="hc-hist-meta-block" id="hc-sess-meta"></div>
        <div id="hc-sess-diferenca" style="margin-bottom:14px;"></div>
        <div id="hc-sess-movs" style="margin-bottom:14px;"></div>
        <div style="display:flex;gap:10px;margin-top:4px;">
            <button type="button" onclick="fecharModalSessao()"
                style="flex:1;padding:12px;background:#f1f5f9;color:#64748b;border:none;border-radius:12px;font-size:14px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;">
                Fechar
            </button>
            <button type="button" id="hc-sess-btn-imprimir" onclick="imprimirSessao()"
                style="flex:1;padding:12px;background:linear-gradient(135deg,#0369a1,#0284c7);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;">
                🖨️ Imprimir Relatório
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Detalhe comanda -->
<div class="hc-modal-overlay" id="hc-modal-hist">
    <div class="hc-modal">
        <div class="hc-hist-modal-header">
            <h3 id="hc-hist-titulo">Comanda #—</h3>
            <span class="hc-hist-status-badge" id="hc-hist-status-badge"></span>
        </div>
        <div style="font-size:13px;color:#64748b;margin-bottom:14px;" id="hc-hist-sub"></div>
        <div class="hc-hist-meta-block" id="hc-hist-meta"></div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:8px;">Itens</div>
        <div class="hc-hist-itens-wrap" id="hc-hist-itens">
            <div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Carregando...</div>
        </div>
        <div class="hc-hist-total-row">
            <span>Total</span>
            <span id="hc-hist-total" style="color:#059669;">R$ —</span>
        </div>
        <div style="margin-top:16px;">
            <button type="button"
                onclick="fecharModal()"
                style="width:100%;padding:13px;background:#f1f5f9;color:#64748b;border:none;border-radius:12px;font-size:14px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;">
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
function fmt(n){ return parseFloat(n).toLocaleString('pt-BR',{minimumFractionDigits:2}); }

/* ─── MODAL SESSÃO DE CAIXA ─── */
let sessaoAtualDetalhe = null;

// Delegação de eventos para linhas de sessão
document.addEventListener('click', function(e) {
    const row = e.target.closest('.hc-sess-row');
    if (row) abrirSessao(parseInt(row.dataset.id));
});

async function abrirSessao(id_sessao) {
    // Abre o modal imediatamente com loading
    document.getElementById('hc-sess-titulo').textContent = 'Sessão #' + id_sessao;
    document.getElementById('hc-sess-status-badge').textContent = '';
    document.getElementById('hc-sess-meta').innerHTML = '<div style="color:#94a3b8;font-size:13px;">Carregando...</div>';
    document.getElementById('hc-sess-diferenca').innerHTML = '';
    document.getElementById('hc-sess-movs').innerHTML = '';
    document.getElementById('hc-modal-sessao').classList.add('show');

    try {
        const res   = await fetch('historico_comandas.php?ajax_sessao_hist=1&id_sessao=' + id_sessao);
        const s = await res.json();
        if (!s || s.erro) { document.getElementById('hc-sess-meta').innerHTML = '<div style="color:#ef4444;">Erro ao carregar.</div>'; return; }

        sessaoAtualDetalhe = s;

        // Badge status
        const badge = document.getElementById('hc-sess-status-badge');
        badge.textContent = s.status === 'aberto' ? 'Aberto' : 'Fechado';
        badge.className = 'hc-hist-status-badge ' + (s.status === 'aberto' ? 'fechada' : 'cancelada');

        // Meta
        const abertura   = new Date(s.aberto_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
        const fechamento = s.fechado_em ? new Date(s.fechado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';
        let meta = `<div class="mi"><strong>Aberto por</strong>${s.aberto_por||'—'}</div>`;
        meta += `<div class="mi"><strong>Abertura</strong>${abertura}</div>`;
        if (s.fechado_por) meta += `<div class="mi"><strong>Fechado por</strong>${s.fechado_por}</div>`;
        meta += `<div class="mi"><strong>Fechamento</strong>${fechamento}</div>`;
        meta += `<div class="mi"><strong>Valor inicial</strong>R$ ${fmt(s.valor_inicial)}</div>`;
        if (s.valor_contado !== null && s.valor_contado !== undefined) meta += `<div class="mi"><strong>Valor contado</strong>R$ ${fmt(s.valor_contado)}</div>`;
        document.getElementById('hc-sess-meta').innerHTML = meta;

        // Diferença
        if (s.diferenca !== null && s.diferenca !== undefined) {
            const dif = parseFloat(s.diferenca);
            const txt = Math.abs(dif) < 0.01 ? 'Sem diferença' : dif > 0 ? 'Sobrou no caixa' : 'Faltou no caixa';
            const cor = Math.abs(dif) < 0.01 ? '#64748b' : dif > 0 ? '#059669' : '#dc2626';
            const bg  = dif > 0 ? '#dcfce7' : dif < 0 ? '#fee2e2' : '#f1f5f9';
            const brd = dif > 0 ? '#86efac' : dif < 0 ? '#fca5a5' : '#cbd5e1';
            document.getElementById('hc-sess-diferenca').innerHTML = `
                <div style="border-radius:12px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;background:${bg};border:1.5px solid ${brd};margin-bottom:10px;">
                    <div><div style="font-size:13px;font-weight:700;">${txt}</div>${s.justificativa?`<div style="font-size:11px;color:#64748b;margin-top:2px;">${s.justificativa}</div>`:''}</div>
                    <span style="font-size:18px;font-weight:800;color:${cor};">${dif>=0?'+':''}R$ ${fmt(dif)}</span>
                </div>`;
        }

        // Resumo financeiro
        let supri = 0, sangr = 0, desp = 0;
        (s.movimentacoes||[]).forEach(m => {
            if (m.tipo==='suprimento') supri += parseFloat(m.valor);
            if (m.tipo==='sangria')    sangr += parseFloat(m.valor);
            if (m.tipo==='despesa')    desp  += parseFloat(m.valor);
        });
        const totalVendido = parseFloat(s.total_vendido||0);
        const saldoEsp = parseFloat(s.valor_inicial) + supri + totalVendido - (sangr + desp);

        let html = '<div style="background:#f8fafc;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0;margin-bottom:10px;">';
        html += '<div style="padding:8px 14px;background:#f1f5f9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;">Resumo Financeiro</div>';
        [['Valor inicial','+',fmt(s.valor_inicial),'#059669'],
         ['Suprimentos','+',fmt(supri),'#059669'],
         ['Vendas ('+(s.qtd_pedidos||0)+' pedidos)','+',fmt(totalVendido),'#059669'],
         ['Sangrias','-',fmt(sangr),'#dc2626'],
         ['Despesas','-',fmt(desp),'#dc2626']
        ].forEach(([l,sn,v,c]) => {
            html += `<div style="display:flex;justify-content:space-between;padding:6px 14px;border-bottom:1px solid #f1f5f9;font-size:12px;"><span>${l}</span><span style="font-weight:700;color:${c};">${sn} R$ ${v}</span></div>`;
        });
        html += `<div style="display:flex;justify-content:space-between;padding:8px 14px;font-size:13px;font-weight:800;"><span>Saldo esperado</span><span style="color:#2563eb;">R$ ${fmt(saldoEsp)}</span></div></div>`;

        if (s.vendas_por_pagamento && s.vendas_por_pagamento.length) {
            html += '<div style="background:#f8fafc;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0;margin-bottom:10px;">';
            html += '<div style="padding:8px 14px;background:#f1f5f9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;">Por Forma de Pagamento</div>';
            s.vendas_por_pagamento.forEach(vp => {
                html += `<div style="display:flex;justify-content:space-between;padding:6px 14px;border-bottom:1px solid #f1f5f9;font-size:12px;"><span>${vp.forma_pagamento} <small style="color:#94a3b8;">(${vp.qtd}x)</small></span><span style="font-weight:700;color:#059669;">R$ ${fmt(vp.total)}</span></div>`;
            });
            html += '</div>';
        }

        if (s.movimentacoes && s.movimentacoes.length) {
            html += '<div style="background:#f8fafc;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0;">';
            html += '<div style="padding:8px 14px;background:#f1f5f9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;">Movimentações</div>';
            s.movimentacoes.forEach(m => {
                const hora  = new Date(m.criado_em).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
                const sinal = m.tipo==='suprimento' ? '+' : '-';
                const cor   = m.tipo==='suprimento' ? '#059669' : '#dc2626';
                html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:7px 14px;border-bottom:1px solid #f1f5f9;font-size:12px;"><div><span style="font-weight:700;">${m.tipo.toUpperCase()}</span> · ${m.descricao||'—'} <small style="color:#94a3b8;">(${hora})</small></div><span style="font-weight:700;color:${cor};">${sinal} R$ ${fmt(m.valor)}</span></div>`;
            });
            html += '</div>';
        }

        document.getElementById('hc-sess-movs').innerHTML = html;

    } catch(e) {
        document.getElementById('hc-sess-meta').innerHTML = '<div style="color:#ef4444;font-size:13px;">Erro: ' + e.message + '</div>';
    }
}

function fecharModalSessao() {
    document.getElementById('hc-modal-sessao').classList.remove('show');
    sessaoAtualDetalhe = null;
}
document.getElementById('hc-modal-sessao').addEventListener('click', function(e) {
    if (e.target === this) fecharModalSessao();
});

function imprimirSessao() {
    const s = sessaoAtualDetalhe;
    if (!s) return;

    let supri = 0, sangr = 0, desp = 0;
    (s.movimentacoes||[]).forEach(m => {
        if (m.tipo==='suprimento') supri += parseFloat(m.valor);
        if (m.tipo==='sangria')    sangr += parseFloat(m.valor);
        if (m.tipo==='despesa')    desp  += parseFloat(m.valor);
    });
    const totalVendido = parseFloat(s.total_vendido||0);
    const saldoEsp = parseFloat(s.valor_inicial) + supri + totalVendido - (sangr + desp);
    const dif = s.diferenca !== null && s.diferenca !== undefined ? parseFloat(s.diferenca) : null;

    const abertura   = new Date(s.aberto_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const fechamento = s.fechado_em ? new Date(s.fechado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';

    let mov_html = '';
    if (s.movimentacoes && s.movimentacoes.length) {
        s.movimentacoes.forEach(m => {
            const hora = new Date(m.criado_em).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
            const sinal = m.tipo==='suprimento' ? '+' : '-';
            mov_html += `<div style="display:flex;justify-content:space-between;font-size:10px;padding:2px 0;"><span>[${m.tipo.toUpperCase()}] ${m.descricao||'—'} (${hora})</span><span>${sinal} R$ ${fmt(m.valor)}</span></div>`;
        });
    } else { mov_html = '<div style="font-size:10px;color:#888;">Nenhuma movimentação.</div>'; }

    let pag_html = '';
    if (s.vendas_por_pagamento && s.vendas_por_pagamento.length) {
        s.vendas_por_pagamento.forEach(vp => {
            pag_html += `<div style="display:flex;justify-content:space-between;font-size:11px;padding:2px 0;"><span>${vp.forma_pagamento} (${vp.qtd}x)</span><span>R$ ${fmt(vp.total)}</span></div>`;
        });
    } else { pag_html = '<div style="font-size:10px;color:#888;">Nenhuma venda.</div>'; }

    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        @page{size:80mm auto;margin:0;}*{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Courier New',monospace;font-size:11px;color:#000;background:#fff;padding:4mm;width:80mm;}
        .titulo{text-align:center;font-weight:bold;font-size:14px;margin-bottom:2px;}
        .sub{text-align:center;font-size:10px;margin-bottom:5px;}
        .linha{border-top:1px dashed #888;margin:5px 0;}
        .linha2{border-top:2px solid #000;margin:5px 0;}
        .row{display:flex;justify-content:space-between;padding:2px 0;font-size:11px;}
        .row-bold{display:flex;justify-content:space-between;padding:3px 0;font-size:12px;font-weight:bold;}
        .secao{font-weight:bold;font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:6px 0 3px;}
    </style></head><body>
        <div class="titulo">RELATÓRIO DE CAIXA</div>
        <div class="sub">Sessão #${s.id_sessao}</div>
        <div class="linha2"></div>
        <div class="row"><span>Operador</span><span>${s.aberto_por||'—'}</span></div>
        <div class="row"><span>Abertura</span><span>${abertura}</span></div>
        <div class="row"><span>Fechamento</span><span>${fechamento}</span></div>
        ${s.fechado_por ? `<div class="row"><span>Fechado por</span><span>${s.fechado_por}</span></div>` : ''}
        <div class="linha"></div>
        <div class="secao">Resumo Financeiro</div>
        <div class="row"><span>Valor inicial</span><span>+ R$ ${fmt(s.valor_inicial)}</span></div>
        <div class="row"><span>Suprimentos</span><span>+ R$ ${fmt(supri)}</span></div>
        <div class="row"><span>Vendas (${s.qtd_pedidos||0} pedidos)</span><span>+ R$ ${fmt(totalVendido)}</span></div>
        <div class="row"><span>Sangrias</span><span>- R$ ${fmt(sangr)}</span></div>
        <div class="row"><span>Despesas</span><span>- R$ ${fmt(desp)}</span></div>
        <div class="linha"></div>
        <div class="row-bold"><span>Saldo esperado</span><span>R$ ${fmt(saldoEsp)}</span></div>
        ${s.valor_contado !== null && s.valor_contado !== undefined ? `<div class="row-bold"><span>Valor contado</span><span>R$ ${fmt(s.valor_contado)}</span></div>` : ''}
        ${dif !== null ? `<div class="row-bold"><span>Diferença</span><span>${dif>=0?'+':''}R$ ${fmt(dif)}</span></div>` : ''}
        ${s.justificativa ? `<div style="font-size:10px;font-style:italic;margin-top:2px;">Obs: ${s.justificativa}</div>` : ''}
        <div class="linha"></div>
        <div class="secao">Vendas por Pagamento</div>
        ${pag_html}
        <div class="linha"></div>
        <div class="secao">Movimentações</div>
        ${mov_html}
        <div class="linha2"></div>
        <div style="text-align:center;font-size:9px;color:#666;margin-top:8px;">Documento sem valor fiscal</div>
    </body></html>`;

    const janela = window.open('', '_blank', 'width=420,height=700');
    janela.document.write(html);
    janela.document.close();
    janela.focus();
    janela.onload = function() { janela.print(); };
}

/* ─── MODAL COMANDA ─── */

async function abrirDetalhe(id) {
    document.getElementById('hc-hist-titulo').textContent = 'Comanda #—';
    document.getElementById('hc-hist-status-badge').textContent = '';
    document.getElementById('hc-hist-itens').innerHTML = '<div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Carregando...</div>';
    document.getElementById('hc-hist-total').textContent = 'R$ —';
    document.getElementById('hc-modal-hist').classList.add('show');

    try {
        const res  = await fetch('historico_comandas.php?detalhe_comanda_hist=1&id=' + id);
        const data = await res.json();

        if (!data) {
            document.getElementById('hc-hist-itens').innerHTML = '<div style="text-align:center;color:#ef4444;font-size:13px;padding:12px;">Erro ao carregar.</div>';
            return;
        }

        document.getElementById('hc-hist-titulo').textContent = 'Comanda #' + data.numero_comanda;
        document.getElementById('hc-hist-sub').textContent    = data.observacao || '';

        const badge = document.getElementById('hc-hist-status-badge');
        badge.textContent  = data.status === 'fechada' ? 'Fechada' : 'Cancelada';
        badge.className    = 'hc-hist-status-badge ' + data.status;

        const abertura   = new Date(data.criado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
        const fechamento = data.fechado_em ? new Date(data.fechado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';

        let meta = `<div class="mi"><strong>Abertura</strong>${abertura}</div>`;
        if (data.lancado_por) meta += `<div class="mi"><strong>Lançado por</strong>${data.lancado_por}</div>`;
        meta += `<div class="mi"><strong>${data.status==='fechada'?'Fechamento':'Cancelamento'}</strong>${fechamento}</div>`;
        document.getElementById('hc-hist-meta').innerHTML = meta;

        document.getElementById('hc-hist-total').textContent = 'R$ ' + fmt(data.valor_total);

        if (!data.itens || !data.itens.length) {
            document.getElementById('hc-hist-itens').innerHTML = '<div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Nenhum item.</div>';
            return;
        }

        document.getElementById('hc-hist-itens').innerHTML = data.itens.map(it => {
            const nomeExib = (it.nome_produto || '').replace(/^\[AVULSO\]\s*/, '');
            const ads = it.adicionais && it.adicionais.length
                ? `<div class="hc-hist-item-ads">+ ${it.adicionais.map(a => { const p = parseFloat(a.preco)>0?' (R$ '+fmt(a.preco)+')':''; return a.nome+p; }).join(', ')}</div>`
                : '';
            const obs = it.observacao ? `<div class="hc-hist-item-obs">"${it.observacao}"</div>` : '';
            return `<div class="hc-hist-item-linha">
                <div class="hc-hist-item-qty">${it.quantidade}</div>
                <div class="hc-hist-item-info">
                    <div class="hc-hist-item-nome">${nomeExib}</div>
                    <div style="font-size:10px;color:#94a3b8;">${it.quantidade}x R$ ${fmt(it.preco_unitario)}</div>
                    ${ads}${obs}
                    ${it.lancado_por ? `<div style="font-size:10px;color:#94a3b8;margin-top:2px;">por ${it.lancado_por}</div>` : ''}
                </div>
                <div class="hc-hist-item-sub">R$ ${fmt(it.subtotal)}</div>
            </div>`;
        }).join('');

    } catch(e) {
        document.getElementById('hc-hist-itens').innerHTML = '<div style="text-align:center;color:#ef4444;font-size:13px;padding:12px;">Erro ao carregar.</div>';
    }
}

function fecharModal() {
    document.getElementById('hc-modal-hist').classList.remove('show');
}

document.getElementById('hc-modal-hist').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});
</script>
</body>
</html>