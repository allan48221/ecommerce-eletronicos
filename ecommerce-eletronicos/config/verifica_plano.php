<?php
function verificar_plano_acesso(array $planos_permitidos, $conn) {
    
    // DEBUG - mostra tudo que está na sessão
    echo '<div style="background:#fff3cd;padding:20px;margin:20px;border-radius:10px;font-family:monospace;">';
    echo '<strong>DEBUG verifica_plano:</strong><br><br>';
    echo 'id_admin: '  . ($_SESSION['id_admin']  ?? 'VAZIO') . '<br>';
    echo 'id_tenant: ' . ($_SESSION['id_tenant'] ?? 'VAZIO') . '<br>';
    echo 'nome_admin: '. ($_SESSION['nome_admin'] ?? 'VAZIO') . '<br>';
    echo '<br><strong>SESSION completa:</strong><br>';
    echo nl2br(print_r($_SESSION, true));
    echo '</div>';
    die();
}
