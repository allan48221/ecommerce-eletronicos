<?php
// get_visitas.php
// Endpoint chamado via AJAX para retornar total de visitantes únicos

require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$session_id = session_id();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// PostgreSQL: ON CONFLICT no lugar de INSERT IGNORE
$stmt = $conn->prepare(
    "INSERT INTO visitas (session_id, ip) VALUES (?, ?)
     ON CONFLICT (session_id) DO NOTHING"
);
$stmt->execute([$session_id, $ip]);

// PDO: fetchColumn() para COUNT
$total = $conn->query("SELECT COUNT(*) FROM visitas")->fetchColumn();

header('Content-Type: application/json');
echo json_encode(['total' => (int)$total]);
exit;
