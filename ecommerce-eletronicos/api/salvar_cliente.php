<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido. Use POST.']);
    exit;
}

function limpar($valor) {
    return trim(htmlspecialchars($valor));
}

$nome        = limpar($_POST['nome']        ?? '');
$email       = limpar($_POST['email']       ?? '');
$senha       = $_POST['senha']              ?? '';
$cpf         = limpar($_POST['cpf']         ?? '');
$telefone    = limpar($_POST['telefone']    ?? '');
$cep         = limpar($_POST['cep']         ?? '');
$endereco    = limpar($_POST['endereco']    ?? '');
$numero      = limpar($_POST['numero']      ?? '');
$complemento = limpar($_POST['complemento'] ?? '');
$bairro      = limpar($_POST['bairro']      ?? '');
$cidade      = limpar($_POST['cidade']      ?? '');
$estado      = limpar($_POST['estado']      ?? '');

// Validações
if (strlen($nome) < 3) {
    echo json_encode(['success' => false, 'message' => 'Nome deve ter no mínimo 3 caracteres.']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']); exit;
}
if (strlen($senha) < 6) {
    echo json_encode(['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.']); exit;
}
if (!preg_match('/^[0-9]{11}$/', $cpf)) {
    echo json_encode(['success' => false, 'message' => 'CPF deve conter 11 dígitos numéricos.']); exit;
}
if (!preg_match('/^[0-9]{8}$/', $cep)) {
    echo json_encode(['success' => false, 'message' => 'CEP deve conter 8 dígitos.']); exit;
}

// Verifica e-mail duplicado — PDO: rowCount() no lugar de num_rows
$stmt_check = $conn->prepare("SELECT id_cliente FROM clientes WHERE email = ?");
$stmt_check->execute([$email]);
if ($stmt_check->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado.']);
    exit;
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// PostgreSQL: RETURNING para obter o ID inserido
$sql = "INSERT INTO clientes
        (nome, email, senha, cpf, telefone, cep, endereco, numero, complemento, bairro, cidade, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id_cliente";

$stmt = $conn->prepare($sql);

try {
    $stmt->execute([$nome, $email, $senha_hash, $cpf, $telefone, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado]);
    $row        = $stmt->fetch();
    $id_cliente = $row['id_cliente'];

    echo json_encode([
        'success'    => true,
        'message'    => 'Cliente cadastrado com sucesso!',
        'id_cliente' => $id_cliente,
        'nome'       => $nome,
        'email'      => $email
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao cadastrar cliente: ' . $e->getMessage()
    ]);
}