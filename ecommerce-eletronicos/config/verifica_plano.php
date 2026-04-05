<?php
function verificar_plano_acesso(array $planos_permitidos, $conn) {
    // Master não tem tenant — deixa passar sempre
    if (empty($_SESSION['id_tenant'])) return;

    $id_tenant = (int)$_SESSION['id_tenant'];

    try {
        $stmt = $conn->prepare("
            SELECT p.nome
            FROM licencas l
            INNER JOIN planos p ON p.id_plano = l.id_plano
            WHERE l.id_tenant = ?
              AND l.ativo = TRUE
              AND l.data_vencimento >= CURRENT_DATE
            LIMIT 1
        ");
        $stmt->execute([$id_tenant]);
        $plano = $stmt->fetchColumn();

        $plano_lower = mb_strtolower(trim($plano ?? ''));
        $permitidos  = array_map('mb_strtolower', $planos_permitidos);

        // ← AQUI é onde substitui o in_array
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

    } catch (\Throwable $e) {
        header('Location: admin.php?acesso_negado=1');
        exit;
    }
}
