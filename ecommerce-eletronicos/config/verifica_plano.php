<?php
function verificar_plano_acesso(array $planos_permitidos, $conn) {

    // Sem sessão de admin = visitante = deixa passar
    if (empty($_SESSION['id_admin'])) return;

    // Master sem tenant = deixa passar sempre
    if (empty($_SESSION['id_tenant'])) return;

    // Pega o plano direto da sessão (já está carregado no login)
    $plano = $_SESSION['plano_nome'] ?? '';
    $plano_lower = mb_strtolower(trim($plano));
    $permitidos  = array_map('mb_strtolower', $planos_permitidos);

    $achou = false;
    foreach ($permitidos as $permit) {
        if (str_contains($plano_lower, $permit) || str_contains($permit, $plano_lower)) {
            $achou = true;
            break;
        }
    }

    if (!$plano || !$achou) {
        header('Location: admin.php?acesso_negado=1');
        exit;
    }
}
