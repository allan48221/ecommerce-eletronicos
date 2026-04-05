<?php
ob_start();
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

// ── TENANT ──
$id_tenant = $_SESSION['id_tenant'] ?? null;
$is_master = empty($_SESSION['id_tenant']);

// ── Garante coluna id_tenant na tabela ──
$conn->exec("
    CREATE TABLE IF NOT EXISTS impressoras (
        id            SERIAL PRIMARY KEY,
        nome          VARCHAR(100) NOT NULL,
        ip            VARCHAR(45)  NOT NULL DEFAULT '',
        porta         VARCHAR(10)  NOT NULL DEFAULT '9100',
        categorias    TEXT[]       NOT NULL DEFAULT '{}',
        ativo         BOOLEAN      NOT NULL DEFAULT TRUE,
        id_tenant     INTEGER,
        criado_em     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
        atualizado_em TIMESTAMPTZ  NOT NULL DEFAULT NOW()
    )
");
try { $conn->exec("ALTER TABLE impressoras ADD COLUMN IF NOT EXISTS id_tenant INTEGER"); } catch (\Throwable $e) {}

$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

if ($acao === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    if (!isset($body['printers'])) { echo json_encode(['success'=>false,'message'=>'Dados invalidos']); exit; }
    try {
        if ($is_master) {
            $conn->exec("DELETE FROM impressoras");
        } else {
            $conn->prepare("DELETE FROM impressoras WHERE id_tenant = ?")->execute([$id_tenant]);
        }
        $stmt = $conn->prepare("INSERT INTO impressoras (id, nome, ip, porta, categorias, id_tenant) VALUES (:id, :nome, :ip, :porta, :cats, :id_tenant)");
        foreach ($body['printers'] as $p) {
            $cats    = $p['cats'] ?? $p['categorias'] ?? [];
            $catsStr = '{' . implode(',', array_map(fn($c) => '"' . addslashes($c) . '"', $cats)) . '}';
            $stmt->execute([':id'=>intval($p['id']),':nome'=>$p['nome']??'',':ip'=>$p['ip']??'',':porta'=>$p['porta']??'9100',':cats'=>$catsStr,':id_tenant'=>$id_tenant]);
        }
        $conn->exec("SELECT setval('impressoras_id_seq', COALESCE((SELECT MAX(id) FROM impressoras), 0) + 1, false)");
        echo json_encode(['success'=>true]);
    } catch (\Throwable $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

if ($acao === 'carregar') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        if ($is_master) {
            $rows = $conn->query("SELECT id, nome, ip, porta, categorias FROM impressoras WHERE ativo = TRUE ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare("SELECT id, nome, ip, porta, categorias FROM impressoras WHERE ativo = TRUE AND id_tenant = ? ORDER BY id");
            $stmt->execute([$id_tenant]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $result = array_map(function($r) {
            $raw  = trim($r['categorias'] ?? '{}', '{}');
            $cats = $raw === '' ? [] : array_map(fn($v) => trim($v, '"'), explode(',', $raw));
            return ['id'=>intval($r['id']),'nome'=>$r['nome'],'ip'=>$r['ip'],'porta'=>$r['porta'],'cats'=>$cats,'status'=>'offline'];
        }, $rows);
        echo json_encode(['success'=>true,'data'=>$result]);
    } catch (\Throwable $e) { echo json_encode(['success'=>false,'data'=>[],'message'=>$e->getMessage()]); }
    exit;
}

// ── Categorias filtradas por tenant ──
try {
    if ($is_master) {
        $categorias_db = $conn->query("
            SELECT c.id_categoria, c.nome
            FROM categorias c
            INNER JOIN produtos p ON p.id_categoria = c.id_categoria AND p.ativo = TRUE
            GROUP BY c.id_categoria, c.nome ORDER BY c.nome ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);
    } else {
        $stmt_cat = $conn->prepare("
            SELECT c.id_categoria, c.nome
            FROM categorias c
            INNER JOIN produtos p ON p.id_categoria = c.id_categoria AND p.ativo = TRUE AND p.id_tenant = ?
            GROUP BY c.id_categoria, c.nome ORDER BY c.nome ASC
        ");
        $stmt_cat->execute([$id_tenant]);
        $categorias_db = $stmt_cat->fetchAll(\PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) { $categorias_db = []; }

$tema = aplicar_tema($conn); // captura o tema para usar no <head>
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Configuração de Impressoras</title>
<?= $tema ?>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; min-height: 100vh; color: #0f172a; }

.imp-page { max-width: 1200px; margin: 0 auto; padding: 28px 20px 80px; }

.imp-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:10px; }
.imp-btn-back {
  display:inline-flex; align-items:center; gap:6px; padding:10px 20px;
  background:#fff; color:#475569; border:1.5px solid #e2e8f0; border-radius:50px;
  font-size:14px; font-weight:600; text-decoration:none; font-family:'Sora',sans-serif; cursor:pointer; transition:.15s;
}
.imp-btn-back:hover { border-color:var(--primary); color:var(--primary); }
.imp-btn-primary {
  display:inline-flex; align-items:center; gap:8px; padding:11px 22px;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:#fff; border:none; border-radius:50px; font-size:14px; font-weight:700;
  font-family:'Sora',sans-serif; cursor:pointer; transition:.15s;
}
.imp-btn-primary:hover { opacity:.9; transform:translateY(-1px); }

.imp-hero {
  background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 100%);
  border-radius:18px; padding:26px 28px; color:#fff; margin-bottom:24px;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;
}
.imp-hero h1 { font-size:22px; font-weight:800; }
.imp-hero p  { font-size:13px; opacity:.8; margin-top:4px; }
.imp-hero-stats { display:flex; gap:28px; }
.imp-hero-stat  { text-align:center; }
.imp-hero-num   { font-size:32px; font-weight:800; line-height:1; }
.imp-hero-lbl   { font-size:11px; opacity:.75; margin-top:3px; }

.imp-alert { padding:14px 18px; border-radius:12px; font-size:14px; font-weight:500; margin-bottom:20px; border-left:4px solid; }
.imp-alert.success { background:#ecfdf5; color:#065f46; border-color:var(--success); }
.imp-alert.danger  { background:#fef2f2; color:#7f1d1d; border-color:var(--danger); }

.imp-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#64748b; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.imp-badge { background:var(--primary); color:#fff; font-size:11px; font-weight:800; padding:2px 9px; border-radius:20px; }

.imp-cats-painel { background:#fff; border-radius:16px; border:1.5px solid #e2e8f0; padding:18px 20px; margin-bottom:24px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.imp-cats-hint { font-size:12px; color:#64748b; background:#fffbeb; border-left:3px solid var(--warning); border-radius:0 9px 9px 0; padding:9px 12px; margin-bottom:14px; }
.imp-cats-grid { display:flex; flex-wrap:wrap; gap:8px; }
.imp-cat-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:50px; font-size:13px; font-weight:600; cursor:pointer; user-select:none; transition:.15s; }
.imp-cat-chip:hover  { border-color:var(--primary); background:#eff6ff; }
.imp-cat-chip.sel    { border-color:var(--primary); background:#eff6ff; color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.imp-cat-chip.mapped { border-color:var(--success,#10b981); background:#ecfdf5; }
.imp-cat-chip .dot   { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.imp-cat-chip .dest  { font-size:10px; color:#94a3b8; font-weight:400; }
.imp-cats-vazio { font-size:13px; color:#94a3b8; font-style:italic; padding:8px 0; }

.imp-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }

.imp-card { background:#fff; border-radius:16px; border:1.5px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,.05); overflow:hidden; transition:box-shadow .2s,border-color .2s; animation:impIn .28s ease; }
@keyframes impIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.imp-card:hover   { box-shadow:0 6px 20px rgba(0,0,0,.08); border-color:#cbd5e1; }
.imp-card.can-drop{ border-color:var(--primary); border-style:dashed; cursor:copy; }

.imp-card-head { background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 100%); padding:14px 18px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.imp-card-icon { width:42px; height:42px; border-radius:10px; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.imp-card-head-info { flex:1; min-width:0; }
.imp-card-head-nome { color:#fff; font-weight:700; font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.imp-card-head-ip   { color:rgba(255,255,255,.65); font-size:11px; margin-top:2px; }
.imp-card-status { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:700; padding:4px 11px; border-radius:20px; flex-shrink:0; }
.imp-card-status.online  { background:rgba(16,185,129,.2); color:#ecfdf5; border:1px solid rgba(16,185,129,.35); }
.imp-card-status.offline { background:rgba(239,68,68,.2);  color:#fee2e2; border:1px solid rgba(239,68,68,.35); }
.imp-card-status.testing { background:rgba(245,158,11,.2); color:#fef3c7; border:1px solid rgba(245,158,11,.35); }
.sdot { width:7px; height:7px; border-radius:50%; background:currentColor; animation:pls 1.8s infinite; }
@keyframes pls { 0%,100%{opacity:1} 50%{opacity:.3} }

.imp-card-body { padding:18px; }
.imp-field { margin-bottom:12px; }
.imp-field label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#64748b; margin-bottom:5px; }
.imp-field input { width:100%; padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:'Sora',sans-serif; outline:none; transition:.2s; background:#f8fafc; color:#0f172a; }
.imp-field input:focus { border-color:var(--primary); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
.imp-field input::placeholder { color:#94a3b8; }
.imp-ip-row { display:grid; grid-template-columns:1fr 80px; gap:8px; }

.imp-drop-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#64748b; margin-bottom:7px; }
.imp-drop-zone { min-height:42px; border:1.5px dashed #cbd5e1; border-radius:10px; padding:8px; display:flex; flex-wrap:wrap; gap:6px; background:#f8fafc; transition:.2s; }
.imp-drop-zone.over      { border-color:var(--primary); background:#eff6ff; }
.imp-drop-zone.has-items { border-style:solid; border-color:#e2e8f0; background:#fff; }
.imp-drop-empty { font-size:12px; color:#94a3b8; font-style:italic; align-self:center; }
.imp-cat-tag { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; }
.imp-cat-tag .rm { cursor:pointer; opacity:.6; font-size:14px; line-height:1; transition:opacity .15s; }
.imp-cat-tag .rm:hover { opacity:1; }

.imp-card-foot { display:flex; gap:8px; padding:12px 18px; border-top:1px solid #f1f5f9; background:#f8fafc; }
.imp-btn-test { flex:1; display:flex; align-items:center; justify-content:center; gap:7px; padding:9px; border:1.5px solid #e2e8f0; border-radius:9px; background:#fff; color:#475569; font-size:13px; font-weight:700; font-family:'Sora',sans-serif; cursor:pointer; transition:.15s; }
.imp-btn-test:hover { border-color:var(--success,#10b981); color:var(--success,#10b981); background:#ecfdf5; }
.imp-btn-del { width:38px; height:38px; border-radius:9px; border:1.5px solid #e2e8f0; background:#fff; color:#94a3b8; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:15px; transition:.15s; flex-shrink:0; }
.imp-btn-del:hover { border-color:var(--danger); color:var(--danger); background:#fef2f2; }

.imp-empty { grid-column:1/-1; background:#fff; border-radius:16px; border:1.5px dashed #cbd5e1; text-align:center; padding:60px 20px; }
.imp-empty-icon { font-size:44px; opacity:.4; margin-bottom:12px; }
.imp-empty-txt  { font-size:15px; font-weight:700; color:#64748b; }
.imp-empty-sub  { font-size:13px; color:#94a3b8; margin-top:6px; }

.imp-loading { grid-column:1/-1; text-align:center; padding:48px 20px; color:#94a3b8; font-size:14px; }

.imp-save-bar { position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1.5px solid #e2e8f0; box-shadow:0 -4px 20px rgba(0,0,0,.08); padding:14px 24px; display:flex; align-items:center; justify-content:space-between; gap:12px; z-index:100; transform:translateY(100%); transition:transform .3s cubic-bezier(.16,1,.3,1); flex-wrap:wrap; }
.imp-save-bar.show { transform:translateY(0); }
.imp-save-info { font-size:13px; color:#64748b; font-weight:500; }
.imp-save-info strong { color:var(--primary); }
.imp-btn-save { display:flex; align-items:center; gap:8px; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; border:none; padding:12px 28px; border-radius:10px; font-size:14px; font-weight:700; font-family:'Sora',sans-serif; cursor:pointer; transition:.15s; }
.imp-btn-save:hover { opacity:.9; }
.imp-btn-save:disabled { opacity:.6; cursor:not-allowed; }

.imp-toast { position:fixed; top:24px; right:24px; background:#fff; border:1.5px solid #e2e8f0; border-radius:12px; padding:13px 18px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:600; z-index:200; box-shadow:0 8px 24px rgba(0,0,0,.1); transform:translateX(130%); transition:transform .3s cubic-bezier(.16,1,.3,1); max-width:320px; pointer-events:none; }
.imp-toast.show    { transform:translateX(0); }
.imp-toast.success { border-color:var(--success,#10b981); color:#065f46; }
.imp-toast.error   { border-color:var(--danger); color:#7f1d1d; }
.imp-toast.info    { border-color:var(--primary); color:var(--primary); }

.imp-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; align-items:center; justify-content:center; padding:20px; }
.imp-overlay.show { display:flex; }
.imp-modal { background:#fff; border-radius:18px; padding:26px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.imp-modal h3  { font-size:17px; font-weight:800; margin-bottom:6px; }
.imp-modal-sub { font-size:13px; color:#64748b; margin-bottom:18px; }
.imp-modal-btns { display:flex; gap:10px; margin-top:18px; }
.imp-btn-cancel { padding:11px 18px; background:#f1f5f9; color:#64748b; border:none; border-radius:10px; font-size:13px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; }
.imp-btn-danger-confirm { flex:1; padding:11px; background:var(--danger); color:#fff; border:none; border-radius:10px; font-size:14px; font-weight:700; font-family:'Sora',sans-serif; cursor:pointer; }

@media (max-width:1100px) { .imp-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:600px)  { .imp-page { padding:16px 12px 70px; } .imp-hero { border-radius:14px; padding:18px; } .imp-hero h1 { font-size:18px; } .imp-hero-stats { gap:16px; } .imp-hero-num { font-size:24px; } .imp-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="imp-page">

  <div class="imp-topbar">
    <a href="admin.php" class="imp-btn-back">&#8592; Voltar ao Admin</a>
    <button class="imp-btn-primary" onclick="addImpressora()">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Nova Impressora
    </button>
  </div>

  <div class="imp-hero">
    <div>
      <h1>Configuração de Impressoras</h1>
      <p>Defina os IPs e as categorias de cada impressora da rede</p>
    </div>
    <div class="imp-hero-stats">
      <div class="imp-hero-stat"><div class="imp-hero-num" id="stat-total">—</div><div class="imp-hero-lbl">Impressoras</div></div>
      <div class="imp-hero-stat"><div class="imp-hero-num" id="stat-online" style="color:#6ee7b7;">—</div><div class="imp-hero-lbl">Online</div></div>
      <div class="imp-hero-stat"><div class="imp-hero-num" id="stat-cats" style="color:#fde68a;">—</div><div class="imp-hero-lbl">Categorias</div></div>
    </div>
  </div>

  <div class="imp-cats-painel">
    <div class="imp-section-title">
      Categorias de Produtos
      <span class="imp-badge" id="badge-livres">—</span>
    </div>
    <div class="imp-cats-hint">
      💡 Clique em uma categoria para selecioná-la, depois clique no card da impressora — ou arraste diretamente para o card.
    </div>
    <div class="imp-cats-grid" id="catsGrid">
      <span class="imp-cats-vazio">Carregando categorias...</span>
    </div>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
    <div class="imp-section-title" style="margin-bottom:0;">
      Impressoras <span class="imp-badge" id="badge-imps">—</span>
    </div>
    <span id="dica-sel" style="font-size:12px;color:var(--primary);font-weight:600;"></span>
  </div>

  <div class="imp-grid" id="impGrid">
    <div class="imp-loading">Carregando impressoras...</div>
  </div>

</div>

<div class="imp-save-bar" id="saveBar">
  <div class="imp-save-info">Você tem alterações <strong>não salvas</strong></div>
  <button class="imp-btn-save" id="btnSave" onclick="salvarTudo()">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    Salvar Configurações
  </button>
</div>

<div class="imp-toast" id="toast"></div>

<div class="imp-overlay" id="overlayDel">
  <div class="imp-modal">
    <h3>Remover Impressora</h3>
    <div class="imp-modal-sub" id="modalDelSub">As categorias atribuídas serão liberadas.</div>
    <div class="imp-modal-btns">
      <button class="imp-btn-cancel" onclick="fecharDel()">Cancelar</button>
      <button class="imp-btn-danger-confirm" onclick="confirmarDel()">Remover</button>
    </div>
  </div>
</div>

<script>
// ── Categorias vindas do banco via PHP ──────────────────────
// Mapa de emojis/cores por nome (case-insensitive)
const EMOJI_MAP = {
  'lanches':'🍔','pizzas':'🍕','massas':'🍝','grelhados':'🥩','porcoes':'🍟',
  'porções':'🍟','saladas':'🥗','sobremesas':'🍰','bebidas':'🍺','sucos':'🍹',
  'drinks':'🍸','carnes':'🥩','frutos do mar':'🦐','café':'☕','doces':'🍫',
  'salgados':'🥐','combos':'🍱','kids':'🧒','vegano':'🥦','fitness':'🏋️',
};
const COR_MAP = {
  'lanches':'#f97316','pizzas':'#ef4444','massas':'#ca8a04','grelhados':'#dc2626',
  'porcoes':'#d97706','porções':'#d97706','saladas':'#16a34a','sobremesas':'#db2777',
  'bebidas':'#2563eb','sucos':'#0891b2','drinks':'#7c3aed','carnes':'#dc2626',
  'frutos do mar':'#0891b2','café':'#92400e','doces':'#db2777','salgados':'#d97706',
  'combos':'#7c3aed','kids':'#f97316','vegano':'#16a34a','fitness':'#059669',
};
const BG_MAP = {
  'lanches':'#fff7ed','pizzas':'#fef2f2','massas':'#fefce8','grelhados':'#fef2f2',
  'porcoes':'#fffbeb','porções':'#fffbeb','saladas':'#f0fdf4','sobremesas':'#fdf2f8',
  'bebidas':'#eff6ff','sucos':'#ecfeff','drinks':'#f5f3ff','carnes':'#fef2f2',
  'frutos do mar':'#ecfeff','café':'#fef3c7','doces':'#fdf2f8','salgados':'#fffbeb',
  'combos':'#f5f3ff','kids':'#fff7ed','vegano':'#f0fdf4','fitness':'#ecfdf5',
};
const CORES_FALLBACK = ['#6366f1','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#0284c7','#16a34a'];
const BG_FALLBACK    = ['#eef2ff','#ecfeff','#ecfdf5','#fffbeb','#fef2f2','#f5f3ff','#e0f2fe','#f0fdf4'];

// Categorias injetadas pelo PHP
const CATS_RAW = <?php echo json_encode(array_values($categorias_db)); ?>;

// Enriquece com emoji/cor
const CATS = CATS_RAW.map((c, i) => {
  const key = c.nome.toLowerCase().trim();
  return {
    id:    String(c.id_categoria),
    nome:  c.nome,
    emoji: EMOJI_MAP[key] || '🍽️',
    cor:   COR_MAP[key]   || CORES_FALLBACK[i % CORES_FALLBACK.length],
    bg:    BG_MAP[key]    || BG_FALLBACK[i % BG_FALLBACK.length],
  };
});

// ── Estado ──────────────────────────────────────────────────
let imps    = [];
let nextId  = 1;
let dirty   = false;
let catSel  = null;
let dragCat = null;
let delId   = null;

// ── Init: carrega impressoras do banco ───────────────────────
async function init() {
  renderCats(); // mostra categorias imediatamente (já vêm do PHP)

  try {
    const r   = await fetch('impressoras_ajax.php?acao=carregar');
    const txt = await r.text();
    let data;
    try { data = JSON.parse(txt); }
    catch(pe) {
      console.error('Carregar - resposta não é JSON:', txt.substring(0, 300));
      toast('Arquivo deve ser .php — PHP não está sendo executado', 'error');
      return;
    }
    if (data.success) {
      imps   = data.data;
      nextId = imps.length ? Math.max(...imps.map(p => p.id)) + 1 : 1;
    } else {
      toast('Erro ao carregar: ' + (data.message || ''), 'error');
      console.error('Erro PHP ao carregar:', data.message);
    }
  } catch(e) {
    console.error('Erro de conexão ao carregar:', e);
    toast('Erro de conexão ao carregar impressoras', 'error');
  }

  renderCats();
  renderImps();
}

// ── Categorias ───────────────────────────────────────────────
function getImpByCat(catId) { return imps.find(p => p.cats.includes(String(catId))) || null; }
function getCat(catId)      { return CATS.find(c => c.id === String(catId)); }

function renderCats() {
  const grid = document.getElementById('catsGrid');

  if (!CATS.length) {
    grid.innerHTML = '<span class="imp-cats-vazio">Nenhuma categoria com produtos ativos encontrada.</span>';
    document.getElementById('badge-livres').textContent = '0 livres';
    return;
  }

  let livres = 0;
  grid.innerHTML = CATS.map(cat => {
    const imp   = getImpByCat(cat.id);
    if (!imp) livres++;
    const isSel = catSel === cat.id;
    let cls = 'imp-cat-chip';
    if (isSel)    cls += ' sel';
    else if (imp) cls += ' mapped';
    const dest = imp ? `<span class="dest">→ ${esc(imp.nome || '?')}</span>` : '';
    return `<div class="${cls}" draggable="true"
        ondragstart="onDragStart(event,'${cat.id}')" ondragend="onDragEnd()"
        onclick="selCat('${cat.id}')">
      <span class="dot" style="background:${cat.cor}"></span>
      ${cat.emoji} ${esc(cat.nome)} ${dest}
    </div>`;
  }).join('');

  document.getElementById('badge-livres').textContent = livres + ' livres';
  atualizarStats();
}

function selCat(catId) {
  catSel = (catSel === catId) ? null : catId;
  renderCats();
  renderImps();
  const cat = getCat(catId);
  document.getElementById('dica-sel').textContent = catSel
    ? `👆 Clique em uma impressora para atribuir "${cat?.nome}"`
    : '';
  if (catSel) toast(`"${cat?.nome}" selecionada — clique em uma impressora`, 'info');
  else        toast('Seleção cancelada', 'info');
}

function onDragStart(e, catId) { dragCat = catId; e.dataTransfer.effectAllowed = 'move'; }
function onDragEnd()           { dragCat = null; }

// ── Impressoras ──────────────────────────────────────────────
function addImpressora() {
  imps.push({ id: nextId++, nome: '', ip: '', porta: '9100', cats: [], status: 'offline' });
  marcar();
  renderCats();
  renderImps();
  // Foca no input nome da nova impressora
  setTimeout(() => {
    const inputs = document.querySelectorAll('.imp-card .imp-field input');
    if (inputs.length) inputs[inputs.length - 2]?.focus();
  }, 100);
}

function removerCat(impId, catId) {
  const imp = imps.find(p => p.id === impId);
  if (imp) { imp.cats = imp.cats.filter(c => c !== String(catId)); marcar(); renderCats(); renderImps(); }
}

function atribuir(impId, catId) {
  const cid = String(catId);
  imps.forEach(p => { p.cats = p.cats.filter(c => c !== cid); });
  const imp = imps.find(p => p.id === impId);
  if (imp && !imp.cats.includes(cid)) {
    imp.cats.push(cid);
    marcar();
    const cat = getCat(cid);
    toast(`"${cat?.nome}" → "${imp.nome || 'Impressora'}"`, 'success');
  }
  catSel = null;
  document.getElementById('dica-sel').textContent = '';
  renderCats();
  renderImps();
}

function upd(id, campo, val) {
  const imp = imps.find(p => p.id === id);
  if (imp) { imp[campo] = val; marcar(); }
  // Atualiza apenas o topo do card sem re-renderizar tudo
  if (campo === 'nome') {
    const el = document.querySelector(`[data-id="${id}"] .imp-card-head-nome`);
    if (el) el.innerHTML = esc(val) || '<span style="opacity:.55;font-style:italic;">Sem nome</span>';
  }
  if (campo === 'ip' || campo === 'porta') {
    const imp2 = imps.find(p => p.id === id);
    const el   = document.querySelector(`[data-id="${id}"] .imp-card-head-ip`);
    if (el && imp2) el.textContent = imp2.ip ? `${imp2.ip}:${imp2.porta}` : 'IP não configurado';
  }
}

function emoji(imp) {
  if (imp.cats.some(c => { const cat = getCat(c); return cat && ['bebidas','sucos','drinks'].some(k => cat.nome.toLowerCase().includes(k)); })) return '🍺';
  if (imp.cats.some(c => { const cat = getCat(c); return cat && cat.nome.toLowerCase().includes('sobremesa'); })) return '🍰';
  if (imp.cats.length) return '👨‍🍳';
  return '🖨️';
}

function renderImps() {
  const grid = document.getElementById('impGrid');
  document.getElementById('badge-imps').textContent = imps.length;

  if (!imps.length) {
    grid.innerHTML = `<div class="imp-empty">
      <div class="imp-empty-icon">🖨️</div>
      <div class="imp-empty-txt">Nenhuma impressora configurada</div>
      <div class="imp-empty-sub">Clique em <strong>Nova Impressora</strong> para começar</div>
    </div>`;
    atualizarStats(); return;
  }

  grid.innerHTML = '';
  const stLbl = { online:'Online', offline:'Offline', testing:'Testando...' };

  imps.forEach(imp => {
    const card = document.createElement('div');
    card.className = 'imp-card' + (catSel ? ' can-drop' : '');
    card.dataset.id = imp.id;

    const ipExib   = imp.ip ? `${esc(imp.ip)}:${esc(imp.porta)}` : 'IP não configurado';
    const nomeTopo = esc(imp.nome) || '<span style="opacity:.55;font-style:italic;">Sem nome</span>';

    const catsHtml = imp.cats.length
      ? imp.cats.map(cid => {
          const c = getCat(cid); if (!c) return '';
          return `<span class="imp-cat-tag" style="background:${c.bg};color:${c.cor};">
            ${c.emoji} ${esc(c.nome)}
            <span class="rm" onclick="event.stopPropagation();removerCat(${imp.id},'${cid}')" title="Remover">×</span>
          </span>`;
        }).join('')
      : `<span class="imp-drop-empty">Sem categorias — arraste ou clique aqui</span>`;

    card.innerHTML = `
      <div class="imp-card-head">
        <div class="imp-card-icon">${emoji(imp)}</div>
        <div class="imp-card-head-info">
          <div class="imp-card-head-nome">${nomeTopo}</div>
          <div class="imp-card-head-ip">${ipExib}</div>
        </div>
        <div class="imp-card-status ${imp.status}">
          <div class="sdot"></div>${stLbl[imp.status] || 'Offline'}
        </div>
      </div>
      <div class="imp-card-body">
        <div class="imp-field">
          <label>Nome</label>
          <input type="text" value="${esc(imp.nome)}" placeholder="Ex: Cozinha Principal"
            oninput="upd(${imp.id},'nome',this.value)">
        </div>
        <div class="imp-field">
          <label>IP e Porta</label>
          <div class="imp-ip-row">
            <input type="text" value="${esc(imp.ip)}" placeholder="192.168.1.X"
              oninput="upd(${imp.id},'ip',this.value)">
            <input type="text" value="${esc(imp.porta)}" placeholder="9100"
              oninput="upd(${imp.id},'porta',this.value)">
          </div>
        </div>
        <div class="imp-drop-label">Categorias → esta impressora</div>
        <div class="imp-drop-zone ${imp.cats.length ? 'has-items' : ''}" id="dz-${imp.id}"
          ondragover="event.preventDefault();this.classList.add('over')"
          ondragleave="this.classList.remove('over')"
          ondrop="onDrop(event,${imp.id})">
          ${catsHtml}
        </div>
      </div>
      <div class="imp-card-foot">
        <button class="imp-btn-test" onclick="testar(${imp.id})">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9v6m4-8v10m4-6v4m4-7v9"/>
          </svg>
          Testar Impressão
        </button>
        <button class="imp-btn-del" onclick="abrirDel(${imp.id})" title="Remover">🗑</button>
      </div>`;

    card.addEventListener('click', e => {
      if (!catSel) return;
      if (e.target.closest('.rm') || e.target.closest('input') || e.target.closest('.imp-btn-test') || e.target.closest('.imp-btn-del')) return;
      atribuir(imp.id, catSel);
    });

    grid.appendChild(card);
  });

  atualizarStats();
}

function onDrop(e, impId) {
  e.preventDefault();
  document.getElementById('dz-' + impId)?.classList.remove('over');
  if (dragCat) { atribuir(impId, dragCat); dragCat = null; }
}

// ── Testar ───────────────────────────────────────────────────
async function testar(id) {
  const imp = imps.find(p => p.id === id);
  if (!imp?.ip?.trim()) { toast('Informe o IP antes de testar', 'error'); return; }
  imp.status = 'testing'; renderImps();
  try {
    const r = await fetch('printer_test.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ip: imp.ip, porta: imp.porta || '9100', nome: imp.nome })
    });
    const d = await r.json();
    imp.status = d.success ? 'online' : 'offline';
    toast(d.success ? `✓ "${imp.nome}" respondeu!` : `Falha: ${d.message}`, d.success ? 'success' : 'error');
  } catch(e) {
    imp.status = 'offline';
    toast('Erro de conexão com o servidor', 'error');
  }
  renderImps();
}

// ── Deletar ──────────────────────────────────────────────────
function abrirDel(id) {
  delId = id;
  const imp = imps.find(p => p.id === id);
  document.getElementById('modalDelSub').textContent = `Remover "${imp?.nome || 'impressora'}"? As categorias serão liberadas.`;
  document.getElementById('overlayDel').classList.add('show');
}
function fecharDel() { document.getElementById('overlayDel').classList.remove('show'); delId = null; }
function confirmarDel() {
  if (delId === null) return;
  imps = imps.filter(p => p.id !== delId);
  marcar(); fecharDel(); renderCats(); renderImps();
  toast('Impressora removida', 'info');
}
document.getElementById('overlayDel').addEventListener('click', e => { if (e.target === e.currentTarget) fecharDel(); });

// ── Salvar ───────────────────────────────────────────────────
async function salvarTudo() {
  const btn = document.getElementById('btnSave');
  btn.textContent = 'Salvando...'; btn.disabled = true;
  try {
    const r = await fetch('impressoras_ajax.php?acao=salvar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ printers: imps })
    });
    const txt = await r.text();
    let data;
    try { data = JSON.parse(txt); }
    catch(pe) {
      // Resposta não é JSON — provavelmente PHP não está sendo executado
      console.error('Resposta do servidor (não é JSON):', txt.substring(0, 300));
      toast('Erro: servidor retornou HTML. Verifique se o arquivo é .php', 'error');
      btn.innerHTML = '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Salvar Configurações';
      btn.disabled = false;
      return;
    }
    if (data.success) {
      dirty = false;
      document.getElementById('saveBar').classList.remove('show');
      toast('✓ Configurações salvas com sucesso!', 'success');
    } else {
      toast('Erro ao salvar: ' + (data.message || ''), 'error');
      console.error('Erro PHP:', data.message);
    }
  } catch(e) {
    console.error('Erro de conexão:', e);
    toast('Erro de conexão ao salvar: ' + e.message, 'error');
  }
  btn.innerHTML = '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Salvar Configurações';
  btn.disabled = false;
}

// ── Helpers ──────────────────────────────────────────────────
function marcar() { dirty = true; document.getElementById('saveBar').classList.add('show'); }

function atualizarStats() {
  document.getElementById('stat-total').textContent  = imps.length;
  document.getElementById('stat-online').textContent = imps.filter(p => p.status === 'online').length;
  const mapped = new Set(imps.flatMap(p => p.cats));
  document.getElementById('stat-cats').textContent   = mapped.size;
}

function toast(msg, tipo = 'info') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = `imp-toast ${tipo} show`;
  clearTimeout(t._t);
  t._t = setTimeout(() => t.classList.remove('show'), 3500);
}

function esc(s) {
  return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

init();
</script>
</body>
</html>
