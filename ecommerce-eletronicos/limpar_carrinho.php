<?php
require_once 'config/database.php';
$_SESSION['carrinho'] = [];
unset($_SESSION['carrinho']);
header('Location: index.php');
exit;
