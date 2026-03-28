<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido. Use POST.']);
    exit;
}

function limpar($valor) {
    return trim(htmlspecialchars($valor));
}

$nome = limpar($_POST['nome'] ?? '');
$descricao = limpar($_POST['descricao'] ?? '');
$marca = limpar($_POST['marca'] ?? '');
$modelo = limpar($_POST['modelo'] ?? '');
$preco = floatval($_POST['preco'] ?? 0);
$preco_promocional = floatval($_POST['preco_promocional'] ?? 0);
$estoque = intval($_POST['estoque'] ?? 0);
$id_categoria = intval($_POST['id_categoria'] ?? 0);
$destaque = isset($_POST['destaque']) ? intval($_POST['destaque']) : 0;
$ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;


if (strlen($nome) < 3) {
    echo json_encode(['success' => false, 'message' => 'Nome do produto deve ter pelo menos 3 caracteres.']); exit;
}
if ($preco <= 0) {
    echo json_encode(['success' => false, 'message' => 'Preço inválido.']); exit;
}
if ($estoque < 0) {
    echo json_encode(['success' => false, 'message' => 'Estoque inválido.']); exit;
}

$imagem_nome = null;
$upload_dir = '../uploads/';

if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
    $tamanho = $_FILES['imagem']['size'];

    if (!in_array($extensao, $permitidos)) {
        echo json_encode(['success' => false, 'message' => 'Formato de imagem não permitido. Use JPG, PNG, GIF ou WEBP.']); exit;
    }

    if ($tamanho > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Imagem excede o limite de 5MB.']); exit;
    }

    $imagem_nome = uniqid() . '_' . time() . '.' . $extensao;
    if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_dir . $imagem_nome)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar imagem.']); exit;
    }
}

$sql = "INSERT INTO produtos 
(nome, descricao, marca, modelo, preco, preco_promocional, estoque, id_categoria, imagem, destaque, ativo)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);

try {
    $stmt->execute([$nome, $descricao, $marca, $modelo, $preco, $preco_promocional, $estoque, $id_categoria, $imagem_nome, $destaque, $ativo]);
    $id_produto = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Produto cadastrado com sucesso!',
        'id_produto' => $id_produto,
        'nome' => $nome,
        'imagem' => $imagem_nome
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar produto: ' . $e->getMessage()]);
}