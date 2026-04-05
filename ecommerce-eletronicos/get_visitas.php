<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$session_id = session_id();
$ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$id_tenant  = $_SESSION['id_tenant'] ?? null;

// Registra a visita com tenant
$stmt = $conn->prepare("
    INSERT INTO visitas (session_id, ip, id_tenant) 
    VALUES (?, ?, ?)
    ON CONFLICT (session_id) DO NOTHING
");
$stmt->execute([$session_id, $ip, $id_tenant]);

// Conta apenas visitas do tenant atual
if ($id_tenant) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM visitas WHERE id_tenant = ?");
    $stmt->execute([$id_tenant]);
    $total = $stmt->fetchColumn();
} else {
    // Master vê todas
    $total = $conn->query("SELECT COUNT(*) FROM visitas")->fetchColumn();
}

header('Content-Type: application/json');
echo json_encode(['total' => (int)$total]);
exit;
