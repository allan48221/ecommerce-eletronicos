<?php
// ============================================================
//  login.php — versão multi-tenant
//  Mudanças em relação ao original:
//   1. Login agora filtra por id_tenant (cada empresa só vê seus users)
//   2. Sessão salva id_tenant + plano + telas_permitidas
//   3. Admin só loga se a licença do tenant estiver válida
//  O HTML/CSS ficou 100% igual ao original.
// ============================================================

require_once 'config/database.php';  // já carrega tenant.php e valida licença
require_once 'config/tema.php';

echo "<pre>";
echo "Host: " . $_SERVER['HTTP_HOST'] . "\n";
echo "Tenant GET: " . ($_GET['tenant'] ?? 'nenhum') . "\n";
echo "Tenant sessão: " . ($_SESSION['tenant_subdominio_url'] ?? 'nenhum') . "\n";
echo "id_tenant sessão: " . ($_SESSION['id_tenant'] ?? 'nenhum') . "\n";
echo "tenant_carregado: " . ($_SESSION['tenant_carregado'] ?? 'nenhum') . "\n";
echo "</pre>";
die();

$mensagem      = '';
$tipo_mensagem = '';

// Tenant já foi validado pelo database.php — se chegou aqui, licença OK
$id_tenant = $_SESSION['id_tenant'] ?? null;

if (isset($_SESSION['id_cliente'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_login = $_POST['tipo_login'] ?? 'admin';
    $senha      = $_POST['senha']      ?? '';

    if ($tipo_login === 'admin') {
        // ── LOGIN ADMIN ──────────────────────────────────────
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || empty($senha)) {
            $mensagem      = 'Preencha todos os campos!';
            $tipo_mensagem = 'danger';
        } else {
            // 1) Tenta na tabela admins (filtra por tenant)
            $stmt = $conn->prepare("
                SELECT * FROM admins
                WHERE email = ?
                  AND ativo = TRUE
                  AND (id_tenant = ? OR id_tenant IS NULL)
            ");
            $stmt->execute([$email, $id_tenant]);
            $admin = $stmt->fetch();
            // DEBUG TEMPORÁRIO
echo "<pre>";
echo "Email digitado: $email\n";
echo "id_tenant usado na query: $id_tenant\n";
echo "Admin encontrado: " . ($admin ? 'SIM' : 'NÃO') . "\n";
if ($admin) {
    echo "Senha hash no banco: " . $admin['senha'] . "\n";
    echo "password_verify: " . (password_verify($senha, $admin['senha']) ? 'OK' : 'FALHOU') . "\n";
    echo "Ativo: " . $admin['ativo'] . "\n";
}
echo "</pre>";
die();

            if ($admin && password_verify($senha, $admin['senha'])) {
                $_SESSION['id_admin']      = $admin['id_admin'];
                $_SESSION['nome_admin']    = $admin['nome'];
                $_SESSION['email_admin']   = $admin['email'];
                $_SESSION['usuario_admin'] = $admin['usuario'];
                header('Location: admin.php');
                exit;
            }

            // 2) Tenta na tabela staff tipo='admin' (filtra por tenant)
            $stmt2 = $conn->prepare("
                SELECT * FROM staff
                WHERE usuario = ?
                  AND tipo = 'admin'
                  AND ativo = TRUE
                  AND (id_tenant = ? OR id_tenant IS NULL)
            ");
            $stmt2->execute([$email, $id_tenant]);
            $staff = $stmt2->fetch();

            if ($staff && password_verify($senha, $staff['senha'])) {
                $id_staff = $staff['id_staff'];
                $_SESSION['id_admin']   = $id_staff;
                $_SESSION['nome_admin'] = $staff['nome'];
                $_SESSION['id_staff']   = $id_staff;
                $_SESSION['nome_staff'] = $staff['nome'];
                $_SESSION['tipo_staff'] = 'admin';
                header('Location: admin.php');
                exit;
            }

            $mensagem      = 'Usuário/email ou senha incorretos!';
            $tipo_mensagem = 'danger';
        }

    } else {
        // ── LOGIN STAFF (vendedor / atendente / caixa) ───────
        $usuario = trim($_POST['usuario'] ?? '');

        if (empty($usuario) || empty($senha)) {
            $mensagem      = 'Preencha usuário e senha!';
            $tipo_mensagem = 'danger';
        } else {
            // Busca na tabela staff filtrando por tenant
            $stmt = $conn->prepare("
                SELECT * FROM staff
                WHERE usuario = ?
                  AND ativo = TRUE
                  AND tipo = ?
                  AND (id_tenant = ? OR id_tenant IS NULL)
            ");
            $stmt->execute([$usuario, $tipo_login, $id_tenant]);
            $staff = $stmt->fetch();

            // Fallback: tabela vendedores legada (sem id_tenant — migração gradual)
            if (!$staff && $tipo_login === 'vendedor') {
                $stmt2 = $conn->prepare("
                    SELECT *, 'vendedor' AS tipo FROM vendedores
                    WHERE usuario = ? AND ativo = TRUE
                ");
                $stmt2->execute([$usuario]);
                $staff = $stmt2->fetch();
                if ($staff) $staff['id_staff'] = $staff['id_vendedor'];
            }

            if ($staff && password_verify($senha, $staff['senha'])) {
                $tipo     = $staff['tipo'];
                $id_staff = $staff['id_staff'] ?? ($staff['id_vendedor'] ?? null);

                // Sessão base
                $_SESSION['id_staff']   = $id_staff;
                $_SESSION['nome_staff'] = $staff['nome'];
                $_SESSION['tipo_staff'] = $tipo;

                // Compatibilidade legada
                $_SESSION['id_vendedor']   = $id_staff;
                $_SESSION['nome_vendedor'] = $staff['nome'];
                $_SESSION['is_vendedor']   = true;
                $_SESSION['nome_admin']    = $staff['nome'];
                $_SESSION['id_admin']      = $tipo . '_' . $id_staff;

                // Verifica se este tipo de staff tem acesso à tela correspondente
                $mapa_tela = [
                    'vendedor'  => 'venda_presencial',
                    'atendente' => 'atendente',
                    'caixa'     => 'caixa',
                ];
                $tela_destino = $mapa_tela[$tipo] ?? null;

                if ($tela_destino && !tela_liberada($tela_destino)) {
                    // Plano não inclui esta tela
                    session_destroy();
                    $mensagem      = 'Seu plano não inclui acesso a esta área. Contate o administrador.';
                    $tipo_mensagem = 'warning';
                } else {
                    $destinos = [
                        'vendedor'  => 'venda_presencial.php',
                        'atendente' => 'atendente.php',
                        'caixa'     => 'caixa.php',
                    ];
                    header('Location: ' . ($destinos[$tipo] ?? 'index.php'));
                    exit;
                }

            } else {
                $labels        = ['vendedor' => 'Vendedor', 'atendente' => 'Atendente', 'caixa' => 'Caixa'];
                $mensagem      = ($labels[$tipo_login] ?? 'Usuário') . ' não encontrado, senha incorreta ou acesso desativado.';
                $tipo_mensagem = 'danger';
            }
        }
    }
}

$aba_ativa = $_POST['tipo_login'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title>Login — <?= htmlspecialchars($_SESSION['tenant_nome'] ?? 'Sistema') ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <?= aplicar_tema($conn) ?>
    <style>
    * { box-sizing: border-box; }
    body {
        display: flex; flex-direction: column; justify-content: center;
        align-items: center; min-height: 100vh;
        background: var(--dash-bg, #f1f5f9); padding: 16px;
    }
    .login-container { max-width: 380px; width: 100%; }
    .card {
        border-radius: 14px !important; padding: 14px 12px !important;
        box-shadow: 0 4px 24px rgba(0,0,0,.08) !important;
    }
    .tipo-selector {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: 5px; margin-bottom: 16px;
    }
    /* Esconde abas cujas telas não estão no plano */
    .tipo-btn { padding: 8px 3px; border: 1.5px solid #e2e8f0; border-radius: 8px;
        background: #f8fafc; cursor: pointer; text-align: center;
        transition: all .2s; font-family: inherit; }
    .tipo-btn.bloqueado { opacity: 0.35; cursor: not-allowed; filter: grayscale(1); }
    .tipo-btn .tipo-icon  { font-size: 14px; display: block; margin-bottom: 2px; }
    .tipo-btn .tipo-label { font-size: 9px; font-weight: 700; color: #64748b; display: block; }
    .tipo-btn:not(.bloqueado):hover { border-color: var(--primary); background: #eff6ff; }
    .tipo-btn.ativo { border-color: var(--primary); background: #fff;
        box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    .tipo-btn.ativo .tipo-label { color: var(--primary); }
    .campo-grupo { display: none; }
    .campo-grupo.show { display: block; }
    .login-hint {
        text-align: center; font-size: 10px; color: #64748b;
        background: #f8fafc; border-radius: 8px; padding: 5px 8px;
        margin-bottom: 12px; border: 1px solid #e2e8f0; min-height: 24px;
    }
    .plano-badge {
        text-align: center; font-size: 10px; font-weight: 700;
        color: #0f766e; background: #f0fdfa; border: 1px solid #99f6e4;
        border-radius: 6px; padding: 4px 10px; margin-bottom: 10px;
    }
    .form-group { margin-bottom: 11px; }
    .form-group label { font-size: 11px; font-weight: 600; margin-bottom: 4px; display: block; color: #475569; }
    .form-control { height: 38px !important; font-size: 13px !important;
        border-radius: 9px !important; padding: 0 11px !important; }
    .btn-primary { height: 40px !important; font-size: 13px !important;
        border-radius: 9px !important; margin-top: 6px !important; }
    .card-title { font-size: 1rem !important; margin: 0.25rem auto 1rem !important; }
    .btn-seta { font-size: 13px; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="card">
        <a href="index.php" class="btn-seta">&#8592;</a>

        <h1 class="card-title" style="text-align:center;margin:1rem auto 1.5rem;display:block;padding:0;color:var(--secondary);">
            Fazer Login
        </h1>

        <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <!-- SELETOR DE TIPO -->
        <div class="tipo-selector">
            <?php
            // Mapeamento: tipo_login => chave da tela
            $telas_tipo = [
                'admin'     => 'admin',
                'vendedor'  => 'venda_presencial',
                'atendente' => 'atendente',
                'caixa'     => 'caixa',
            ];
            $icons = ['admin'=>'','vendedor'=>'','atendente'=>'','caixa'=>''];
            $labels = ['admin'=>'Admin','vendedor'=>'Vendedor','atendente'=>'Atendente','caixa'=>'Caixa'];
            foreach (['admin','vendedor','atendente','caixa'] as $tipo):
                // Admin sempre liberado; staff verifica plano
                $liberado = ($tipo === 'admin') || tela_liberada($telas_tipo[$tipo]);
                $ativo    = $aba_ativa === $tipo ? 'ativo' : '';
                $bloq     = !$liberado ? 'bloqueado' : '';
                $onclick  = $liberado ? "onclick=\"selecionarTipo('$tipo')\"" : '';
            ?>
            <button type="button" class="tipo-btn <?= $ativo ?> <?= $bloq ?>"
                    id="btn-<?= $tipo ?>" <?= $onclick ?>
                    title="<?= $liberado ? '' : 'Não incluído no seu plano' ?>">
                <span class="tipo-icon"><?= $icons[$tipo] ?></span>
                <span class="tipo-label"><?= $labels[$tipo] ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <form method="POST" id="form-login">
            <input type="hidden" name="tipo_login" id="tipo_login" value="<?= $aba_ativa ?>">

            <div class="login-hint" id="login-hint"></div>

            <!-- CAMPO EMAIL (admin) -->
            <div class="campo-grupo <?= $aba_ativa==='admin' ? 'show':'' ?>" id="campos-admin">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="seu@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email">
                </div>
            </div>

            <!-- CAMPO USUÁRIO (vendedor / atendente / caixa) -->
            <div class="campo-grupo <?= in_array($aba_ativa,['vendedor','atendente','caixa']) ? 'show':'' ?>" id="campos-staff">
                <div class="form-group">
                    <label for="usuario">Usuário</label>
                    <input type="text" class="form-control" id="usuario" name="usuario"
                           placeholder="Digite seu usuário"
                           value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                           autocomplete="username">
                </div>
            </div>

            <!-- SENHA -->
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" class="form-control" id="senha" name="senha"
                       placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem;">
                Entrar
            </button>
        </form>
    </div>
</div>

<script src="js/main.js"></script>
<script>
const hints = {
    admin:     ' Acesso ao painel administrativo completo',
    vendedor:  ' Acesso à tela de Venda Presencial',
    atendente: ' Acesso à tela de Lançar Comanda',
    caixa:     ' Acesso ao Painel do Caixa',
};

function selecionarTipo(tipo) {
    document.getElementById('tipo_login').value = tipo;
    ['admin','vendedor','atendente','caixa'].forEach(t => {
        document.getElementById('btn-' + t).classList.toggle('ativo', t === tipo);
    });
    document.getElementById('campos-admin').classList.toggle('show',  tipo === 'admin');
    document.getElementById('campos-staff').classList.toggle('show',  tipo !== 'admin');
    document.getElementById('login-hint').innerHTML = hints[tipo] || '';
    setTimeout(() => {
        const el = tipo === 'admin'
            ? document.getElementById('email')
            : document.getElementById('usuario');
        if (el) el.focus();
    }, 50);
}

selecionarTipo('<?= $aba_ativa ?>');

document.getElementById('form-login').addEventListener('submit', function(e) {
    const tipo  = document.getElementById('tipo_login').value;
    const senha = document.getElementById('senha').value;
    if (tipo === 'admin') {
        if (!document.getElementById('email').value.trim() || !senha) { e.preventDefault(); return; }
    } else {
        if (!document.getElementById('usuario').value.trim() || !senha) { e.preventDefault(); return; }
    }
});
</script>
</body>
</html>
