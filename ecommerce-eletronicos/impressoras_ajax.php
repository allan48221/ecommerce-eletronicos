<?php
/**
 * impressoras_ajax.php
 * Endpoints AJAX para salvar/carregar impressoras.
 * NÃO tem nenhum output HTML — só JSON puro.
 */
require_once 'config/database.php';
header('Content-Type: application/json');

// ── SEGURANÇA: requer sessão autenticada ──────────────────────
if (!isset($_SESSION['id_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$id_tenant = $_SESSION['id_tenant'] ?? null;
$is_master = empty($id_tenant);

// ── Garante coluna id_tenant na tabela ────────────────────────
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS impressoras (
            id            SERIAL PRIMARY KEY,
            nome          VARCHAR(100) NOT NULL,
            ip            VARCHAR(45)  NOT NULL DEFAULT '',
            porta         VARCHAR(10)  NOT NULL DEFAULT '9100',
            categorias    TEXT[]       NOT NULL DEFAULT '{}',
            ativo         BOOLEAN      NOT NULL DEFAULT TRUE,
            id_tenant     INTEGER,
            criado_em     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            atualizado_em TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        )
    ");
    $conn->exec("ALTER TABLE impressoras ADD COLUMN IF NOT EXISTS id_tenant INTEGER");
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
        // Deleta APENAS as impressoras do tenant atual
        if ($is_master) {
            $conn->exec("DELETE FROM impressoras");
        } else {
            $conn->prepare("DELETE FROM impressoras WHERE id_tenant = ?")->execute([$id_tenant]);
        }

        $stmt = $conn->prepare("
            INSERT INTO impressoras (id, nome, ip, porta, categorias, id_tenant)
            VALUES (:id, :nome, :ip, :porta, :cats, :id_tenant)
        ");
        foreach ($body['printers'] as $p) {
            $cats    = $p['cats'] ?? $p['categorias'] ?? [];
            $catsStr = '{' . implode(',', array_map(fn($c) => '"' . addslashes($c) . '"', $cats)) . '}';
            $stmt->execute([
                ':id'        => intval($p['id']),
                ':nome'      => $p['nome']  ?? '',
                ':ip'        => $p['ip']    ?? '',
                ':porta'     => $p['porta'] ?? '9100',
                ':cats'      => $catsStr,
                ':id_tenant' => $id_tenant,  // <-- salva o tenant!
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
        if ($is_master) {
            $rows = $conn->query("
                SELECT id, nome, ip, porta, categorias 
                FROM impressoras 
                WHERE ativo = TRUE 
                ORDER BY id
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare("
                SELECT id, nome, ip, porta, categorias 
                FROM impressoras 
                WHERE ativo = TRUE AND id_tenant = ? 
                ORDER BY id
            ");
            $stmt->execute([$id_tenant]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

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
