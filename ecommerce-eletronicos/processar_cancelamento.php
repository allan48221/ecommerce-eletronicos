<?php
/**
 * processar_cancelamento.php
 * Recebe JSON via POST: { id_pedido: int, motivo: string }
 * - Valida sessão de admin
 * - Muda status para 'cancelado'
 * - Devolve o estoque de cada item
 * - Registra motivo + data/hora + id_admin na tabela cancelamentos
 */

require_once 'config/database.php';

header('Content-Type: application/json');

// ── Autenticação ──
if (!isset($_SESSION['id_admin'])) {
    echo json_encode(['success' => false, 'msg' => 'Não autorizado.']);
    exit;
}

// ── Lê o JSON enviado ──
$input     = json_decode(file_get_contents('php://input'), true);
$id_pedido = intval($input['id_pedido'] ?? 0);
$motivo    = trim($input['motivo']    ?? '');

// ── Validações básicas ──
if ($id_pedido <= 0) {
    echo json_encode(['success' => false, 'msg' => 'ID de pedido inválido.']);
    exit;
}
if (empty($motivo)) {
    echo json_encode(['success' => false, 'msg' => 'Informe o motivo do cancelamento.']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Busca o pedido e valida se ainda pode ser cancelado
    $stmt = $conn->prepare("SELECT id_pedido, status FROM pedidos WHERE id_pedido = ? FOR UPDATE");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'msg' => 'Pedido não encontrado.']);
        exit;
    }
    if (in_array($pedido['status'], ['cancelado', 'expirado'])) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'msg' => 'Este pedido já está cancelado.']);
        exit;
    }

    // 2. Devolve o estoque de cada item
    $itens_stmt = $conn->prepare("SELECT id_produto, quantidade FROM itens_pedido WHERE id_pedido = ?");
    $itens_stmt->execute([$id_pedido]);
    $itens = $itens_stmt->fetchAll();

    foreach ($itens as $item) {
        $conn->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id_produto = ?")
             ->execute([$item['quantidade'], $item['id_produto']]);
    }

    // 3. Atualiza status do pedido para 'cancelado'
    $conn->prepare("UPDATE pedidos SET status = 'cancelado', data_acao = NOW() WHERE id_pedido = ?")
         ->execute([$id_pedido]);

    // 4. Registra o cancelamento na tabela de log
    //    Tenta criar a tabela se ainda não existir (seguro para primeira execução)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS cancelamentos (
            id              SERIAL PRIMARY KEY,
            id_pedido       INTEGER NOT NULL REFERENCES pedidos(id_pedido),
            motivo          TEXT    NOT NULL,
            id_admin        INTEGER,
            data_cancelamento TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");

    $conn->prepare("
        INSERT INTO cancelamentos (id_pedido, motivo, id_admin, data_cancelamento)
        VALUES (?, ?, ?, NOW())
    ")->execute([$id_pedido, $motivo, $_SESSION['id_admin']]);

    $conn->commit();

    $qtd_itens = count($itens);
    echo json_encode([
        'success' => true,
        'msg'     => "Pedido #$id_pedido cancelado. Estoque de $qtd_itens produto(s) devolvido(s)."
    ]);

} catch (\Throwable $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'msg'     => 'Erro interno: ' . $e->getMessage()
    ]);
}