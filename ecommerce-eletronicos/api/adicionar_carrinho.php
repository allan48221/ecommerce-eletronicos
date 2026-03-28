
<?php
// database.php já chama session_start() — NÃO repetir aqui
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

$id_produto = isset($_POST['id_produto']) ? intval($_POST['id_produto']) : 0;
$quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 1;
$acao       = isset($_POST['acao'])       ? $_POST['acao']               : 'adicionar';

// Adicionais enviados como JSON no campo 'adicionais'
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

if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Garante que itens antigos (formato simples) sejam convertidos para o novo formato
foreach ($_SESSION['carrinho'] as $pid => $val) {
    if (!is_array($val)) {
        $_SESSION['carrinho'][$pid] = [
            'quantidade' => intval($val),
            'adicionais' => [],
        ];
    }
}

switch ($acao) {

    case 'adicionar':
        $item_atual = $_SESSION['carrinho'][$id_produto] ?? ['quantidade' => 0, 'adicionais' => []];
        $nova_qtd   = $item_atual['quantidade'] + $quantidade;

        if ($nova_qtd > $produto['estoque']) {
            echo json_encode([
                'success' => false,
                'message' => 'Estoque insuficiente. Disponivel: ' . $produto['estoque']
            ]);
            exit;
        }

        $_SESSION['carrinho'][$id_produto] = [
            'quantidade' => $nova_qtd,
            'adicionais' => $adicionais,
        ];

        $total_itens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));

        echo json_encode([
            'success'     => true,
            'message'     => 'Produto adicionado ao carrinho',
            'total_itens' => $total_itens,
        ]);
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
            $item_atual = $_SESSION['carrinho'][$id_produto] ?? ['quantidade' => 0, 'adicionais' => []];
            $_SESSION['carrinho'][$id_produto] = [
                'quantidade' => $quantidade,
                'adicionais' => $item_atual['adicionais'],
            ];
        } else {
            unset($_SESSION['carrinho'][$id_produto]);
        }

        $total_itens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));

        echo json_encode([
            'success'     => true,
            'message'     => 'Quantidade atualizada',
            'total_itens' => $total_itens,
        ]);
        break;

    case 'remover':
        unset($_SESSION['carrinho'][$id_produto]);

        $total_itens = !empty($_SESSION['carrinho'])
            ? array_sum(array_column($_SESSION['carrinho'], 'quantidade'))
            : 0;

        echo json_encode([
            'success'     => true,
            'message'     => 'Produto removido do carrinho',
            'total_itens' => $total_itens,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acao invalida']);
        break;
}