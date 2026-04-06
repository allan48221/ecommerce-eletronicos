<?php
require_once 'config/database.php';
require_once 'config/tema.php';
require_once 'config/cloudinary.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$id_tenant = $_SESSION['id_tenant'] ?? null;

$mensagem      = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome              = trim($_POST['nome']);
    $descricao         = trim($_POST['descricao']);
    $marca             = trim($_POST['marca']);
    $modelo            = trim($_POST['modelo']);
    $preco             = !empty($_POST['preco'])             ? floatval($_POST['preco'])             : null;
    $preco_promocional = !empty($_POST['preco_promocional']) ? floatval($_POST['preco_promocional']) : null;
    $estoque           = intval($_POST['estoque']);
    $id_categoria      = intval($_POST['id_categoria']);
    $destaque          = isset($_POST['destaque']) ? 1 : 0;
    $codigo_barras     = trim($_POST['codigo_barras'] ?? '');

    if (empty($nome)) {
        $mensagem      = "Preencha todos os campos obrigatorios!";
        $tipo_mensagem = "danger";
    }

    $imagens_salvas = [];

    if (empty($mensagem)) {
        if (!empty($_FILES['imagens']['name'][0])) {
            foreach ($_FILES['imagens']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['imagens']['error'][$key] === UPLOAD_ERR_OK) {
                    $extensoes_permitidas = ['jpg','jpeg','png','gif','webp','avif'];
                    $extensao = strtolower(pathinfo($_FILES['imagens']['name'][$key], PATHINFO_EXTENSION));

                    if (!in_array($extensao, $extensoes_permitidas)) {
                        $mensagem = "Formato invalido: " . htmlspecialchars($_FILES['imagens']['name'][$key]);
                        $tipo_mensagem = "danger"; break;
                    }
                    if ($_FILES['imagens']['size'][$key] > 5242880) {
                        $mensagem = "Imagem muito grande: " . htmlspecialchars($_FILES['imagens']['name'][$key]) . ". Max 5MB.";
                        $tipo_mensagem = "danger"; break;
                    }

                    // ── Faz upload para o Cloudinary ──
                    try {
                        $url_cloudinary = cloudinary_upload($tmp_name, 'produtos');
                        $imagens_salvas[] = $url_cloudinary;
                    } catch (Exception $e) {
                        $mensagem = "Erro ao enviar imagem para o Cloudinary: " . $e->getMessage();
                        $tipo_mensagem = "danger"; break;
                    }

                } elseif ($_FILES['imagens']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    $mensagem = "Erro no upload da imagem " . ($key + 1);
                    $tipo_mensagem = "danger"; break;
                }
            }
        }
    }

    if (empty($mensagem)) {
        $imagem_principal = !empty($imagens_salvas) ? $imagens_salvas[0] : null;

        $sql  = "INSERT INTO produtos (nome, descricao, marca, modelo, preco, preco_promocional, estoque, id_categoria, imagem, destaque, codigo_barras, id_tenant)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id_produto";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $mensagem = "Erro ao preparar consulta.";
            $tipo_mensagem = "danger";
        } else {
            $ok = $stmt->execute([$nome, $descricao, $marca, $modelo, $preco, $preco_promocional, $estoque, $id_categoria, $imagem_principal, $destaque, $codigo_barras ?: null, $id_tenant]);

            if ($ok) {
                $row        = $stmt->fetch();
                $id_produto = $row['id_produto'];

                $erros_imagem = [];
                foreach ($imagens_salvas as $url_img) {
                    $stmt_img = $conn->prepare("INSERT INTO produto_imagens (id_produto, imagem) VALUES (?, ?)");
                    if (!$stmt_img || !$stmt_img->execute([$id_produto, $url_img])) {
                        $erros_imagem[] = $url_img;
                    }
                }

                $mensagem = 'Produto cadastrado com sucesso! ' . count($imagens_salvas) . ' imagem(ns) salva(s).';
                $tipo_mensagem = empty($erros_imagem) ? 'success' : 'warning';
            } else {
                $mensagem = 'Erro ao cadastrar produto.';
                $tipo_mensagem = 'danger';
            }
        }
    }
}

$result_categorias = $conn->prepare("SELECT * FROM categorias WHERE ativo = TRUE AND (id_tenant = ? OR id_tenant IS NULL) ORDER BY nome");
$result_categorias->execute([$id_tenant]);
$categorias = $result_categorias->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cadastro de Produto</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <style>
        .form-produto { display: flex; flex-direction: column; gap: 1.25rem; margin-top: 1.5rem; }
        .form-produto .field { display: flex; flex-direction: column; gap: 0.4rem; }
        .form-produto .field label { font-weight: 600; font-size: 0.9rem; color: var(--dark); }
        .form-produto .field label span { color: var(--danger); margin-left: 2px; }
        .form-produto .field input,
        .form-produto .field select,
        .form-produto .field textarea {
            width: 100%; padding: 0.72rem 1rem;
            border: 1.5px solid #ddd; border-radius: 0.65rem;
            font-size: 0.93rem; font-family: inherit;
            color: var(--dark); background: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-produto .field input:focus,
        .form-produto .field select:focus,
        .form-produto .field textarea:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
            outline: none;
        }
        .form-produto .field textarea { min-height: 100px; resize: vertical; }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; }
        .barcode-field-wrap { position: relative; }
        .barcode-field-wrap input { padding-right: 44px !important; letter-spacing: 1px; }
        .barcode-field-icon { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); font-size: 20px; pointer-events: none; opacity: 0.45; }
        .barcode-hint { font-size: 0.75rem; color: #64748b; margin-top: 4px; }
        .preco-wrap { position: relative; }
        .preco-wrap .preco-rs { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); font-size: 13px; font-weight: 700; color: #64748b; pointer-events: none; }
        .preco-wrap input { padding-left: 34px !important; font-size: 1.05rem !important; font-weight: 700 !important; letter-spacing: 0.5px; color: var(--dark) !important; }
        .upload-area { border: 2px dashed #c4b5fd; border-radius: 0.75rem; padding: 1.5rem; background: #faf5ff; text-align: center; cursor: pointer; transition: all 0.2s; }
        .upload-area:hover { border-color: var(--purple); background: #f5f3ff; }
        .upload-area p { color: var(--gray); font-size: 0.85rem; margin-top: 0.5rem; }
        .btn-upload { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.4rem; background: linear-gradient(135deg, var(--primary), var(--purple)); color: white; border: none; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
        .btn-upload:hover { opacity: 0.85; }
        .preview-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 1rem; }
        .preview-item { position: relative; }
        .preview-item img { width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd6fe; display: block; }
        .preview-item .btn-remover { position: absolute; top: -6px; right: -6px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 13px; cursor: pointer; line-height: 22px; text-align: center; padding: 0; }
        .contador-imgs { font-size: 0.82rem; color: var(--gray); margin-top: 0.5rem; }
        .field-check { display: flex; align-items: center; gap: 0.6rem; padding: 0.85rem 1rem; background: var(--light); border-radius: 0.65rem; border: 1.5px solid #ddd; cursor: pointer; transition: border-color 0.2s; }
        .field-check:hover { border-color: var(--purple); }
        .field-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--purple); cursor: pointer; flex-shrink: 0; }
        .field-check span { font-weight: 600; font-size: 0.92rem; color: var(--dark); }
        .btn-cadastrar { width: 100%; padding: 1rem; font-size: 1.05rem; font-weight: 700; border: none; border-radius: 0.75rem; background: linear-gradient(135deg, var(--primary), var(--purple)); color: white; cursor: pointer; transition: all 0.25s; box-shadow: 0 4px 14px rgba(124,58,237,0.35); }
        .btn-cadastrar:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: #ffffff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 50px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background .2s, border-color .2s; }
        .btn-back:hover { background: #f1f5f9; border-color: #cbd5e1; }
        #crop-modal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.75); align-items: center; justify-content: center; }
        #crop-modal.open { display: flex; }
        .crop-modal-box { background: #fff; border-radius: 1rem; padding: 1rem; width: min(420px, 92vw); max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.4); display: flex; flex-direction: column; gap: 0.75rem; }
        .crop-modal-title { font-size: 0.95rem; font-weight: 700; color: var(--dark); margin: 0; }
        .crop-modal-sub { font-size: 0.78rem; color: var(--gray); margin: 0; }
        #crop-canvas { display: block; max-width: 100%; border-radius: 0.5rem; }
        #crop-preview-canvas { width: 60px; height: 60px; border-radius: 6px; border: 2px solid #ddd6fe; display: block; }
        .crop-actions { display: flex; gap: 0.75rem; justify-content: flex-end; }
        .btn-crop-cancel { padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1.5px solid #ddd; background: #fff; color: var(--gray); font-weight: 600; cursor: pointer; font-size: 0.85rem; }
        .btn-crop-ok { padding: 0.5rem 1.2rem; border-radius: 0.5rem; border: none; background: linear-gradient(135deg, var(--primary), var(--purple)); color: white; font-weight: 700; cursor: pointer; font-size: 0.85rem; }
        @media (max-width: 640px) { .form-row-2 { grid-template-columns: 1fr; } .form-row-3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">

    <div style="display:flex; justify-content:flex-end; margin-bottom:0.75rem;">
        <a href="admin.php" class="btn-back">&#8592; Voltar ao Admin</a>
    </div>

    <div class="card">

        <h1 style="font-size:1.7rem; font-weight:800; margin-bottom:0.25rem; background:linear-gradient(135deg,var(--primary),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
            Cadastro de Produto
        </h1>
        <p style="color:var(--gray); font-size:0.9rem; margin-bottom:0.5rem;">Preencha todos os campos obrigatorios *</p>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>" style="margin-top:1rem;">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <form id="form-produto" method="POST" enctype="multipart/form-data" class="form-produto">

            <div class="form-row-2">
                <div class="field">
                    <label>Nome <span>*</span></label>
                    <input type="text" name="nome" placeholder="Ex: iPhone 15 Pro" required>
                </div>
                <div class="field">
                    <label>Categoria <span>*</span></label>
                    <select name="id_categoria" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Descricao</label>
                <textarea name="descricao" placeholder="Descreva o produto..."></textarea>
            </div>

            <div class="form-row-2">
                <div class="field">
                    <label>Marca <span>*</span></label>
                    <input type="text" name="marca" placeholder="Ex: Apple">
                </div>
                <div class="field">
                    <label>Modelo <span>*</span></label>
                    <input type="text" name="modelo" placeholder="Ex: iPhone 15 Pro 256GB">
                </div>
            </div>

            <div class="form-row-3">
                <div class="field">
                    <label>Preco <span>*</span></label>
                    <div class="preco-wrap">
                        <span class="preco-rs">R$</span>
                        <input type="text" inputmode="numeric" name="preco" id="inp-preco" data-preco value="0,00" required autocomplete="off">
                    </div>
                </div>
                <div class="field">
                    <label>Preco Promocional</label>
                    <div class="preco-wrap">
                        <span class="preco-rs">R$</span>
                        <input type="text" inputmode="numeric" name="preco_promocional" id="inp-preco-promo" data-preco value="0,00" autocomplete="off">
                    </div>
                </div>
                <div class="field">
                    <label>Estoque <span>*</span></label>
                    <input type="number" name="estoque" min="0" placeholder="0" required>
                </div>
            </div>

            <div class="field">
                <label>Codigo de Barras</label>
                <div class="barcode-field-wrap">
                    <input type="text" name="codigo_barras" id="campo-codigo-barras" placeholder="Bipe a pistola ou digite manualmente" autocomplete="off">
                    <span class="barcode-field-icon">&#128440;</span>
                </div>
                <p class="barcode-hint">Opcional. Clique no campo e bipe o produto com a pistola, ou digite o codigo manualmente.</p>
            </div>

            <div class="field">
                <label>Imagens</label>
                <div class="upload-area" onclick="document.getElementById('input-imagens').click()">
                    <button type="button" class="btn-upload">+ Adicionar imagens</button>
                    <p>JPG, PNG, GIF, WEBP &bull; Maximo 5MB por imagem &bull; Recorte quadrado automatico</p>
                </div>
                <input type="file" id="input-imagens" accept="image/*" multiple style="display:none;">
                <div class="preview-container" id="preview-imagens"></div>
                <p class="contador-imgs" id="contador-imagens"></p>
            </div>

            <label class="field-check">
                <input type="checkbox" name="destaque" value="1">
                <span>Produto em destaque</span>
            </label>

            <button type="button" class="btn-cadastrar" onclick="enviarFormulario()">
                Cadastrar Produto
            </button>

        </form>
    </div>
</div>

<!-- MODAL DE CROP -->
<div id="crop-modal">
    <div class="crop-modal-box">
        <div>
            <p class="crop-modal-title">Ajustar imagem</p>
            <p class="crop-modal-sub">Arraste para mover &bull; Alcas nas bordas para redimensionar</p>
        </div>
        <canvas id="crop-canvas" style="display:block;width:100%;border-radius:0.5rem;cursor:move;touch-action:none;user-select:none;"></canvas>
        <div id="crop-preview-wrap" style="display:flex;align-items:center;gap:0.75rem;">
            <canvas id="crop-preview-canvas" style="width:60px;height:60px;border-radius:6px;border:2px solid #ddd6fe;display:block;"></canvas>
            <span style="font-size:0.78rem;color:var(--gray);">Pre-visualizacao 1:1</span>
        </div>
        <div class="crop-actions">
            <button type="button" class="btn-crop-cancel" onclick="fecharCrop()">Cancelar</button>
            <button type="button" class="btn-crop-ok" onclick="confirmarCrop()">Usar este recorte</button>
        </div>
    </div>
</div>

<script>
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
    input.addEventListener('focus', function() { input._centavos = lerCentavos(this.value); this.value = formatarCentavos(input._centavos); setTimeout(function() { input.select(); }, 10); });
    input.addEventListener('keydown', function(e) { var allow = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End']; if (allow.indexOf(e.key) >= 0) return; if (e.key >= '0' && e.key <= '9') return; e.preventDefault(); });
    input.addEventListener('input', function() { var digits = this.value.replace(/\D/g, ''); var centavos = parseInt(digits, 10) || 0; input._centavos = centavos; this.value = formatarCentavos(centavos); });
    input.addEventListener('blur', function() { if (!this.value) this.value = '0,00'; });
}
function converterPrecosParaEnvio(form) {
    form.querySelectorAll('[data-preco]').forEach(function(inp) { var raw = inp.value.replace(/\./g, '').replace(',', '.'); var num = parseFloat(raw) || 0; inp.value = num > 0 ? num.toFixed(2) : ''; });
}
document.querySelectorAll('[data-preco]').forEach(aplicarMascaraPreco);

let arquivosSelecionados = [], filesFila = [], cropIndex = 0, cropImg = new Image(), box = {x:0,y:0,s:0}, CW = 0, CH = 0, scale = 1, mode = '', dragStart = {};
const OUT = 800, MINBOX = 40;
document.getElementById('input-imagens').addEventListener('change', function(){ filesFila = Array.from(this.files); this.value = ''; if (!filesFila.length) return; cropIndex = 0; proximoCrop(); });
function proximoCrop() { if (cropIndex >= filesFila.length) { atualizarPreview(); return; } const r = new FileReader(); r.onload = e => abrirCrop(e.target.result); r.readAsDataURL(filesFila[cropIndex]); }
function abrirCrop(src) { cropImg = new Image(); cropImg.onload = function() { document.getElementById('crop-modal').classList.add('open'); requestAnimationFrame(() => requestAnimationFrame(initCanvas)); }; cropImg.src = src; }
function fecharCrop() { document.getElementById('crop-modal').classList.remove('open'); filesFila = []; }
function initCanvas() { const canvas = document.getElementById('crop-canvas'), ctx = canvas.getContext('2d'); CW = cropImg.naturalWidth; CH = cropImg.naturalHeight; canvas.width = CW; canvas.height = CH; const cssW = canvas.parentElement.clientWidth - 32, maxH = Math.min(window.innerHeight * 0.40, 300), r = Math.min(cssW / CW, maxH / CH, 1); canvas.style.width = Math.round(CW * r) + 'px'; canvas.style.height = Math.round(CH * r) + 'px'; scale = 1; const s = Math.round(Math.min(CW, CH) * 0.80); box = { x: Math.round((CW-s)/2), y: Math.round((CH-s)/2), s }; renderTudo(ctx); renderPreview(); }
function renderTudo(ctx) { if (!ctx) ctx = document.getElementById('crop-canvas').getContext('2d'); const canvas = document.getElementById('crop-canvas'), vf = canvas.width / canvas.getBoundingClientRect().width; ctx.drawImage(cropImg, 0, 0, CW, CH); ctx.fillStyle = 'rgba(0,0,0,0.50)'; ctx.fillRect(0, 0, CW, box.y); ctx.fillRect(0, box.y+box.s, CW, CH-(box.y+box.s)); ctx.fillRect(0, box.y, box.x, box.s); ctx.fillRect(box.x+box.s, box.y, CW-(box.x+box.s), box.s); ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 2*vf; ctx.strokeRect(box.x, box.y, box.s, box.s); const handles = getHandleRects(vf); ctx.fillStyle = '#ffffff'; ctx.strokeStyle = 'rgba(124,58,237,0.9)'; ctx.lineWidth = 2*vf; handles.forEach(h => { ctx.fillRect(h.x, h.y, h.w, h.h); ctx.strokeRect(h.x, h.y, h.w, h.h); }); }
function getVF() { const canvas = document.getElementById('crop-canvas'), r = canvas.getBoundingClientRect(); return r.width > 0 ? canvas.width / r.width : 1; }
function getHandleRects(vf) { if (!vf) vf = getVF(); const hs = 10*vf, {x,y,s} = box, mx = x+s/2-hs/2, my = y+s/2-hs/2; return [{id:'tl',x:x-hs/2,y:y-hs/2,w:hs,h:hs},{id:'tr',x:x+s-hs/2,y:y-hs/2,w:hs,h:hs},{id:'bl',x:x-hs/2,y:y+s-hs/2,w:hs,h:hs},{id:'br',x:x+s-hs/2,y:y+s-hs/2,w:hs,h:hs},{id:'tm',x:mx,y:y-hs/2,w:hs,h:hs},{id:'bm',x:mx,y:y+s-hs/2,w:hs,h:hs},{id:'ml',x:x-hs/2,y:my,w:hs,h:hs},{id:'mr',x:x+s-hs/2,y:my,w:hs,h:hs}]; }
function hitHandle(px,py) { const vf=getVF(),pad=10*vf; for (const h of getHandleRects(vf)) { if(px>=h.x-pad&&px<=h.x+h.w+pad&&py>=h.y-pad&&py<=h.y+h.h+pad) return h.id; } return null; }
function hitBox(px,py) { return px>=box.x&&px<=box.x+box.s&&py>=box.y&&py<=box.y+box.s; }
function toCanvas(clientX,clientY) { const canvas=document.getElementById('crop-canvas'),r=canvas.getBoundingClientRect(); return {x:(clientX-r.left)*(canvas.width/r.width),y:(clientY-r.top)*(canvas.height/r.height)}; }
const cvs = document.getElementById('crop-canvas');
cvs.addEventListener('mousedown', function(e) { e.preventDefault(); const p=toCanvas(e.clientX,e.clientY),h=hitHandle(p.x,p.y); if(h) mode=h; else if(hitBox(p.x,p.y)) mode='move'; else return; dragStart={px:p.x,py:p.y,bx:box.x,by:box.y,bs:box.s}; });
document.addEventListener('mousemove', function(e) { if(!mode) return; const p=toCanvas(e.clientX,e.clientY); aplicar(p.x-dragStart.px,p.y-dragStart.py); renderTudo(); renderPreview(); });
document.addEventListener('mouseup', () => { mode=''; });
cvs.addEventListener('touchstart', function(e) { e.preventDefault(); const t=e.touches[0],p=toCanvas(t.clientX,t.clientY),h=hitHandle(p.x,p.y); if(h) mode=h; else if(hitBox(p.x,p.y)) mode='move'; else return; dragStart={px:p.x,py:p.y,bx:box.x,by:box.y,bs:box.s}; },{passive:false});
document.addEventListener('touchmove', function(e) { if(!mode) return; e.preventDefault(); const t=e.touches[0],p=toCanvas(t.clientX,t.clientY); aplicar(p.x-dragStart.px,p.y-dragStart.py); renderTudo(); renderPreview(); },{passive:false});
document.addEventListener('touchend', () => { mode=''; });
function aplicar(dx,dy) { if(mode==='move'){box.x=clamp(dragStart.bx+dx,0,CW-box.s);box.y=clamp(dragStart.by+dy,0,CH-box.s);return;} const h=mode;let nx=dragStart.bx,ny=dragStart.by,ns=dragStart.bs; if(h==='br')ns=dragStart.bs+Math.max(dx,dy); else if(h==='tl'){const d=Math.max(-dx,-dy);ns=dragStart.bs+d;nx=dragStart.bx+dragStart.bs-ns;ny=dragStart.by+dragStart.bs-ns;} else if(h==='tr'){ns=dragStart.bs+Math.max(dx,-dy);ny=dragStart.by+dragStart.bs-ns;} else if(h==='bl'){ns=dragStart.bs+Math.max(-dx,dy);nx=dragStart.bx+dragStart.bs-ns;} else if(h==='mr')ns=dragStart.bs+dx; else if(h==='ml'){ns=dragStart.bs-dx;nx=dragStart.bx+dragStart.bs-ns;} else if(h==='bm')ns=dragStart.bs+dy; else if(h==='tm'){ns=dragStart.bs-dy;ny=dragStart.by+dragStart.bs-ns;} ns=Math.max(MINBOX,ns);nx=clamp(nx,0,CW-ns);ny=clamp(ny,0,CH-ns); if(nx+ns>CW)ns=CW-nx;if(ny+ns>CH)ns=CH-ny; box.x=nx;box.y=ny;box.s=ns; }
function renderPreview() { const prev=document.getElementById('crop-preview-canvas'),pctx=prev.getContext('2d'); prev.width=prev.height=80; pctx.drawImage(cropImg,box.x/scale,box.y/scale,box.s/scale,box.s/scale,0,0,80,80); }
function confirmarCrop() { const out=document.createElement('canvas'); out.width=out.height=OUT; out.getContext('2d').drawImage(cropImg,box.x/scale,box.y/scale,box.s/scale,box.s/scale,0,0,OUT,OUT); out.toBlob(function(blob){ const nome=filesFila[cropIndex]?.name||('img_'+Date.now()+'.jpg'); arquivosSelecionados.push(new File([blob],nome,{type:'image/jpeg'})); document.getElementById('crop-modal').classList.remove('open'); cropIndex++; setTimeout(proximoCrop,80); },'image/jpeg',0.92); }
function atualizarPreview() { const wrap=document.getElementById('preview-imagens'),cnt=document.getElementById('contador-imagens'); wrap.innerHTML=''; arquivosSelecionados.forEach(function(file,i){ const url=URL.createObjectURL(file); const item=document.createElement('div'); item.className='preview-item'; const img=document.createElement('img'); img.src=url; img.title=file.name; img.onload=()=>URL.revokeObjectURL(url); const btn=document.createElement('button'); btn.className='btn-remover'; btn.type='button'; btn.innerHTML='&times;'; btn.onclick=()=>{arquivosSelecionados.splice(i,1);atualizarPreview();}; item.appendChild(img); item.appendChild(btn); wrap.appendChild(item); }); cnt.textContent=arquivosSelecionados.length?arquivosSelecionados.length+' imagem(ns) selecionada(s).':''; }
function enviarFormulario() { const form=document.getElementById('form-produto'); if(!form.checkValidity()){form.reportValidity();return;} converterPrecosParaEnvio(form); const fd=new FormData(form); fd.delete('imagens[]'); arquivosSelecionados.forEach(f=>fd.append('imagens[]',f)); const btn=document.querySelector('.btn-cadastrar'); btn.textContent='Enviando...'; btn.disabled=true; fetch(window.location.href,{method:'POST',body:fd}).then(r=>r.text()).then(html=>{document.open();document.write(html);document.close();}).catch(err=>{alert('Erro: '+err);btn.textContent='Cadastrar Produto';btn.disabled=false;}); }
function clamp(v,mn,mx){return Math.min(Math.max(v,mn),mx);}
</script>
</body>
</html>
