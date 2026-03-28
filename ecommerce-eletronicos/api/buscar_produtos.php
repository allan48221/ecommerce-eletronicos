<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$termo = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($termo) < 2) {
    echo json_encode(['success' => false, 'message' => 'Digite pelo menos 2 caracteres']);
    exit;
}

$sql = "SELECT p.id_produto, p.nome, p.preco, p.preco_promocional, p.imagem, c.nome as categoria_nome
        FROM produtos p
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        WHERE p.ativo = TRUE
        AND (p.nome ILIKE ? OR p.marca ILIKE ? OR p.modelo ILIKE ? OR c.nome ILIKE ?)
        ORDER BY p.nome ASC
        LIMIT 10";

// PostgreSQL: ILIKE para busca case-insensitive (equivalente ao LIKE do MySQL)
$termo_busca = "%{$termo}%";
$stmt = $conn->prepare($sql);
$stmt->execute([$termo_busca, $termo_busca, $termo_busca, $termo_busca]);

$produtos = [];
while ($produto = $stmt->fetch()) {
    $preco_atual = $produto['preco_promocional'] ?: $produto['preco'];

    $produtos[] = [
        'id_produto'     => $produto['id_produto'],
        'nome'           => $produto['nome'],
        'preco_atual'    => $preco_atual,
        'preco_original' => $produto['preco'],
        'imagem'         => $produto['imagem'],
        'categoria'      => $produto['categoria_nome']
    ];
}

if (count($produtos) > 0) {
    echo json_encode([
        'success'  => true,
        'produtos' => $produtos,
        'total'    => count($produtos)
    ]);
} else {
    echo json_encode([
        'success'  => false,
        'message'  => 'Nenhum produto encontrado',
        'produtos' => []
    ]);
}