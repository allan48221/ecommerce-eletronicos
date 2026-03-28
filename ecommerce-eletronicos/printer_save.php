<?php
// printer_save.php
// Salva configuração das impressoras no PostgreSQL

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php'; // sua conexão PDO com PostgreSQL

$body = json_decode(file_get_contents('php://input'), true);

if (!isset($body['printers'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    // Garante que a tabela existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS impressoras (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            porta VARCHAR(10) DEFAULT '9100',
            categorias TEXT[] DEFAULT '{}',
            ativo BOOLEAN DEFAULT TRUE,
            criado_em TIMESTAMPTZ DEFAULT NOW(),
            atualizado_em TIMESTAMPTZ DEFAULT NOW()
        )
    ");

    // Apaga todas e reinseretadas (simples para poucos registros)
    $pdo->exec("DELETE FROM impressoras");

    $stmt = $pdo->prepare("
        INSERT INTO impressoras (id, nome, ip, porta, categorias, ativo)
        VALUES (:id, :nome, :ip, :porta, :categorias, TRUE)
    ");

    foreach ($body['printers'] as $p) {
        $cats = isset($p['categorias']) ? $p['categorias'] : [];

        // Converte array PHP para formato array do PostgreSQL: {val1,val2}
        $catsStr = '{' . implode(',', array_map(fn($c) => '"' . addslashes($c) . '"', $cats)) . '}';

        $stmt->execute([
            ':id'         => $p['id'],
            ':nome'       => $p['nome'],
            ':ip'         => $p['ip'],
            ':porta'      => $p['porta'] ?? '9100',
            ':categorias' => $catsStr,
        ]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}