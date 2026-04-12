<?php
require_once 'config/database.php';
require_once 'config/tema.php';
require_once 'empresa_helper.php';

if (!isset($_SESSION['id_admin']) || !is_numeric($_SESSION['id_admin'])) {
    header('Location: login.php'); exit;
}

$id_tenant = $_SESSION['id_tenant'] ?? null;

// Garante que a tabela existe
$conn->exec("
    CREATE TABLE IF NOT EXISTS empresa (
        id                   SERIAL PRIMARY KEY,
        id_tenant            INTEGER,
        nome_empresa         VARCHAR(200) NOT NULL DEFAULT '',
        nome_fantasia        VARCHAR(200) NOT NULL DEFAULT '',
        cnpj                 VARCHAR(20)  NOT NULL DEFAULT '',
        telefone             VARCHAR(20)  NOT NULL DEFAULT '',
        celular              VARCHAR(20)  NOT NULL DEFAULT '',
        email                VARCHAR(200) NOT NULL DEFAULT '',
        nome_responsavel     VARCHAR(200) NOT NULL DEFAULT '',
        cep                  VARCHAR(10)  NOT NULL DEFAULT '',
        endereco             VARCHAR(200) NOT NULL DEFAULT '',
        numero               VARCHAR(20)  NOT NULL DEFAULT '',
        complemento          VARCHAR(100) NOT NULL DEFAULT '',
        bairro               VARCHAR(100) NOT NULL DEFAULT '',
        cidade               VARCHAR(100) NOT NULL DEFAULT '',
        uf                   VARCHAR(2)   NOT NULL DEFAULT '',
        site                 VARCHAR(200) NOT NULL DEFAULT '',
        instagram            VARCHAR(200) NOT NULL DEFAULT '',
        whatsapp             VARCHAR(20)  NOT NULL DEFAULT '',
        horario_atendimento  VARCHAR(200) NOT NULL DEFAULT '',
        formas_pagamento     VARCHAR(200) NOT NULL DEFAULT '',
        descricao_loja       TEXT         NOT NULL DEFAULT '',
        logo                 VARCHAR(300) NOT NULL DEFAULT '',
        atualizado_em        TIMESTAMPTZ  NOT NULL DEFAULT NOW()
    )
");

foreach ([
    "ALTER TABLE empresa ADD COLUMN IF NOT EXISTS id_tenant            INTEGER",
    "ALTER TABLE empresa ADD COLUMN IF NOT EXISTS instagram            VARCHAR(200) NOT NULL DEFAULT ''",
    "ALTER TABLE empresa ADD COLUMN IF NOT EXISTS whatsapp             VARCHAR(20)  NOT NULL DEFAULT ''",
    "ALTER TABLE empresa ADD COLUMN IF NOT EXISTS horario_atendimento  VARCHAR(200) NOT NULL DEFAULT ''",
    "ALTER TABLE empresa ADD COLUMN IF NOT EXISTS formas_pagamento     VARCHAR(200) NOT NULL DEFAULT ''",
    "ALTER TABLE empresa ADD COLUMN IF NOT EXISTS descricao_loja       TEXT         NOT NULL DEFAULT ''",
    "ALTER TABLE empresa ADD COLUMN IF NOT EXISTS logo                 VARCHAR(300) NOT NULL DEFAULT ''",
    "ALTER TABLE empresa ALTER COLUMN logo TYPE VARCHAR(600)",
] as $alter) {
    try { $conn->exec($alter); } catch (\Throwable $e) {}
}

// Garante que sempre existe 1 registro por tenant
$stmtExiste = $conn->prepare("SELECT id FROM empresa WHERE id_tenant = ? LIMIT 1");
$stmtExiste->execute([$id_tenant]);
$existe = $stmtExiste->fetch();
if (!$existe) {
    $conn->prepare("INSERT INTO empresa (nome_empresa, id_tenant) VALUES ('', ?)")->execute([$id_tenant]);
}

$msg      = '';
$tipo_msg = '';

// Salvar / remover / toggle de locais
if (isset($_POST['acao_local'])) {
    if ($_POST['acao_local'] === 'adicionar') {
        $stmt = $conn->prepare("
            INSERT INTO locais_permitidos (id_tenant, nome, lat, lng, raio_metros)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_tenant,
            trim($_POST['local_nome']),
            $_POST['local_lat'],
            $_POST['local_lng'],
            intval($_POST['local_raio'])
        ]);
    }
    if ($_POST['acao_local'] === 'remover') {
        $conn->prepare("DELETE FROM locais_permitidos WHERE id = ? AND id_tenant = ?")
             ->execute([$_POST['local_id'], $id_tenant]);
    }
    if ($_POST['acao_local'] === 'toggle') {
        $conn->prepare("UPDATE locais_permitidos SET ativo = NOT ativo WHERE id = ? AND id_tenant = ?")
             ->execute([$_POST['local_id'], $id_tenant]);
    }
    header('Location: empresa.php#secao-locais');
    exit;
}

// Carrega locais
$stmt_locais = $conn->prepare("SELECT * FROM locais_permitidos WHERE id_tenant = ? ORDER BY criado_em DESC");
$stmt_locais->execute([$id_tenant]);
$locais = $stmt_locais->fetchAll(PDO::FETCH_ASSOC);

// POST: Salvar dados da empresa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmtLogoAtual = $conn->prepare("SELECT logo FROM empresa WHERE id_tenant = ? LIMIT 1");
    $stmtLogoAtual->execute([$id_tenant]);
    $logoAtual = $stmtLogoAtual->fetchColumn() ?: '';

    $campos = [
        'id_tenant'           => $id_tenant,
        'nome_empresa'        => trim($_POST['nome_empresa']        ?? ''),
        'nome_fantasia'       => trim($_POST['nome_fantasia']       ?? ''),
        'cnpj'                => preg_replace('/\D/', '', $_POST['cnpj'] ?? ''),
        'telefone'            => trim($_POST['telefone']            ?? ''),
        'celular'             => trim($_POST['celular']             ?? ''),
        'email'               => trim($_POST['email']               ?? ''),
        'nome_responsavel'    => trim($_POST['nome_responsavel']    ?? ''),
        'cep'                 => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
        'endereco'            => trim($_POST['endereco']            ?? ''),
        'numero'              => trim($_POST['numero']              ?? ''),
        'complemento'         => trim($_POST['complemento']        ?? ''),
        'bairro'              => trim($_POST['bairro']              ?? ''),
        'cidade'              => trim($_POST['cidade']              ?? ''),
        'uf'                  => strtoupper(trim($_POST['uf']       ?? '')),
        'site'                => trim($_POST['site']                ?? ''),
        'instagram'           => trim($_POST['instagram']           ?? ''),
        'whatsapp'            => preg_replace('/\D/', '', $_POST['whatsapp'] ?? ''),
        'horario_atendimento' => trim($_POST['horario_atendimento'] ?? ''),
        'formas_pagamento'    => trim($_POST['formas_pagamento']    ?? ''),
        'descricao_loja'      => trim($_POST['descricao_loja']      ?? ''),
        'logo'                => $logoAtual,
    ];

    if (!empty($_FILES['logo']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            try {
                require_once __DIR__ . '/config/cloudinary.php';
                $url_logo = cloudinary_upload($_FILES['logo']['tmp_name'], 'logos');
                $campos['logo'] = $url_logo;
            } catch (Exception $e) {
                $msg = 'Erro ao enviar logo: ' . $e->getMessage();
                $tipo_msg = 'danger';
            }
        } else {
            $msg = 'Logo invalida (max 2MB, formatos: JPG, PNG, WEBP).';
            $tipo_msg = 'danger';
        }
    }

    if (empty($campos['nome_empresa'])) {
        $msg = 'O nome da empresa e obrigatorio.'; $tipo_msg = 'danger';
    } elseif ($tipo_msg !== 'danger') {
        try {
            $conn->prepare("
                UPDATE empresa SET
                    nome_empresa         = :nome_empresa,
                    nome_fantasia        = :nome_fantasia,
                    cnpj                 = :cnpj,
                    telefone             = :telefone,
                    celular              = :celular,
                    email                = :email,
                    nome_responsavel     = :nome_responsavel,
                    cep                  = :cep,
                    endereco             = :endereco,
                    numero               = :numero,
                    complemento          = :complemento,
                    bairro               = :bairro,
                    cidade               = :cidade,
                    uf                   = :uf,
                    site                 = :site,
                    instagram            = :instagram,
                    whatsapp             = :whatsapp,
                    horario_atendimento  = :horario_atendimento,
                    formas_pagamento     = :formas_pagamento,
                    descricao_loja       = :descricao_loja,
                    logo                 = :logo,
                    atualizado_em        = NOW()
                WHERE id_tenant = :id_tenant
            ")->execute($campos);
            $msg = 'Dados da empresa salvos com sucesso!'; $tipo_msg = 'success';
        } catch (\Throwable $e) {
            $msg = 'Erro ao salvar: ' . $e->getMessage(); $tipo_msg = 'danger';
        }
    }
}

// Carrega dados atuais
$stmtEmp = $conn->prepare("SELECT * FROM empresa WHERE id_tenant = ? LIMIT 1");
$stmtEmp->execute([$id_tenant]);
$empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC);

function fmtCnpj(string $v): string {
    $v = preg_replace('/\D/', '', $v);
    if (strlen($v) === 14)
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
    return $v;
}
function fmtCep(string $v): string {
    $v = preg_replace('/\D/', '', $v);
    if (strlen($v) === 8) return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $v);
    return $v;
}
function fmtWpp(string $v): string {
    $v = preg_replace('/\D/', '', $v);
    if (strlen($v) === 11) return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $v);
    if (strlen($v) === 10) return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $v);
    return $v;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Dados da Empresa</title>
<?= aplicar_tema($conn) ?>
<link rel="icon" href="/favicon.png" type="image/png">
<link rel="shortcut icon" href="/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; background: var(--dash-bg, #f1f5f9); min-height: 100vh; color: #0f172a; }

.emp-page { max-width: 860px; margin: 0 auto; padding: 28px 20px 80px; }

.emp-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:10px; }
.emp-btn-back { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:#fff; color:#475569; border:1.5px solid #e2e8f0; border-radius:50px; font-size:14px; font-weight:600; text-decoration:none; font-family:'Sora',sans-serif; transition:.15s; }
.emp-btn-back:hover { border-color:var(--primary); color:var(--primary); }

.emp-hero { background:linear-gradient(135deg, var(--primary-dark, #1e40af) 0%, var(--primary, #2563eb) 100%); border-radius:18px; padding:26px 28px; color:#fff; margin-bottom:24px; }
.emp-hero h1 { font-size:22px; font-weight:800; }
.emp-hero p  { font-size:13px; opacity:.8; margin-top:4px; }

.emp-alert { padding:14px 18px; border-radius:12px; font-size:14px; font-weight:500; margin-bottom:20px; border-left:4px solid; }
.emp-alert.success { background:#ecfdf5; color:#065f46; border-color:#10b981; }
.emp-alert.danger  { background:#fef2f2; color:#7f1d1d; border-color:#ef4444; }

.emp-card { background:#fff; border-radius:16px; border:1.5px solid #e2e8f0; overflow:hidden; margin-bottom:16px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.emp-card-head { padding:14px 20px; background:#f8fafc; border-bottom:1.5px solid #e2e8f0; display:flex; align-items:center; gap:10px; }
.emp-card-head h2 { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#64748b; }
.emp-card-head .ico { width:32px; height:32px; border-radius:8px; background:var(--primary,#2563eb); display:flex; align-items:center; justify-content:center; font-size:15px; color:#fff; font-weight:700; }
.emp-card-body { padding:20px; }

.emp-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.emp-grid.three { grid-template-columns:1fr 1fr 1fr; }
@media (max-width:600px) { .emp-grid, .emp-grid.three { grid-template-columns:1fr; } }

.emp-field { display:flex; flex-direction:column; gap:5px; }
.emp-field label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#64748b; }
.emp-field input,
.emp-field textarea { padding:10px 13px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:'Sora',sans-serif; outline:none; transition:.2s; background:#f8fafc; color:#0f172a; }
.emp-field textarea { resize:vertical; min-height:80px; }
.emp-field input:focus,
.emp-field textarea:focus { border-color:var(--primary,#2563eb); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
.emp-field input::placeholder,
.emp-field textarea::placeholder { color:#94a3b8; }
.emp-field .hint { font-size:11px; color:#94a3b8; }

.emp-input-prefix { display:flex; align-items:center; border:1.5px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f8fafc; transition:.2s; }
.emp-input-prefix:focus-within { border-color:var(--primary,#2563eb); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
.emp-prefix-label { padding:10px 12px; font-size:13px; font-weight:600; color:#94a3b8; background:#f1f5f9; border-right:1.5px solid #e2e8f0; white-space:nowrap; }
.emp-input-prefix input { border:none !important; border-radius:0 !important; background:transparent !important; box-shadow:none !important; flex:1; padding:10px 12px; font-size:14px; font-family:'Sora',sans-serif; outline:none; color:#0f172a; }

.span-2 { grid-column: span 2; }
@media (max-width:600px) { .span-2 { grid-column: span 1; } }

.emp-preview { background:#1e1b4b; border-radius:12px; padding:20px 24px; margin-top:4px; }
.emp-preview-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#94a3b8; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
.emp-preview-inner { display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; }
.emp-preview-col h4 { font-size:13px; font-weight:700; color:#fff; margin-bottom:6px; }
.emp-preview-col p, .emp-preview-col a { font-size:12px; color:rgba(255,255,255,.7); display:block; margin-bottom:3px; text-decoration:none; }

.emp-pag-grid { display:flex; flex-wrap:wrap; gap:8px; }
.emp-pag-pill { position:relative; }
.emp-pag-pill input[type=checkbox] { position:absolute; opacity:0; width:0; height:0; }
.emp-pag-pill label { display:flex; align-items:center; gap:6px; padding:8px 14px; border:1.5px solid #e2e8f0; border-radius:20px; font-size:13px; font-weight:600; color:#475569; cursor:pointer; transition:.15s; background:#f8fafc; font-family:'Sora',sans-serif; }
.emp-pag-pill input:checked + label { border-color:var(--primary,#2563eb); background:#eff6ff; color:var(--primary,#2563eb); }

.emp-btn-save { display:inline-flex; align-items:center; gap:8px; padding:13px 32px; background:linear-gradient(135deg,var(--primary,#2563eb),var(--primary-dark,#1e40af)); color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:700; font-family:'Sora',sans-serif; cursor:pointer; transition:.15s; }
.emp-btn-save:hover { opacity:.9; transform:translateY(-1px); }

.emp-cnpj-atual { display:inline-flex; align-items:center; gap:6px; background:#eff6ff; color:#1e40af; font-size:12px; font-weight:700; padding:4px 12px; border-radius:20px; margin-top:4px; }

.emp-logo-preview { display:flex; align-items:center; gap:16px; padding:14px; background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; margin-bottom:10px; }
.emp-logo-preview img { max-height:64px; max-width:180px; object-fit:contain; border-radius:8px; }
.emp-logo-preview .emp-logo-info { font-size:12px; color:#64748b; }
.emp-logo-preview .emp-logo-info strong { display:block; color:#0f172a; margin-bottom:2px; }
.emp-file-input { padding:10px 13px; border:1.5px dashed #cbd5e1; border-radius:10px; font-size:13px; font-family:'Sora',sans-serif; background:#f8fafc; cursor:pointer; width:100%; }
.emp-file-input:hover { border-color:var(--primary,#2563eb); background:#eff6ff; }

/* Locais permitidos */
.local-card { background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:8px; transition:.2s; }
.local-card:hover { border-color:var(--primary,#2563eb); }
.local-card.inativo { opacity:.5; }
.local-nome { font-size:14px; font-weight:700; color:#0f172a; }
.local-info { font-size:12px; color:#64748b; margin-top:2px; }
.local-acoes { display:flex; gap:6px; flex-shrink:0; }
.btn-local { padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:1.5px solid; font-family:'Sora',sans-serif; transition:.15s; }
.btn-local-toggle { border-color:#e2e8f0; background:#fff; color:#475569; }
.btn-local-toggle:hover { border-color:#94a3b8; }
.btn-local-del { border-color:#fecaca; background:#fef2f2; color:#dc2626; }
.btn-local-del:hover { background:#dc2626; color:#fff; }
#mapa-local { width:100%; height:320px; border-radius:12px; border:1.5px solid #e2e8f0; margin:12px 0; }
#mapa-todos { width:100%; height:260px; border-radius:12px; border:1.5px solid #e2e8f0; }
.raio-wrap { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.raio-wrap input[type=range] { flex:1; }
.raio-num { font-size:14px; font-weight:700; min-width:46px; color:var(--primary,#2563eb); }
.tag-ativo   { background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; }
.tag-inativo { background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; }
</style>
</head>
<body>
<div class="emp-page">

    <div class="emp-topbar">
        <a href="admin.php" class="emp-btn-back">&larr; Voltar ao Admin</a>
    </div>

    <div class="emp-hero">
        <h1>Dados da Empresa</h1>
        <p>Informacoes exibidas no rodape da loja, comprovantes e documentos do sistema</p>
    </div>

    <?php if ($msg): ?>
    <div class="emp-alert <?= $tipo_msg ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" enctype="multipart/form-data">

        <!-- IDENTIFICACAO -->
        <div class="emp-card">
            <div class="emp-card-head">
                <div class="ico">ID</div>
                <h2>Identificacao</h2>
            </div>
            <div class="emp-card-body">
                <div class="emp-grid">
                    <div class="emp-field span-2">
                        <label>Razao Social / Nome da Empresa *</label>
                        <input type="text" name="nome_empresa" value="<?= htmlspecialchars($empresa['nome_empresa'] ?? '') ?>" placeholder="Ex: Restaurante Sabor Ltda" required>
                    </div>
                    <div class="emp-field span-2">
                        <label>Nome Fantasia <span style="font-weight:400;text-transform:none;letter-spacing:0;">(aparece no rodape e comprovantes)</span></label>
                        <input type="text" name="nome_fantasia" value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?>" placeholder="Ex: Sabor & Cia">
                    </div>
                    <div class="emp-field">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" id="inp-cnpj" value="<?= fmtCnpj($empresa['cnpj'] ?? '') ?>" placeholder="00.000.000/0001-00" maxlength="18">
                        <?php if (!empty($empresa['cnpj'])): ?>
                        <span class="emp-cnpj-atual">CNPJ atual: <?= fmtCnpj($empresa['cnpj']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="emp-field">
                        <label>Nome do Responsavel</label>
                        <input type="text" name="nome_responsavel" value="<?= htmlspecialchars($empresa['nome_responsavel'] ?? '') ?>" placeholder="Nome completo">
                    </div>
                    <div class="emp-field span-2">
                        <label>Descricao da Loja <span style="font-weight:400;text-transform:none;letter-spacing:0;">(aparece no rodape)</span></label>
                        <textarea name="descricao_loja" placeholder="Ex: Sua loja de eletronicos premium com os melhores precos e atendimento de excelencia."><?= htmlspecialchars($empresa['descricao_loja'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOGO -->
        <div class="emp-card">
            <div class="emp-card-head">
                <div class="ico">LG</div>
                <h2>Logo da Empresa <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;">(exibida no topo da loja)</span></h2>
            </div>
            <div class="emp-card-body">
                <?php if (!empty($empresa['logo'])): ?>
                <div class="emp-logo-preview">
                    <img src="<?= img_src($empresa['logo']) ?>" alt="Logo atual">
                    <div class="emp-logo-info">
                        <strong>Logo atual</strong>
                        Para trocar, selecione uma nova imagem abaixo.
                    </div>
                </div>
                <?php endif; ?>
                <div class="emp-field">
                    <label><?= !empty($empresa['logo']) ? 'Trocar Logo' : 'Enviar Logo' ?></label>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif" class="emp-file-input">
                    <span class="hint">Formatos aceitos: JPG, PNG, WEBP. Tamanho maximo: 2MB. Recomendado: fundo transparente (PNG).</span>
                </div>
            </div>
        </div>

        <!-- CONTATO -->
        <div class="emp-card">
            <div class="emp-card-head">
                <div class="ico">CT</div>
                <h2>Contato &amp; Atendimento</h2>
            </div>
            <div class="emp-card-body">
                <div class="emp-grid three">
                    <div class="emp-field">
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="inp-tel" value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>" placeholder="(00) 0000-0000" maxlength="15">
                    </div>
                    <div class="emp-field">
                        <label>Celular (exibido no rodape)</label>
                        <input type="text" name="celular" id="inp-cel" value="<?= htmlspecialchars($empresa['celular'] ?? '') ?>" placeholder="(00) 00000-0000" maxlength="16">
                    </div>
                    <div class="emp-field">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>" placeholder="contato@empresa.com.br">
                    </div>
                    <div class="emp-field">
                        <label>Horario de Atendimento <span style="font-weight:400;text-transform:none;letter-spacing:0;">(rodape)</span></label>
                        <input type="text" name="horario_atendimento" value="<?= htmlspecialchars($empresa['horario_atendimento'] ?? '') ?>" placeholder="Ex: Seg-Sex: 9h as 18h">
                    </div>
                    <div class="emp-field span-2">
                        <label>Site</label>
                        <input type="text" name="site" value="<?= htmlspecialchars($empresa['site'] ?? '') ?>" placeholder="https://www.empresa.com.br">
                    </div>
                </div>
            </div>
        </div>

        <!-- REDES SOCIAIS -->
        <div class="emp-card">
            <div class="emp-card-head">
                <div class="ico">RS</div>
                <h2>Redes Sociais</h2>
            </div>
            <div class="emp-card-body">
                <div class="emp-grid">
                    <div class="emp-field">
                        <label>Instagram</label>
                        <div class="emp-input-prefix">
                            <span class="emp-prefix-label">instagram.com/</span>
                            <input type="text" name="instagram" value="<?= htmlspecialchars(ltrim($empresa['instagram'] ?? '', '@/')) ?>" placeholder="nomedapagina">
                        </div>
                        <span class="hint">So o nome da pagina, sem @ nem URL completa</span>
                    </div>
                    <div class="emp-field">
                        <label>WhatsApp <span style="font-weight:400;text-transform:none;letter-spacing:0;">(numero para contato)</span></label>
                        <input type="text" name="whatsapp" id="inp-wpp" value="<?= fmtWpp($empresa['whatsapp'] ?? '') ?>" placeholder="(91) 99999-9999" maxlength="16">
                        <span class="hint">Usado no link wa.me do rodape</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORMAS DE PAGAMENTO -->
        <div class="emp-card">
            <div class="emp-card-head">
                <div class="ico">PG</div>
                <h2>Formas de Pagamento <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;">(exibidas no rodape)</span></h2>
            </div>
            <div class="emp-card-body">
                <?php
                $pagsAtivos = array_map('trim', explode(',', $empresa['formas_pagamento'] ?? ''));
                $opcoesPag  = ['Cartao de Credito', 'Cartao de Debito', 'PIX', 'Boleto', 'Dinheiro', 'Vale Alimentacao'];
                ?>
                <div class="emp-pag-grid">
                    <?php foreach ($opcoesPag as $op): ?>
                    <div class="emp-pag-pill">
                        <input type="checkbox" name="formas_pagamento_arr[]" id="pag-<?= md5($op) ?>" value="<?= htmlspecialchars($op) ?>" <?= in_array($op, $pagsAtivos) ? 'checked' : '' ?>>
                        <label for="pag-<?= md5($op) ?>"><?= htmlspecialchars($op) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="formas_pagamento" id="hid-formas-pag" value="<?= htmlspecialchars($empresa['formas_pagamento'] ?? '') ?>">
            </div>
        </div>

        <!-- ENDERECO -->
        <div class="emp-card">
            <div class="emp-card-head">
                <div class="ico">EN</div>
                <h2>Endereco</h2>
            </div>
            <div class="emp-card-body">
                <div class="emp-grid">
                    <div class="emp-field">
                        <label>CEP</label>
                        <input type="text" name="cep" id="inp-cep" value="<?= fmtCep($empresa['cep'] ?? '') ?>" placeholder="00000-000" maxlength="9">
                        <span class="hint">Digite o CEP para preencher automaticamente</span>
                    </div>
                    <div class="emp-field">
                        <label>Numero</label>
                        <input type="text" name="numero" id="inp-numero" value="<?= htmlspecialchars($empresa['numero'] ?? '') ?>" placeholder="123">
                    </div>
                    <div class="emp-field span-2">
                        <label>Endereco (Rua/Av.)</label>
                        <input type="text" name="endereco" id="inp-endereco" value="<?= htmlspecialchars($empresa['endereco'] ?? '') ?>" placeholder="Rua Exemplo">
                    </div>
                    <div class="emp-field">
                        <label>Complemento</label>
                        <input type="text" name="complemento" value="<?= htmlspecialchars($empresa['complemento'] ?? '') ?>" placeholder="Sala 2, Bloco B...">
                    </div>
                    <div class="emp-field">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="inp-bairro" value="<?= htmlspecialchars($empresa['bairro'] ?? '') ?>" placeholder="Centro">
                    </div>
                    <div class="emp-field">
                        <label>Cidade</label>
                        <input type="text" name="cidade" id="inp-cidade" value="<?= htmlspecialchars($empresa['cidade'] ?? '') ?>" placeholder="Belem">
                    </div>
                    <div class="emp-field">
                        <label>UF</label>
                        <input type="text" name="uf" id="inp-uf" value="<?= htmlspecialchars($empresa['uf'] ?? '') ?>" placeholder="PA" maxlength="2" style="text-transform:uppercase;">
                    </div>
                </div>
            </div>
        </div>

        <!-- PREVIEW DO RODAPE -->
        <div class="emp-card">
            <div class="emp-card-head">
                <div class="ico">PV</div>
                <h2>Preview do rodape da loja</h2>
            </div>
            <div class="emp-card-body" style="padding:12px 16px;">
                <div class="emp-preview">
                    <div class="emp-preview-title">Rodape da loja (preview em tempo real)</div>
                    <div class="emp-preview-inner">
                        <div class="emp-preview-col" style="flex:1;min-width:160px;">
                            <h4 id="pv-nome">-</h4>
                            <p id="pv-desc" style="font-size:11px;"></p>
                        </div>
                        <div class="emp-preview-col" style="flex:1;min-width:140px;">
                            <h4>Atendimento</h4>
                            <p id="pv-cel"></p>
                            <p id="pv-email"></p>
                            <p id="pv-horario"></p>
                            <p id="pv-cidade"></p>
                        </div>
                        <div class="emp-preview-col" style="flex:1;min-width:120px;">
                            <h4>Redes Sociais</h4>
                            <a id="pv-insta" href="#" target="_blank"></a>
                            <a id="pv-wpp"   href="#" target="_blank"></a>
                        </div>
                    </div>
                    <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1);font-size:11px;color:rgba(255,255,255,.5);" id="pv-pag"></div>
                </div>
            </div>
        </div>

        <!-- LOCAIS PERMITIDOS -->
        <div class="emp-card" id="secao-locais">
            <div class="emp-card-head">
                <div class="ico">GP</div>
                <h2>Locais de Acesso Permitidos <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;">(GPS - atendentes e caixas)</span></h2>
            </div>
            <div class="emp-card-body">
                <p style="font-size:13px;color:#64748b;margin-bottom:16px;">
                    Atendentes, caixas e vendedores so poderao fazer login dentro do raio de algum local cadastrado abaixo.
                </p>

                <?php if (empty($locais)): ?>
                <div style="text-align:center;padding:1.5rem;color:#94a3b8;font-size:13px;background:#f8fafc;border-radius:10px;margin-bottom:16px;">
                    Nenhum local cadastrado. Adicione abaixo.
                </div>
                <?php else: ?>
                <div style="margin-bottom:16px;">
                    <?php foreach ($locais as $local): ?>
                    <div class="local-card <?= $local['ativo'] ? '' : 'inativo' ?>">
                        <div>
                            <div class="local-nome"><?= htmlspecialchars($local['nome']) ?></div>
                            <div class="local-info">
                                Raio: <?= $local['raio_metros'] ?>m &nbsp;&middot;&nbsp;
                                <?= number_format($local['lat'], 6) ?>, <?= number_format($local['lng'], 6) ?>
                                &nbsp;&middot;&nbsp;
                                <span class="<?= $local['ativo'] ? 'tag-ativo' : 'tag-inativo' ?>">
                                    <?= $local['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </div>
                        </div>
                        <div class="local-acoes">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="acao_local" value="toggle">
                                <input type="hidden" name="local_id" value="<?= $local['id'] ?>">
                                <button class="btn-local btn-local-toggle"><?= $local['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Remover este local?')">
                                <input type="hidden" name="acao_local" value="remover">
                                <input type="hidden" name="local_id" value="<?= $local['id'] ?>">
                                <button class="btn-local btn-local-del">Remover</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Formulario para adicionar -->
                <div style="background:#f0f7ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:16px;">
                    <p style="font-size:13px;font-weight:700;color:#1e40af;margin-bottom:12px;">
                        Adicionar novo local
                    </p>

                    <div class="emp-field" style="margin-bottom:12px;">
                        <label>Nome do local</label>
                        <input type="text" id="inp-local-nome" placeholder="Ex: Filial Centro, Loja Principal..." style="background:#fff;">
                    </div>

                    <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                        <button type="button" onclick="usarGPSLocal()"
                            style="padding:9px 16px;background:#2563eb;color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif;">
                            Usar minha localizacao
                        </button>
                        <span style="font-size:12px;color:#64748b;align-self:center;">ou clique no mapa para definir o ponto</span>
                    </div>

                    <div id="mapa-local"></div>

                    <div class="raio-wrap">
                        <label style="font-size:13px;font-weight:600;color:#374151;white-space:nowrap;">Raio:</label>
                        <input type="range" min="10" max="500" step="5" value="30" id="raio-slider" oninput="atualizarRaioMapa(this.value)">
                        <span class="raio-num" id="raio-num">30m</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                        <div>
                            <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Latitude</label>
                            <div style="font-size:13px;font-weight:600;color:#1e40af;background:#fff;padding:8px 12px;border-radius:8px;border:1.5px solid #bfdbfe;margin-top:4px;" id="show-lat">-</div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Longitude</label>
                            <div style="font-size:13px;font-weight:600;color:#1e40af;background:#fff;padding:8px 12px;border-radius:8px;border:1.5px solid #bfdbfe;margin-top:4px;" id="show-lng">-</div>
                        </div>
                    </div>

                    <form method="POST" onsubmit="return prepararLocalForm(this)">
                        <input type="hidden" name="acao_local" value="adicionar">
                        <input type="hidden" name="local_nome" id="hid-nome">
                        <input type="hidden" name="local_lat"  id="hid-lat">
                        <input type="hidden" name="local_lng"  id="hid-lng">
                        <input type="hidden" name="local_raio" id="hid-raio" value="30">
                        <button type="submit" class="emp-btn-save" style="width:100%;justify-content:center;">
                            Salvar Local
                        </button>
                    </form>
                </div>

                <?php if (!empty($locais)): ?>
                <div style="margin-top:16px;">
                    <p style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Locais cadastrados no mapa</p>
                    <div id="mapa-todos"></div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- SALVAR -->
        <div style="display:flex; justify-content:flex-end; margin-top:8px;">
            <button type="submit" class="emp-btn-save">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Salvar Dados da Empresa
            </button>
        </div>

    </form>
</div>

<script>
document.getElementById('inp-cnpj').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,14);
    v = v.replace(/(\d{2})(\d)/,'$1.$2');
    v = v.replace(/(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/,'.$1/$2');
    v = v.replace(/(\d{4})(\d)/,'$1-$2');
    this.value = v;
});
function maskTel(el,cel){
    let v=el.value.replace(/\D/g,'').slice(0,cel?11:10);
    if(cel){v=v.replace(/(\d{2})(\d)/,'($1) $2');v=v.replace(/(\d{5})(\d)/,'$1-$2');}
    else{v=v.replace(/(\d{2})(\d)/,'($1) $2');v=v.replace(/(\d{4})(\d)/,'$1-$2');}
    el.value=v;
}
document.getElementById('inp-tel').addEventListener('input',function(){maskTel(this,false);});
document.getElementById('inp-cel').addEventListener('input',function(){maskTel(this,true);});
document.getElementById('inp-wpp').addEventListener('input',function(){maskTel(this,true);});

document.getElementById('inp-cep').addEventListener('input', function() {
    let v=this.value.replace(/\D/g,'').slice(0,8);
    if(v.length===8)this.value=v.slice(0,5)+'-'+v.slice(5); else this.value=v;
    if(v.length===8)buscarCep(v);
});
async function buscarCep(cep){
    try{
        const r=await fetch('https://viacep.com.br/ws/'+cep+'/json/');
        const d=await r.json();
        if(!d.erro){
            document.getElementById('inp-endereco').value=d.logradouro||'';
            document.getElementById('inp-bairro').value=d.bairro||'';
            document.getElementById('inp-cidade').value=d.localidade||'';
            document.getElementById('inp-uf').value=d.uf||'';
            document.getElementById('inp-numero').focus();
            atualizarPreview();
        }
    }catch(e){}
}

function syncPagamento(){
    const checks=document.querySelectorAll('input[name="formas_pagamento_arr[]"]:checked');
    document.getElementById('hid-formas-pag').value=Array.from(checks).map(c=>c.value).join(', ');
    atualizarPreview();
}
document.querySelectorAll('input[name="formas_pagamento_arr[]"]').forEach(c=>c.addEventListener('change',syncPagamento));

function vv(id){return (document.querySelector('[name="'+id+'"]')||{}).value||'';}
function atualizarPreview(){
    const nome    = vv('nome_fantasia')||vv('nome_empresa');
    const desc    = vv('descricao_loja');
    const cel     = vv('celular');
    const email   = vv('email');
    const horario = vv('horario_atendimento');
    const cidade  = vv('cidade');
    const uf      = vv('uf');
    const insta   = vv('instagram').replace(/^@/,'').replace(/^.*instagram\.com\//,'').trim();
    const wpp     = vv('whatsapp').replace(/\D/g,'');
    const pag     = document.getElementById('hid-formas-pag').value;

    document.getElementById('pv-nome').textContent    = nome    || '-';
    document.getElementById('pv-desc').textContent    = desc    || '';
    document.getElementById('pv-cel').textContent     = cel     ? 'Tel: '+cel : '';
    document.getElementById('pv-email').textContent   = email   ? 'Email: '+email : '';
    document.getElementById('pv-horario').textContent = horario ? 'Horario: '+horario : '';
    document.getElementById('pv-cidade').textContent  = (cidade||uf) ? 'Local: '+(cidade||'')+(uf?' - '+uf:'') : '';

    const elInsta = document.getElementById('pv-insta');
    if(insta){ elInsta.textContent='Instagram: @'+insta; elInsta.href='https://instagram.com/'+insta; }
    else { elInsta.textContent=''; }

    const elWpp = document.getElementById('pv-wpp');
    if(wpp){ elWpp.textContent='WhatsApp'; elWpp.href='https://wa.me/55'+wpp; }
    else { elWpp.textContent=''; }

    document.getElementById('pv-pag').textContent = pag ? 'Pagamentos: '+pag : '';
}

document.querySelectorAll('.emp-field input, .emp-field textarea').forEach(el=>{
    el.addEventListener('input', atualizarPreview);
});
atualizarPreview();
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var mapaAdd   = L.map('mapa-local').setView([-15.7801, -47.9292], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(mapaAdd);

var markerAdd  = null;
var circuloAdd = null;
var raioAtual  = 30;

function atualizarPontoAdd(lat, lng) {
    document.getElementById('show-lat').textContent = lat.toFixed(7);
    document.getElementById('show-lng').textContent = lng.toFixed(7);
    document.getElementById('hid-lat').value = lat.toFixed(8);
    document.getElementById('hid-lng').value = lng.toFixed(8);

    if (markerAdd) {
        markerAdd.setLatLng([lat, lng]);
    } else {
        markerAdd = L.marker([lat, lng], {draggable: true}).addTo(mapaAdd);
        markerAdd.on('dragend', function(e) {
            var p = e.target.getLatLng();
            atualizarPontoAdd(p.lat, p.lng);
        });
    }

    if (circuloAdd) {
        circuloAdd.setLatLng([lat, lng]);
    } else {
        circuloAdd = L.circle([lat, lng], {
            radius: raioAtual,
            color: '#2563eb',
            fillColor: '#2563eb',
            fillOpacity: 0.15,
            weight: 2
        }).addTo(mapaAdd);
    }
}

function atualizarRaioMapa(v) {
    raioAtual = parseInt(v);
    document.getElementById('raio-num').textContent = v + 'm';
    document.getElementById('hid-raio').value = v;
    if (circuloAdd) circuloAdd.setRadius(raioAtual);
}

mapaAdd.on('click', function(e) {
    atualizarPontoAdd(e.latlng.lat, e.latlng.lng);
});

function usarGPSLocal() {
    if (!navigator.geolocation) { alert('GPS nao disponivel neste dispositivo.'); return; }
    navigator.geolocation.getCurrentPosition(function(pos) {
        mapaAdd.setView([pos.coords.latitude, pos.coords.longitude], 18);
        atualizarPontoAdd(pos.coords.latitude, pos.coords.longitude);
    }, function() {
        alert('Nao foi possivel obter sua localizacao. Verifique as permissoes do navegador.');
    }, { enableHighAccuracy: true, timeout: 10000 });
}

function prepararLocalForm(form) {
    var nome = document.getElementById('inp-local-nome').value.trim();
    var lat  = document.getElementById('hid-lat').value;
    var lng  = document.getElementById('hid-lng').value;
    if (!nome) { alert('Digite um nome para o local.'); return false; }
    if (!lat || !lng) { alert('Defina um ponto no mapa ou use sua localizacao.'); return false; }
    document.getElementById('hid-nome').value = nome;
    return true;
}

<?php if (!empty($locais)): ?>
var mapaTodos = L.map('mapa-todos');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(mapaTodos);
var bounds = [];

<?php foreach ($locais as $local): ?>
(function() {
    var ll = [<?= floatval($local['lat']) ?>, <?= floatval($local['lng']) ?>];
    L.circle(ll, {
        radius: <?= intval($local['raio_metros']) ?>,
        color: '<?= $local['ativo'] ? '#2563eb' : '#94a3b8' ?>',
        fillOpacity: 0.15,
        weight: 2
    }).addTo(mapaTodos);
    L.marker(ll).addTo(mapaTodos)
     .bindPopup('<?= addslashes(htmlspecialchars($local['nome'])) ?> - <?= intval($local['raio_metros']) ?>m');
    bounds.push(ll);
})();
<?php endforeach; ?>

if (bounds.length) mapaTodos.fitBounds(bounds, {padding: [30, 30]});
<?php endif; ?>
</script>

</body>
</html>
