<?php
require_once 'config/database.php';

$email = 'empresa@gmail.com';
$id_tenant = 3; // id da nova empresa

echo "<h3>1. Sessão atual:</h3>";
echo "id_tenant na sessão: " . ($_SESSION['id_tenant'] ?? 'NULO') . "<br>";
echo "subdomínio detectado: " . ($_SESSION['tenant_subdominio'] ?? 'NULO') . "<br>";

echo "<h3>2. Buscando admin no banco:</h3>";
$stmt = $conn->prepare("SELECT id_admin, nome, email, usuario, id_tenant, ativo, senha FROM admins WHERE email = ?");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if ($admin) {
    echo "Encontrado: <pre>" . print_r($admin, true) . "</pre>";
    echo "Senha hash salva: " . $admin['senha'] . "<br>";
    echo "Teste password_verify com '101216': ";
    echo password_verify('101216', $admin['senha']) ? "✅ OK" : "❌ FALHOU";
} else {
    echo "❌ Admin NÃO encontrado com email: $email";
}

echo "<h3>3. Tenant na sessão:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
?>