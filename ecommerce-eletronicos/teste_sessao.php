<?php
require_once 'config/database.php';
echo "<h2>Teste de Sessão</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Save handler: " . ini_get('session.save_handler') . "</p>";
echo "<p>Save path: " . ini_get('session.save_path') . "</p>";
$_SESSION['teste'] = ($_SESSION['teste'] ?? 0) + 1;
echo "<p>Contador (deve crescer a cada F5): " . $_SESSION['teste'] . "</p>";
echo "<p>Carrinho: <pre>" . print_r($_SESSION['carrinho'] ?? 'vazio', true) . "</pre></p>";

// Testa se tabela sessoes existe
try {
  $conn->query("SELECT 1 FROM sessoes LIMIT 1");
  echo "<p style='color:green'>✓ Tabela sessoes existe</p>";
} catch(Exception $e) {
  echo "<p style='color:red'>✗ Tabela sessoes NÃO existe: " . $e->getMessage() . "</p>";
}
?>
