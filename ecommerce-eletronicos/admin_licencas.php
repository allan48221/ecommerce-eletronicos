<?php
// ============================================================
//  admin_licencas.php  — Painel de gestão de licenças
//  Acesse: seusite.com/admin_licencas.php
//  Só admin master (sem id_tenant) consegue acessar.
// ============================================================
require_once 'config/database.php';
require_once 'config/tema.php';

// Só admin logado
if (empty($_SESSION['id_admin'])) {
    header('Location: login.php'); exit;
}

$msg = '';

// ── AÇÕES POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // Criar novo tenant + licença
    if ($acao === 'criar_tenant') {
        $nome       = trim($_POST['nome'] ?? '');
        $subdominio = strtolower(trim($_POST['subdominio'] ?? ''));
        $subdominio = preg_replace('/[^a-z0-9\-]/', '', $subdominio);
        $cnpj       = trim($_POST['cnpj']  ?? '');
        $email      = trim($_POST['email'] ?? '');
        $id_plano   = (int)$_POST['id_plano'];
        $vencimento = $_POST['data_vencimento'] ?? '';

        if ($nome && $subdominio && $id_plano && $vencimento) {
            try {
                $conn->beginTransaction();
                $conn->prepare("
                    INSERT INTO empresas_tenants (nome, subdominio, cnpj, email)
                    VALUES (?, ?, ?, ?)
                ")->execute([$nome, $subdominio, $cnpj, $email]);

                $id_tenant = $conn->lastInsertId();

                $conn->prepare("
                    INSERT INTO licencas (id_tenant, id_plano, data_vencimento)
                    VALUES (?, ?, ?)
                ")->execute([$id_tenant, $id_plano, $vencimento]);

                $conn->commit();
                $msg = "Tenant <strong>$nome</strong> criado com sucesso! Subdominio: <code>$subdominio.seusite.com</code>";
            } catch (\Exception $e) {
                $conn->rollBack();
                $msg = "ERRO: " . $e->getMessage();
            }
        } else {
            $msg = "ERRO: Preencha todos os campos obrigatorios.";
        }
    }

    // Criar admin do tenant
    if ($acao === 'criar_admin_tenant') {
        $id_tenant  = (int)$_POST['id_tenant'];
        $nome       = trim($_POST['nome']    ?? '');
        $email      = trim($_POST['email']   ?? '');
        $usuario    = trim($_POST['usuario'] ?? '');
        $senha      = trim($_POST['senha']   ?? '');

        if ($nome && $email && $usuario && $senha && $id_tenant) {
            $check = $conn->prepare("
                SELECT COUNT(*) FROM admins 
                WHERE (email = ? OR usuario = ?) AND id_tenant = ?
            ");
            $check->execute([$email, $usuario, $id_tenant]);

            if ($check->fetchColumn() > 0) {
                $msg = "ERRO: Email ou usuario ja cadastrado para este tenant.";
            } else {
                $hash = password_hash($senha, PASSWORD_BCRYPT);
                $conn->prepare("
                    INSERT INTO admins (nome, email, usuario, senha, id_tenant, ativo)
                    VALUES (?, ?, ?, ?, ?, TRUE)
                ")->execute([$nome, $email, $usuario, $hash, $id_tenant]);
                $msg = "Admin <strong>$nome</strong> criado com sucesso!";
            }
        } else {
            $msg = "ERRO: Preencha todos os campos.";
        }
    }

    // Renovar/alterar licença
    if ($acao === 'renovar') {
        $id_licenca = (int)$_POST['id_licenca'];
        $id_plano   = (int)$_POST['id_plano'];
        $vencimento = $_POST['data_vencimento'];
        $ativo      = isset($_POST['ativo']) ? 'TRUE' : 'FALSE';
        $conn->prepare("
            UPDATE licencas
            SET id_plano = ?, data_vencimento = ?, ativo = $ativo
            WHERE id_licenca = ?
        ")->execute([$id_plano, $vencimento, $id_licenca]);
        $msg = "Licenca atualizada com sucesso.";
    }

    // Mudar plano do tenant
    if ($acao === 'mudar_plano') {
        $id_licenca = (int)$_POST['id_licenca'];
        $id_plano   = (int)$_POST['id_plano'];
        $conn->prepare("
            UPDATE licencas SET id_plano = ? WHERE id_licenca = ?
        ")->execute([$id_plano, $id_licenca]);
        $msg = "Plano alterado com sucesso.";
    }
// Remover tenant e licença
if ($acao === 'remover_tenant') {
    $id_tenant = (int)$_POST['id_tenant'];
    $conn->beginTransaction();
    try {
        $conn->prepare("DELETE FROM licencas WHERE id_tenant = ?")->execute([$id_tenant]);
        $conn->prepare("DELETE FROM admins WHERE id_tenant = ?")->execute([$id_tenant]);
        $conn->prepare("DELETE FROM empresas_tenants WHERE id_tenant = ?")->execute([$id_tenant]);
        $conn->commit();
        $msg = "Tenant removido com sucesso.";
    } catch (\Exception $e) {
        $conn->rollBack();
        $msg = "ERRO ao remover: " . $e->getMessage();
    }
}
    // Bloquear/desbloquear tenant
    if ($acao === 'toggle_tenant') {
        $id_tenant = (int)$_POST['id_tenant'];
        $conn->prepare("
            UPDATE empresas_tenants SET ativo = NOT ativo WHERE id_tenant = ?
        ")->execute([$id_tenant]);
        $msg = "Status do tenant alterado.";
    }
}

// ── CARREGAR DADOS ────────────────────────────────────────────
$tenants = $conn->query("
    SELECT
        et.*,
        l.id_licenca,
        l.id_plano,
        l.data_vencimento,
        l.ativo AS licenca_ativa,
        p.nome  AS plano_nome
    FROM empresas_tenants et
    LEFT JOIN licencas l ON l.id_tenant = et.id_tenant AND l.ativo = TRUE
    LEFT JOIN planos p ON p.id_plano = l.id_plano
    ORDER BY et.criado_em DESC
")->fetchAll();

$planos = $conn->query("SELECT * FROM planos WHERE ativo = TRUE ORDER BY preco")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestao de Licencas</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <?= aplicar_tema($conn) ?>
    <style>
    body { background: var(--dash-bg, #f1f5f9); padding: 24px; }
    .page-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
    h1 { font-size:1.4rem; margin:0; }
    .card { background:#fff; border-radius:12px; padding:20px; margin-bottom:20px;
            box-shadow:0 2px 12px rgba(0,0,0,.06); }
    .card h2 { font-size:1rem; margin:0 0 16px; color:var(--secondary);
               font-weight:700; letter-spacing:.3px; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th { text-align:left; padding:8px 10px; border-bottom:2px solid #e2e8f0;
         color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; }
    td { padding:10px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .badge-ok  { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
    .badge-exp { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    .badge-off { background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; }
    .btn-sm { padding:5px 12px; font-size:12px; border-radius:7px; cursor:pointer;
              border:1px solid #e2e8f0; background:#f8fafc; white-space:nowrap; }
    .btn-sm:hover { background:#eff6ff; border-color:var(--primary); }
    .btn-danger-sm { border-color:#fecaca; color:#dc2626; }
    .btn-danger-sm:hover { background:#fef2f2; }
    .btn-plano-sm { border-color:#c7d2fe; color:#4338ca; background:#eef2ff; }
    .btn-plano-sm:hover { background:#e0e7ff; border-color:#818cf8; }
    .form-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
    .form-row .form-group { flex:1; min-width:160px; }
    .form-group label { font-size:11px; font-weight:600; color:#475569;
                        display:block; margin-bottom:4px; }
    .form-control { height:36px; border:1px solid #e2e8f0; border-radius:8px;
                    padding:0 10px; font-size:13px; width:100%; }
    .alert { padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; }
    .alert-success { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
    .alert-danger   { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }

    /* Modal */
    .modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
                z-index:1000; align-items:center; justify-content:center; }
    .modal-box { background:#fff; border-radius:14px; padding:28px;
                 width:460px; max-width:95vw; box-shadow:0 8px 40px rgba(0,0,0,.15); }
    .modal-titulo { margin:0 0 20px; font-size:1rem; font-weight:700; color:var(--dark); }
    </style>
</head>
<body>

<div class="page-header">
    <a href="admin.php" style="font-size:20px">&#8592;</a>
    <h1 style="color:#ffffff;">Gestao de Licencas</h1>
</div>

<?php if ($msg): ?>
<div class="alert <?= str_contains($msg,'ERRO') ? 'alert-danger' : 'alert-success' ?>">
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- ── LISTA DE TENANTS ── -->
<div class="card">
    <h2>Clientes cadastrados</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Empresa</th>
                <th>Subdominio</th>
                <th>Plano</th>
                <th>Vencimento</th>
                <th>Status</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tenants as $t):
            $venc    = $t['data_vencimento'] ?? null;
            $expirou = $venc && $venc < date('Y-m-d');
            $ativo   = $t['ativo'] && !$expirou && $t['licenca_ativa'];
        ?>
        <tr>
            <td><?= $t['id_tenant'] ?></td>
            <td><strong><?= htmlspecialchars($t['nome']) ?></strong></td>
           <?php 
$base_url = 'https://ecommerce-eletronicos-1.onrender.com';
$link = $base_url . '/login.php?tenant=' . htmlspecialchars($t['subdominio']);
?>
<td>
    <a href="<?= $link ?>" target="_blank" style="font-size:11px;color:#2563eb;">
        <?= $link ?>
    </a>
</td>
            <td><?= htmlspecialchars($t['plano_nome'] ?? '—') ?></td>
            <td><?= $venc ? date('d/m/Y', strtotime($venc)) : '—' ?></td>
            <td>
                <?php if (!$t['ativo']): ?>
                    <span class="badge badge-off">Inativo</span>
                <?php elseif ($expirou): ?>
                    <span class="badge badge-exp">Expirado</span>
                <?php else: ?>
                    <span class="badge badge-ok">Ativo</span>
                <?php endif; ?>
            </td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">

                <!-- Renovar datas/ativo -->
                <button class="btn-sm"
                    onclick="abrirRenovar(<?= htmlspecialchars(json_encode($t)) ?>)">
                    Renovar
                </button>

                <!-- Mudar plano -->
                <?php if ($t['id_licenca']): ?>
                <button class="btn-sm btn-plano-sm"
                    onclick="abrirMudarPlano(<?= htmlspecialchars(json_encode($t)) ?>)">
                    Mudar Plano
                </button>
                <?php endif; ?>

                <!-- Bloquear/Ativar -->
                <form method="POST" style="display:inline">
                    <input type="hidden" name="acao"      value="toggle_tenant">
                    <input type="hidden" name="id_tenant" value="<?= $t['id_tenant'] ?>">
                    <button type="submit" class="btn-sm <?= $t['ativo'] ? 'btn-danger-sm' : '' ?>">
                        <?= $t['ativo'] ? 'Bloquear' : 'Ativar' ?>
                    </button>
                </form>
                  <!-- Remover tenant -->
<form method="POST" style="display:inline"
      onsubmit="return confirm('Tem certeza que deseja remover <?= htmlspecialchars($t['nome']) ?>?')">
    <input type="hidden" name="acao"      value="remover_tenant">
    <input type="hidden" name="id_tenant" value="<?= $t['id_tenant'] ?>">
    <button type="submit" class="btn-sm btn-danger-sm">Remover</button>
</form> 
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ── CRIAR NOVO TENANT ── -->
<div class="card">
    <h2>Novo cliente / Tenant</h2>
    <form method="POST">
        <input type="hidden" name="acao" value="criar_tenant">
        <div class="form-row">
            <div class="form-group">
                <label>Nome da empresa *</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: Pizzaria Joao">
            </div>
            <div class="form-group">
                <label>Subdominio * (so letras, numeros e -)</label>
                <input type="text" name="subdominio" class="form-control" required
                       placeholder="pizzaria-joao"
                       oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9\-]/g,'')">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>CNPJ</label>
                <input type="text" name="cnpj" class="form-control" placeholder="00.000.000/0001-00">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" placeholder="contato@empresa.com">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Plano *</label>
                <select name="id_plano" class="form-control" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($planos as $p): ?>
                    <option value="<?= $p['id_plano'] ?>">
                        <?= htmlspecialchars($p['nome']) ?> — R$ <?= number_format($p['preco'],2,',','.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Valido ate *</label>
                <input type="date" name="data_vencimento" class="form-control" required
                       value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Criar Tenant e Licenca</button>
    </form>
</div>

<!-- ── CRIAR ADMIN DO TENANT ── -->
<div class="card">
    <h2>Criar Admin para Tenant</h2>
    <form method="POST">
        <input type="hidden" name="acao" value="criar_admin_tenant">
        <div class="form-row">
            <div class="form-group">
                <label>Tenant *</label>
                <select name="id_tenant" class="form-control" required>
                    <option value="">Selecione o cliente...</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= $t['id_tenant'] ?>">
                        <?= htmlspecialchars($t['nome']) ?>
                        (<?= htmlspecialchars($t['subdominio']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Nome completo *</label>
                <input type="text" name="nome" class="form-control"
                       required placeholder="Ex: Joao Silva">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control"
                       required placeholder="joao@empresa.com">
            </div>
            <div class="form-group">
                <label>Usuario *</label>
                <input type="text" name="usuario" class="form-control"
                       required placeholder="joaosilva">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Senha *</label>
                <input type="text" name="senha" class="form-control"
                       required placeholder="Senha inicial">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Criar Admin do Tenant</button>
    </form>
</div>

<!-- ── MODAL RENOVAR ── -->
<div id="modal-renovar" class="modal-bg">
    <div class="modal-box">
        <h3 class="modal-titulo" id="modal-titulo-renovar">Renovar Licenca</h3>
        <form method="POST">
            <input type="hidden" name="acao"       value="renovar">
            <input type="hidden" name="id_licenca" id="m_id_licenca">
            <div class="form-group" style="margin-bottom:12px">
                <label>Plano</label>
                <select name="id_plano" id="m_id_plano" class="form-control">
                    <?php foreach ($planos as $p): ?>
                    <option value="<?= $p['id_plano'] ?>">
                        <?= htmlspecialchars($p['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label>Valido ate</label>
                <input type="date" name="data_vencimento" id="m_vencimento" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom:20px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="ativo" id="m_ativo" style="width:16px;height:16px">
                    Licenca ativa
                </label>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary" style="flex:1">Salvar</button>
                <button type="button" class="btn-sm" style="flex:1"
                        onclick="fecharModal('modal-renovar')">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL MUDAR PLANO ── -->
<div id="modal-mudar-plano" class="modal-bg">
    <div class="modal-box">
        <h3 class="modal-titulo" id="modal-titulo-plano">Mudar Plano</h3>
        <p style="font-size:13px;color:#64748b;margin-bottom:16px">
            Plano atual: <strong id="m_plano_atual"></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="acao"       value="mudar_plano">
            <input type="hidden" name="id_licenca" id="mp_id_licenca">
            <div class="form-group" style="margin-bottom:20px">
                <label>Novo plano *</label>
                <select name="id_plano" id="mp_id_plano" class="form-control">
                    <?php foreach ($planos as $p): ?>
                    <option value="<?= $p['id_plano'] ?>">
                        <?= htmlspecialchars($p['nome']) ?> — R$ <?= number_format($p['preco'],2,',','.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary" style="flex:1">Confirmar Mudanca</button>
                <button type="button" class="btn-sm" style="flex:1"
                        onclick="fecharModal('modal-mudar-plano')">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function fecharModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Fecha modal clicando fora
document.querySelectorAll('.modal-bg').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.style.display = 'none';
    });
});

function abrirRenovar(t) {
    document.getElementById('modal-renovar').style.display        = 'flex';
    document.getElementById('modal-titulo-renovar').textContent   = 'Renovar: ' + t.nome;
    document.getElementById('m_id_licenca').value  = t.id_licenca  || '';
    document.getElementById('m_id_plano').value    = t.id_plano    || '';
    document.getElementById('m_vencimento').value  = t.data_vencimento || '';
    document.getElementById('m_ativo').checked     = t.licenca_ativa == '1' || t.licenca_ativa === true;
}

function abrirMudarPlano(t) {
    document.getElementById('modal-mudar-plano').style.display     = 'flex';
    document.getElementById('modal-titulo-plano').textContent      = 'Mudar Plano: ' + t.nome;
    document.getElementById('m_plano_atual').textContent           = t.plano_nome || '—';
    document.getElementById('mp_id_licenca').value = t.id_licenca  || '';
    document.getElementById('mp_id_plano').value   = t.id_plano    || '';
}
</script>
</body>
</html>
