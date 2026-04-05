<?php
require_once 'config/database.php';
require_once 'config/tema.php';
require_once 'empresa_helper.php';
$emp = getDadosEmpresa($conn);

$is_vendedor = !empty($_SESSION['is_vendedor']) && isset($_SESSION['id_vendedor']);
$is_admin    = isset($_SESSION['id_admin']) && !$is_vendedor;

if (!$is_admin && !$is_vendedor) { header('Location: login.php'); exit; }

// ── TENANT ──
$id_tenant = $_SESSION['id_tenant'] ?? null;
$is_master = empty($_SESSION['id_tenant']);

$msg  = '';
$tipo = '';

// ── BUSCA POR NOME ──
if (isset($_GET['buscar'])) {
    header('Content-Type: application/json');
    $termo = trim($_GET['buscar']);
    if (empty($termo)) { echo json_encode([]); exit; }

    if ($is_master) {
        $stmt = $conn->prepare("SELECT id_produto, nome, marca, modelo, preco, estoque, imagem FROM produtos WHERE ativo=TRUE AND estoque>0 AND (nome ILIKE ? OR marca ILIKE ? OR modelo ILIKE ?) LIMIT 8");
        $like = '%'.$termo.'%';
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $conn->prepare("SELECT id_produto, nome, marca, modelo, preco, estoque, imagem FROM produtos WHERE ativo=TRUE AND estoque>0 AND id_tenant=? AND (nome ILIKE ? OR marca ILIKE ? OR modelo ILIKE ?) LIMIT 8");
        $like = '%'.$termo.'%';
        $stmt->execute([$id_tenant, $like, $like, $like]);
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── BUSCA POR CÓDIGO DE BARRAS ──
if (isset($_GET['barcode'])) {
    header('Content-Type: application/json');
    $codigo = trim($_GET['barcode']);
    if (empty($codigo)) { echo json_encode(null); exit; }

    if ($is_master) {
        $stmt = $conn->prepare("SELECT id_produto, nome, marca, modelo, preco, estoque, imagem FROM produtos WHERE ativo=TRUE AND estoque>0 AND codigo_barras = ? LIMIT 1");
        $stmt->execute([$codigo]);
    } else {
        $stmt = $conn->prepare("SELECT id_produto, nome, marca, modelo, preco, estoque, imagem FROM produtos WHERE ativo=TRUE AND estoque>0 AND id_tenant=? AND codigo_barras = ? LIMIT 1");
        $stmt->execute([$id_tenant, $codigo]);
    }
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
    exit;
}

$comprovante = null;

// ── PROCESSAR VENDA ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '');
    $cpf             = trim($_POST['cpf']             ?? '');
    $observacao      = trim($_POST['observacao']      ?? '');
    $itens_json      = trim($_POST['itens_json']      ?? '[]');
    $itens = json_decode($itens_json, true);

    $erros = [];
    if (empty($forma_pagamento))            $erros[] = "Selecione a forma de pagamento.";
    if (empty($itens) || !is_array($itens)) $erros[] = "Nenhum item no carrinho.";

    $prods_db = [];
    if (empty($erros)) {
        foreach ($itens as $item) {
            if ($is_master) {
                $r = $conn->prepare("SELECT nome, preco, estoque FROM produtos WHERE id_produto=?");
                $r->execute([$item['id_produto']]);
            } else {
                $r = $conn->prepare("SELECT nome, preco, estoque FROM produtos WHERE id_produto=? AND id_tenant=?");
                $r->execute([$item['id_produto'], $id_tenant]);
            }
            $p = $r->fetch();
            if (!$p) { $erros[] = "Produto ID {$item['id_produto']} não encontrado."; break; }
            if ($p['estoque'] < $item['quantidade']) { $erros[] = "Estoque insuficiente para \"{$p['nome']}\"."; break; }
            $prods_db[$item['id_produto']] = $p;
        }
    }

    if (empty($erros)) {
        $subtotal_total = 0;
        foreach ($itens as $item) $subtotal_total += $prods_db[$item['id_produto']]['preco'] * $item['quantidade'];
        try {
            $conn->beginTransaction();

            $stmt_pedido = $conn->prepare("INSERT INTO pedidos (nome_cliente,cpf_cliente,telefone_cliente,forma_pagamento,valor_produtos,valor_total,valor_frete,status,observacoes,data_pedido,tipo,id_tenant) VALUES (?,?,?,?,?,?,0,'aprovado',?,NOW(),'presencial',?)");
            $stmt_pedido->execute(['Cliente Balcao',$cpf,'',$forma_pagamento,$subtotal_total,$subtotal_total,$observacao?:'Venda presencial',$id_tenant]);
            $id_pedido = $conn->lastInsertId();

            $itens_comprovante = [];
            foreach ($itens as $item) {
                $p   = $prods_db[$item['id_produto']];
                $sub = $p['preco'] * $item['quantidade'];
                $conn->prepare("INSERT INTO itens_pedido (id_pedido,id_produto,nome_produto,quantidade,preco_unitario,subtotal) VALUES (?,?,?,?,?,?)")->execute([$id_pedido,$item['id_produto'],$p['nome'],$item['quantidade'],$p['preco'],$sub]);
                $conn->prepare("UPDATE produtos SET estoque=estoque-? WHERE id_produto=? AND id_tenant=?")->execute([$item['quantidade'],$item['id_produto'],$id_tenant]);
                try {
                    if ($is_vendedor) $conn->prepare("INSERT INTO vendas_presenciais (id_pedido,id_produto,quantidade,id_vendedor,criado_em) VALUES (?,?,?,?,NOW())")->execute([$id_pedido,$item['id_produto'],$item['quantidade'],$_SESSION['id_vendedor']]);
                    else              $conn->prepare("INSERT INTO vendas_presenciais (id_pedido,id_produto,quantidade,id_admin,criado_em) VALUES (?,?,?,?,NOW())")->execute([$id_pedido,$item['id_produto'],$item['quantidade'],$_SESSION['id_admin']]);
                } catch (\Throwable $e) {}
                $itens_comprovante[] = ['nome'=>$p['nome'],'quantidade'=>$item['quantidade'],'preco_unitario'=>$p['preco'],'subtotal'=>$sub];
            }
            $conn->commit();
            $msg  = "Venda registrada! Pedido <strong>#$id_pedido</strong> — ".count($itens)." item(ns) | Total: <strong>R$ ".number_format($subtotal_total,2,',','.')."</strong>";
            $tipo = "success";
            $comprovante = ['id_pedido'=>$id_pedido,'data'=>date('d/m/Y H:i'),'itens'=>$itens_comprovante,'forma_pagamento'=>$forma_pagamento,'observacao'=>$observacao,'cpf'=>$cpf,'total'=>$subtotal_total];
        } catch (\Throwable $e) { $conn->rollBack(); $msg = "Erro: ".$e->getMessage(); $tipo = "danger"; }
    } else { $msg = implode(' ',$erros); $tipo = "danger"; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Venda Presencial</title>
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════
   BASE
══════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--dash-bg, #f0fdf4);
  color: #0f172a;
  min-height: 100vh;
}

/* ══════════════════════════════════════
   TOPBAR
══════════════════════════════════════ */
.vp-topbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 24px; height: 56px;
  background: #fff; border-bottom: 1.5px solid #d1fae5;
  position: sticky; top: 0; z-index: 400;
  box-shadow: 0 2px 8px rgba(5,150,105,.08);
}
.vp-topbar-brand { display: flex; align-items: center; gap: 10px; }
.vp-topbar-brand h1 { font-size: 17px; font-weight: 800; color: #065f46; letter-spacing: -.4px; }
.vp-topbar-brand span { font-size: 12px; color: #6b7280; font-weight: 500; }
.vp-topbar-right { display: flex; align-items: center; gap: 10px; }
.vp-topbar-user { font-size: 13px; font-weight: 600; color: #374151; background: #f0fdf4; border: 1.5px solid #a7f3d0; padding: 5px 14px; border-radius: 20px; }
.vp-btn-back { display: inline-flex; align-items: center; gap: 5px; padding: 7px 16px; background: #f0fdf4; color: #059669; border: 1.5px solid #a7f3d0; border-radius: 20px; font-size: 12px; font-weight: 700; text-decoration: none; font-family: 'Sora', sans-serif; transition: .15s; }
.vp-btn-back:hover { background: #dcfce7; }
.vp-btn-sair { display: inline-flex; align-items: center; gap: 5px; padding: 7px 16px; background: #fef2f2; color: #ef4444; border: 1.5px solid #fecaca; border-radius: 20px; font-size: 12px; font-weight: 700; text-decoration: none; font-family: 'Sora', sans-serif; }

/* ══════════════════════════════════════
   ALERT
══════════════════════════════════════ */
.vp-alert-bar {
  padding: 12px 24px;
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  font-size: 14px; font-weight: 500; border-bottom: 1.5px solid;
  flex-wrap: wrap;
}
.vp-alert-bar.success { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; }
.vp-alert-bar.danger  { background: #fef2f2; color: #7f1d1d; border-color: #fca5a5; }
.vp-btn-imprimir { padding: 8px 18px; background: #059669; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Sora', sans-serif; display: flex; align-items: center; gap: 6px; white-space: nowrap; }

/* ══════════════════════════════════════
   DESKTOP LAYOUT — 3 colunas
══════════════════════════════════════ */
.vp-desk {
  display: none;
  height: calc(100vh - 56px);
  overflow: hidden;
}
.vp-desk-col-produtos {
  flex: 1; display: flex; flex-direction: column;
  border-right: 1.5px solid #e2e8f0; overflow: hidden; min-width: 0;
  background: #f8fafc;
}
.vp-desk-col-cart {
  width: 320px; flex-shrink: 0; display: flex; flex-direction: column;
  border-right: 1.5px solid #e2e8f0; overflow: hidden;
  background: #fff;
}
.vp-desk-col-pag {
  width: 300px; flex-shrink: 0; display: flex; flex-direction: column;
  overflow: hidden; background: #fff;
}

/* Cabeçalho de coluna */
.vp-col-head {
  padding: 14px 18px; background: #fff; border-bottom: 1.5px solid #e2e8f0;
  display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
}
.vp-col-head h2 { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: #6b7280; }

/* ══════════════════════════════════════
   COLUNA PRODUTOS
══════════════════════════════════════ */
.vp-produtos-filtros {
  padding: 12px 18px; background: #fff; border-bottom: 1.5px solid #e2e8f0; flex-shrink: 0;
  display: flex; flex-direction: column; gap: 10px;
}
.vp-barcode-strip {
  display: flex; align-items: center; gap: 10px;
  background: #f0fdf4; border: 1.5px dashed #6ee7b7; border-radius: 10px;
  padding: 10px 14px; font-size: 12px;
}
.vp-barcode-strip span { font-weight: 700; color: #065f46; }
.vp-barcode-strip small { color: #6b7280; font-size: 11px; }
.vp-barcode-badge { margin-left: auto; font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: #dcfce7; color: #059669; }

.vp-search-wrap { position: relative; }
.vp-search-ico { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; pointer-events: none; font-weight: 700; }
.vp-search {
  width: 100%; padding: 10px 12px 10px 38px;
  border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; font-family: 'Sora', sans-serif;
  outline: none; transition: .2s; background: #f8fafc; color: #0f172a;
}
.vp-search:focus { border-color: #059669; background: #fff; box-shadow: 0 0 0 3px rgba(5,150,105,.1); }

.vp-feedback { display:none; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; font-size:12px; font-weight:600; }
.vp-feedback.show { display:flex; }
.vp-feedback.ok  { background:#dcfce7; color:#065f46; }
.vp-feedback.err { background:#fee2e2; color:#991b1b; }

.vp-results {
  display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0;
  background: #fff; border: 1.5px solid #d1fae5; border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,.1); max-height: 260px; overflow-y: auto; z-index: 100;
}
.vp-results.show { display: block; }
.vp-res-item { display:flex; align-items:center; gap:10px; padding:10px 14px; cursor:pointer; border-bottom:1px solid #f0fdf4; transition:background .1s; }
.vp-res-item:last-child { border-bottom:none; }
.vp-res-item:hover { background:#f0fdf4; }
.vp-res-thumb { width:42px; height:42px; object-fit:cover; border-radius:8px; border:1.5px solid #d1fae5; flex-shrink:0; }
.vp-res-nome  { font-size:13px; font-weight:700; }
.vp-res-sub   { font-size:10px; color:#9ca3af; margin-top:1px; }
.vp-res-preco { font-size:13px; font-weight:800; color:#059669; white-space:nowrap; flex-shrink:0; }
.vp-no-res    { padding:18px; text-align:center; color:#9ca3af; font-size:13px; }

/* Grid de produtos */
.vp-prod-grid-wrap { flex: 1; overflow-y: auto; padding: 16px 18px; background: var(--dash-bg); }
.vp-prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; }
.vp-prod-card {
  background: #fff; border: 1.5px solid #e2e8f0; border-radius: 10px;
  overflow: hidden; cursor: pointer; transition: border-color .15s, box-shadow .15s, transform .1s;
  display: flex; flex-direction: column;
}
.vp-prod-card:hover { border-color: #059669; box-shadow: 0 4px 16px rgba(5,150,105,.15); }
.vp-prod-card:active { transform: scale(.98); }
.vp-prod-info { padding:10px 12px 8px; flex:1; display:flex; flex-direction:column; gap:3px; }
.vp-prod-nome { font-size:13px; font-weight:700; line-height:1.3; }
.vp-prod-preco { font-size:14px; font-weight:800; color:#059669; }
.vp-prod-btn {
  width:100%; padding:7px; background:#059669; color:#fff; border:none;
  font-size:11px; font-weight:700; font-family:'Sora',sans-serif; cursor:pointer;
  transition:background .15s; flex-shrink:0;
}
.vp-prod-btn:hover { background:#047857; }
.vp-grid-loading { text-align:center; padding:40px; color:#9ca3af; font-size:14px; grid-column:1/-1; }

/* ══════════════════════════════════════
   COLUNA CARRINHO
══════════════════════════════════════ */
.vp-badge { background:#059669; color:#fff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:20px; margin-left:6px; }
.vp-btn-limpar { padding:5px 12px; background:#fef2f2; color:#ef4444; border:1.5px solid #fecaca; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer; font-family:'Sora',sans-serif; }

.vp-cart-items { flex:1; overflow-y:auto; padding:12px 16px; }
.vp-cart-vazio { text-align:center; color:#9ca3af; font-size:13px; padding:24px 0; }

.vp-cart-item { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid #f0fdf4; }
.vp-cart-item:last-of-type { border-bottom:none; }
.vp-ci-thumb { width:44px; height:44px; object-fit:cover; border-radius:8px; border:1.5px solid #d1fae5; flex-shrink:0; }
.vp-ci-info { flex:1; min-width:0; }
.vp-ci-nome { font-size:12px; font-weight:700; line-height:1.3; margin-bottom:5px; }
.vp-ci-bottom { display:flex; align-items:center; justify-content:space-between; gap:6px; }
.vp-qty-ctrl { display:flex; align-items:center; background:#f0fdf4; border-radius:8px; padding:2px; }
.vp-qty-btn { width:26px; height:26px; border:none; background:#fff; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; color:#059669; display:flex; align-items:center; justify-content:center; }
.vp-qty-num { width:28px; text-align:center; font-size:12px; font-weight:800; border:none; outline:none; background:transparent; color:#0f172a; font-family:'Sora',sans-serif; }
.vp-ci-preco { font-size:13px; font-weight:800; color:#059669; white-space:nowrap; }
.vp-ci-del { width:26px; height:26px; border:none; background:#fef2f2; border-radius:6px; cursor:pointer; color:#ef4444; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

.vp-cart-footer {
  padding:14px 16px; border-top:1.5px solid #d1fae5; background:#f8fffe; flex-shrink:0;
}
.vp-cart-total { display:flex; justify-content:space-between; font-size:16px; font-weight:800; color:#065f46; margin-bottom:12px; }

/* ══════════════════════════════════════
   COLUNA PAGAMENTO
══════════════════════════════════════ */
.vp-pag-body { flex:1; overflow-y:auto; padding:16px; }

.vp-field { margin-bottom:14px; }
.vp-field label { display:block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#6b7280; margin-bottom:5px; }
.vp-field input, .vp-field textarea {
  width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px;
  font-size:13px; font-family:'Sora',sans-serif; background:#fff; color:#0f172a; outline:none; transition:.2s;
}
.vp-field input:focus, .vp-field textarea:focus { border-color:#059669; box-shadow:0 0 0 3px rgba(5,150,105,.1); }
.vp-field textarea { resize:none; height:64px; line-height:1.5; }

.vp-pag-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
.vp-pag-pill { position:relative; }
.vp-pag-pill input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
.vp-pag-pill label {
  display:flex; align-items:center; gap:7px; padding:10px 12px;
  border:1.5px solid #e2e8f0; border-radius:10px; font-size:12px; font-weight:600;
  color:#475569; cursor:pointer; transition:.15s; background:#fff;
}
.vp-pag-pill input:checked + label { border-color:#059669; background:#ecfdf5; color:#065f46; }
.vp-pag-icon { font-size:16px; }

/* Resumo */
.vp-resumo {
  background:#f0fdf4; border:1.5px solid #a7f3d0; border-radius:12px;
  padding:12px 14px; margin-bottom:14px;
}
.vp-resumo-linha { display:flex; justify-content:space-between; font-size:12px; padding:3px 0; gap:8px; }
.vp-resumo-linha span:first-child { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#374151; }
.vp-resumo-linha span:last-child { font-weight:700; white-space:nowrap; }
.vp-resumo-sep { border:none; border-top:1px dashed #a7f3d0; margin:8px 0; }
.vp-resumo-row { display:flex; justify-content:space-between; font-size:12px; padding:3px 0; }
.vp-resumo-label { color:#6b7280; }
.vp-resumo-val { font-weight:700; }
.vp-resumo-total { display:flex; justify-content:space-between; font-size:16px; font-weight:800; color:#065f46; border-top:2px solid #a7f3d0; padding-top:10px; margin-top:8px; }

/* Botão confirmar */
.vp-btn-confirmar {
  width:100%; padding:14px; background:linear-gradient(135deg,#059669,#047857);
  color:#fff; border:none; border-radius:12px; font-size:14px; font-weight:700;
  font-family:'Sora',sans-serif; cursor:pointer; transition:opacity .15s;
  box-shadow:0 4px 14px rgba(5,150,105,.3);
}
.vp-btn-confirmar:hover { opacity:.9; }
.vp-btn-confirmar:active { transform:scale(.99); }

/* Histórico */
.vp-hist-body { padding:0 16px 12px; }
.vp-hist-item { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f0fdf4; }
.vp-hist-item:last-child { border-bottom:none; }
.vp-hist-icon { width:28px; height:28px; background:#dcfce7; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
.vp-hist-nome { font-size:12px; font-weight:600; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.vp-hist-qty  { font-size:11px; font-weight:700; color:#059669; white-space:nowrap; }
.vp-hist-time { font-size:10px; color:#9ca3af; white-space:nowrap; }
.vp-hist-empty { font-size:12px; color:#9ca3af; padding:10px 0; }

/* ══════════════════════════════════════
   MOBILE LAYOUT (original mantido)
══════════════════════════════════════ */
.vp-mobile { display: block; }

/* Steps */
.vp-steps { display:flex; align-items:center; padding:10px 16px; background:#fff; border-bottom:1px solid #e2e8f0; gap:0; }
.vp-step  { display:flex; align-items:center; gap:6px; flex:1; }
.vp-step-dot { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; flex-shrink:0; transition:all .25s; }
.vp-step-dot.active { background:#059669; color:#fff; box-shadow:0 0 0 4px rgba(5,150,105,.18); }
.vp-step-dot.done   { background:#dcfce7; color:#059669; font-size:13px; }
.vp-step-dot.idle   { background:#f1f5f9; color:#94a3b8; }
.vp-step-lbl { font-size:11px; font-weight:600; color:#94a3b8; }
.vp-step-lbl.active { color:#059669; }
.vp-step-line { flex:1; height:2px; background:#e2e8f0; margin:0 6px; transition:background .25s; }
.vp-step-line.done { background:#6ee7b7; }

.vp-wrap  { max-width:520px; margin:0 auto; padding:0 0 100px; }
.vp-section { padding:14px 16px; }
.vp-card { background:#fff; border-radius:16px; border:1.5px solid #d1fae5; overflow:hidden; margin-bottom:12px; box-shadow:0 1px 6px rgba(5,150,105,.07); }
.vp-card-head { padding:12px 16px; background:#f8fffe; border-bottom:1px solid #f0fdf4; display:flex; align-items:center; justify-content:space-between; }
.vp-card-head h2 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#64748b; }
.vp-card-body { padding:16px; }
.vp-barcode { display:flex; align-items:center; gap:12px; background:#f0fdf4; border:2px dashed #6ee7b7; border-radius:14px; padding:14px; margin-bottom:14px; }
.vp-barcode-icon { font-size:24px; flex-shrink:0; }
.vp-barcode-lbl  { font-size:13px; font-weight:700; color:#065f46; }
.vp-barcode-sub  { font-size:11px; color:#6b7280; margin-top:2px; }
.vp-barcode-badge2 { font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; background:#dcfce7; color:#059669; white-space:nowrap; flex-shrink:0; }
.vp-ou { display:flex; align-items:center; gap:10px; margin-bottom:12px; color:#94a3b8; font-size:12px; font-weight:600; }
.vp-ou::before,.vp-ou::after { content:''; flex:1; height:1px; background:#e2e8f0; }
.vp-search-mob { width:100%; padding:13px 14px 13px 44px; border:2px solid #e2e8f0; border-radius:14px; font-size:15px; font-family:'Sora',sans-serif; outline:none; transition:.2s; background:#fff; color:#0f172a; }
.vp-search-mob:focus { border-color:#059669; box-shadow:0 0 0 4px rgba(5,150,105,.1); }
.vp-results-mob { border:1.5px solid #d1fae5; border-radius:14px; overflow:hidden; margin-top:10px; box-shadow:0 8px 24px rgba(0,0,0,.1); display:none; background:#fff; max-height:280px; overflow-y:auto; }
.vp-results-mob.show { display:block; }
.vp-cart-item-mob { display:flex; align-items:flex-start; gap:10px; padding:12px 0; border-bottom:1px solid #f0fdf4; }
.vp-cart-item-mob:last-of-type { border-bottom:none; }
.vp-ci-thumb { width:48px; height:48px; object-fit:cover; border-radius:10px; border:1.5px solid #d1fae5; flex-shrink:0; margin-top:2px; }
.vp-cart-total-mob { display:flex; justify-content:space-between; align-items:center; padding:12px 0 0; border-top:2px solid #d1fae5; margin-top:6px; font-size:16px; font-weight:800; color:#065f46; }
.vp-pag-grid-mob { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.vp-resumo-mob { background:#f0fdf4; border:2px solid #a7f3d0; border-radius:16px; padding:16px; margin-bottom:16px; }
.vp-resumo-total-mob { display:flex; justify-content:space-between; font-size:18px; font-weight:800; color:#065f46; border-top:2px solid #a7f3d0; padding-top:12px; margin-top:8px; }
.vp-btn-confirmar-mob { width:100%; padding:16px; background:linear-gradient(135deg,#059669,#047857); color:#fff; border:none; border-radius:14px; font-size:16px; font-weight:700; font-family:'Sora',sans-serif; cursor:pointer; box-shadow:0 6px 18px rgba(5,150,105,.35); }
.vp-btn-row { display:flex; gap:10px; margin-top:12px; }
.vp-btn-sec { flex:1; padding:13px; background:#fff; color:#475569; border:1.5px solid #e2e8f0; border-radius:12px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; }
.vp-btn-cancel { flex:1; padding:13px; background:#fef2f2; color:#ef4444; border:1.5px solid #fecaca; border-radius:12px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; }
.vp-fab { position:fixed; bottom:0; left:0; right:0; z-index:300; padding:12px 16px calc(12px + env(safe-area-inset-bottom)); background:#fff; border-top:1px solid #d1fae5; box-shadow:0 -4px 20px rgba(5,150,105,.12); display:none; }
.vp-fab.show { display:block; }
.vp-fab-btn { width:100%; padding:15px; background:linear-gradient(135deg,#059669,#047857); color:#fff; border:none; border-radius:14px; font-size:15px; font-weight:700; font-family:'Sora',sans-serif; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; box-shadow:0 4px 14px rgba(5,150,105,.35); }
.vp-field-mob { margin-bottom:16px; }
.vp-field-mob label { display:block; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#64748b; margin-bottom:7px; }
.vp-field-mob input, .vp-field-mob textarea { width:100%; padding:13px 15px; border:2px solid #e2e8f0; border-radius:12px; font-size:15px; font-family:'Sora',sans-serif; background:#fff; color:#0f172a; outline:none; transition:.2s; }
.vp-field-mob input:focus, .vp-field-mob textarea:focus { border-color:#059669; box-shadow:0 0 0 4px rgba(5,150,105,.1); }
.vp-field-mob textarea { resize:vertical; min-height:76px; line-height:1.6; }

/* ══════════════════════════════════════
   RESPONSIVO
══════════════════════════════════════ */
@media (min-width: 900px) {
  .vp-mobile { display: none !important; }
  .vp-desk   { display: flex; }
}
@media (max-width: 899px) {
  .vp-desk   { display: none !important; }
  .vp-mobile { display: block; }
}
@media (min-width: 900px) and (max-width: 1200px) {
  .vp-desk-col-cart { width: 280px; }
  .vp-desk-col-pag  { width: 260px; }
  .vp-prod-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}

/* COMPROVANTE PRINT */
#vp-comprovante-print { position:absolute; left:-9999px; top:0; width:80mm; padding:6mm 5mm; font-family:'Courier New',monospace; font-size:11px; color:#000; background:#fff; visibility:hidden; }
@media print { body > * { display:none !important; } #vp-comprovante-print { display:block !important; position:static !important; left:0 !important; visibility:visible !important; width:80mm; padding:4mm; } }
.cp-empresa  { text-align:center; font-weight:bold; font-size:14px; margin-bottom:2px; }
.cp-cnpj     { text-align:center; font-size:11px; margin-bottom:2px; }
.cp-endereco { text-align:center; font-size:10px; margin-bottom:2px; }
.cp-fone     { text-align:center; font-size:10px; margin-bottom:4px; }
.cp-linha-dupla { border-top:2px solid #000; margin:5px 0; }
.cp-linha    { border-top:1px dashed #888; margin:5px 0; }
.cp-titulo   { text-align:center; font-weight:bold; font-size:12px; letter-spacing:2px; margin:6px 0 4px; }
.cp-info     { display:flex; justify-content:space-between; font-size:11px; margin:3px 0; }
.cp-produto-nome    { font-weight:bold; font-size:11px; margin:4px 0 2px; }
.cp-produto-detalhe { display:flex; justify-content:space-between; font-size:11px; color:#333; padding-left:4px; }
.cp-obs      { font-size:10px; color:#444; margin:3px 0; word-break:break-word; }
.cp-total-row{ display:flex; justify-content:space-between; font-weight:bold; font-size:14px; margin:5px 0 3px; }
.cp-rodape   { text-align:center; font-size:10px; color:#666; margin-top:10px; line-height:1.5; }
</style>
</head>
<body>

<!-- COMPROVANTE OCULTO -->
<div id="vp-comprovante-print">
<?php if ($comprovante): ?>
    <div class="cp-empresa"><?= htmlspecialchars($emp['nome_fantasia'] ?: $emp['nome_empresa']) ?></div>
<?php if (!empty($emp['cnpj'])): ?>
<div class="cp-cnpj">CNPJ: <?= htmlspecialchars($emp['cnpj_formatado']) ?></div>
<?php endif; ?>
<?php if (!empty($emp['endereco_completo'])): ?>
<div class="cp-endereco"><?= htmlspecialchars($emp['endereco_completo']) ?></div>
<?php endif; ?>
<?php if (!empty($emp['telefone']) || !empty($emp['celular'])): ?>
<div class="cp-fone">Tel: <?= htmlspecialchars($emp['telefone'] ?: $emp['celular']) ?></div>
<?php endif; ?>
    <div class="cp-linha-dupla"></div>
    <div class="cp-titulo">COMPROVANTE DE VENDA</div>
    <div class="cp-linha"></div>
    <div class="cp-info"><span>Pedido</span><span>#<?= $comprovante['id_pedido'] ?></span></div>
    <div class="cp-info"><span>Data</span><span><?= $comprovante['data'] ?></span></div>
    <?php if (!empty($comprovante['cpf'])): ?><div class="cp-info"><span>CPF</span><span><?= htmlspecialchars($comprovante['cpf']) ?></span></div><?php endif; ?>
    <div class="cp-linha"></div>
    <?php foreach ($comprovante['itens'] as $it): ?>
    <div class="cp-produto-nome"><?= htmlspecialchars($it['nome']) ?></div>
    <div class="cp-produto-detalhe"><span><?= $it['quantidade'] ?> un. x R$ <?= number_format($it['preco_unitario'],2,',','.') ?></span><span>R$ <?= number_format($it['subtotal'],2,',','.') ?></span></div>
    <?php endforeach; ?>
    <?php if (!empty($comprovante['observacao']) && $comprovante['observacao'] !== 'Venda presencial'): ?>
    <div class="cp-linha"></div><div class="cp-obs"><strong>Obs:</strong> <?= htmlspecialchars($comprovante['observacao']) ?></div>
    <?php endif; ?>
    <div class="cp-linha"></div>
    <div class="cp-total-row"><span>TOTAL</span><span>R$ <?= number_format($comprovante['total'],2,',','.') ?></span></div>
    <div class="cp-info"><span>Pagamento</span><span><?= htmlspecialchars($comprovante['forma_pagamento']) ?></span></div>
    <div class="cp-linha-dupla"></div>
    <div class="cp-rodape"><?= htmlspecialchars($emp['nome_fantasia'] ?: $emp['nome_empresa']) ?></div>
<?php endif; ?>
</div>

<!-- TOPBAR -->
<div class="vp-topbar">
    <div class="vp-topbar-brand">
        <h1>Venda Presencial</h1>
        <span>·</span>
        <span><?= $is_vendedor ? htmlspecialchars($_SESSION['nome_vendedor'] ?? 'Vendedor') : 'Admin' ?></span>
    </div>
    <div class="vp-topbar-right">
        <?php if ($is_admin): ?>
            <a href="admin.php" class="vp-btn-back">Voltar</a>
        <?php else: ?>
            <a href="logout_vendedor.php" class="vp-btn-sair">Sair</a>
        <?php endif; ?>
    </div>
</div>

<!-- ALERT BAR -->
<?php if ($msg): ?>
<div class="vp-alert-bar <?= $tipo ?>" id="vp-alert-msg">
    <span><?= $msg ?></span>
    <?php if ($tipo === 'success'): ?>
    <button type="button" onclick="imprimirComprovante()" class="vp-btn-imprimir">Imprimir Comprovante</button>
    <?php endif; ?>
</div>
<script>
    setTimeout(function(){
        var el = document.getElementById('vp-alert-msg');
        if (el) { el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }
    }, 3000);
</script>
<?php endif; ?>
<!-- ════════════════════════════════════
     DESKTOP LAYOUT
════════════════════════════════════ -->
<div class="vp-desk">

    <!-- COL 1: PRODUTOS -->
    <div class="vp-desk-col-produtos">
        <div class="vp-col-head">
            <h2>Produtos</h2>
            <div id="desk-prod-count" style="font-size:11px;color:#9ca3af;"></div>
        </div>
        <div class="vp-produtos-filtros">
            <div class="vp-barcode-strip">
                <span></span>
                <div><span>Pistola ativa</span><br><small>Bipe o produto para adicionar</small></div>
                <span class="vp-barcode-badge">Aguardando</span>
            </div>
            <div id="desk-feedback" class="vp-feedback"></div>
            <div class="vp-search-wrap">
                <div class="vp-search-ico"></div>
                <input type="text" id="desk-search" class="vp-search" placeholder="Buscar por nome, marca ou modelo..." autocomplete="off">
                <div class="vp-results" id="desk-results"></div>
            </div>
        </div>
        <div class="vp-prod-grid-wrap">
            <div id="desk-prod-grid" class="vp-prod-grid">
                <div class="vp-grid-loading">Carregando produtos...</div>
            </div>
        </div>
    </div>

    <!-- COL 2: CARRINHO -->
    <div class="vp-desk-col-cart">
        <div class="vp-col-head">
            <h2>Carrinho <span class="vp-badge" id="desk-badge">0</span></h2>
            <button class="vp-btn-limpar" onclick="limparCarrinho()">Limpar</button>
        </div>
        <div class="vp-cart-items" id="desk-cart-body">
            <div class="vp-cart-vazio">Nenhum produto ainda.</div>
        </div>
        <!-- Histórico -->
        <div class="vp-col-head" style="flex-shrink:0;border-top:1.5px solid #e2e8f0;">
            <h2>Histórico da sessão</h2>
        </div>
        <div class="vp-hist-body" id="desk-hist-body" style="overflow-y:auto;max-height:160px;flex-shrink:0;">
            <div class="vp-hist-empty">Nenhuma venda registrada.</div>
        </div>
    </div>

    <!-- COL 3: PAGAMENTO + CONFIRMAR -->
    <div class="vp-desk-col-pag">
        <div class="vp-col-head"><h2>Pagamento</h2></div>
        <div class="vp-pag-body">

            <div class="vp-field">
                <label>Forma de Pagamento <span style="color:#ef4444;">*</span></label>
                <div class="vp-pag-grid" style="margin-top:6px;">
                    <div class="vp-pag-pill"><input type="radio" name="desk_pag" id="dp-pix"      value="PIX"><label for="dp-pix">PIX</label></div>
                    <div class="vp-pag-pill"><input type="radio" name="desk_pag" id="dp-credito"  value="Cartao de Credito"><label for="dp-credito">Crédito</label></div>
                    <div class="vp-pag-pill"><input type="radio" name="desk_pag" id="dp-debito"   value="Cartao de Debito"><label for="dp-debito">Débito</label></div>
                    <div class="vp-pag-pill"><input type="radio" name="desk_pag" id="dp-dinheiro" value="Dinheiro"><label for="dp-dinheiro">Dinheiro</label></div>
                </div>
            </div>

            <div class="vp-field">
                <label>CPF do Cliente <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px;color:#9ca3af;">(opcional)</span></label>
                <input type="text" id="desk-cpf" placeholder="000.000.000-00" maxlength="14" autocomplete="off">
            </div>

            <div class="vp-field">
                <label>Observação <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px;color:#9ca3af;">(opcional)</span></label>
                <textarea id="desk-obs" placeholder="Ex: Película instalada, capa inclusa..."></textarea>
            </div>

            <!-- Resumo dos itens -->
            <div class="vp-resumo" id="desk-resumo" style="display:none;">
                <div id="desk-resumo-itens"></div>
                <hr class="vp-resumo-sep">
                <div class="vp-resumo-row"><span class="vp-resumo-label">Pagamento</span><span class="vp-resumo-val" id="desk-resumo-pag">—</span></div>
                <div class="vp-resumo-row" id="desk-resumo-cpf-row" style="display:none;"><span class="vp-resumo-label">CPF</span><span class="vp-resumo-val" id="desk-resumo-cpf">—</span></div>
                <div class="vp-resumo-row" id="desk-resumo-obs-row" style="display:none;"><span class="vp-resumo-label">Obs</span><span class="vp-resumo-val" id="desk-resumo-obs" style="max-width:55%;text-align:right;font-size:11px;">—</span></div>
                <div class="vp-resumo-total"><span>TOTAL</span><span id="desk-resumo-total">—</span></div>
            </div>

            <form method="POST" id="desk-form">
                <input type="hidden" name="forma_pagamento" id="desk-hid-pag">
                <input type="hidden" name="cpf"             id="desk-hid-cpf">
                <input type="hidden" name="observacao"      id="desk-hid-obs">
                <input type="hidden" name="itens_json"      id="desk-hid-itens">
                <button type="button" onclick="deskConfirmar()" class="vp-btn-confirmar">
                    Confirmar Venda
                </button>
            </form>
        </div>
    </div>

</div><!-- /vp-desk -->

<!-- ════════════════════════════════════
     MOBILE LAYOUT (original)
════════════════════════════════════ -->
<div class="vp-mobile">
    <div class="vp-steps">
        <div class="vp-step"><div class="vp-step-dot active" id="s1">1</div><span class="vp-step-lbl active" id="sl1">Produtos</span></div>
        <div class="vp-step-line" id="sep1"></div>
        <div class="vp-step"><div class="vp-step-dot idle" id="s2">2</div><span class="vp-step-lbl" id="sl2">Pagamento</span></div>
        <div class="vp-step-line" id="sep2"></div>
        <div class="vp-step"><div class="vp-step-dot idle" id="s3">3</div><span class="vp-step-lbl" id="sl3">Confirmar</span></div>
    </div>

    <div class="vp-wrap">
        <!-- PASSO 1 -->
        <div id="passo1">
            <div class="vp-section">
                <div class="vp-card">
                    <div class="vp-card-head"><h2>Adicionar Produtos</h2></div>
                    <div class="vp-card-body">
                        <div class="vp-barcode">
                            <span class="vp-barcode-icon"></span>
                            <div style="flex:1;"><div class="vp-barcode-lbl">Pistola de código de barras ativa</div><div class="vp-barcode-sub">Bipe o produto para adicionar</div></div>
                            <span class="vp-barcode-badge2">Aguardando</span>
                        </div>
                        <div id="mob-feedback" class="vp-feedback"></div>
                        <div class="vp-ou">ou busque pelo nome</div>
                        <div class="vp-search-wrap">
                            <div class="vp-search-ico">&#128269;</div>
                            <input type="text" id="mob-search" class="vp-search-mob" placeholder="Nome, marca ou modelo..." autocomplete="off" style="padding-left:44px;">
                            <div class="vp-results-mob" id="mob-results"></div>
                        </div>
                    </div>
                </div>
                <div class="vp-card">
                    <div class="vp-card-head">
                        <h2>Carrinho <span class="vp-badge" id="mob-badge">0</span></h2>
                        <button class="vp-btn-limpar" onclick="limparCarrinho()">Limpar</button>
                    </div>
                    <div class="vp-card-body" id="mob-cart-body"><div class="vp-cart-vazio">Nenhum produto ainda.</div></div>
                </div>
                <div class="vp-card">
                    <div class="vp-card-head"><h2>Histórico desta sessão</h2></div>
                    <div class="vp-card-body" id="mob-hist-body"><div class="vp-hist-empty">Nenhuma venda registrada.</div></div>
                </div>
            </div>
        </div>
        <!-- PASSO 2 -->
        <div id="passo2" style="display:none;">
            <div class="vp-section">
                <div class="vp-card">
                    <div class="vp-card-head"><h2>Pagamento e Dados</h2></div>
                    <div class="vp-card-body">
                        <div class="vp-field-mob">
                            <label>Forma de Pagamento <span style="color:#ef4444;">*</span></label>
                            <div class="vp-pag-grid-mob" style="margin-top:8px;">
                                <div class="vp-pag-pill"><input type="radio" name="mob_pag" id="mp-pix"      value="PIX"><label for="mp-pix">PIX</label></div>
                                <div class="vp-pag-pill"><input type="radio" name="mob_pag" id="mp-credito"  value="Cartao de Credito"><label for="mp-credito">Crédito</label></div>
                                <div class="vp-pag-pill"><input type="radio" name="mob_pag" id="mp-debito"   value="Cartao de Debito"><label for="mp-debito">Débito</label></div>
                                <div class="vp-pag-pill"><input type="radio" name="mob_pag" id="mp-dinheiro" value="Dinheiro"><label for="mp-dinheiro">Dinheiro</label></div>
                            </div>
                        </div>
                        <div class="vp-field-mob"><label>CPF do Cliente <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></label><input type="text" id="mob-cpf" placeholder="000.000.000-00" maxlength="14" autocomplete="off"></div>
                        <div class="vp-field-mob"><label>Observação <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></label><textarea id="mob-obs" placeholder="Ex: Película instalada..."></textarea></div>
                        <div class="vp-btn-row">
                            <button class="vp-btn-sec"    onclick="voltarPasso1()">Voltar</button>
                            <button class="vp-btn-cancel" onclick="cancelarTudo()">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- PASSO 3 -->
        <div id="passo3" style="display:none;">
            <div class="vp-section">
                <div class="vp-card">
                    <div class="vp-card-head"><h2>Revisar e Confirmar</h2></div>
                    <div class="vp-card-body">
                        <div class="vp-resumo-mob">
                            <div id="mob-resumo-itens"></div>
                            <hr class="vp-resumo-sep">
                            <div class="vp-resumo-row"><span class="vp-resumo-label">Pagamento</span><span class="vp-resumo-val" id="mob-resumo-pag">—</span></div>
                            <div class="vp-resumo-row" id="mob-resumo-cpf-row" style="display:none;"><span class="vp-resumo-label">CPF</span><span class="vp-resumo-val" id="mob-resumo-cpf">—</span></div>
                            <div class="vp-resumo-row" id="mob-resumo-obs-row" style="display:none;"><span class="vp-resumo-label">Obs</span><span class="vp-resumo-val" id="mob-resumo-obs" style="max-width:55%;text-align:right;font-size:12px;">—</span></div>
                            <div class="vp-resumo-total-mob"><span>TOTAL</span><span id="mob-resumo-total">—</span></div>
                        </div>
                        <form method="POST" id="mob-form">
                            <input type="hidden" name="forma_pagamento" id="mob-hid-pag">
                            <input type="hidden" name="cpf"             id="mob-hid-cpf">
                            <input type="hidden" name="observacao"      id="mob-hid-obs">
                            <input type="hidden" name="itens_json"      id="mob-hid-itens">
                            <button type="submit" class="vp-btn-confirmar-mob">Confirmar Venda</button>
                        </form>
                        <div class="vp-btn-row" style="margin-top:12px;">
                            <button class="vp-btn-sec"    onclick="voltarPasso2()">Voltar</button>
                            <button class="vp-btn-cancel" onclick="cancelarTudo()">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /vp-wrap -->

    <div class="vp-fab" id="vp-fab">
        <button class="vp-fab-btn" onclick="fabAcao()"><span id="vp-fab-txt">Avançar para Pagamento </span></button>
    </div>
</div><!-- /vp-mobile -->

<script>
/* ════════════════════
   ESTADO GLOBAL
════════════════════ */
let carrinho  = [];
let debTimer, debDeskTimer;
let historico = [];
let passoAtual = 1;

/* ════════════════════
   BARCODE
════════════════════ */
let barcodeBuffer = '', barcodeTimer = null;
document.addEventListener('keydown', function(e) {
    const tag = document.activeElement?.tagName || '';
    const id  = document.activeElement?.id      || '';
    if ((tag==='INPUT'||tag==='TEXTAREA') && ['mob-search','mob-cpf','mob-obs','desk-search','desk-cpf','desk-obs'].includes(id)) return;
    if (e.key === 'Enter') {
        if (barcodeBuffer.length > 2) processarBarcode(barcodeBuffer.trim());
        barcodeBuffer = ''; clearTimeout(barcodeTimer); return;
    }
    if (e.key.length === 1) {
        barcodeBuffer += e.key;
        clearTimeout(barcodeTimer);
        barcodeTimer = setTimeout(() => { if (barcodeBuffer.length > 2) processarBarcode(barcodeBuffer.trim()); barcodeBuffer = ''; }, 80);
    }
});

async function processarBarcode(codigo) {
    mostrarFeedback('Buscando ' + codigo + '...', 'ok');
    try {
        const r    = await fetch('venda_presencial.php?barcode=' + encodeURIComponent(codigo));
        const prod = await r.json();
        if (prod?.id_produto) {
            adicionarAoCarrinho(prod);
            mostrarFeedback('✓ ' + prod.nome + ' adicionado!', 'ok');
            setTimeout(ocultarFeedback, 2000);
        } else {
            mostrarFeedback('Código ' + codigo + ' não encontrado.', 'err');
            setTimeout(ocultarFeedback, 3000);
        }
    } catch(e) { mostrarFeedback('Erro ao consultar código.', 'err'); setTimeout(ocultarFeedback, 3000); }
}

function mostrarFeedback(txt, tipo) {
    ['desk-feedback','mob-feedback'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.textContent = txt; el.className = 'vp-feedback show ' + tipo; }
    });
}
function ocultarFeedback() {
    ['desk-feedback','mob-feedback'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.className = 'vp-feedback';
    });
}

/* ════════════════════
   BUSCA DESKTOP
════════════════════ */
document.getElementById('desk-search').addEventListener('input', function() {
    clearTimeout(debDeskTimer);
    const q = this.value.trim();
    if (q.length < 2) { fecharResultsDesk(); return; }
    debDeskTimer = setTimeout(() => buscarDesk(q), 280);
});

async function buscarDesk(q) {
    try {
        const items = await (await fetch('venda_presencial.php?buscar=' + encodeURIComponent(q))).json();
        renderResultsDesk(items);
    } catch(e) {}
}

function renderResultsDesk(items) {
    const box = document.getElementById('desk-results');
    box.innerHTML = !items.length
        ? '<div class="vp-no-res">Nenhum produto encontrado</div>'
        : items.map(p => `<div class="vp-res-item" onclick='adicionarAoCarrinho(${JSON.stringify(p).replace(/'/g,"&#39;")});fecharResultsDesk();document.getElementById("desk-search").value=""'>
            <img class="vp-res-thumb" src="uploads/${p.imagem||'placeholder.jpg'}" onerror="this.src='uploads/placeholder.jpg'">
            <div style="flex:1;min-width:0;"><div class="vp-res-nome">${p.nome}</div><div class="vp-res-sub">${[p.marca,p.modelo].filter(Boolean).join(' · ')}</div></div>
            <div class="vp-res-preco">R$ ${fmt(p.preco)}</div>
          </div>`).join('');
    box.classList.add('show');
}
function fecharResultsDesk() { document.getElementById('desk-results').classList.remove('show'); }

/* ════════════════════
   BUSCA MOBILE
════════════════════ */
document.getElementById('mob-search').addEventListener('input', function() {
    clearTimeout(debTimer);
    const q = this.value.trim();
    if (q.length < 2) { fecharResultsMob(); return; }
    debTimer = setTimeout(() => buscarMob(q), 280);
});
async function buscarMob(q) {
    try { renderResultsMob(await (await fetch('venda_presencial.php?buscar=' + encodeURIComponent(q))).json()); } catch(e) {}
}
function renderResultsMob(items) {
    const box = document.getElementById('mob-results');
    box.innerHTML = !items.length
        ? '<div class="vp-no-res">Nenhum produto encontrado</div>'
        : items.map(p => `<div class="vp-res-item" onclick='adicionarAoCarrinho(${JSON.stringify(p).replace(/'/g,"&#39;")});fecharResultsMob();document.getElementById("mob-search").value=""'>
            <img class="vp-res-thumb" src="uploads/${p.imagem||'placeholder.jpg'}" onerror="this.src='uploads/placeholder.jpg'">
            <div style="flex:1;min-width:0;"><div class="vp-res-nome">${p.nome}</div><div class="vp-res-sub">${[p.marca,p.modelo].filter(Boolean).join(' · ')}</div></div>
            <div class="vp-res-preco">R$ ${fmt(p.preco)}</div>
          </div>`).join('');
    box.classList.add('show');
}
function fecharResultsMob() { document.getElementById('mob-results').classList.remove('show'); }

document.addEventListener('click', e => {
    if (!e.target.closest('#desk-search') && !e.target.closest('#desk-results')) fecharResultsDesk();
    if (!e.target.closest('#mob-search')  && !e.target.closest('#mob-results'))  fecharResultsMob();
});

/* ════════════════════
   GRID DE PRODUTOS DESKTOP
════════════════════ */
async function carregarProdutosDesk() {
    const grid = document.getElementById('desk-prod-grid');
    grid.innerHTML = '<div class="vp-grid-loading">Carregando...</div>';
    try {
        const prods = await (await fetch('venda_presencial.php?buscar= ')).json();
        // Fallback: se não retornar nada, busca com query genérica
        renderProdGrid(prods.length ? prods : []);
    } catch(e) { grid.innerHTML = '<div class="vp-grid-loading">Erro ao carregar produtos.</div>'; }
}

// Busca inicial com todos os produtos (query vazia retorna top 8, mas vamos usar 'a' para pegar mais)
async function carregarTodosProdutos() {
    const grid = document.getElementById('desk-prod-grid');
    try {
        // Busca com letra mais comum para pegar produtos
        const r = await fetch('venda_presencial.php?buscar=a');
        const prods = await r.json();
        renderProdGrid(prods);
        const cnt = document.getElementById('desk-prod-count');
        if (cnt) cnt.textContent = prods.length + ' produto(s)';
    } catch(e) {}
}

function renderProdGrid(prods) {
    const grid = document.getElementById('desk-prod-grid');
    if (!prods.length) { grid.innerHTML = '<div class="vp-grid-loading">Nenhum produto encontrado.</div>'; return; }
    grid.innerHTML = prods.map(p => `
        <div class="vp-prod-card">
            <div class="vp-prod-info">
                <div class="vp-prod-nome">${p.nome}</div>
                <div class="vp-prod-preco">R$ ${fmt(p.preco)}</div>
            </div>
            <button class="vp-prod-btn" onclick='adicionarAoCarrinho(${JSON.stringify(p).replace(/'/g,"&#39;")})'>+ Adicionar</button>
        </div>`).join('');
}

// Busca em tempo real no desktop atualiza o grid
document.getElementById('desk-search').addEventListener('input', function() {
    clearTimeout(debDeskTimer);
    const q = this.value.trim();
    fecharResultsDesk();
    if (q.length >= 2) {
        debDeskTimer = setTimeout(async () => {
            try {
                const prods = await (await fetch('venda_presencial.php?buscar=' + encodeURIComponent(q))).json();
                renderProdGrid(prods);
                const cnt = document.getElementById('desk-prod-count');
                if (cnt) cnt.textContent = prods.length + ' resultado(s)';
            } catch(e) {}
        }, 280);
    } else if (q.length === 0) {
        carregarTodosProdutos();
    }
});
<?php if ($tipo === 'success' && $comprovante): ?>
window.addEventListener('load', function() {
    setTimeout(function() { imprimirComprovante(); }, 400);
});
<?php endif; ?>
/* ════════════════════
   CARRINHO
════════════════════ */
function adicionarAoCarrinho(prod) {
    const idx = carrinho.findIndex(i => i.id_produto == prod.id_produto);
    if (idx >= 0) {
        if (carrinho[idx].quantidade < carrinho[idx].estoque) carrinho[idx].quantidade++;
        else { mostrarFeedback('Estoque máximo atingido!', 'err'); setTimeout(ocultarFeedback, 2500); return; }
    } else {
        carrinho.push({ ...prod, quantidade: 1 });
    }
    renderCarrinho();
}

function removerDoCarrinho(id) { carrinho = carrinho.filter(i => i.id_produto != id); renderCarrinho(); }

function alterarQty(id, delta) {
    const idx = carrinho.findIndex(i => i.id_produto == id);
    if (idx < 0) return;
    const nova = carrinho[idx].quantidade + delta;
    if (nova < 1) { removerDoCarrinho(id); return; }
    if (nova > carrinho[idx].estoque) return;
    carrinho[idx].quantidade = nova;
    renderCarrinho();
}

function limparCarrinho() {
    if (!carrinho.length) return;
    if (!confirm('Limpar todos os itens?')) return;
    carrinho = []; renderCarrinho();
}

function totalCarrinho() { return carrinho.reduce((s,i) => s + parseFloat(i.preco) * i.quantidade, 0); }
function fmt(n) { return parseFloat(n).toLocaleString('pt-BR',{minimumFractionDigits:2}); }

function renderCarrinho() {
    const total = totalCarrinho();
    const qtd   = carrinho.reduce((s,i) => s+i.quantidade, 0);

    // Badges
    ['desk-badge','mob-badge'].forEach(id => { const el=document.getElementById(id); if(el) el.textContent=qtd; });

    // Mobile FAB
    const fab = document.getElementById('vp-fab');
    if (fab) fab.className = carrinho.length > 0 && passoAtual === 1 ? 'vp-fab show' : 'vp-fab';

    // Atualiza resumo desktop em tempo real
    atualizarResumoDesk();

    const itemsHtml = !carrinho.length
        ? '<div class="vp-cart-vazio">Nenhum produto ainda.</div>'
        : carrinho.map(item => `
            <div class="vp-cart-item">
                <div class="vp-ci-info">
                    <div class="vp-ci-nome">${item.nome}</div>
                    <div class="vp-ci-bottom">
                        <div class="vp-qty-ctrl">
                            <button class="vp-qty-btn" onclick="alterarQty(${item.id_produto},-1)">&#8722;</button>
                            <span class="vp-qty-num">${item.quantidade}</span>
                            <button class="vp-qty-btn" onclick="alterarQty(${item.id_produto},+1)">+</button>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span class="vp-ci-preco">R$ ${fmt(parseFloat(item.preco)*item.quantidade)}</span>
                            <button class="vp-ci-del" onclick="removerDoCarrinho(${item.id_produto})">&#10005;</button>
                        </div>
                    </div>
                </div>
            </div>`).join('')
          + `<div style="display:flex;justify-content:space-between;font-size:14px;font-weight:800;color:#065f46;padding:10px 0 0;border-top:1.5px solid #d1fae5;margin-top:6px;"><span>Total</span><span>R$ ${fmt(total)}</span></div>`;

    // Desktop cart
    const dc = document.getElementById('desk-cart-body');
    if (dc) dc.innerHTML = itemsHtml;

    // Mobile cart (passo 1)
    const mc = document.getElementById('mob-cart-body');
    if (mc) mc.innerHTML = itemsHtml.replace(/class="vp-cart-item"/g, 'class="vp-cart-item-mob"').replace(/class="vp-ci-thumb"/g, 'class="vp-ci-thumb"');
}

/* ════════════════════
   RESUMO DESKTOP (tempo real)
════════════════════ */
function atualizarResumoDesk() {
    const resumo = document.getElementById('desk-resumo');
    if (!resumo) return;
    if (!carrinho.length) { resumo.style.display = 'none'; return; }
    resumo.style.display = 'block';
    const total = totalCarrinho();
    let itensHtml = '';
    carrinho.forEach(item => {
        itensHtml += `<div class="vp-resumo-linha"><span>${item.quantidade}x ${item.nome}</span><span>R$ ${fmt(parseFloat(item.preco)*item.quantidade)}</span></div>`;
    });
    document.getElementById('desk-resumo-itens').innerHTML = itensHtml;
    document.getElementById('desk-resumo-total').textContent = 'R$ ' + fmt(total);
    const pag = document.querySelector('input[name="desk_pag"]:checked');
    const pagEl = document.getElementById('desk-resumo-pag');
    if (pagEl) pagEl.textContent = pag ? pag.value : '—';
}

// Atualiza resumo quando muda pagamento
document.querySelectorAll('input[name="desk_pag"]').forEach(r => r.addEventListener('change', atualizarResumoDesk));

/* ════════════════════
   CONFIRMAR DESKTOP
════════════════════ */
function deskConfirmar() {
    if (!carrinho.length) { alert('Adicione ao menos um produto.'); return; }
    const pag = document.querySelector('input[name="desk_pag"]:checked');
    if (!pag) { alert('Selecione a forma de pagamento.'); return; }
    const cpf = document.getElementById('desk-cpf').value.trim();
    const obs = document.getElementById('desk-obs').value.trim();
    if (!confirm('Confirmar venda de R$ ' + fmt(totalCarrinho()) + ' — ' + pag.value + '?')) return;
    document.getElementById('desk-hid-pag').value   = pag.value;
    document.getElementById('desk-hid-cpf').value   = cpf;
    document.getElementById('desk-hid-obs').value   = obs;
    document.getElementById('desk-hid-itens').value = JSON.stringify(carrinho.map(i=>({id_produto:i.id_produto,quantidade:i.quantidade})));
    document.getElementById('desk-form').submit();
}

/* ════════════════════
   STEPS MOBILE
════════════════════ */
function setStep(n) {
    passoAtual = n;
    for (let i=1; i<=3; i++) {
        const dot=document.getElementById('s'+i), lbl=document.getElementById('sl'+i);
        if (!dot) continue;
        if (i<n)       { dot.className='vp-step-dot done';   dot.innerHTML=''; lbl.className='vp-step-lbl'; }
        else if (i===n){ dot.className='vp-step-dot active'; dot.textContent=i;        lbl.className='vp-step-lbl active'; }
        else           { dot.className='vp-step-dot idle';   dot.textContent=i;        lbl.className='vp-step-lbl'; }
        if (i<3) { const sep=document.getElementById('sep'+i); if(sep) sep.className='vp-step-line'+(n>i?' done':''); }
    }
}

/* ════════════════════
   NAVEGAÇÃO MOBILE
════════════════════ */
function fabAcao() { if (passoAtual===1) avancarPasso2(); else if (passoAtual===2) avancarPasso3(); }

function avancarPasso2() {
    if (!carrinho.length) { alert('Adicione ao menos um produto.'); return; }
    document.getElementById('passo1').style.display='none';
    document.getElementById('passo2').style.display='block';
    const fab=document.getElementById('vp-fab'); if(fab){fab.className='vp-fab show';}
    document.getElementById('vp-fab-txt').textContent='Avançar para Revisão →';
    setStep(2); window.scrollTo({top:0,behavior:'smooth'});
}
function voltarPasso1() {
    document.getElementById('passo2').style.display='none';
    document.getElementById('passo1').style.display='block';
    document.getElementById('vp-fab-txt').textContent='Avançar para Pagamento →';
    renderCarrinho(); setStep(1); window.scrollTo({top:0,behavior:'smooth'});
}
function avancarPasso3() {
    const pag = document.querySelector('input[name="mob_pag"]:checked');
    if (!pag) { alert('Selecione a forma de pagamento.'); return; }
    const cpf=document.getElementById('mob-cpf').value.trim(), obs=document.getElementById('mob-obs').value.trim(), total=totalCarrinho();
    let itensHtml='';
    carrinho.forEach(item=>{ itensHtml+=`<div class="vp-resumo-linha"><span>${item.quantidade}x ${item.nome}</span><span>R$ ${fmt(parseFloat(item.preco)*item.quantidade)}</span></div>`; });
    document.getElementById('mob-resumo-itens').innerHTML=itensHtml;
    document.getElementById('mob-resumo-pag').textContent=pag.value;
    document.getElementById('mob-resumo-total').textContent='R$ '+fmt(total);
    const cpfRow=document.getElementById('mob-resumo-cpf-row'), obsRow=document.getElementById('mob-resumo-obs-row');
    if(cpf){document.getElementById('mob-resumo-cpf').textContent=cpf;cpfRow.style.display='flex';}else cpfRow.style.display='none';
    if(obs){document.getElementById('mob-resumo-obs').textContent=obs;obsRow.style.display='flex';}else obsRow.style.display='none';
    document.getElementById('mob-hid-pag').value=pag.value;
    document.getElementById('mob-hid-cpf').value=cpf;
    document.getElementById('mob-hid-obs').value=obs;
    document.getElementById('mob-hid-itens').value=JSON.stringify(carrinho.map(i=>({id_produto:i.id_produto,quantidade:i.quantidade})));
    document.getElementById('passo2').style.display='none';
    document.getElementById('passo3').style.display='block';
    const fab=document.getElementById('vp-fab'); if(fab) fab.className='vp-fab';
    setStep(3); window.scrollTo({top:0,behavior:'smooth'});
}
function voltarPasso2() {
    document.getElementById('passo3').style.display='none';
    document.getElementById('passo2').style.display='block';
    const fab=document.getElementById('vp-fab'); if(fab){fab.className='vp-fab show';}
    document.getElementById('vp-fab-txt').textContent='Avançar para Revisão →';
    setStep(2); window.scrollTo({top:0,behavior:'smooth'});
}
function cancelarTudo() {
    if (!confirm('Cancelar a venda?')) return;
    carrinho=[]; renderCarrinho();
    ['mob-search','mob-cpf','mob-obs'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    document.querySelectorAll('input[name="mob_pag"]').forEach(r=>r.checked=false);
    document.getElementById('passo1').style.display='block';
    document.getElementById('passo2').style.display='none';
    document.getElementById('passo3').style.display='none';
    setStep(1); window.scrollTo({top:0,behavior:'smooth'});
}

/* ════════════════════
   CPF MASK
════════════════════ */
function cpfMask(el) {
    let v = el.value.replace(/\D/g,'').slice(0,11);
    v = v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    el.value = v;
}
document.getElementById('mob-cpf').addEventListener('input', function(){ cpfMask(this); });
document.getElementById('desk-cpf').addEventListener('input', function(){ cpfMask(this); });

/* ════════════════════
   HISTÓRICO
════════════════════ */
function renderHistorico() {
    try { const s=sessionStorage.getItem('vp_hist'); if(s) historico=JSON.parse(s); } catch(e) {}
    const html = !historico.length
        ? '<div class="vp-hist-empty">Nenhuma venda registrada.</div>'
        : historico.map(h=>`<div class="vp-hist-item"><div class="vp-hist-icon"></div><div class="vp-hist-nome">${h.nome}</div><div class="vp-hist-qty">${h.itens} item(ns)</div><div class="vp-hist-time">${h.hora}</div></div>`).join('');
    ['desk-hist-body','mob-hist-body'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=html;});
}
renderHistorico();

<?php if ($tipo === 'success'): ?>
try {
    const hist = JSON.parse(sessionStorage.getItem('vp_hist')||'[]');
    hist.unshift({ nome:'Pedido #<?= $comprovante['id_pedido'] ?>', itens:<?= count($comprovante['itens']) ?>, hora:new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}) });
    sessionStorage.setItem('vp_hist', JSON.stringify(hist.slice(0,20)));
    renderHistorico();
} catch(e) {}
<?php endif; ?>

/* ════════════════════
   IMPRIMIR
════════════════════ */
function imprimirComprovante() {
    const cp = document.getElementById('vp-comprovante-print');
    if (!cp) return;
    const janela = window.open('','_blank','width=400,height=600');
    janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:80mm auto;margin:0;}*{box-sizing:border-box;margin:0;padding:0;}body{font-family:"Courier New",monospace;font-size:11px;color:#000;background:#fff;padding:4mm;width:80mm;}.cp-empresa{text-align:center;font-weight:bold;font-size:14px;margin-bottom:2px;}.cp-cnpj{text-align:center;font-size:11px;margin-bottom:2px;}.cp-endereco{text-align:center;font-size:10px;margin-bottom:2px;}.cp-fone{text-align:center;font-size:10px;margin-bottom:4px;}.cp-linha-dupla{border-top:2px solid #000;margin:5px 0;}.cp-linha{border-top:1px dashed #888;margin:5px 0;}.cp-titulo{text-align:center;font-weight:bold;font-size:12px;letter-spacing:2px;margin:6px 0 4px;}.cp-info{display:flex;justify-content:space-between;font-size:11px;margin:3px 0;}.cp-produto-nome{font-weight:bold;font-size:11px;margin:4px 0 2px;}.cp-produto-detalhe{display:flex;justify-content:space-between;font-size:11px;color:#333;padding-left:4px;}.cp-obs{font-size:10px;color:#444;margin:3px 0;word-break:break-word;}.cp-total-row{display:flex;justify-content:space-between;font-weight:bold;font-size:14px;margin:5px 0 3px;}.cp-rodape{text-align:center;font-size:10px;color:#666;margin-top:10px;line-height:1.5;}</style></head><body>'+cp.innerHTML+'</body></html>');
    janela.document.close(); janela.focus();
    janela.onload = function(){ janela.print(); };
}

/* ════════════════════
   INIT
════════════════════ */
carregarTodosProdutos();
</script>
</body>
</html>
