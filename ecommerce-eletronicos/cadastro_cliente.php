<?php
require_once 'config/database.php';

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['nome']);
    $email       = trim($_POST['email']);
    $senha       = $_POST['senha'];
    $cpf         = preg_replace('/[^0-9]/', '', $_POST['cpf']);
    $telefone    = trim($_POST['telefone']);
    $cep         = preg_replace('/[^0-9]/', '', $_POST['cep']);
    $endereco    = trim($_POST['endereco']);
    $numero      = trim($_POST['numero']);
    $complemento = trim($_POST['complemento']);
    $bairro      = trim($_POST['bairro']);
    $cidade      = trim($_POST['cidade']);
    $estado      = trim($_POST['estado']);

    if (!validarEmail($email)) {
        $mensagem = 'Email inválido!';
        $tipo_mensagem = 'danger';
    } elseif (!validarCPF($cpf)) {
        $mensagem = 'CPF inválido!';
        $tipo_mensagem = 'danger';
    } elseif (strlen($senha) < 6) {
        $mensagem = 'A senha deve ter pelo menos 6 caracteres!';
        $tipo_mensagem = 'danger';
    } else {
        // Verifica e-mail duplicado
        $stmt = $conn->prepare("SELECT id_cliente FROM clientes WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $mensagem = 'Este email já está cadastrado!';
            $tipo_mensagem = 'danger';
        } else {
            // Verifica CPF duplicado
            $stmt_cpf = $conn->prepare("SELECT id_cliente FROM clientes WHERE cpf = ?");
            $stmt_cpf->execute([$cpf]);
            if ($stmt_cpf->fetch()) {
                $mensagem = 'Este CPF já está cadastrado!';
                $tipo_mensagem = 'danger';
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                // PostgreSQL: RETURNING para obter o ID do novo cliente
                $sql = "INSERT INTO clientes
                        (nome, email, senha, cpf, telefone, cep, endereco, numero, complemento, bairro, cidade, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id_cliente";

                $stmt = $conn->prepare($sql);
                $ok   = $stmt->execute([
                    $nome, $email, $senha_hash, $cpf, $telefone,
                    $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado
                ]);

                if ($ok) {
                    $row = $stmt->fetch();
                    $_SESSION['id_cliente']    = $row['id_cliente'];
                    $_SESSION['nome_cliente']  = $nome;
                    $_SESSION['email_cliente'] = $email;

                    $mensagem = 'Cadastro realizado com sucesso! Redirecionando...';
                    $tipo_mensagem = 'success';

                    header("refresh:2;url=index.php");
                } else {
                    $mensagem = 'Erro ao cadastrar cliente.';
                    $tipo_mensagem = 'danger';
                }
            }
        }
    }
}

$total_carrinho = 0;
if (isset($_SESSION['carrinho'])) {
    $total_carrinho = array_sum($_SESSION['carrinho']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Cliente - TechStore</title>
    <link rel="stylesheet" href="css/style.css">
     <link rel="icon" type="image/png" href="favicon.png?v=1">
</head>
<body>
    <header class="navbar">
        <div class="container">
            <a href="index.php" class="logo">⚡ TechStore</a>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="cadastro_cliente.php">Cadastro</a></li>
                    <li><a href="cadastro_produto.php">Admin</a></li>
                    <li>
                        <a href="carrinho.php" class="btn-carrinho">
                             Carrinho
                            <?php if ($total_carrinho > 0): ?>
                                <span class="cart-badge"><?php echo $total_carrinho; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="card form-card">
      <div class="voltar-topo">
    <a href="index.php" class="btn-seta">&#8592;</a>
</div>
            <h1 class="card-title"> Cadastro de Cliente</h1>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="form-cliente">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cpf">CPF *</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" maxlength="14" required>
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone *</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" maxlength="15" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">Senha *</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha *</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                    </div>
                </div>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: var(--dark);"> Endereço</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cep">CEP *</label>
                        <input type="text" class="form-control" id="cep" name="cep" maxlength="9" required>
                        <small style="color: var(--gray);">Digite o CEP para buscar automaticamente</small>
                    </div>
                    <div class="form-group">
                        <label for="endereco">Endereço *</label>
                        <input type="text" class="form-control" id="endereco" name="endereco" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="numero">Número *</label>
                        <input type="text" class="form-control" id="numero" name="numero" required>
                    </div>
                    <div class="form-group">
                        <label for="complemento">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="bairro">Bairro *</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" required>
                    </div>
                    <div class="form-group">
                        <label for="cidade">Cidade *</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" required>
                    </div>
                    <div class="form-group">
                        <label for="estado">Estado *</label>
                        <select class="form-control" id="estado" name="estado" required>
                            <option value="">Selecione...</option>
                            <option value="AC">Acre</option>
                            <option value="AL">Alagoas</option>
                            <option value="AP">Amapá</option>
                            <option value="AM">Amazonas</option>
                            <option value="BA">Bahia</option>
                            <option value="CE">Ceará</option>
                            <option value="DF">Distrito Federal</option>
                            <option value="ES">Espírito Santo</option>
                            <option value="GO">Goiás</option>
                            <option value="MA">Maranhão</option>
                            <option value="MT">Mato Grosso</option>
                            <option value="MS">Mato Grosso do Sul</option>
                            <option value="MG">Minas Gerais</option>
                            <option value="PA">Pará</option>
                            <option value="PB">Paraíba</option>
                            <option value="PR">Paraná</option>
                            <option value="PE">Pernambuco</option>
                            <option value="PI">Piauí</option>
                            <option value="RJ">Rio de Janeiro</option>
                            <option value="RN">Rio Grande do Norte</option>
                            <option value="RS">Rio Grande do Sul</option>
                            <option value="RO">Rondônia</option>
                            <option value="RR">Roraima</option>
                            <option value="SC">Santa Catarina</option>
                            <option value="SP">São Paulo</option>
                            <option value="SE">Sergipe</option>
                            <option value="TO">Tocantins</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 2rem;">
                    ✓ Cadastrar
                </button>
            </form>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>