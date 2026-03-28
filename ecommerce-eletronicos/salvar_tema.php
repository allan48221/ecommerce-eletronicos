<?php
/**
 * salvar_tema.php
 * Recebe as cores via POST e salva no banco.
 * Chamado via fetch() pelo painel admin.
 */
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_admin'])) {
    echo json_encode(['success' => false, 'msg' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Método inválido']);
    exit;
}

$campos = [
    'cor_primary',
    'cor_primary_dark',
    'cor_secondary',
    'cor_danger',
    'cor_header_grad1',
    'cor_header_grad2',
    'cor_fundo',
];

$erros = [];

foreach ($campos as $campo) {
    if (!isset($_POST[$campo])) continue;

    $valor = trim($_POST[$campo]);

    // Valida formato hex
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $valor)) {
        $erros[] = "Valor inválido para $campo: $valor";
        continue;
    }

    // PostgreSQL: ON CONFLICT ... DO UPDATE (equivalente ao ON DUPLICATE KEY do MySQL)
    $sql = "INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
            ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor";

    $stmt = $conn->prepare($sql);
    if (!$stmt->execute([$campo, $valor])) {
        $erros[] = "Erro ao salvar $campo.";
    }
}

if (empty($erros)) {
    echo json_encode(['success' => true, 'msg' => 'Tema salvo com sucesso!']);
} else {
    echo json_encode(['success' => false, 'msg' => implode('; ', $erros)]);
}
?>
