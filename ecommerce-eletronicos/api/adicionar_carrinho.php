<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

$id_produto = isset($_POST['id_produto']) ? intval($_POST['id_produto']) : 0;
$quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 1;
$acao       = isset($_POST['acao'])       ? $_POST['acao']               : 'adicionar';

$adicionais = [];
if (!empty($_POST['adicionais'])) {
    $decoded = json_decode($_POST['adicionais'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $ad) {
            $adicionais[] = [
                'id'    => intval($ad['id']    ?? 0),
                'nome'  => strip_tags($ad['nome']  ?? ''),
                'preco' => floatval($ad['preco'] ?? 0),
            ];
        }
    }
}

if ($id_produto <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produto invalido']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM produtos WHERE id_produto = ? AND ativo = TRUE");
$stmt->execute([$id_produto]);
$produto = $stmt->fetch();

if (!$produto) {
    echo json_encode(['success' => false, 'message' => 'Produto nao encontrado']);
    exit;
}

// Lê carrinho do cookie
$carrinho = [];
if (!empty($_COOKIE['carrinho'])) {
    $decoded = json_decode(base64_decode($_COOKIE['carrinho']), true);
    if (is_array($decoded)) $carrinho = $decoded;
}

// Normaliza itens antigos
foreach ($carrinho as $pid => $val) {
    if (!is_array($val)) {
        $carrinho[$pid] = ['quantidade' => intval($val), 'adicionais' => []];
    }
}

switch ($acao) {

    case 'adicionar':
        $item_atual = $carrinho[$id_produto] ?? ['quantidade' => 0, 'adicionais' => []];
        $nova_qtd   = $item_atual['quantidade'] + $quantidade;

        if ($nova_qtd > $produto['estoque']) {
            echo json_encode([
                'success' => false,
                'message' => 'Estoque insuficiente. Disponivel: ' . $produto['estoque']
            ]);
            exit;
        }

        $carrinho[$id_produto] = ['quantidade' => $nova_qtd, 'adicionais' => $adicionais];
        break;

    case 'atualizar':
        if ($quantidade > $produto['estoque']) {
            echo json_encode([
                'success' => false,
                'message' => 'Estoque insuficiente. Disponivel: ' . $produto['estoque']
            ]);
            exit;
        }

        if ($quantidade > 0) {
            $item_atual = $carrinho[$id_produto] ?? ['quantidade' => 0, 'adicionais' => []];
            $carrinho[$id_produto] = ['quantidade' => $quantidade, 'adicionais' => $item_atual['adicionais']];
        } else {
            unset($carrinho[$id_produto]);
        }
        break;

    case 'remover':
        unset($carrinho[$id_produto]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acao invalida']);
        exit;
}

// Salva no cookie (30 dias)
$cookie_val = base64_encode(json_encode($carrinho));
setcookie('carrinho', $cookie_val, [
    'expires'  => time() + (86400 * 30),
    'path'     => '/',
    'secure'   => true,
    'httponly' => false,
    'samesite' => 'Lax',
]);

// Sincroniza com a sessão para carrinho.php e finalizar_compra.php funcionarem
$_SESSION['carrinho'] = $carrinho;

$total_itens = array_sum(array_column($carrinho, 'quantidade'));

$mensagens = [
    'adicionar' => 'Produto adicionado ao carrinho',
    'atualizar' => 'Quantidade atualizada',
    'remover'   => 'Produto removido do carrinho',
];

echo json_encode([
    'success'     => true,
    'message'     => $mensagens[$acao] ?? 'OK',
    'total_itens' => $total_itens,
]);
