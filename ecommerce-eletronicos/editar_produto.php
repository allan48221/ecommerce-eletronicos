<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) { header('Location: login.php'); exit; }
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header('Location: admin.php'); exit; }

$id_produto    = intval($_GET['id']);
$mensagem      = '';
$tipo_mensagem = '';

// Endpoint AJAX: incrementar estoque via barcode
if (isset($_POST['action']) && $_POST['action'] === 'incrementar_estoque') {
    header('Content-Type: application/json');
    $codigo    = trim($_POST['codigo_barras'] ?? '');
    $quantidade = intval($_POST['quantidade'] ?? 1);
    if (empty($codigo) || $quantidade < 1) { echo json_encode(['ok' => false, 'msg' => 'Dados invalidos.']); exit; }
    $stmt = $conn->prepare("SELECT id_produto, nome, estoque FROM produtos WHERE codigo_barras = ? AND id_produto = ?");
    $stmt->execute([$codigo, $id_produto]);
    $prod = $stmt->fetch();
    if (!$prod) { echo json_encode(['ok' => false, 'msg' => 'Codigo de barras nao corresponde a este produto.']); exit; }
    $conn->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id_produto = ?")->execute([$quantidade, $id_produto]);
    $novo_estoque = $prod['estoque'] + $quantidade;
    echo json_encode(['ok' => true, 'novo_estoque' => $novo_estoque, 'msg' => "Estoque atualizado: +{$quantidade} unidade(s). Total: {$novo_estoque}"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome         = trim($_POST['nome']);
    $descricao    = trim($_POST['descricao']);
    $marca        = trim($_POST['marca']);
    $modelo       = trim($_POST['modelo']);
    $preco = !empty($_POST['preco']) 
    ? floatval($_POST['preco'])
    : null;
    $preco_promo = !empty($_POST['preco_promocional']) 
    ? floatval($_POST['preco_promocional'])
    : null;
    $estoque      = intval($_POST['estoque']);
    $id_categoria = intval($_POST['id_categoria']);
    $destaque     = isset($_POST['destaque']) ? 1 : 0;
    $ativo        = isset($_POST['ativo']) ? 1 : 0;
    $id_img_principal = intval($_POST['id_img_principal'] ?? 0);
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');

   if (empty($nome)) {
    $mensagem = "Preencha todos os campos obrigatorios!";
    $tipo_mensagem = "danger";
}

    if (empty($mensagem)) {
        $stmt = $conn->prepare("UPDATE produtos SET nome=?,descricao=?,marca=?,modelo=?,preco=?,preco_promocional=?,estoque=?,id_categoria=?,destaque=?,ativo=?,codigo_barras=? WHERE id_produto=?");
        $stmt->execute([$nome,$descricao,$marca,$modelo,$preco,$preco_promo,$estoque,$id_categoria,$destaque,$ativo,$codigo_barras ?: null,$id_produto]);

        if (!empty($_FILES['novas_imagens']['name'][0])) {
            foreach ($_FILES['novas_imagens']['tmp_name'] as $k => $tmp) {
                if ($_FILES['novas_imagens']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['novas_imagens']['name'][$k], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp','avif'])) continue;
                $nome_arquivo = uniqid().'_'.time().'.'.$ext;
                if (move_uploaded_file($tmp, 'uploads/'.$nome_arquivo)) {
                    $conn->prepare("INSERT INTO produto_imagens (id_produto,imagem) VALUES (?,?)")->execute([$id_produto,$nome_arquivo]);
                }
            }
        }

        $deletar = $_POST['deletar_imgs'] ?? [];
        foreach ($deletar as $id_img) {
            $id_img = intval($id_img);
            $r = $conn->prepare("SELECT imagem FROM produto_imagens WHERE id=? AND id_produto=?");
            $r->execute([$id_img, $id_produto]);
            $img = $r->fetchColumn();
            if ($img && file_exists('uploads/'.$img)) @unlink('uploads/'.$img);
            $conn->prepare("DELETE FROM produto_imagens WHERE id=? AND id_produto=?")->execute([$id_img,$id_produto]);
        }

        if ($id_img_principal > 0) {
            $r = $conn->prepare("SELECT imagem FROM produto_imagens WHERE id=? AND id_produto=?");
            $r->execute([$id_img_principal, $id_produto]);
            $img_principal = $r->fetchColumn();
            if ($img_principal) {
                $conn->prepare("UPDATE produtos SET imagem=? WHERE id_produto=?")->execute([$img_principal,$id_produto]);
            }
        } else {
            $r = $conn->prepare("SELECT imagem FROM produto_imagens WHERE id_produto=? LIMIT 1");
            $r->execute([$id_produto]);
            $first = $r->fetchColumn();
            if ($first) $conn->prepare("UPDATE produtos SET imagem=? WHERE id_produto=?")->execute([$first,$id_produto]);
        }

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'replace_img_') !== 0) continue;
            $id_img = intval(str_replace('replace_img_', '', $key));
            if ($file['error'] !== UPLOAD_ERR_OK || $id_img <= 0) continue;
            $r = $conn->prepare("SELECT imagem FROM produto_imagens WHERE id=? AND id_produto=?");
            $r->execute([$id_img, $id_produto]);
            $nome_atual = $r->fetchColumn();
            if (!$nome_atual) continue;
            move_uploaded_file($file['tmp_name'], 'uploads/'.$nome_atual);
        }

        $mensagem = "Produto atualizado com sucesso!";
        $tipo_mensagem = "success";
    }
}

$stmt = $conn->prepare("SELECT p.*,c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.id_categoria=c.id_categoria WHERE p.id_produto=?");
$stmt->execute([$id_produto]);
$produto = $stmt->fetch();
if (!$produto) { header('Location: admin.php'); exit; }

$stmt_imgs = $conn->prepare("SELECT * FROM produto_imagens WHERE id_produto=? ORDER BY id ASC");
$stmt_imgs->execute([$id_produto]);
$imagens = $stmt_imgs->fetchAll(PDO::FETCH_ASSOC);

$result_categorias = $conn->query("SELECT * FROM categorias WHERE ativo=TRUE ORDER BY nome");

// Formata os precos ja salvos no formato de mascara "1.234,56"
$preco_formatado       = ($produto['preco'] > 0)             ? number_format($produto['preco'], 2, ',', '.')             : '0,00';
$preco_promo_formatado = ($produto['preco_promocional'] > 0) ? number_format($produto['preco_promocional'], 2, ',', '.') : '0,00';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>Editar Produto</title>
<link rel="stylesheet" href="css/style.css">
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
.container{background:transparent!important;max-width:100%!important;padding:0!important;margin:0!important;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --brand:var(--primary,#2563eb);--brand-dark:var(--primary-dark,#1e40af);
    --surface:#fff;--surface-2:#f8fafc;--border:#e2e8f0;
    --text:#0f172a;--text-muted:#64748b;
    --clr-danger:#ef4444;--clr-success:#10b981;
    --radius:14px;--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 16px rgba(0,0,0,.08);
}
body{font-family:'Sora',sans-serif;background:#f1f5f9;color:var(--text);min-height:100vh;}
.page-body{max-width:980px;margin:30px auto 60px;padding:0 24px;}
.page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem;}
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#fff;color:#475569;border:1.5px solid #e2e8f0;border-radius:50px;font-size:13px;font-weight:600;font-family:'Sora',sans-serif;text-decoration:none;transition:background .2s,border-color .2s;}
.btn-back:hover{background:#f1f5f9;border-color:#cbd5e1;}
.ep-alert{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:10px;font-size:14px;font-weight:500;margin-bottom:22px;border-left:4px solid transparent;animation:slideDown .3s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1}}
.ep-alert.success{background:#ecfdf5;color:#065f46;border-color:var(--clr-success);}
.ep-alert.danger{background:#fef2f2;color:#7f1d1d;border-color:var(--clr-danger);}
.ep-alert svg{width:18px;height:18px;flex-shrink:0;}
.ep-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.ep-grid .span-2{grid-column:1/-1;}
.ep-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;transition:box-shadow .2s;}
.ep-card:hover{box-shadow:var(--shadow-md);}
.ep-card-head{display:flex;align-items:center;gap:10px;padding:15px 22px;border-bottom:1px solid var(--border);background:var(--surface-2);}
.ep-card-icon{width:32px;height:32px;background:var(--brand);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ep-card-icon svg{width:16px;height:16px;color:#fff;}
.ep-card-head h2{font-size:12px;font-weight:700;color:var(--text);letter-spacing:.8px;text-transform:uppercase;}
.ep-card-body{padding:22px;}
.ep-field{margin-bottom:18px;}
.ep-field:last-child{margin-bottom:0;}
.ep-field label{display:block;font-size:11.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:7px;}
.ep-field label .req{color:var(--clr-danger);margin-left:2px;}
.ep-field input[type="text"],.ep-field input[type="number"],.ep-field textarea,.ep-field select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;font-family:'Sora',sans-serif;background:var(--surface);color:var(--text);transition:border-color .2s,box-shadow .2s;}
.ep-field input:focus,.ep-field textarea:focus,.ep-field select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12);outline:none;}
.ep-field textarea{min-height:110px;resize:vertical;line-height:1.6;}
.ep-input-prefix{position:relative;}
.ep-input-prefix span{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:13px;font-weight:600;color:var(--text-muted);pointer-events:none;}
.ep-input-prefix input{padding-left:36px;}

/* ── Campo de preco estilo banco ── */
.ep-preco-wrap { position: relative; }
.ep-preco-wrap .ep-preco-rs {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    font-size: 13px; font-weight: 700; color: var(--text-muted); pointer-events: none;
}
.ep-preco-wrap input {
    padding-left: 34px !important;
    font-size: 1rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.5px;
}

.ep-stock-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:700;margin-left:8px;vertical-align:middle;}
.ep-stock-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.7;}
.ep-stock-badge.ok{background:#dcfce7;color:#166534;}
.ep-stock-badge.low{background:#fef9c3;color:#854d0e;}
.ep-stock-badge.zero{background:#fee2e2;color:#991b1b;}
.ep-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);}
.ep-toggle-row:last-of-type{border-bottom:none;padding-bottom:0;}
.ep-toggle-info strong{display:block;font-size:14px;font-weight:600;color:var(--text);}
.ep-toggle-info small{font-size:12px;color:var(--text-muted);margin-top:2px;display:block;}
.ep-switch{position:relative;width:48px;height:28px;flex-shrink:0;}
.ep-switch input{opacity:0;width:0;height:0;}
.ep-slider{position:absolute;inset:0;background:#cbd5e1;border-radius:28px;cursor:pointer;transition:background .3s;}
.ep-slider::before{content:'';position:absolute;width:22px;height:22px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .3s;box-shadow:0 1px 4px rgba(0,0,0,.2);}
.ep-switch input:checked+.ep-slider{background:var(--brand);}
.ep-switch input:checked+.ep-slider::before{transform:translateX(20px);}
.ep-save-wrap{padding:20px 22px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.btn-save{flex:1;min-width:200px;padding:15px 24px;background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 4px 16px rgba(37,99,235,.35);transition:box-shadow .2s,transform .15s,opacity .2s;}
.btn-save:hover{box-shadow:0 6px 22px rgba(37,99,235,.45);transform:translateY(-1px);}
.btn-save:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-save svg{width:18px;height:18px;}
.save-hint{font-size:12px;color:var(--text-muted);line-height:1.5;}
@keyframes spin{to{transform:rotate(360deg)}}
.barcode-input-wrap { position: relative; }
.barcode-input-wrap input { padding-right: 44px !important; letter-spacing: 1px; }
.barcode-input-icon { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); font-size: 20px; pointer-events: none; opacity: 0.4; }
.barcode-hint-text { font-size: 11px; color: var(--text-muted); margin-top: 5px; }
.remessa-card { background: #f0fdf4; border: 2px solid #a7f3d0; border-radius: 14px; padding: 18px 20px; margin-top: 20px; }
.remessa-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #065f46; margin-bottom: 14px; display: flex; align-items: center; gap: 7px; }
.remessa-status { display: none; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-top: 12px; animation: slideDown .25s ease; }
.remessa-status.show { display: flex; }
.remessa-status.ok   { background: #dcfce7; color: #065f46; }
.remessa-status.err  { background: #fee2e2; color: #991b1b; }
.remessa-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.remessa-row .ep-field { margin-bottom: 0; flex: 1; min-width: 120px; }
.btn-remessa { padding: 11px 20px; background: linear-gradient(135deg, #059669, #047857); color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; white-space: nowrap; transition: opacity .2s, transform .15s; flex-shrink: 0; height: 44px; }
.btn-remessa:hover { opacity: .9; transform: translateY(-1px); }
.remessa-barcode-wrap { display: flex; align-items: center; gap: 10px; background: #ecfdf5; border: 2px dashed #6ee7b7; border-radius: 12px; padding: 12px 16px; margin-bottom: 14px; transition: border-color .2s; }
.remessa-barcode-wrap.listening { border-color: #059669; animation: pulse-border 1.2s infinite; }
@keyframes pulse-border { 0%,100% { box-shadow: 0 0 0 0 rgba(5,150,105,0); } 50% { box-shadow: 0 0 0 6px rgba(5,150,105,.15); } }
.remessa-barcode-icon { font-size: 22px; flex-shrink: 0; }
.remessa-barcode-info { flex: 1; min-width: 0; }
.remessa-barcode-title { font-size: 13px; font-weight: 700; color: #065f46; }
.remessa-barcode-sub   { font-size: 11px; color: #6b7280; margin-top: 2px; }
.img-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px; }
.img-card { position: relative; border-radius: 10px; border: 2px solid var(--border); overflow: visible; transition: border-color .2s, box-shadow .2s; background: var(--surface-2); }
.img-card.is-principal { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
.img-card.marcada-deletar { opacity: .45; filter: grayscale(1); }
.img-card img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; border-radius: 8px 8px 0 0; }
.img-card-actions { display: flex; gap: 4px; padding: 6px; background: var(--surface); border-top: 1px solid var(--border); border-radius: 0 0 8px 8px; justify-content: center; }
.img-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; transition: background .15px, transform .1s; flex-shrink: 0; }
.img-btn:active { transform: scale(.92); }
.img-btn-crop { background: #eff6ff; color: var(--brand); }
.img-btn-crop:hover { background: #dbeafe; }
.img-btn-star { background: #fefce8; color: #ca8a04; }
.img-btn-star:hover { background: #fef08a; }
.img-btn-del { background: #fef2f2; color: var(--clr-danger); }
.img-btn-del:hover { background: #fee2e2; }
.badge-principal { position: absolute; top: -8px; left: 50%; transform: translateX(-50%); background: var(--brand); color: #fff; font-size: 9px; font-weight: 700; letter-spacing: .5px; padding: 2px 8px; border-radius: 20px; white-space: nowrap; text-transform: uppercase; box-shadow: 0 2px 6px rgba(37,99,235,.4); pointer-events: none; }
.add-img-area { border: 2px dashed #bfdbfe; border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; background: #eff6ff; transition: all .2s; margin-top: 4px; }
.add-img-area:hover { border-color: var(--brand); background: #dbeafe; }
.add-img-area p { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
.btn-add-imgs { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: var(--brand); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'Sora', sans-serif; cursor: pointer; pointer-events: none; }
.new-imgs-queue { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.new-img-item { position: relative; width: 64px; height: 64px; }
.new-img-item img { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 2px solid #bfdbfe; }
.new-img-item .btn-rm-new { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; border-radius: 50%; background: var(--clr-danger); color: #fff; border: none; font-size: 11px; cursor: pointer; line-height: 18px; text-align: center; padding: 0; }
#crop-modal{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);align-items:center;justify-content:center;}
#crop-modal.open{display:flex;}
.crop-modal-box{background:#fff;border-radius:1rem;padding:1rem;width:min(420px,92vw);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.4);display:flex;flex-direction:column;gap:.75rem;}
.crop-modal-title{font-size:.95rem;font-weight:700;color:var(--text);margin:0;}
.crop-modal-sub{font-size:.78rem;color:var(--text-muted);margin:0;}
#crop-canvas{display:block;max-width:100%;border-radius:.5rem;cursor:move;touch-action:none;user-select:none;}
#crop-preview-canvas{width:60px;height:60px;border-radius:6px;border:2px solid #bfdbfe;display:block;}
.crop-actions{display:flex;gap:.75rem;justify-content:flex-end;}
.btn-crop-cancel{padding:.5rem 1rem;border-radius:.5rem;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;font-weight:600;cursor:pointer;font-size:.85rem;font-family:'Sora',sans-serif;}
.btn-crop-cancel:hover{background:#f1f5f9;}
.btn-crop-ok{padding:.5rem 1.2rem;border-radius:.5rem;border:none;background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;font-weight:700;cursor:pointer;font-size:.85rem;font-family:'Sora',sans-serif;}
.btn-crop-ok:hover{opacity:.88;}
@media(max-width:680px){
    .page-body{padding:0 12px;margin-top:20px;}
    .ep-grid{grid-template-columns:1fr;gap:14px;}
    .ep-grid .span-2{grid-column:1;}
    .ep-card-body{padding:16px;}
    .ep-card-head{padding:13px 16px;}
    .btn-save{min-width:0;font-size:14px;padding:13px 18px;}
    .ep-save-wrap{padding:16px;}
    .save-hint{display:none;}
    .img-gallery{grid-template-columns:repeat(auto-fill,minmax(100px,1fr));}
    .crop-modal-box{padding:.75rem;gap:.6rem;width:96vw;}
    .remessa-row { flex-direction: column; }
    .btn-remessa { width: 100%; }
}
</style>
</head>
<body>
<div class="page-body">

    <div class="page-header">
        <a href="admin.php" class="btn-back">&#8592; Voltar ao Admin</a>
    </div>

    <?php if ($mensagem): ?>
    <div class="ep-alert <?= $tipo_mensagem ?>">
        <?php if ($tipo_mensagem === 'success'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php endif; ?>
        <?= $mensagem ?>
    </div>
    <?php endif; ?>

    <form id="form-editar" method="POST" enctype="multipart/form-data">
    <input type="file" id="input-novas-imgs" name="novas_imagens[]" accept="image/*" multiple style="display:none;">
    <input type="hidden" name="id_img_principal" id="id_img_principal" value="<?= $imagens[0]['id'] ?? 0 ?>">

    <div class="ep-grid">

        <!-- INFORMACOES BASICAS -->
        <div class="ep-card">
            <div class="ep-card-head">
                <div class="ep-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <h2>Informacoes Basicas</h2>
            </div>
            <div class="ep-card-body">
                <div class="ep-field">
                    <label>Nome do Produto <span class="req">*</span></label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($produto['nome']) ?>" required>
                </div>
                <div class="ep-field">
                    <label>Categoria <span class="req">*</span></label>
                    <select name="id_categoria" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($result_categorias->fetchAll() as $cat): ?>
                        <option value="<?= $cat['id_categoria'] ?>" <?= $cat['id_categoria']==$produto['id_categoria']?'selected':'' ?>><?= htmlspecialchars($cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ep-field">
                    <label>Marca <span class="req">*</span></label>
                    <input type="text" name="marca" value="<?= htmlspecialchars($produto['marca']) ?>">
                </div>
                <div class="ep-field">
                    <label>Modelo <span class="req">*</span></label>
                    <input type="text" name="modelo" value="<?= htmlspecialchars($produto['modelo']) ?>">
                </div>
                <div class="ep-field">
                    <label>Codigo de Barras</label>
                    <div class="barcode-input-wrap">
                        <input type="text" name="codigo_barras" id="campo-codigo-barras"
                            value="<?= htmlspecialchars($produto['codigo_barras'] ?? '') ?>"
                            placeholder="Bipe a pistola ou digite manualmente"
                            autocomplete="off">
                        <span class="barcode-input-icon">&#128440;</span>
                    </div>
                    <p class="barcode-hint-text">Clique no campo e bipe o produto para preencher automaticamente.</p>
                </div>
            </div>
        </div>

        <!-- PRECO & ESTOQUE -->
        <div class="ep-card">
            <div class="ep-card-head">
                <div class="ep-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <h2>Preco &amp; Estoque</h2>
            </div>
            <div class="ep-card-body">
                <div class="ep-field">
                    <label>Preco Normal <span class="req">*</span></label>
                    <div class="ep-preco-wrap">
                        <span class="ep-preco-rs">R$</span>
                        <input type="text" inputmode="numeric" name="preco" id="ep-inp-preco"
                               data-preco value="<?= $preco_formatado ?>" required autocomplete="off">
                    </div>
                </div>
                <div class="ep-field">
                    <label>Preco Promocional</label>
                    <div class="ep-preco-wrap">
                        <span class="ep-preco-rs">R$</span>
                        <input type="text" inputmode="numeric" name="preco_promocional" id="ep-inp-preco-promo"
                               data-preco value="<?= $preco_promo_formatado ?>" autocomplete="off">
                    </div>
                </div>
                <div class="ep-field">
                    <label>Estoque <span class="req">*</span>
                        <?php $est=$produto['estoque'];
                        if($est==0) echo '<span class="ep-stock-badge zero">Sem estoque</span>';
                        elseif($est<=5) echo '<span class="ep-stock-badge low">Baixo</span>';
                        else echo '<span class="ep-stock-badge ok">OK</span>'; ?>
                    </label>
                    <input type="number" name="estoque" id="campo-estoque" min="0" value="<?= $est ?>">
                </div>

                <?php if (!empty($produto['codigo_barras'])): ?>
                <div class="remessa-card">
                    <div class="remessa-card-title">
                        &#128440; Entrada de Remessa por Pistola
                    </div>
                    <div class="remessa-barcode-wrap listening" id="remessa-barcode-wrap">
                        <span class="remessa-barcode-icon">&#128440;</span>
                        <div class="remessa-barcode-info">
                            <div class="remessa-barcode-title">Pistola ativa — bipe o produto</div>
                            <div class="remessa-barcode-sub">Ao bipar, o campo quantidade sera preenchido automaticamente</div>
                        </div>
                    </div>
                    <div class="remessa-row">
                        <div class="ep-field">
                            <label>Quantidade a adicionar</label>
                            <input type="number" id="remessa-qty" min="1" value="1" placeholder="Qtd">
                        </div>
                        <button type="button" class="btn-remessa" onclick="confirmarRemessa()">
                            + Adicionar ao Estoque
                        </button>
                    </div>
                    <div class="remessa-status" id="remessa-status"></div>
                </div>
                <?php else: ?>
                <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-top:16px;font-size:12px;color:#854d0e;">
                    Cadastre o codigo de barras acima para habilitar a entrada de remessa por pistola.
                </div>
                <?php endif; ?>

                <div class="ep-toggle-row" style="margin-top:16px;">
                    <div class="ep-toggle-info"><strong>Produto Ativo</strong><small>Visivel para clientes</small></div>
                    <label class="ep-switch"><input type="checkbox" name="ativo" value="1" <?= $produto['ativo']?'checked':''?>><span class="ep-slider"></span></label>
                </div>
                <div class="ep-toggle-row">
                    <div class="ep-toggle-info"><strong>Em Destaque</strong><small>Aparece na secao destaque</small></div>
                    <label class="ep-switch"><input type="checkbox" name="destaque" value="1" <?= $produto['destaque']?'checked':''?>><span class="ep-slider"></span></label>
                </div>
            </div>
        </div>

        <!-- DESCRICAO -->
        <div class="ep-card span-2">
            <div class="ep-card-head">
                <div class="ep-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg></div>
                <h2>Descricao</h2>
            </div>
            <div class="ep-card-body">
                <div class="ep-field">
                    <label>Descricao do Produto <span class="req"></span></label>
                    <textarea name="descricao"><?= htmlspecialchars($produto['descricao']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- GALERIA DE IMAGENS -->
        <div class="ep-card span-2">
            <div class="ep-card-head">
                <div class="ep-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                <h2>Imagens do Produto</h2>
            </div>
            <div class="ep-card-body">
                <div class="img-gallery" id="img-gallery">
                <?php
                $img_principal_nome = $produto['imagem'];
                foreach ($imagens as $img):
                    $is_principal = ($img['imagem'] === $img_principal_nome);
                ?>
                <div class="img-card <?= $is_principal?'is-principal':'' ?>" id="imgcard-<?= $img['id'] ?>" data-id="<?= $img['id'] ?>" data-nome="<?= htmlspecialchars($img['imagem']) ?>">
                    <?php if($is_principal): ?>
                    <div class="badge-principal">&#11088; Principal</div>
                    <?php endif; ?>
                    <img src="uploads/<?= htmlspecialchars($img['imagem']) ?>" alt="Imagem" onerror="this.src='uploads/placeholder.jpg'">
                    <div class="img-card-actions">
                        <button type="button" class="img-btn img-btn-crop" title="Recortar" onclick="editarImagem(<?= $img['id'] ?>, 'uploads/<?= $img['imagem'] ?>')">&#9986;</button>
                        <button type="button" class="img-btn img-btn-star" title="Definir como principal" onclick="definirPrincipal(<?= $img['id'] ?>)">&#11088;</button>
                        <button type="button" class="img-btn img-btn-del"  title="Remover" onclick="toggleDeletar(<?= $img['id'] ?>)">&#128465;</button>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>

                <div class="add-img-area" onclick="document.getElementById('input-novas-imgs').click()">
                    <button type="button" class="btn-add-imgs">+ Adicionar imagens</button>
                    <p>JPG, PNG, WEBP &bull; Max 5MB &bull; Recorte quadrado sera aplicado</p>
                </div>

                <div class="new-imgs-queue" id="new-imgs-queue"></div>
                <div id="delete-inputs"></div>
                <div id="replace-inputs"></div>
            </div>
        </div>

        <!-- SALVAR -->
        <div class="ep-card span-2">
            <div class="ep-save-wrap">
                <button type="button" class="btn-save" id="btn-save" onclick="salvarProduto()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Salvar Alteracoes
                </button>
                <p class="save-hint">As alteracoes serao aplicadas<br>imediatamente na loja.</p>
            </div>
        </div>

    </div>
    </form>
</div>

<!-- MODAL DE CROP -->
<div id="crop-modal">
    <div class="crop-modal-box">
        <div>
            <p class="crop-modal-title">Ajustar imagem</p>
            <p class="crop-modal-sub">Arraste para mover &bull; Alcas nas bordas para redimensionar</p>
        </div>
        <canvas id="crop-canvas"></canvas>
        <div style="display:flex;align-items:center;gap:.75rem;">
            <canvas id="crop-preview-canvas"></canvas>
            <span style="font-size:.78rem;color:#64748b;">Pre-visualizacao 1:1</span>
        </div>
        <div class="crop-actions">
            <button type="button" class="btn-crop-cancel" onclick="fecharCrop()">Cancelar</button>
            <button type="button" class="btn-crop-ok"     onclick="confirmarCrop()">Usar este recorte</button>
        </div>
    </div>
</div>

<script>
/* ═══════════════════════════════════════
   MASCARA DE PRECO ESTILO BANCO
═══════════════════════════════════════ */
function aplicarMascaraPreco(input) {
    function formatarCentavos(centavos) {
        if (!centavos || centavos === 0) return '0,00';
        var s = String(centavos).replace(/\D/g, '');
        while (s.length < 3) s = '0' + s;
        var integer = s.slice(0, -2).replace(/^0+/, '') || '0';
        var cents   = s.slice(-2);
        integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return integer + ',' + cents;
    }
    function lerCentavos(valor) {
        var limpo = valor.replace(/\./g, '').replace(',', '');
        return parseInt(limpo, 10) || 0;
    }
    input.addEventListener('focus', function() {
        input._centavos = lerCentavos(this.value);
        this.value = formatarCentavos(input._centavos);
        setTimeout(function() { input.select(); }, 10);
    });
    input.addEventListener('keydown', function(e) {
        var allow = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'];
        if (allow.indexOf(e.key) >= 0) return;
        if (e.key >= '0' && e.key <= '9') return;
        e.preventDefault();
    });
    input.addEventListener('input', function() {
        var digits = this.value.replace(/\D/g, '');
        var centavos = parseInt(digits, 10) || 0;
        input._centavos = centavos;
        this.value = formatarCentavos(centavos);
    });
    input.addEventListener('blur', function() {
        if (!this.value) this.value = '0,00';
    });
}

function converterPrecosParaEnvio(form) {
    form.querySelectorAll('[data-preco]').forEach(function(inp) {
        var raw = inp.value.replace(/\./g, '').replace(',', '.');
        var num = parseFloat(raw) || 0;
        inp.value = num > 0 ? raw : '';
    });
}

document.querySelectorAll('[data-preco]').forEach(aplicarMascaraPreco);

/* ═══════════════════════════════════════
   REMESSA VIA PISTOLA
═══════════════════════════════════════ */
const CODIGO_BARRAS_CADASTRADO = '<?= addslashes($produto['codigo_barras'] ?? '') ?>';
const ID_PRODUTO_ATUAL = <?= $id_produto ?>;
const URL_ATUAL = window.location.href;

let barcodeBuffer = '';
let barcodeTimer  = null;
const BARCODE_DELAY = 80;

document.addEventListener('keydown', function(e) {
    const tag = document.activeElement ? document.activeElement.tagName : '';
    const id  = document.activeElement ? document.activeElement.id    : '';
    const camposIgnorados = ['campo-codigo-barras'];
    if ((tag === 'INPUT' || tag === 'TEXTAREA') && !camposIgnorados.includes(id)) return;
    if (!document.getElementById('remessa-qty')) return;
    if (e.key === 'Enter') {
        if (barcodeBuffer.length > 3) processarBarcodeRemessa(barcodeBuffer.trim());
        barcodeBuffer = '';
        clearTimeout(barcodeTimer);
        return;
    }
    if (e.key.length === 1) {
        barcodeBuffer += e.key;
        clearTimeout(barcodeTimer);
        barcodeTimer = setTimeout(function() {
            if (barcodeBuffer.length > 3) processarBarcodeRemessa(barcodeBuffer.trim());
            barcodeBuffer = '';
        }, BARCODE_DELAY);
    }
});

function processarBarcodeRemessa(codigo) {
    if (CODIGO_BARRAS_CADASTRADO && codigo !== CODIGO_BARRAS_CADASTRADO) {
        mostrarStatusRemessa('Codigo ' + codigo + ' nao corresponde a este produto.', 'err');
        return;
    }
    const qtyInput = document.getElementById('remessa-qty');
    if (qtyInput) { qtyInput.value = 1; qtyInput.focus(); qtyInput.select(); }
    mostrarStatusRemessa('Produto identificado! Ajuste a quantidade e clique em Adicionar ao Estoque.', 'ok');
}

async function confirmarRemessa() {
    const qty = parseInt(document.getElementById('remessa-qty').value);
    if (isNaN(qty) || qty < 1) { mostrarStatusRemessa('Informe uma quantidade valida.', 'err'); return; }
    if (!CODIGO_BARRAS_CADASTRADO) { mostrarStatusRemessa('Produto sem codigo de barras cadastrado.', 'err'); return; }
    const fd = new FormData();
    fd.append('action', 'incrementar_estoque');
    fd.append('codigo_barras', CODIGO_BARRAS_CADASTRADO);
    fd.append('quantidade', qty);
    try {
        const r    = await fetch(URL_ATUAL, { method: 'POST', body: fd });
        const data = await r.json();
        if (data.ok) {
            mostrarStatusRemessa(data.msg, 'ok');
            const campoEstoque = document.getElementById('campo-estoque');
            if (campoEstoque) campoEstoque.value = data.novo_estoque;
            document.getElementById('remessa-qty').value = 1;
        } else {
            mostrarStatusRemessa(data.msg, 'err');
        }
    } catch(e) {
        mostrarStatusRemessa('Erro de conexao. Tente novamente.', 'err');
    }
}

function mostrarStatusRemessa(texto, tipo) {
    const el = document.getElementById('remessa-status');
    if (!el) return;
    el.textContent = texto;
    el.className = 'remessa-status show ' + tipo;
    clearTimeout(el._timer);
    if (tipo === 'ok') { el._timer = setTimeout(function() { el.className = 'remessa-status'; }, 4000); }
}

/* ═══════════════════════════════════════
   CROP ENGINE
═══════════════════════════════════════ */
let novasImagens = [];
let imgSubstituidas = {};
let imgsDeletar = new Set();
let filaCrop = [];
let filaCropIndex = 0;
let cropImg = new Image();
let box = {x:0,y:0,s:0}, CW=0, CH=0;
let mode='', dragStart={};
const OUT=800, MINBOX=40;

function abrirCropSrc(src, callback) {
    cropImg = new Image();
    cropImg.onload = function() {
        document.getElementById('crop-modal').classList.add('open');
        requestAnimationFrame(()=>requestAnimationFrame(()=>initCanvas(callback)));
    };
    cropImg.src = src;
}

let cropCallback = null;
function initCanvas(cb) {
    cropCallback = cb || cropCallback;
    const canvas = document.getElementById('crop-canvas');
    CW = cropImg.naturalWidth; CH = cropImg.naturalHeight;
    canvas.width=CW; canvas.height=CH;
    const cssW = canvas.parentElement.clientWidth - 32;
    const maxH = Math.min(window.innerHeight*0.40, 300);
    const r = Math.min(cssW/CW, maxH/CH, 1);
    canvas.style.width  = Math.round(CW*r)+'px';
    canvas.style.height = Math.round(CH*r)+'px';
    const s = Math.round(Math.min(CW,CH)*0.80);
    box = {x:Math.round((CW-s)/2), y:Math.round((CH-s)/2), s};
    renderTudo(); renderPreview();
}

function fecharCrop() { document.getElementById('crop-modal').classList.remove('open'); }

function renderTudo() {
    const canvas = document.getElementById('crop-canvas'), ctx = canvas.getContext('2d');
    const vf = canvas.width / canvas.getBoundingClientRect().width;
    ctx.drawImage(cropImg,0,0,CW,CH);
    ctx.fillStyle='rgba(0,0,0,.50)';
    ctx.fillRect(0,0,CW,box.y);ctx.fillRect(0,box.y+box.s,CW,CH-(box.y+box.s));
    ctx.fillRect(0,box.y,box.x,box.s);ctx.fillRect(box.x+box.s,box.y,CW-(box.x+box.s),box.s);
    ctx.strokeStyle='#fff'; ctx.lineWidth=2*vf; ctx.strokeRect(box.x,box.y,box.s,box.s);
    ctx.strokeStyle='rgba(255,255,255,.35)'; ctx.lineWidth=1*vf;
    for(let i=1;i<3;i++){
        const lx=box.x+(box.s/3)*i,ly=box.y+(box.s/3)*i;
        ctx.beginPath();ctx.moveTo(lx,box.y);ctx.lineTo(lx,box.y+box.s);ctx.stroke();
        ctx.beginPath();ctx.moveTo(box.x,ly);ctx.lineTo(box.x+box.s,ly);ctx.stroke();
    }
    ctx.fillStyle='#fff';ctx.strokeStyle='rgba(37,99,235,.9)';ctx.lineWidth=2*vf;
    handles(vf).forEach(h=>{ctx.fillRect(h.x,h.y,h.w,h.h);ctx.strokeRect(h.x,h.y,h.w,h.h);});
}
function getVF(){const c=document.getElementById('crop-canvas'),r=c.getBoundingClientRect();return r.width>0?c.width/r.width:1;}
function handles(vf){if(!vf)vf=getVF();const hs=10*vf,{x,y,s}=box,mx=x+s/2-hs/2,my=y+s/2-hs/2;
    return[{id:'tl',x:x-hs/2,y:y-hs/2,w:hs,h:hs},{id:'tr',x:x+s-hs/2,y:y-hs/2,w:hs,h:hs},
           {id:'bl',x:x-hs/2,y:y+s-hs/2,w:hs,h:hs},{id:'br',x:x+s-hs/2,y:y+s-hs/2,w:hs,h:hs},
           {id:'tm',x:mx,y:y-hs/2,w:hs,h:hs},{id:'bm',x:mx,y:y+s-hs/2,w:hs,h:hs},
           {id:'ml',x:x-hs/2,y:my,w:hs,h:hs},{id:'mr',x:x+s-hs/2,y:my,w:hs,h:hs}];}
function hitHandle(px,py){const vf=getVF(),pad=10*vf;for(const h of handles(vf)){if(px>=h.x-pad&&px<=h.x+h.w+pad&&py>=h.y-pad&&py<=h.y+h.h+pad)return h.id;}return null;}
function hitBox(px,py){return px>=box.x&&px<=box.x+box.s&&py>=box.y&&py<=box.y+box.s;}
function toCanvas(cx,cy){const c=document.getElementById('crop-canvas'),r=c.getBoundingClientRect();return{x:(cx-r.left)*(c.width/r.width),y:(cy-r.top)*(c.height/r.height)};}
function renderPreview(){const p=document.getElementById('crop-preview-canvas'),ctx=p.getContext('2d');p.width=p.height=80;ctx.drawImage(cropImg,box.x,box.y,box.s,box.s,0,0,80,80);}
function aplicar(dx,dy){
    if(mode==='move'){box.x=clamp(dragStart.bx+dx,0,CW-box.s);box.y=clamp(dragStart.by+dy,0,CH-box.s);return;}
    const h=mode;let nx=dragStart.bx,ny=dragStart.by,ns=dragStart.bs;
    if(h==='br')ns=dragStart.bs+Math.max(dx,dy);
    else if(h==='tl'){const d=Math.max(-dx,-dy);ns=dragStart.bs+d;nx=dragStart.bx+dragStart.bs-ns;ny=dragStart.by+dragStart.bs-ns;}
    else if(h==='tr'){ns=dragStart.bs+Math.max(dx,-dy);ny=dragStart.by+dragStart.bs-ns;}
    else if(h==='bl'){ns=dragStart.bs+Math.max(-dx,dy);nx=dragStart.bx+dragStart.bs-ns;}
    else if(h==='mr')ns=dragStart.bs+dx;
    else if(h==='ml'){ns=dragStart.bs-dx;nx=dragStart.bx+dragStart.bs-ns;}
    else if(h==='bm')ns=dragStart.bs+dy;
    else if(h==='tm'){ns=dragStart.bs-dy;ny=dragStart.by+dragStart.bs-ns;}
    ns=Math.max(MINBOX,ns);nx=clamp(nx,0,CW-ns);ny=clamp(ny,0,CH-ns);
    if(nx+ns>CW)ns=CW-nx;if(ny+ns>CH)ns=CH-ny;
    box.x=nx;box.y=ny;box.s=ns;
}
function startDrag(px,py){const p=toCanvas(px,py);const h=hitHandle(p.x,p.y);if(h)mode=h;else if(hitBox(p.x,p.y))mode='move';else return;dragStart={px:p.x,py:p.y,bx:box.x,by:box.y,bs:box.s};}
function moveDrag(px,py){if(!mode)return;const p=toCanvas(px,py);aplicar(p.x-dragStart.px,p.y-dragStart.py);renderTudo();renderPreview();}
const cvs=document.getElementById('crop-canvas');
cvs.addEventListener('mousedown',e=>{e.preventDefault();startDrag(e.clientX,e.clientY);});
document.addEventListener('mousemove',e=>{if(mode)moveDrag(e.clientX,e.clientY);});
document.addEventListener('mouseup',()=>{mode='';});
cvs.addEventListener('touchstart',e=>{e.preventDefault();startDrag(e.touches[0].clientX,e.touches[0].clientY);},{passive:false});
document.addEventListener('touchmove',e=>{if(mode){e.preventDefault();moveDrag(e.touches[0].clientX,e.touches[0].clientY);}},{passive:false});
document.addEventListener('touchend',()=>{mode='';});

function confirmarCrop() {
    const out=document.createElement('canvas');out.width=out.height=OUT;
    out.getContext('2d').drawImage(cropImg,box.x,box.y,box.s,box.s,0,0,OUT,OUT);
    out.toBlob(blob=>{fecharCrop();if(cropCallback)cropCallback(blob);cropCallback=null;},'image/jpeg',0.92);
}

document.getElementById('input-novas-imgs').addEventListener('change', function(){
    filaCrop = Array.from(this.files); this.value='';
    if(!filaCrop.length) return;
    filaCropIndex=0; proximaNovaCrop();
});
function proximaNovaCrop(){
    if(filaCropIndex >= filaCrop.length){renderNovasImagens();return;}
    const file=filaCrop[filaCropIndex];
    const r=new FileReader();
    r.onload=e=>{abrirCropSrc(e.target.result,blob=>{
        const url=URL.createObjectURL(blob);
        novasImagens.push({blob:new File([blob],file.name,{type:'image/jpeg'}),url,nome:file.name});
        filaCropIndex++;setTimeout(proximaNovaCrop,80);
    });};
    r.readAsDataURL(file);
}
function renderNovasImagens(){
    const wrap=document.getElementById('new-imgs-queue'); wrap.innerHTML='';
    novasImagens.forEach((img,i)=>{
        const item=document.createElement('div');item.className='new-img-item';
        const el=document.createElement('img');el.src=img.url;
        const btn=document.createElement('button');btn.className='btn-rm-new';btn.type='button';btn.innerHTML='x';
        btn.onclick=()=>{URL.revokeObjectURL(img.url);novasImagens.splice(i,1);renderNovasImagens();};
        item.appendChild(el);item.appendChild(btn);wrap.appendChild(item);
    });
}

function editarImagem(idImagem,src){
    abrirCropSrc(src,blob=>{
        const url=URL.createObjectURL(blob);
        const card=document.getElementById('imgcard-'+idImagem);
        if(card)card.querySelector('img').src=url;
        imgSubstituidas[idImagem]={blob:new File([blob],'img_'+idImagem+'.jpg',{type:'image/jpeg'}),url};
    });
}

function definirPrincipal(idImagem){
    document.getElementById('id_img_principal').value=idImagem;
    document.querySelectorAll('.img-card').forEach(c=>{c.classList.remove('is-principal');const b=c.querySelector('.badge-principal');if(b)b.remove();});
    const card=document.getElementById('imgcard-'+idImagem);
    if(card){card.classList.add('is-principal');const badge=document.createElement('div');badge.className='badge-principal';badge.textContent='Estrela Principal';card.insertBefore(badge,card.firstChild);}
}
function toggleDeletar(idImagem){
    const card=document.getElementById('imgcard-'+idImagem);
    if(imgsDeletar.has(idImagem)){imgsDeletar.delete(idImagem);card.classList.remove('marcada-deletar');}
    else{imgsDeletar.add(idImagem);card.classList.add('marcada-deletar');}
    const wrap=document.getElementById('delete-inputs');wrap.innerHTML='';
    imgsDeletar.forEach(id=>{const inp=document.createElement('input');inp.type='hidden';inp.name='deletar_imgs[]';inp.value=id;wrap.appendChild(inp);});
}

function salvarProduto(){
    const form=document.getElementById('form-editar');
    if(!form.checkValidity()){form.reportValidity();return;}
    const btn=document.getElementById('btn-save');
    btn.disabled=true;
    btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;width:18px;height:18px"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Salvando...';
    // Converte mascaras antes de enviar
    converterPrecosParaEnvio(form);
    const fd=new FormData(form);
    fd.delete('novas_imagens[]');
    novasImagens.forEach(img=>fd.append('novas_imagens[]',img.blob,img.nome));
    Object.entries(imgSubstituidas).forEach(([id,data])=>fd.append('replace_img_'+id,data.blob,'img_'+id+'.jpg'));
    fetch(window.location.href,{method:'POST',body:fd})
        .then(r=>r.text()).then(html=>{document.open();document.write(html);document.close();})
        .catch(err=>{alert('Erro: '+err);btn.disabled=false;btn.innerHTML='Salvar Alteracoes';});
}

function clamp(v,mn,mx){return Math.min(Math.max(v,mn),mx);}
</script>
</body>
</html>