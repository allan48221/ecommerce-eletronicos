<?php
/**
 * impressoras_ajax.php
 * Endpoints AJAX para salvar/carregar impressoras.
 * NÃO tem nenhum output HTML — só JSON puro.
 */
require_once 'config/database.php';

header('Content-Type: application/json');

// Garante que a tabela existe
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS impressoras (
            id            SERIAL PRIMARY KEY,
            nome          VARCHAR(100) NOT NULL,
            ip            VARCHAR(45)  NOT NULL DEFAULT '',
            porta         VARCHAR(10)  NOT NULL DEFAULT '9100',
            categorias    TEXT[]       NOT NULL DEFAULT '{}',
            ativo         BOOLEAN      NOT NULL DEFAULT TRUE,
            criado_em     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            atualizado_em TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        )
    ");
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tabela: ' . $e->getMessage()]);
    exit;
}

$acao = $_GET['acao'] ?? '';

// ── SALVAR ────────────────────────────────────────────────────
if ($acao === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!isset($body['printers'])) {
        echo json_encode(['success' => false, 'message' => 'Dados invalidos']);
        exit;
    }

    try {
        $conn->exec("DELETE FROM impressoras");
        $stmt = $conn->prepare("
            INSERT INTO impressoras (id, nome, ip, porta, categorias)
            VALUES (:id, :nome, :ip, :porta, :cats)
        ");
        foreach ($body['printers'] as $p) {
            $cats    = $p['cats'] ?? $p['categorias'] ?? [];
            $catsStr = '{' . implode(',', array_map(fn($c) => '"' . addslashes($c) . '"', $cats)) . '}';
            $stmt->execute([
                ':id'    => intval($p['id']),
                ':nome'  => $p['nome']  ?? '',
                ':ip'    => $p['ip']    ?? '',
                ':porta' => $p['porta'] ?? '9100',
                ':cats'  => $catsStr,
            ]);
        }
        $conn->exec("SELECT setval('impressoras_id_seq', COALESCE((SELECT MAX(id) FROM impressoras), 0) + 1, false)");
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── CARREGAR ──────────────────────────────────────────────────
if ($acao === 'carregar') {
    try {
        $rows   = $conn->query("SELECT id, nome, ip, porta, categorias FROM impressoras WHERE ativo = TRUE ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $result = array_map(function($r) {
            $raw  = trim($r['categorias'] ?? '{}', '{}');
            $cats = $raw === '' ? [] : array_map(fn($v) => trim($v, '"'), explode(',', $raw));
            return [
                'id'     => intval($r['id']),
                'nome'   => $r['nome'],
                'ip'     => $r['ip'],
                'porta'  => $r['porta'],
                'cats'   => $cats,
                'status' => 'offline',
            ];
        }, $rows);
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acao invalida']);