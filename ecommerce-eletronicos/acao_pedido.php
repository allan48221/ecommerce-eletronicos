<?php
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
        // Baixa no estoque
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

        // Atualiza status do pedido
        $stmt_upd = $conn->prepare("UPDATE pedidos SET status = 'confirmado', data_acao = NOW() WHERE id_pedido = ?");
        $stmt_upd->execute([$id_pedido]);

        // ✅ Insere na tabela compras para aparecer no dashboard
        $stmt_compra = $conn->prepare("
            INSERT INTO compras (
                id_tenant,
                valor_produtos,
                valor_frete,
                valor_total,
                forma_pagamento,
                observacoes,
                status,
                endereco_entrega,
                numero_entrega,
                complemento_entrega,
                bairro_entrega,
                cidade_entrega,
                estado_entrega,
                cep_entrega,
                data_compra
            ) VALUES (?, ?, ?, ?, ?, ?, 'confirmado', ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt_compra->execute([
            $pedido['id_tenant']          ?? null,
            $pedido['valor_total']         ?? 0,
            $pedido['valor_frete']         ?? 0,
            $pedido['valor_total']         ?? 0,
            $pedido['forma_pagamento']     ?? '',
            $pedido['observacoes']         ?? '',
            $pedido['endereco_entrega']    ?? '',
            $pedido['numero_entrega']      ?? '',
            $pedido['complemento_entrega'] ?? '',
            $pedido['bairro_entrega']      ?? '',
            $pedido['cidade_entrega']      ?? '',
            $pedido['estado_entrega']      ?? '',
            $pedido['cep_entrega']         ?? '',
        ]);

        $conn->commit();
        echo json_encode(['success' => true, 'msg' => '✅ Venda confirmada e estoque atualizado!']);

    } else {
        $stmt_upd = $conn->prepare("UPDATE pedidos SET status = 'cancelado', data_acao = NOW() WHERE id_pedido = ?");
        $stmt_upd->execute([$id_pedido]);
        $conn->commit();
        echo json_encode(['success' => true, 'msg' => '❌ Pedido marcado como não realizado. Estoque mantido.']);
    }

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
