<?php
session_start();

$tenant   = $_GET['tenant']   ?? '';
$redirect = $_GET['redirect'] ?? 'index.php';

session_unset();
session_destroy();

// Preserva o tenant na URL de destino
if ($tenant) {
    header('Location: ' . $redirect . '?tenant=' . urlencode($tenant));
} else {
    header('Location: ' . $redirect);
}
exit;
