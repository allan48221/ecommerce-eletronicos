<?php
require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_admin'])) {
    echo json_encode(['success' => false, 'msg' => 'Nao autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Metodo invalido']);
    exit;
}

$id_tenant = $_SESSION['id_tenant'] ?? null;

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

    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $valor)) {
        $erros[] = "Valor invalido para $campo: $valor";
        continue;
    }

    $sql  = "INSERT INTO configuracoes (chave, valor, id_tenant) VALUES (?, ?, ?)
             ON CONFLICT (chave, id_tenant) DO UPDATE SET valor = EXCLUDED.valor";
    $stmt = $conn->prepare($sql);
    if (!$stmt->execute([$campo, $valor, $id_tenant])) {
        $erros[] = "Erro ao salvar $campo.";
    }
}

if (empty($erros)) {
    echo json_encode(['success' => true, 'msg' => 'Tema salvo com sucesso!']);
} else {
    echo json_encode(['success' => false, 'msg' => implode('; ', $erros)]);
}
?>
