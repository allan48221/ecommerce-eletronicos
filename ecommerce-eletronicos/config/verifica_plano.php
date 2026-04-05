<?php
function verificar_plano_acesso(array $planos_permitidos, $conn) {

    // Visitante sem nenhuma sessão = cliente comum = passa sempre
    if (empty($_SESSION['id_tenant']) && empty($_SESSION['id_admin'])) return;

    // Tem id_tenant na sessão mas não é admin = passa
    // (nunca deve acontecer mas por segurança)
    if (empty($_SESSION['id_tenant'])) return;

    // Pega o plano da sessão
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

    // Plano não permitido = redireciona para o admin
    if (!$plano || !$achou) {
        header('Location: admin.php?acesso_negado=1');
        exit;
    }
}
