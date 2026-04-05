<?php
/**
 * verifica_plano.php
 * Bloqueia acesso a telas conforme o plano do tenant.
 * Uso: require_once 'config/verifica_plano.php';
 *      verificar_plano_acesso(['basico']); // só básico passa
 */

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

        // Normaliza para minúsculo e sem acento
        $plano_lower = mb_strtolower(trim($plano ?? ''));
        $permitidos  = array_map('mb_strtolower', $planos_permitidos);

        if (!$plano || !in_array($plano_lower, $permitidos)) {
            // Redireciona para admin com aviso
            header('Location: admin.php?acesso_negado=1');
            exit;
        }

    } catch (\Throwable $e) {
        header('Location: admin.php?acesso_negado=1');
        exit;
    }
}
