<?php
session_start();
$tenant   = $_GET['tenant']   ?? '';
$redirect = $_GET['redirect'] ?? 'login.php'; // ← login faz mais sentido que index após sair

session_unset();
session_destroy();

// Trava no subdomínio atual
$host      = $_SERVER['HTTP_HOST'];
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base      = $protocolo . '://' . $host;

// Garante que $redirect não seja uma URL externa (segurança)
$redirect = basename($redirect);

if ($tenant) {
    header("Location: {$base}/{$redirect}?tenant=" . urlencode($tenant));
} else {
    header("Location: {$base}/{$redirect}");
}
exit;
