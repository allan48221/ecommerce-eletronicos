<?php
require_once 'config/database.php';
require_once 'config/tema.php';

// Se já está logado como vendedor, vai direto para venda
if (isset($_SESSION['id_vendedor'])) {
    header('Location: venda_presencial.php');
    exit;
}
// Se já está logado como admin, vai para admin
if (isset($_SESSION['id_admin'])) {
    header('Location: admin.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = trim($_POST['senha']   ?? '');

    if (empty($usuario) || empty($senha)) {
        $mensagem = 'Preencha usuário e senha.';
        $tipo_mensagem = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT * FROM vendedores WHERE usuario = ? AND ativo = TRUE");
        $stmt->execute([$usuario]);
        $vendedor = $stmt->fetch();

        if ($vendedor && password_verify($senha, $vendedor['senha'])) {
            $_SESSION['id_vendedor']   = $vendedor['id_vendedor'];
            $_SESSION['nome_vendedor'] = $vendedor['nome'];
            // Define id_admin temporário para passar pela verificação do venda_presencial.php
            // Usa um valor negativo reservado para não colidir com admins reais
            $_SESSION['id_admin']      = 'vendedor_' . $vendedor['id_vendedor'];
            $_SESSION['nome_admin']    = $vendedor['nome'];
            $_SESSION['is_vendedor']   = true;
            header('Location: venda_presencial.php');
            exit;
        } else {
            $mensagem = 'Usuário ou senha incorretos, ou acesso desativado.';
            $tipo_mensagem = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Login — Vendedor</title>
<link rel="stylesheet" href="css/style.css">
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; background: #f0fdf4; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-box { background: #fff; border-radius: 22px; border: 1.5px solid #d1fae5; box-shadow: 0 8px 40px rgba(5,150,105,.13); width: 100%; max-width: 400px; overflow: hidden; }
.login-hero { background: linear-gradient(135deg, #059669 0%, #065f46 100%); padding: 32px 28px 28px; text-align: center; color: #fff; }
.login-hero .icon { font-size: 48px; margin-bottom: 10px; }
.login-hero h1 { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
.login-hero p  { font-size: 13px; opacity: .75; }
.login-body { padding: 28px; }
.alert { padding: 12px 15px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 18px; border-left: 4px solid; }
.alert.danger  { background: #fef2f2; color: #7f1d1d; border-color: #ef4444; }
.form-field { margin-bottom: 16px; }
.form-field label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #64748b; margin-bottom: 7px; }
.form-field input { width: 100%; padding: 13px 15px; border: 2px solid #e2e8f0; border-radius: 11px; font-size: 15px; font-family: 'Sora', sans-serif; outline: none; transition: .2s; background: #fff; color: #0f172a; }
.form-field input:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5,150,105,.1); }
.btn-entrar { width: 100%; padding: 15px; background: linear-gradient(135deg, #059669, #047857); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; box-shadow: 0 4px 16px rgba(5,150,105,.3); transition: .2s; margin-top: 6px; }
.btn-entrar:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,.4); }
.login-footer { text-align: center; padding: 0 28px 24px; font-size: 12px; color: #9ca3af; }
@media (max-width: 440px) {
    body { align-items: flex-start; padding: 16px; }
    .login-hero { padding: 24px 20px 20px; }
    .login-body { padding: 20px; }
}
</style>
</head>
<body>

<div class="login-box">
    <div class="login-hero">
        <div class="icon">&#128100;</div>
        <h1>Área do Vendedor</h1>
        <p>Acesso exclusivo para venda presencial</p>
    </div>

    <div class="login-body">
        <?php if ($mensagem): ?>
        <div class="alert danger"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-field">
                <label>Usuário</label>
                <input type="text" name="usuario" placeholder="Digite seu usuário" required autocomplete="username"
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </div>
            <div class="form-field">
                <label>Senha</label>
                <input type="password" name="senha" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-entrar">&#10003; Entrar</button>
        </form>
    </div>

    <div class="login-footer">
        Problemas de acesso? Fale com o administrador.
    </div>
</div>

</body>
</html>