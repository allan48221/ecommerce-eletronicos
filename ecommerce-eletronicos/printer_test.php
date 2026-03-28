<?php
// printer_test.php
// Testa conexão com a impressora e imprime página de teste via ESC/POS

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

$ip    = $body['ip']    ?? '';
$porta = (int)($body['porta'] ?? 9100);
$nome  = $body['nome']  ?? 'Impressora';

if (!$ip) {
    echo json_encode(['success' => false, 'message' => 'IP não informado']);
    exit;
}

// Valida IP básico
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['success' => false, 'message' => 'IP inválido']);
    exit;
}

// Tenta abrir socket TCP para a impressora
$socket = @fsockopen($ip, $porta, $errno, $errstr, 3); // timeout 3s

if (!$socket) {
    echo json_encode([
        'success' => false,
        'message' => "Não foi possível conectar em $ip:$porta — $errstr"
    ]);
    exit;
}

// ==================== COMANDOS ESC/POS ====================
$ESC = chr(27);
$LF  = chr(10);
$GS  = chr(29);

$cmd  = '';
$cmd .= $ESC . '@';                    // Inicializa impressora
$cmd .= $ESC . 'a' . chr(1);          // Centralizar
$cmd .= $ESC . 'E' . chr(1);          // Negrito ON
$cmd .= $GS  . '!' . chr(0x11);       // Dobra o tamanho
$cmd .= "TESTE DE IMPRESSAO" . $LF;
$cmd .= $GS  . '!' . chr(0x00);       // Tamanho normal
$cmd .= $ESC . 'E' . chr(0);          // Negrito OFF
$cmd .= str_repeat('-', 32) . $LF;
$cmd .= $ESC . 'a' . chr(0);          // Alinhar esquerda
$cmd .= "Impressora : $nome" . $LF;
$cmd .= "IP         : $ip:$porta" . $LF;
$cmd .= "Data/Hora  : " . date('d/m/Y H:i:s') . $LF;
$cmd .= str_repeat('-', 32) . $LF;
$cmd .= $ESC . 'a' . chr(1);          // Centralizar
$cmd .= "Configuracao OK!" . $LF;
$cmd .= $ESC . 'd' . chr(4);          // Avanca 4 linhas
// Corte (se a impressora tiver guilhotina):
// $cmd .= $GS . 'V' . chr(66) . chr(0);

fwrite($socket, $cmd);
fclose($socket);

echo json_encode(['success' => true, 'message' => 'Impressão de teste enviada']);