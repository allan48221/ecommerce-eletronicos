<?php
require_once 'config/database.php';
$id_tenant = $_SESSION['id_tenant'] ?? null;
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Metodo invalido']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!$dados) {
    echo json_encode(['success' => false, 'msg' => 'Dados invalidos']);
    exit;
}

$nome        = trim($dados['nome'] ?? '');
$email       = trim($dados['email'] ?? '');
$cpf         = preg_replace('/[^0-9]/', '', $dados['cpf'] ?? '');
$telefone    = trim($dados['telefone'] ?? '');
$endereco    = trim($dados['endereco'] ?? '');
$numero      = trim($dados['numero'] ?? '');
$complemento = trim($dados['complemento'] ?? '');
$bairro      = trim($dados['bairro'] ?? '');
$cidade      = trim($dados['cidade'] ?? '');
$estado      = trim($dados['estado'] ?? '');
$cep         = preg_replace('/[^0-9]/', '', $dados['cep'] ?? '');
$pagamento   = trim($dados['forma_pagamento'] ?? '');
$observacoes = trim($dados['observacoes'] ?? '');
$val_produtos = floatval($dados['valor_produtos'] ?? 0);
$val_frete    = floatval($dados['valor_frete'] ?? 0);
$val_total    = floatval($dados['valor_total'] ?? 0);
$itens        = $dados['itens'] ?? [];

if (!$nome || !$pagamento || empty($itens)) {
    echo json_encode(['success' => false, 'msg' => 'Campos obrigatorios faltando']);
    exit;
}

try {
    $conn->exec("ALTER TABLE itens_pedido ADD COLUMN IF NOT EXISTS adicionais_json TEXT DEFAULT NULL");
} catch (\Throwable $e) {}

$conn->beginTransaction();
try {
    $sql = "INSERT INTO pedidos 
            (nome_cliente, email_cliente, cpf_cliente, telefone_cliente,
             endereco, numero, complemento, bairro, cidade, estado, cep,
             forma_pagamento, observacoes, valor_produtos, valor_frete, valor_total, status, data_pedido, id_tenant)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW(), ?)
            RETURNING id_pedido";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $nome, $email, $cpf, $telefone,
        $endereco, $numero, $complemento, $bairro, $cidade, $estado, $cep,
        $pagamento, $observacoes, $val_produtos, $val_frete, $val_total, $id_tenant
    ]);

    $row       = $stmt->fetch();
    $id_pedido = $row['id_pedido'];

    $stmt_item = $conn->prepare(
        "INSERT INTO itens_pedido
            (id_pedido, id_produto, nome_produto, quantidade, preco_unitario, subtotal, adicionais_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($itens as $item) {
        $adicionais = $item['adicionais'] ?? [];
        $adicionais_json = !empty($adicionais)
            ? json_encode($adicionais, JSON_UNESCAPED_UNICODE)
            : null;

        $stmt_item->execute([
            $id_pedido,
            intval($item['id_produto']),
            trim($item['nome_produto']),
            intval($item['quantidade']),
            floatval($item['preco_unitario']),
            floatval($item['subtotal']),
            $adicionais_json,
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'id_pedido' => $id_pedido]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
