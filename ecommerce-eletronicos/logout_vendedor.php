<?php
session_start();
 
// Remove apenas as variáveis do vendedor, mantendo o restante intacto
unset(
    $_SESSION['id_vendedor'],
    $_SESSION['nome_vendedor'],
    $_SESSION['id_admin'],
    $_SESSION['nome_admin'],
    $_SESSION['is_vendedor']
);
 
header('Location: login.php');
exit;
 