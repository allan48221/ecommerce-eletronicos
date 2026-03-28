<?php
// acao_pedido.php
// Chamado via AJAX pelo admin
// Processa confirmação ou cancelamento de pedido

require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_admin'])) {
    echo json_encode(['success' => false, 'msg' => 'Não autorizado']);
    exit;
}

$dados     = json_decode(file_get_contents('php://input'), true);
$id_pedido = intval($dados['id_pedido'] ?? 0);
$acao      = $dados['acao'] ?? '';

if (!$id_pedido || !in_array($acao, ['confirmar', 'cancelar'])) {
    echo json_encode(['success' => false, 'msg' => 'Dados inválidos']);
    exit;
}

// Verificar se pedido existe e ainda está pendente
$stmt = $conn->prepare("SELECT * FROM pedidos WHERE id_pedido = ? AND status = 'pendente'");
$stmt->execute([$id_pedido]);
$pedido = $stmt->fetch();

if (!$pedido) {
    echo json_encode(['success' => false, 'msg' => 'Pedido não encontrado ou já processado.']);
    exit;
}

$conn->beginTransaction();
try {
    if ($acao === 'confirmar') {
        // Buscar itens e dar baixa no estoque
        $stmt_itens = $conn->prepare("SELECT * FROM itens_pedido WHERE id_pedido = ?");
        $stmt_itens->execute([$id_pedido]);
        $itens = $stmt_itens->fetchAll();

        $stmt_estoque = $conn->prepare(
            "UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ? AND estoque >= ?"
        );

        foreach ($itens as $item) {
            $stmt_estoque->execute([$item['quantidade'], $item['id_produto'], $item['quantidade']]);
            if ($stmt_estoque->rowCount() === 0) {
                throw new Exception("Estoque insuficiente para: {$item['nome_produto']}");
            }
        }

        $stmt_upd = $conn->prepare("UPDATE pedidos SET status = 'confirmado', data_acao = NOW() WHERE id_pedido = ?");
        $stmt_upd->execute([$id_pedido]);

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => ' Venda confirmada e estoque atualizado!']);

    } else {
        // Cancelar: não mexe no estoque
        $stmt_upd = $conn->prepare("UPDATE pedidos SET status = 'cancelado', data_acao = NOW() WHERE id_pedido = ?");
        $stmt_upd->execute([$id_pedido]);

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => ' Pedido marcado como não realizado. Estoque mantido.']);
    }

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
