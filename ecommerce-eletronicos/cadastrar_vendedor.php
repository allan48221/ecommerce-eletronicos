<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

$msg  = '';
$tipo = '';

// ── CRIAR ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar') {
    $nome        = trim($_POST['nome']        ?? '');
    $usuario     = trim($_POST['usuario']     ?? '');
    $senha       = trim($_POST['senha']       ?? '');
    $tipo_staff  = trim($_POST['tipo_staff']  ?? 'vendedor');

    $tipos_validos = ['vendedor', 'atendente', 'caixa', 'admin'];
    if (!in_array($tipo_staff, $tipos_validos)) $tipo_staff = 'vendedor';

    if (empty($nome) || empty($usuario) || empty($senha)) {
        $msg = 'Preencha todos os campos.';
        $tipo = 'danger';
    } elseif (strlen($senha) < 6) {
        $msg = 'A senha deve ter pelo menos 6 caracteres.';
        $tipo = 'danger';
    } else {
        $chk = $conn->prepare("SELECT id_staff FROM staff WHERE usuario = ?");
        $chk->execute([$usuario]);
        if ($chk->fetch()) {
            $msg = "Usuario \"$usuario\" ja existe. Escolha outro.";
            $tipo = 'danger';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $conn->prepare("INSERT INTO staff (nome, usuario, senha, tipo) VALUES (?, ?, ?, ?)")
                 ->execute([$nome, $usuario, $hash, $tipo_staff]);
            $labels = ['vendedor'=>'Vendedor','atendente'=>'Atendente','caixa'=>'Caixa','admin'=>'Administrador'];
            $msg  = "<strong>" . htmlspecialchars($nome) . "</strong> cadastrado como <strong>" . $labels[$tipo_staff] . "</strong>!";
            $tipo = 'success';
        }
    }
}

// ── EXCLUIR ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = intval($_POST['id_staff'] ?? 0);
    if ($id > 0) {
        $conn->prepare("DELETE FROM staff WHERE id_staff = ?")->execute([$id]);
        $msg  = 'Usuario excluido.';
        $tipo = 'success';
    }
}

// ── TOGGLE ATIVO ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle') {
    $id = intval($_POST['id_staff'] ?? 0);
    if ($id > 0) {
        $conn->prepare("UPDATE staff SET ativo = NOT ativo WHERE id_staff = ?")->execute([$id]);
        $msg  = 'Status atualizado.';
        $tipo = 'success';
    }
}

// ── EDITAR ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'editar') {
    $id          = intval($_POST['id_staff']    ?? 0);
    $nome        = trim($_POST['nome']          ?? '');
    $usuario     = trim($_POST['usuario']       ?? '');
    $nova_senha  = trim($_POST['nova_senha']    ?? '');
    $tipo_staff  = trim($_POST['tipo_staff']    ?? 'vendedor');

    $tipos_validos = ['vendedor', 'atendente', 'caixa', 'admin'];
    if (!in_array($tipo_staff, $tipos_validos)) $tipo_staff = 'vendedor';

    if (empty($nome) || empty($usuario)) {
        $msg = 'Nome e usuario sao obrigatorios.';
        $tipo = 'danger';
    } else {
        $chk = $conn->prepare("SELECT id_staff FROM staff WHERE usuario = ? AND id_staff != ?");
        $chk->execute([$usuario, $id]);
        if ($chk->fetch()) {
            $msg = "Usuario \"$usuario\" ja esta em uso.";
            $tipo = 'danger';
        } else {
            if (!empty($nova_senha)) {
                if (strlen($nova_senha) < 6) {
                    $msg = 'A nova senha deve ter pelo menos 6 caracteres.';
                    $tipo = 'danger';
                } else {
                    $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $conn->prepare("UPDATE staff SET nome=?, usuario=?, senha=?, tipo=? WHERE id_staff=?")
                         ->execute([$nome, $usuario, $hash, $tipo_staff, $id]);
                    $msg  = 'Usuario atualizado com nova senha.';
                    $tipo = 'success';
                }
            } else {
                $conn->prepare("UPDATE staff SET nome=?, usuario=?, tipo=? WHERE id_staff=?")
                     ->execute([$nome, $usuario, $tipo_staff, $id]);
                $msg  = 'Usuario atualizado.';
                $tipo = 'success';
            }
        }
    }
}

// ── LISTAR ──
$todos = $conn->query("SELECT * FROM staff ORDER BY tipo ASC, nome ASC")->fetchAll();

$tipo_configs = [
    'vendedor'  => ['label'=>'Vendedor',      'desc'=>'Acessa Venda Presencial',      'cor'=>'#059669', 'bg'=>'#dcfce7'],
    'atendente' => ['label'=>'Atendente',     'desc'=>'Acessa Lancar Comanda',        'cor'=>'#2563eb', 'bg'=>'#dbeafe'],
    'caixa'     => ['label'=>'Caixa',         'desc'=>'Acessa Painel do Caixa',       'cor'=>'#d97706', 'bg'=>'#fef3c7'],
    'admin'     => ['label'=>'Administrador', 'desc'=>'Autoriza cancelamentos',       'cor'=>'#7c3aed', 'bg'=>'#ede9fe'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Equipe — Admin</title>
<link rel="stylesheet" href="css/style.css">
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; background: var(--dash-bg, #f1f5f9); min-height: 100vh; color: #0f172a; }
.vwrap { max-width: 720px; margin: 0 auto; padding: 36px 24px 80px; }
.vtopbar { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
.btn-back { display: inline-flex; align-items: center; gap: 7px; padding: 10px 20px; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 50px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Sora', sans-serif; }

.vhero { background: linear-gradient(135deg, var(--primary,#2563eb) 0%, var(--primary-dark,#1e40af) 100%); border-radius: 20px; padding: 28px; color: #fff; margin-bottom: 24px; }
.vhero h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.vhero p  { font-size: 13px; opacity: .75; }

.vtipo-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 24px; }
.vtipo-card { background: #fff; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 16px; text-align: center; }
.vtipo-num   { font-size: 26px; font-weight: 800; line-height: 1; }
.vtipo-label { font-size: 11px; color: #64748b; margin-top: 4px; font-weight: 600; }

.valert { padding: 13px 16px; border-radius: 12px; font-size: 14px; font-weight: 500; margin-bottom: 18px; border-left: 4px solid; }
.valert.success { background: #ecfdf5; color: #065f46; border-color: #10b981; }
.valert.danger  { background: #fef2f2; color: #7f1d1d; border-color: #ef4444; }

.vcard { background: #fff; border-radius: 16px; border: 1.5px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,.05); overflow: hidden; margin-bottom: 18px; }
.vcard-head { padding: 14px 20px; border-bottom: 1px solid #f8fafc; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.vcard-head h2 { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #64748b; }
.vcard-body { padding: 20px; }

.vform-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.vform-field { display: flex; flex-direction: column; gap: 6px; }
.vform-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; }
.vform-field input { padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: 'Sora', sans-serif; outline: none; transition: .2s; background: #fff; color: #0f172a; }
.vform-field input:focus { border-color: var(--primary,#2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

.tipo-pills { display: grid; grid-template-columns: repeat(4,1fr); gap: 8px; margin-bottom: 14px; }
.tipo-pill { position: relative; }
.tipo-pill input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
.tipo-pill label { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 12px 8px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer; transition: .2s; background: #fff; text-align: center; font-family: 'Sora', sans-serif; }
.tipo-pill input:checked + label { border-color: var(--primary,#2563eb); background: #eff6ff; color: var(--primary,#2563eb); }
.tipo-pill input[value="admin"]:checked + label { border-color: #7c3aed; background: #ede9fe; color: #7c3aed; }

/* Badge especial para admin */
.tipo-pill label .admin-badge { display: inline-block; background: #7c3aed; color: #fff; font-size: 9px; font-weight: 800; padding: 1px 5px; border-radius: 4px; margin-top: 2px; letter-spacing: .3px; }

.btn-criar { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--primary,#2563eb), var(--primary-dark,#1e40af)); color: #fff; border: none; border-radius: 11px; font-size: 15px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; margin-top: 4px; transition: .2s; }
.btn-criar:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(37,99,235,.35); }

.vsection-badge { padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 800; }

.vendedor-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f8fafc; }
.vendedor-item:last-child { border-bottom: none; }
.v-avatar { width: 42px; height: 42px; min-width: 42px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 800; flex-shrink: 0; }
.v-info { flex: 1; min-width: 0; }
.v-nome    { font-size: 14px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #0f172a; }
.v-usuario { font-size: 12px; color: #6b7280; margin-top: 2px; }
.v-badge-ativo   { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: #dcfce7; color: #059669; white-space: nowrap; flex-shrink: 0; }
.v-badge-inativo { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: #f1f5f9; color: #94a3b8; white-space: nowrap; flex-shrink: 0; }
.v-acoes { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
.vbtn-sm { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; font-family: 'Sora', sans-serif; cursor: pointer; border: none; transition: .2s; white-space: nowrap; }
.vbtn-editar  { background: #eff6ff; color: #2563eb; border: 1.5px solid #bfdbfe; }
.vbtn-editar:hover { background: #dbeafe; }
.vbtn-toggle  { background: #f0fdf4; color: #059669; border: 1.5px solid #a7f3d0; }
.vbtn-toggle.desativar { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; }
.vbtn-excluir { background: #fef2f2; color: #ef4444; border: 1.5px solid #fecaca; }
.vbtn-excluir:hover { background: #fee2e2; }
.vvazio { text-align: center; color: #94a3b8; font-size: 14px; padding: 16px 0; }

/* Card de admin com destaque especial */
.vcard.admin-card { border-color: #c4b5fd; }
.vcard.admin-card .vcard-head { background: #f5f3ff; border-color: #ddd6fe; }

/* Info box admin */
.admin-info-box { background: #f5f3ff; border: 1.5px solid #ddd6fe; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; color: #5b21b6; display: flex; align-items: center; gap: 8px; }
.admin-info-box strong { font-weight: 700; }

.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; align-items: center; justify-content: center; padding: 16px; }
.modal-overlay.show { display: flex; }
.vmodal { background: #fff; border-radius: 18px; padding: 28px; width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,.2); max-height: 90vh; overflow-y: auto; }
.vmodal h3 { font-size: 16px; font-weight: 800; margin-bottom: 18px; }
.vmodal .vform-field { margin-bottom: 12px; }
.vmodal-btns { display: flex; gap: 10px; margin-top: 18px; }
.vbtn-salvar { flex: 1; padding: 12px; background: linear-gradient(135deg, var(--primary,#2563eb), var(--primary-dark,#1e40af)); color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; }
.vbtn-fechar { flex: 1; padding: 12px; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: 'Sora', sans-serif; cursor: pointer; }

@media (max-width: 500px) {
    .vform-row { grid-template-columns: 1fr; }
    .v-acoes { flex-wrap: wrap; }
    .vtipo-grid { grid-template-columns: repeat(2,1fr); }
    .tipo-pills { grid-template-columns: repeat(2,1fr); }
}
</style>
</head>
<body>
<div class="vwrap">

    <div class="vtopbar">
        <a href="admin.php" class="btn-back">&#8592; Voltar ao Admin</a>
    </div>

    <div class="vhero">
        <h1>Equipe</h1>
        <p>Gerencie vendedores, atendentes, caixas e administradores do sistema</p>
    </div>

    <div class="vtipo-grid">
        <?php foreach ($tipo_configs as $t => $cfg):
            $count = count(array_filter($todos, fn($s) => $s['tipo'] === $t));
        ?>
        <div class="vtipo-card">
            <div class="vtipo-num" style="color:<?= $cfg['cor'] ?>"><?= $count ?></div>
            <div class="vtipo-label"><?= $cfg['label'] ?>(s)</div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($msg): ?>
    <div class="valert <?= $tipo ?>"><?= $msg ?></div>
    <?php endif; ?>

    <div class="vcard">
        <div class="vcard-head"><h2>Cadastrar Novo Usuario</h2></div>
        <div class="vcard-body">
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:8px;">Tipo de acesso</div>
                    <div class="tipo-pills">
                        <div class="tipo-pill">
                            <input type="radio" name="tipo_staff" id="tp-vendedor" value="vendedor" checked>
                            <label for="tp-vendedor">Vendedor</label>
                        </div>
                        <div class="tipo-pill">
                            <input type="radio" name="tipo_staff" id="tp-atendente" value="atendente">
                            <label for="tp-atendente">Atendente</label>
                        </div>
                        <div class="tipo-pill">
                            <input type="radio" name="tipo_staff" id="tp-caixa" value="caixa">
                            <label for="tp-caixa">Caixa</label>
                        </div>
                        <div class="tipo-pill">
                            <input type="radio" name="tipo_staff" id="tp-admin" value="admin">
                            <label for="tp-admin">
                                Admin
                                <span class="admin-badge">CANCEL</span>
                            </label>
                        </div>
                    </div>
                    <div id="tipo-desc" style="font-size:12px;color:#64748b;text-align:center;padding:6px 0;"></div>
                </div>
                <div class="vform-row">
                    <div class="vform-field">
                        <label>Nome completo</label>
                        <input type="text" name="nome" placeholder="Ex: Joao Silva" required>
                    </div>
                    <div class="vform-field">
                        <label>Usuario</label>
                        <input type="text" name="usuario" placeholder="Ex: joao" required autocomplete="off">
                    </div>
                </div>
                <div class="vform-field" style="margin-bottom:14px;">
                    <label>Senha</label>
                    <input type="password" name="senha" placeholder="Minimo 6 caracteres" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-criar">+ Cadastrar</button>
            </form>
        </div>
    </div>

    <?php foreach (['vendedor','atendente','caixa','admin'] as $t):
        $cfg   = $tipo_configs[$t];
        $lista = array_filter($todos, fn($s) => $s['tipo'] === $t);
    ?>
    <div class="vcard <?= $t === 'admin' ? 'admin-card' : '' ?>">
        <div class="vcard-head">
            <h2><?= $cfg['label'] ?>s — <?= $cfg['desc'] ?></h2>
            <span class="vsection-badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['cor'] ?>;"><?= count($lista) ?></span>
        </div>
        <div class="vcard-body">
            <?php if ($t === 'admin'): ?>
            <div class="admin-info-box">
                <span>&#128274;</span>
                <span>A senha deste usuario autoriza <strong>cancelamentos de comanda</strong> e <strong>remoção de itens</strong> no caixa.</span>
            </div>
            <?php endif; ?>
            <?php if (empty($lista)): ?>
                <div class="vvazio">Nenhum <?= strtolower($cfg['label']) ?> cadastrado.</div>
            <?php else: ?>
                <?php foreach ($lista as $v): ?>
                <div class="vendedor-item">
                    <div class="v-avatar" style="background:linear-gradient(135deg,<?= $cfg['cor'] ?>,<?= $cfg['cor'] ?>cc);">
                        <?= strtoupper(substr($v['nome'], 0, 1)) ?>
                    </div>
                    <div class="v-info">
                        <div class="v-nome"><?= htmlspecialchars($v['nome']) ?></div>
                        <div class="v-usuario">@<?= htmlspecialchars($v['usuario']) ?></div>
                    </div>
                    <span class="<?= $v['ativo'] ? 'v-badge-ativo' : 'v-badge-inativo' ?>">
                        <?= $v['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                    <div class="v-acoes">
                        <button class="vbtn-sm vbtn-editar"
                            onclick="abrirEditar(<?= $v['id_staff'] ?>, '<?= htmlspecialchars(addslashes($v['nome'])) ?>', '<?= htmlspecialchars(addslashes($v['usuario'])) ?>', '<?= $v['tipo'] ?>')">
                            Editar
                        </button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="acao"     value="toggle">
                            <input type="hidden" name="id_staff" value="<?= $v['id_staff'] ?>">
                            <button type="submit" class="vbtn-sm vbtn-toggle <?= $v['ativo'] ? 'desativar' : '' ?>">
                                <?= $v['ativo'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir <?= htmlspecialchars(addslashes($v['nome'])) ?>?')">
                            <input type="hidden" name="acao"     value="excluir">
                            <input type="hidden" name="id_staff" value="<?= $v['id_staff'] ?>">
                            <button type="submit" class="vbtn-sm vbtn-excluir">Excluir</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<div class="modal-overlay" id="modal-editar">
    <div class="vmodal">
        <h3>Editar Usuario</h3>
        <form method="POST">
            <input type="hidden" name="acao"     value="editar">
            <input type="hidden" name="id_staff" id="edit-id">
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:8px;">Tipo de acesso</div>
                <div class="tipo-pills">
                    <div class="tipo-pill">
                        <input type="radio" name="tipo_staff" id="etp-vendedor" value="vendedor">
                        <label for="etp-vendedor">Vendedor</label>
                    </div>
                    <div class="tipo-pill">
                        <input type="radio" name="tipo_staff" id="etp-atendente" value="atendente">
                        <label for="etp-atendente">Atendente</label>
                    </div>
                    <div class="tipo-pill">
                        <input type="radio" name="tipo_staff" id="etp-caixa" value="caixa">
                        <label for="etp-caixa">Caixa</label>
                    </div>
                    <div class="tipo-pill">
                        <input type="radio" name="tipo_staff" id="etp-admin" value="admin">
                        <label for="etp-admin">
                            Admin
                            <span class="admin-badge">CANCEL</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="vform-field" style="margin-bottom:12px;">
                <label>Nome completo</label>
                <input type="text" name="nome" id="edit-nome" required>
            </div>
            <div class="vform-field" style="margin-bottom:12px;">
                <label>Usuario</label>
                <input type="text" name="usuario" id="edit-usuario" required>
            </div>
            <div class="vform-field">
                <label>Nova senha <span style="font-size:10px;color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;">(deixe em branco para manter)</span></label>
                <input type="password" name="nova_senha" placeholder="Nova senha (opcional)" autocomplete="new-password">
            </div>
            <div class="vmodal-btns">
                <button type="button" class="vbtn-fechar" onclick="fecharEditar()">Cancelar</button>
                <button type="submit" class="vbtn-salvar">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
const tipoDescs = {
    vendedor:  'Acessa a tela de Venda Presencial',
    atendente: 'Acessa a tela de Lancar Comanda',
    caixa:     'Acessa o Painel do Caixa',
    admin:     'Autoriza cancelamentos no caixa com sua senha',
};

document.querySelectorAll('input[name="tipo_staff"]').forEach(r => {
    r.addEventListener('change', () => {
        const desc = document.getElementById('tipo-desc');
        if (desc) desc.textContent = tipoDescs[r.value] || '';
    });
});

const checked = document.querySelector('input[name="tipo_staff"]:checked');
if (checked) {
    const desc = document.getElementById('tipo-desc');
    if (desc) desc.textContent = tipoDescs[checked.value] || '';
}

function abrirEditar(id, nome, usuario, tipo) {
    document.getElementById('edit-id').value      = id;
    document.getElementById('edit-nome').value    = nome;
    document.getElementById('edit-usuario').value = usuario;
    const radio = document.getElementById('etp-' + tipo);
    if (radio) radio.checked = true;
    document.getElementById('modal-editar').classList.add('show');
}
function fecharEditar() {
    document.getElementById('modal-editar').classList.remove('show');
}
document.getElementById('modal-editar').addEventListener('click', function(e) {
    if (e.target === this) fecharEditar();
});
</script>
</body>
</html>