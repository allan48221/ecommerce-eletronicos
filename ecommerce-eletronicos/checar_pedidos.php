<?php
// checar_pedidos.php
// Chamado a cada 30s pelo admin.php para verificar novos pedidos
// Retorna pedidos pendentes criados após o timestamp enviado

require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_admin'])) {
    echo json_encode(['novos' => 0]);
    exit;
}

$desde = $_GET['desde'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));

// PDO PostgreSQL: parâmetro preparado, sem real_escape_string
$stmt = $conn->prepare("
    SELECT id_pedido, nome_cliente, valor_total
    FROM pedidos
    WHERE status = 'pendente'
    AND data_pedido > ?
    ORDER BY data_pedido DESC
");
$stmt->execute([$desde]);
$novos = $stmt->fetchAll();

echo json_encode([
    'novos'   => count($novos),
    'pedidos' => $novos
]);
