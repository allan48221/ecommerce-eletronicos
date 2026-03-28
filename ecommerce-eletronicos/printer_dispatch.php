<?php
/**
 * printer_dispatch.php
 * Chamado internamente pelo caixa.php / atendente.php quando uma comanda é lançada.
 * NÃO é um endpoint HTTP — é incluído via require_once e chamado como função.
 */

function despacharParaImpressoras(
    PDO    $conn,
    int    $id_comanda,
    string $numero,
    string $mesa,
    string $atendente,
    array  $itens
): array {

    // 1. Busca impressoras ativas e suas categorias
    try {
        $rows = $conn->query("
            SELECT id, nome, ip, porta, categorias
            FROM impressoras
            WHERE ativo = TRUE
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [['erro' => 'Falha ao buscar impressoras: ' . $e->getMessage()]];
    }

    if (empty($rows)) return [];

    // 2. Monta mapa: id_categoria (string) -> impressora
    $mapaCategoria = [];
    foreach ($rows as $imp) {
        $cats = parsePgArrayLocal($imp['categorias']);
        foreach ($cats as $catId) {
            $mapaCategoria[trim($catId)] = $imp;
        }
    }

    if (empty($mapaCategoria)) return [];

    // 3. Separa itens normais e avulsos
    //    Itens avulsos não têm id_categoria — vão para TODAS as impressoras
    //    que receberam pelo menos uma categoria mapeada (ou para a primeira disponível)
    $itens_normais = [];
    $itens_avulsos = [];

    foreach ($itens as $item) {
        $isAvulso = (
            isset($item['nome_produto']) &&
            str_starts_with($item['nome_produto'], '[AVULSO]')
        ) || empty($item['id_categoria']);

        if ($isAvulso) {
            $itens_avulsos[] = $item;
        } else {
            $itens_normais[] = $item;
        }
    }

    // 4. Agrupa itens normais por impressora (via id_categoria)
    $grupos = [];

    foreach ($itens_normais as $item) {
        $catId = (string)($item['id_categoria'] ?? '');

        if (!isset($mapaCategoria[$catId])) continue;

        $imp = $mapaCategoria[$catId];
        $key = $imp['ip'] . ':' . $imp['porta'];

        if (!isset($grupos[$key])) {
            $grupos[$key] = ['impressora' => $imp, 'itens' => []];
        }

        // Parse dos adicionais embutidos (JSON salvo no banco)
        $adicionais = [];
        if (!empty($item['adicionais'])) {
            $ads_raw = is_string($item['adicionais'])
                ? json_decode($item['adicionais'], true)
                : $item['adicionais'];
            if (is_array($ads_raw)) {
                foreach ($ads_raw as $ad) {
                    $adicionais[] = $ad['nome'] ?? '';
                }
            }
        }

        $grupos[$key]['itens'][] = [
            'nome'       => preg_replace('/^\[AVULSO\]\s*/', '', $item['nome_produto'] ?? ''),
            'qtd'        => $item['quantidade'] ?? 1,
            'obs'        => $item['observacao'] ?? '',
            'adicionais' => $adicionais,
            'is_avulso'  => false,
        ];
    }

    // 5. Itens avulsos vão para a primeira impressora disponível
    //    (ou para todas se quiser — ajuste aqui conforme preferir)
    if (!empty($itens_avulsos) && !empty($grupos)) {
        // Adiciona os avulsos na primeira impressora que tiver itens
        $primeiraKey = array_key_first($grupos);
        foreach ($itens_avulsos as $item) {
            $grupos[$primeiraKey]['itens'][] = [
                'nome'       => preg_replace('/^\[AVULSO\]\s*/', '', $item['nome_produto'] ?? ''),
                'qtd'        => $item['quantidade'] ?? 1,
                'obs'        => $item['observacao'] ?? '',
                'adicionais' => [],
                'is_avulso'  => true,
            ];
        }
    } elseif (!empty($itens_avulsos) && empty($grupos)) {
        // Só tem avulsos — manda para a primeira impressora cadastrada
        $imp = reset($rows);
        $key = $imp['ip'] . ':' . $imp['porta'];
        $grupos[$key] = ['impressora' => $imp, 'itens' => []];
        foreach ($itens_avulsos as $item) {
            $grupos[$key]['itens'][] = [
                'nome'       => preg_replace('/^\[AVULSO\]\s*/', '', $item['nome_produto'] ?? ''),
                'qtd'        => $item['quantidade'] ?? 1,
                'obs'        => $item['observacao'] ?? '',
                'adicionais' => [],
                'is_avulso'  => true,
            ];
        }
    }

    // 6. Envia para cada impressora
    $resultados = [];

    foreach ($grupos as $grupo) {
        $imp   = $grupo['impressora'];
        $ip    = $imp['ip'];
        $porta = (int)($imp['porta'] ?? 9100);

        if (empty($ip)) continue;

        $comanda = buildComandaLocal([
            'numero'    => $numero,
            'mesa'      => $mesa,
            'atendente' => $atendente,
        ], $grupo['itens'], $imp['nome']);

        $ok = sendToPrinterLocal($ip, $porta, $comanda);

        $resultados[] = [
            'impressora'  => $imp['nome'],
            'ip'          => "$ip:$porta",
            'sucesso'     => $ok,
            'itens_count' => count($grupo['itens']),
        ];
    }

    return $resultados;
}

// ── Funções internas ──────────────────────────────────────────

function buildComandaLocal(array $pedido, array $itens, string $nomeImp): string
{
    $ESC = chr(27);
    $LF  = chr(10);
    $GS  = chr(29);

    $cmd  = $ESC . '@';               // Inicializa

    // Cabeçalho centralizado
    $cmd .= $ESC . 'a' . chr(1);     // Centralizar
    $cmd .= $ESC . 'E' . chr(1);     // Negrito ON
    $cmd .= $GS  . '!' . chr(0x11);  // Fonte grande
    $cmd .= strtoupper($nomeImp) . $LF;
    $cmd .= $GS  . '!' . chr(0x00);  // Normal
    $cmd .= $ESC . 'E' . chr(0);     // Negrito OFF
    $cmd .= str_repeat('=', 32) . $LF;

    // Info do pedido
    $cmd .= $ESC . 'a' . chr(0);     // Alinhar esquerda
    $cmd .= "Comanda  : " . ($pedido['numero']    ?? '-') . $LF;
    $cmd .= "Mesa     : "  . ($pedido['mesa']      ?? '-') . $LF;
    $cmd .= "Atendente: "  . ($pedido['atendente'] ?? '-') . $LF;
    $cmd .= "Hora     : "  . (new DateTime('now', new DateTimeZone('America/Belem')))->format('d/m/Y H:i:s') . $LF;
    $cmd .= str_repeat('-', 32) . $LF;

    // Itens
    $cmd .= $ESC . 'E' . chr(1);
    $cmd .= "ITENS:" . $LF;
    $cmd .= $ESC . 'E' . chr(0);

    foreach ($itens as $item) {
        $qtd        = $item['qtd']        ?? 1;
        $nome       = $item['nome']       ?? '';
        $obs        = $item['obs']        ?? '';
        $adicionais = $item['adicionais'] ?? [];
        $isAvulso   = $item['is_avulso']  ?? false;

        // Marca item avulso com prefixo
        $prefixo = $isAvulso ? '[ADICIONAL A PARTE] ' : '';

        $cmd .= $GS . '!' . chr(0x11);       // Fonte grande
        $cmd .= "$qtd x {$prefixo}{$nome}" . $LF;
        $cmd .= $GS . '!' . chr(0x00);       // Normal

        // Adicionais embutidos
        if (!empty($adicionais)) {
            foreach ($adicionais as $ad) {
                if (!empty($ad)) {
                    $cmd .= "  + $ad" . $LF;
                }
            }
        }

        // Observação do item
        if (!empty($obs)) {
            $cmd .= "  >> $obs" . $LF;
        }
    }

    $cmd .= str_repeat('=', 32) . $LF;
    $cmd .= $ESC . 'd' . chr(5);     // Avança papel

    return $cmd;
}

function sendToPrinterLocal(string $ip, int $porta, string $data): bool
{
    $socket = @fsockopen($ip, $porta, $errno, $errstr, 3);
    if (!$socket) return false;
    fwrite($socket, $data);
    fclose($socket);
    return true;
}

function parsePgArrayLocal(?string $pgArr): array
{
    if (!$pgArr || $pgArr === '{}') return [];
    $inner = trim($pgArr, '{}');
    if ($inner === '') return [];
    return array_map(fn($v) => trim($v, '"'), explode(',', $inner));
}