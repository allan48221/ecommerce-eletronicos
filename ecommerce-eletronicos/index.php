<?php
session_start();

require_once 'config/database.php';
require_once 'config/tema.php';
require_once 'empresa_helper.php';

$id_tenant = $_SESSION['id_tenant'] ?? null;

// Dados da empresa
$emp = getDadosEmpresa($conn, $id_tenant);

// Produtos
$produtos = $conn->query("SELECT * FROM produtos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$produtos_destaque = $conn->query("SELECT * FROM produtos WHERE destaque = 1 ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($emp['nome'] ?? 'Minha Loja') ?></title>
</head>

<body>

<!-- LOGO -->
<h2>Logo da Empresa</h2>

<?php if (!empty($emp['logo'])): ?>
    <img src="<?= img_src($emp['logo']) ?>" width="150">
<?php else: ?>
    <p>Sem logo</p>
<?php endif; ?>

<hr>

<!-- PRODUTOS EM DESTAQUE -->
<h2>Produtos em Destaque</h2>

<?php foreach ($produtos_destaque as $produto): ?>
    <div style="margin-bottom:20px;">
        
        <img src="<?= img_src($produto['imagem']) ?: 'uploads/placeholder.jpg' ?>" width="120">

        <p><strong><?= htmlspecialchars($produto['nome']) ?></strong></p>
        <p>R$ <?= number_format($produto['preco'], 2, ',', '.') ?></p>

        <!-- DEBUG (REMOVE DEPOIS) -->
        <pre><?= $produto['imagem'] ?></pre>

    </div>
<?php endforeach; ?>

<hr>

<!-- TODOS OS PRODUTOS -->
<h2>Todos os Produtos</h2>

<?php foreach ($produtos as $produto): ?>
    <div style="margin-bottom:20px;">
        
        <img src="<?= img_src($produto['imagem']) ?: 'uploads/placeholder.jpg' ?>" width="120">

        <p><strong><?= htmlspecialchars($produto['nome']) ?></strong></p>
        <p>R$ <?= number_format($produto['preco'], 2, ',', '.') ?></p>

        <!-- DEBUG (REMOVE DEPOIS) -->
        <pre><?= $produto['imagem'] ?></pre>

    </div>
<?php endforeach; ?>

</body>
</html>
