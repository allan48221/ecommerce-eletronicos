<?php
require_once 'config/database.php';
require_once 'config/tema.php';
require_once 'empresa_helper.php';
$emp = getDadosEmpresa($conn);

// -- Aceita admin real OU caixa logado via staff --
$tipo_staff    = $_SESSION['tipo_staff'] ?? null;
$id_admin_real = null;
if (isset($_SESSION['id_admin']) && is_numeric($_SESSION['id_admin'])) {
    $id_admin_real = intval($_SESSION['id_admin']);
}
if ($id_admin_real === null && $tipo_staff !== 'caixa') {
    header('Location: login.php');
    exit;
}

$id_tenant = $_SESSION['id_tenant'] ?? null;
$is_master = empty($_SESSION['id_tenant']);

// -- Nome do usuario logado --
$nome_caixa = 'Caixa';
if (!empty($_SESSION['tipo_staff'])) {
    $nome_caixa = $_SESSION['nome_staff'] ?? 'Caixa';
} elseif (!empty($_SESSION['id_admin']) && is_numeric($_SESSION['id_admin'])) {
    $nome_caixa = $_SESSION['nome_admin'] ?? 'Administrador';
}

$msg         = '';
$tipo_msg    = '';
$comprovante = null;

// ===============================================================
// HELPERS CAIXA
// ===============================================================
function sessaoAtiva(PDO $conn): ?array {
    $s = $conn->query("SELECT * FROM caixa_sessoes WHERE status = 'aberto' ORDER BY aberto_em DESC LIMIT 1");
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Helper: monta WHERE + binds para filtro de tenant em comandas
// Retorna ['where' => string, 'binds' => array]
function tenantFiltroComandas(bool $is_master, ?int $id_tenant, string $alias = 'c'): array {
    if ($is_master) return ['where' => '', 'binds' => []];
    return ['where' => " AND {$alias}.id_tenant = ?", 'binds' => [$id_tenant]];
}

// ===============================================================
// AJAX ENDPOINTS
// ===============================================================

// -- AJAX: lista comandas abertas --
if (isset($_GET['ajax_comandas'])) {
    header('Content-Type: application/json');
    try {
        $tf = tenantFiltroComandas($is_master, $id_tenant, 'c');
        $stmt = $conn->prepare("
            SELECT c.*,
                   COUNT(ci.id_item) AS total_itens,
                   EXTRACT(EPOCH FROM c.criado_em AT TIME ZONE 'America/Belem')::int AS criado_em_ts
            FROM comandas c
            LEFT JOIN comanda_itens ci ON ci.id_comanda = c.id_comanda
            WHERE c.status = 'aberta'
            {$tf['where']}
            GROUP BY c.id_comanda
            ORDER BY c.criado_em ASC
        ");
        $stmt->execute($tf['binds']);
        $agora_ts = (int) $conn->query("SELECT EXTRACT(EPOCH FROM NOW())")->fetchColumn();
        echo json_encode(['agora_ts' => $agora_ts, 'comandas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) { echo json_encode(['agora_ts' => time(), 'comandas' => []]); }
    exit;
}

// -- AJAX: detalhes completos de comanda (historico) --
if (isset($_GET['detalhe_historico'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['detalhe_historico']);
    try {
        if ($is_master) {
            $sc = $conn->prepare("SELECT * FROM comandas WHERE id_comanda = ? LIMIT 1");
            $sc->execute([$id]);
        } else {
            $sc = $conn->prepare("SELECT * FROM comandas WHERE id_comanda = ? AND id_tenant = ? LIMIT 1");
            $sc->execute([$id, $id_tenant]);
        }
        $comanda = $sc->fetch(PDO::FETCH_ASSOC);
        if (!$comanda) { echo json_encode(null); exit; }
        $si = $conn->prepare("SELECT * FROM comanda_itens WHERE id_comanda = ? ORDER BY criado_em");
        $si->execute([$id]);
        $itens = $si->fetchAll(PDO::FETCH_ASSOC);
        foreach ($itens as &$it) { $it['adicionais'] = $it['adicionais'] ? json_decode($it['adicionais'], true) : []; }
        $comanda['itens'] = $itens;
        echo json_encode($comanda);
    } catch (\Throwable $e) { echo json_encode(null); }
    exit;
}

// -- AJAX: itens da comanda --
if (isset($_GET['itens_comanda'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['itens_comanda']);
    // Valida que a comanda pertence ao tenant antes de retornar os itens
    if ($is_master) {
        $check = $conn->prepare("SELECT id_comanda FROM comandas WHERE id_comanda = ? LIMIT 1");
        $check->execute([$id]);
    } else {
        $check = $conn->prepare("SELECT id_comanda FROM comandas WHERE id_comanda = ? AND id_tenant = ? LIMIT 1");
        $check->execute([$id, $id_tenant]);
    }
    if (!$check->fetch()) { echo json_encode([]); exit; }

    $stmt = $conn->prepare("SELECT * FROM comanda_itens WHERE id_comanda = ? ORDER BY criado_em");
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll();
    foreach ($itens as &$it) { $it['adicionais'] = $it['adicionais'] ? json_decode($it['adicionais'], true) : []; }
    echo json_encode($itens);
    exit;
}

// -- AJAX: produtos por categoria --
if (isset($_GET['categoria'])) {
    header('Content-Type: application/json');
    $id_cat = intval($_GET['categoria']);
    if ($id_cat === 0) {
        if ($is_master) {
            $stmt = $conn->query("
                SELECT p.id_produto, p.nome, p.marca, p.modelo, p.preco, p.preco_promocional, p.estoque, p.imagem,
                       c.nome AS categoria_nome
                FROM produtos p LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.ativo = TRUE AND p.estoque > 0
                ORDER BY p.nome ASC LIMIT 60
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT p.id_produto, p.nome, p.marca, p.modelo, p.preco, p.preco_promocional, p.estoque, p.imagem,
                       c.nome AS categoria_nome
                FROM produtos p LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.ativo = TRUE AND p.estoque > 0 AND p.id_tenant = ?
                ORDER BY p.nome ASC LIMIT 60
            ");
            $stmt->execute([$id_tenant]);
        }
    } else {
        if ($is_master) {
            $stmt = $conn->prepare("
                SELECT p.id_produto, p.nome, p.marca, p.modelo, p.preco, p.preco_promocional, p.estoque, p.imagem,
                       c.nome AS categoria_nome
                FROM produtos p LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.ativo = TRUE AND p.estoque > 0 AND p.id_categoria = ?
                ORDER BY p.nome ASC LIMIT 60
            ");
            $stmt->execute([$id_cat]);
        } else {
            $stmt = $conn->prepare("
                SELECT p.id_produto, p.nome, p.marca, p.modelo, p.preco, p.preco_promocional, p.estoque, p.imagem,
                       c.nome AS categoria_nome
                FROM produtos p LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.ativo = TRUE AND p.estoque > 0 AND p.id_categoria = ? AND p.id_tenant = ?
                ORDER BY p.nome ASC LIMIT 60
            ");
            $stmt->execute([$id_cat, $id_tenant]);
        }
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// -- AJAX: adicionais do produto --
if (isset($_GET['adicionais'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['adicionais']);
    try {
        $stmt = $conn->prepare("
            SELECT a.id_adicional, a.nome, a.preco
            FROM produto_adicionais pa
            JOIN adicionais a ON a.id_adicional = pa.id_adicional
            WHERE pa.id_produto = ? AND a.ativo = TRUE
            ORDER BY a.nome
        ");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (\Throwable $e) { echo json_encode([]); }
    exit;
}

// -- AJAX: listar adicionais avulsos --
if (isset($_GET['adicionais_avulsos'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['adicionais_avulsos'] ?? '');
    try {
        if ($q !== '') {
            if ($is_master) {
                $stmt = $conn->prepare("
                    SELECT id_adicional, nome, preco FROM adicionais
                    WHERE ativo = TRUE AND nome ILIKE ?
                    ORDER BY nome ASC LIMIT 40
                ");
                $stmt->execute(['%' . $q . '%']);
            } else {
                $stmt = $conn->prepare("
                    SELECT id_adicional, nome, preco FROM adicionais
                    WHERE ativo = TRUE AND nome ILIKE ? AND id_tenant = ?
                    ORDER BY nome ASC LIMIT 40
                ");
                $stmt->execute(['%' . $q . '%', $id_tenant]);
            }
        } else {
            if ($is_master) {
                $stmt = $conn->query("
                    SELECT id_adicional, nome, preco FROM adicionais
                    WHERE ativo = TRUE ORDER BY nome ASC LIMIT 40
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT id_adicional, nome, preco FROM adicionais
                    WHERE ativo = TRUE AND id_tenant = ?
                    ORDER BY nome ASC LIMIT 40
                ");
                $stmt->execute([$id_tenant]);
            }
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (\Throwable $e) { echo json_encode([]); }
    exit;
}

// -- AJAX: dados da sessao ativa para o painel de caixa --
if (isset($_GET['ajax_sessao'])) {
    header('Content-Type: application/json');
    try {
        if (!empty($_GET['id_sessao']) && is_numeric($_GET['id_sessao'])) {
            $s = $conn->prepare("SELECT * FROM caixa_sessoes WHERE id_sessao = ? LIMIT 1");
            $s->execute([intval($_GET['id_sessao'])]);
            $sessao = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $sessao = sessaoAtiva($conn);
        }
        if (!$sessao) { echo json_encode(null); exit; }
        $id_sessao = $sessao['id_sessao'];

        $sm = $conn->prepare("SELECT * FROM caixa_movimentacoes WHERE id_sessao = ? ORDER BY criado_em");
        $sm->execute([$id_sessao]);
        $sessao['movimentacoes'] = $sm->fetchAll(PDO::FETCH_ASSOC);

        $ate = $sessao['fechado_em'] ? "'{$sessao['fechado_em']}'" : "NOW()";

        // Filtro de tenant nas vendas por pagamento
        if ($is_master) {
            $sv = $conn->query("
                SELECT forma_pagamento, COUNT(*) AS qtd, SUM(valor_total) AS total
                FROM pedidos
                WHERE status = 'aprovado'
                  AND data_pedido >= '{$sessao['aberto_em']}'
                  AND data_pedido <= $ate
                GROUP BY forma_pagamento
            ");
        } else {
            $sv = $conn->prepare("
                SELECT forma_pagamento, COUNT(*) AS qtd, SUM(valor_total) AS total
                FROM pedidos
                WHERE status = 'aprovado'
                  AND data_pedido >= '{$sessao['aberto_em']}'
                  AND data_pedido <= $ate
                  AND id_tenant = ?
                GROUP BY forma_pagamento
            ");
            $sv->execute([$id_tenant]);
        }
        $sessao['vendas_por_pagamento'] = $sv->fetchAll(PDO::FETCH_ASSOC);

        // Filtro de tenant nos totais
        if ($is_master) {
            $st = $conn->query("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao['aberto_em']}' AND data_pedido <= $ate");
        } else {
            $st = $conn->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao['aberto_em']}' AND data_pedido <= $ate AND id_tenant = ?");
            $st->execute([$id_tenant]);
        }
        $totais = $st->fetch(PDO::FETCH_ASSOC);
        $sessao['total_vendido'] = $totais['total'];
        $sessao['qtd_pedidos']   = $totais['qtd'];

        echo json_encode($sessao);
    } catch (\Throwable $e) { echo json_encode(['erro' => $e->getMessage()]); }
    exit;
}

// -- AJAX: historico de sessoes fechadas --
if (isset($_GET['ajax_historico_sessoes'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->query("SELECT * FROM caixa_sessoes ORDER BY aberto_em DESC LIMIT 30");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (\Throwable $e) { echo json_encode([]); }
    exit;
}

// -- AJAX: verificar senha de cancelamento --
if (isset($_GET['verificar_senha_cancel'])) {
    header('Content-Type: application/json');
    $senha = trim($_POST['senha'] ?? '');
    try {
        $ra = $conn->query("SELECT senha FROM admins")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ra as $row) {
            if (password_verify($senha, $row['senha'])) { echo json_encode(['ok' => true]); exit; }
        }
        $rs = $conn->query("SELECT senha FROM staff WHERE tipo = 'admin' AND ativo = TRUE")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rs as $row) {
            if (password_verify($senha, $row['senha'])) { echo json_encode(['ok' => true]); exit; }
        }
        echo json_encode(['ok' => false]);
    } catch (\Throwable $e) { echo json_encode(['ok' => false]); }
    exit;
}

// -- AJAX: cancelar item individual da comanda --
if (isset($_GET['cancelar_item'])) {
    header('Content-Type: application/json');
    $id_item = intval($_POST['id_item'] ?? 0);
    if ($id_item <= 0) { echo json_encode(['ok' => false, 'msg' => 'ID do item invalido.']); exit; }
    try {
        $conn->beginTransaction();

        // Busca o item e valida tenant via JOIN com comandas
        if ($is_master) {
            $stmt = $conn->prepare("SELECT ci.id_comanda, ci.subtotal FROM comanda_itens ci WHERE ci.id_item = ?");
            $stmt->execute([$id_item]);
        } else {
            $stmt = $conn->prepare("
                SELECT ci.id_comanda, ci.subtotal
                FROM comanda_itens ci
                JOIN comandas c ON c.id_comanda = ci.id_comanda
                WHERE ci.id_item = ? AND c.id_tenant = ?
            ");
            $stmt->execute([$id_item, $id_tenant]);
        }
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item) {
            $id_comanda = $item['id_comanda'];
            $valor_item = $item['subtotal'];
            $conn->prepare("
                INSERT INTO comanda_itens_removidos
                (id_comanda, numero_comanda, nome_produto, quantidade, subtotal, removido_por)
                SELECT ci.id_comanda, c.numero_comanda, ci.nome_produto, ci.quantidade, ci.subtotal, ?
                FROM comanda_itens ci
                JOIN comandas c ON c.id_comanda = ci.id_comanda
                WHERE ci.id_item = ?
            ")->execute([$nome_caixa, $id_item]);
            $conn->prepare("DELETE FROM comanda_itens WHERE id_item = ?")->execute([$id_item]);
            $conn->prepare("UPDATE comandas SET valor_total = valor_total - ? WHERE id_comanda = ?")->execute([$valor_item, $id_comanda]);
            $conn->commit();
            registrar_log($conn, 'item_removido', "Item removido da comanda #$id_comanda", "Valor subtraido: R$ $valor_item", $valor_item, $id_item, $nome_caixa);
            echo json_encode(['ok' => true]);
        } else {
            throw new Exception("Item nao encontrado.");
        }
    } catch (\Throwable $e) {
        $conn->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ===============================================================
// POST: GESTAO DE CAIXA
// ===============================================================

// -- POST: ABRIR CAIXA --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'abrir_caixa') {
    $valor_inicial = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor_inicial'] ?? '0')));
    $obs           = trim($_POST['obs_abertura'] ?? '');
    try {
        $sessao_atual = sessaoAtiva($conn);
        if ($sessao_atual) {
            $msg = 'Ja existe um caixa aberto.'; $tipo_msg = 'danger';
        } else {
            $conn->prepare("INSERT INTO caixa_sessoes (aberto_por, valor_inicial, status, aberto_em, observacao) VALUES (?, ?, 'aberto', NOW(), ?)")
                 ->execute([$nome_caixa, $valor_inicial, $obs]);
            registrar_log($conn, 'caixa_aberto', "Caixa aberto por $nome_caixa", "Valor inicial: R$ " . number_format($valor_inicial, 2, ',', '.'), $valor_inicial, null, $nome_caixa);
            $msg = 'Caixa aberto com sucesso!'; $tipo_msg = 'success';
        }
    } catch (\Throwable $e) { $msg = 'Erro: ' . $e->getMessage(); $tipo_msg = 'danger'; }
}

// -- POST: MOVIMENTACAO --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'movimentacao') {
    $id_sessao = intval($_POST['id_sessao'] ?? 0);
    $tipo_mov  = trim($_POST['tipo_mov'] ?? '');
    $descricao = trim($_POST['descricao_mov'] ?? '');
    $valor     = floatval(str_replace(',', '.', $_POST['valor_mov'] ?? '0'));
    if ($id_sessao <= 0 || !in_array($tipo_mov, ['sangria','suprimento','despesa']) || $valor <= 0) {
        $msg = 'Dados invalidos.'; $tipo_msg = 'danger';
    } else {
        try {
            $conn->prepare("INSERT INTO caixa_movimentacoes (id_sessao, tipo, descricao, valor, operador, criado_em) VALUES (?,?,?,?,?,NOW())")
                 ->execute([$id_sessao, $tipo_mov, $descricao, $valor, $nome_caixa]);
            registrar_log($conn, 'caixa_mov_'.$tipo_mov, ucfirst($tipo_mov)." no caixa por $nome_caixa", "$descricao - R$ " . number_format($valor, 2, ',', '.'), $valor, null, $nome_caixa);
            $msg = ucfirst($tipo_mov) . ' registrado: R$ ' . number_format($valor, 2, ',', '.'); $tipo_msg = 'success';
        } catch (\Throwable $e) { $msg = 'Erro: ' . $e->getMessage(); $tipo_msg = 'danger'; }
    }
}

// -- POST: FECHAR CAIXA --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'fechar_caixa') {
    $id_sessao     = intval($_POST['id_sessao'] ?? 0);
    $valor_contado = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor_contado'] ?? '0')));
    $justificativa = trim($_POST['justificativa'] ?? '');
    try {
        $sessao = sessaoAtiva($conn);
        if (!$sessao || $sessao['id_sessao'] != $id_sessao) throw new \Exception("Sessao invalida.");

        // Total de vendas filtrado por tenant
        if ($is_master) {
            $st = $conn->query("SELECT COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao['aberto_em']}'");
        } else {
            $st = $conn->prepare("SELECT COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao['aberto_em']}' AND id_tenant = ?");
            $st->execute([$id_tenant]);
        }
        $total_sistema = floatval($st->fetchColumn());

        $sm = $conn->prepare("SELECT tipo, COALESCE(SUM(valor),0) AS soma FROM caixa_movimentacoes WHERE id_sessao = ? GROUP BY tipo");
        $sm->execute([$id_sessao]);
        $movs = []; foreach($sm->fetchAll(PDO::FETCH_ASSOC) as $r) $movs[$r['tipo']] = floatval($r['soma']);
        $suprimentos = $movs['suprimento'] ?? 0;
        $saidas      = ($movs['sangria'] ?? 0) + ($movs['despesa'] ?? 0);
        $saldo_esperado = $sessao['valor_inicial'] + $suprimentos + $total_sistema - $saidas;
        $diferenca      = $valor_contado - $saldo_esperado;
        $conn->prepare("UPDATE caixa_sessoes SET status='fechado', fechado_por=?, fechado_em=NOW(), valor_contado=?, diferenca=?, justificativa=? WHERE id_sessao=?")
             ->execute([$nome_caixa, $valor_contado, $diferenca, $justificativa, $id_sessao]);
        registrar_log($conn, 'caixa_fechado', "Caixa fechado por $nome_caixa",
            "Total sistema: R$ ".number_format($total_sistema,2,',','.')." | Contado: R$ ".number_format($valor_contado,2,',','.')." | Diferenca: R$ ".number_format($diferenca,2,',','.'),
            $total_sistema, null, $nome_caixa);
        $msg = 'Caixa fechado com sucesso!'; $tipo_msg = 'success';
    } catch (\Throwable $e) { $msg = 'Erro: ' . $e->getMessage(); $tipo_msg = 'danger'; }
}

// -- POST: REABRIR CAIXA --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'reabrir_caixa') {
    $id_sessao   = intval($_POST['id_sessao_reabrir'] ?? 0);
    $senha_admin = trim($_POST['senha_reabrir'] ?? '');
    try {
        $ra = $conn->query("SELECT senha FROM admins ORDER BY id_admin LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$ra || !password_verify($senha_admin, $ra['senha'])) throw new \Exception("Senha do administrador incorreta.");
        $conn->prepare("UPDATE caixa_sessoes SET status='aberto', fechado_por=NULL, fechado_em=NULL, valor_contado=NULL, diferenca=NULL, justificativa=NULL WHERE id_sessao=? AND status='fechado'")
             ->execute([$id_sessao]);
        registrar_log($conn, 'caixa_reaberto', "Caixa reaberto por $nome_caixa", "Sessao ID: $id_sessao", 0, null, $nome_caixa);
        $msg = 'Caixa reaberto.'; $tipo_msg = 'success';
    } catch (\Throwable $e) { $msg = 'Erro: ' . $e->getMessage(); $tipo_msg = 'danger'; }
}

// ===============================================================
// POST: COMANDAS
// ===============================================================

// Helper: valida que a comanda pertence ao tenant do usuario logado
function validarTenantComanda(PDO $conn, int $id_comanda, bool $is_master, ?int $id_tenant): ?array {
    if ($is_master) {
        $s = $conn->prepare("SELECT * FROM comandas WHERE id_comanda = ? AND status = 'aberta'");
        $s->execute([$id_comanda]);
    } else {
        $s = $conn->prepare("SELECT * FROM comandas WHERE id_comanda = ? AND status = 'aberta' AND id_tenant = ?");
        $s->execute([$id_comanda, $id_tenant]);
    }
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

// -- POST: FECHAR COMANDA --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'fechar') {
    $id_comanda      = intval($_POST['id_comanda'] ?? 0);
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '');
    if ($id_comanda <= 0 || empty($forma_pagamento)) {
        $msg = 'Dados invalidos.'; $tipo_msg = 'danger';
    } else {
        try {
            $conn->beginTransaction();
            $stmt_itens = $conn->prepare("SELECT * FROM comanda_itens WHERE id_comanda = ? ORDER BY criado_em");
            $stmt_itens->execute([$id_comanda]);
            $itens = $stmt_itens->fetchAll();
            $comanda = validarTenantComanda($conn, $id_comanda, $is_master, $id_tenant);
            if (!$comanda) throw new \Exception("Comanda nao encontrada ou ja fechada.");
            foreach ($itens as $item) {
                if (!empty($item['id_produto'])) {
                    $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ? AND estoque >= ?")->execute([$item['quantidade'], $item['id_produto'], $item['quantidade']]);
                }
            }
            $stmt = $conn->prepare("INSERT INTO pedidos (nome_cliente, cpf_cliente, telefone_cliente, forma_pagamento, valor_produtos, valor_total, valor_frete, status, observacoes, data_pedido, tipo, id_tenant) VALUES (?, '', '', ?, ?, ?, 0, 'aprovado', ?, NOW(), 'presencial', ?)");
            $stmt->execute(['Comanda #' . $comanda['numero_comanda'], $forma_pagamento, $comanda['valor_total'], $comanda['valor_total'], 'Comanda #' . $comanda['numero_comanda'] . ($comanda['observacao'] ? ' - ' . $comanda['observacao'] : ''), $id_tenant]);
            $id_pedido = $conn->lastInsertId();
            $itens_comprovante = [];
            foreach ($itens as $item) {
                $conn->prepare("INSERT INTO itens_pedido (id_pedido, id_produto, nome_produto, quantidade, preco_unitario, subtotal) VALUES (?,?,?,?,?,?)")->execute([$id_pedido, $item['id_produto'], $item['nome_produto'], $item['quantidade'], $item['preco_unitario'], $item['subtotal']]);
                $ads = $item['adicionais'] ? json_decode($item['adicionais'], true) : [];
                $nome_exib = preg_replace('/^\[AVULSO\]\s*/', '', $item['nome_produto']);
                $itens_comprovante[] = ['nome'=>$nome_exib,'quantidade'=>$item['quantidade'],'preco'=>$item['preco_unitario'],'subtotal'=>$item['subtotal'],'adicionais'=>$ads?:[],'obs'=>$item['observacao']??''];
            }
            $conn->prepare("UPDATE comandas SET status = 'fechada', fechado_em = NOW(), id_admin = ? WHERE id_comanda = ?")->execute([$id_admin_real, $id_comanda]);
            $conn->commit();
            registrar_log($conn, 'comanda_fechada', "Comanda #" . $comanda['numero_comanda'] . " fechada", "Pagamento: $forma_pagamento - Pedido #$id_pedido gerado", $comanda['valor_total'], $id_pedido, $nome_caixa);
            $comprovante = [
                'id_pedido'       => $id_pedido,
                'numero_comanda'  => $comanda['numero_comanda'],
                'data'            => date('d/m/Y H:i'),
                'itens'           => $itens_comprovante,
                'forma_pagamento' => $forma_pagamento,
                'observacao'      => $comanda['observacao'] ?? '',
                'lancado_por'     => $comanda['lancado_por'] ?? '',
                'total'           => $comanda['valor_total'],
                'nome_empresa'    => $emp['nome_fantasia'] ?: $emp['nome_empresa'],
                'cnpj'            => $emp['cnpj_formatado'],
                'endereco'        => $emp['endereco_completo'],
                'telefone'        => $emp['telefone'] ?: $emp['celular'],
            ];
            $msg = "Comanda <strong>#" . htmlspecialchars($comanda['numero_comanda']) . "</strong> fechada! Pedido <strong>#$id_pedido</strong> gerado.";
            $tipo_msg = 'success';
        } catch (\Throwable $e) { $conn->rollBack(); $msg = 'Erro: ' . $e->getMessage(); $tipo_msg = 'danger'; }
    }
}

// -- POST: CANCELAR COMANDA --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cancelar') {
    $id_comanda = intval($_POST['id_comanda'] ?? 0);
    if ($id_comanda > 0) {
        if ($is_master) {
            $conn->prepare("UPDATE comandas SET status = 'cancelada', fechado_em = NOW() WHERE id_comanda = ? AND status = 'aberta'")->execute([$id_comanda]);
        } else {
            $conn->prepare("UPDATE comandas SET status = 'cancelada', fechado_em = NOW() WHERE id_comanda = ? AND status = 'aberta' AND id_tenant = ?")->execute([$id_comanda, $id_tenant]);
        }
        registrar_log($conn, 'comanda_cancelada', "Comanda cancelada", "ID comanda: $id_comanda", 0, $id_comanda, $nome_caixa);
        $msg = 'Comanda cancelada.'; $tipo_msg = 'success';
    }
}

// -- POST: ADICIONAR ITENS --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'adicionar_itens') {
    $id_comanda = intval($_POST['id_comanda'] ?? 0);
    $itens      = json_decode(trim($_POST['itens_json'] ?? '[]'), true);
    if ($id_comanda <= 0 || empty($itens)) { $msg = 'Dados invalidos.'; $tipo_msg = 'danger'; }
    else {
        try {
            $conn->beginTransaction();
            $comanda = validarTenantComanda($conn, $id_comanda, $is_master, $id_tenant);
            if (!$comanda) throw new \Exception("Comanda nao encontrada ou ja fechada.");
            $total_novo = 0;
            foreach ($itens as $item) {
                if (!empty($item['is_avulso'])) {
                    $r = $conn->prepare("SELECT nome, preco FROM adicionais WHERE id_adicional = ? AND ativo = TRUE");
                    $r->execute([$item['id_adicional_avulso']]); $ad = $r->fetch();
                    if (!$ad) throw new \Exception("Adicional nao encontrado.");
                    $sub = floatval($ad['preco']) * $item['quantidade'];
                    $total_novo += $sub;
                    $conn->prepare("INSERT INTO comanda_itens (id_comanda, id_produto, nome_produto, preco_unitario, quantidade, subtotal, adicionais, observacao, lancado_por) VALUES (?, NULL, ?, ?, ?, ?, NULL, ?, ?)")
                         ->execute([$id_comanda, '[AVULSO] '.$ad['nome'], $ad['preco'], $item['quantidade'], $sub, $item['obs']??null, $nome_caixa]);
                    continue;
                }
                $r = $conn->prepare("SELECT nome, preco, preco_promocional, estoque FROM produtos WHERE id_produto = ? AND ativo = TRUE");
                $r->execute([$item['id_produto']]); $p = $r->fetch();
                if (!$p) throw new \Exception("Produto nao encontrado.");
                if ($p['estoque'] < $item['quantidade']) throw new \Exception("Estoque insuficiente para \"{$p['nome']}\".");
                $preco_base = (!empty($p['preco_promocional']) && floatval($p['preco_promocional']) > 0) ? floatval($p['preco_promocional']) : floatval($p['preco']);
                $sub = $preco_base * $item['quantidade'];
                $extras = 0;
                if (!empty($item['adicionais'])) foreach ($item['adicionais'] as $ad) $extras += floatval($ad['preco'] ?? 0) * $item['quantidade'];
                $total_novo += $sub + $extras;
                $conn->prepare("INSERT INTO comanda_itens (id_comanda, id_produto, nome_produto, preco_unitario, quantidade, subtotal, adicionais, observacao, lancado_por) VALUES (?,?,?,?,?,?,?,?,?)")
                     ->execute([$id_comanda, $item['id_produto'], $p['nome'], $preco_base, $item['quantidade'], $sub + $extras, !empty($item['adicionais']) ? json_encode($item['adicionais']) : null, $item['obs'] ?? null, $nome_caixa]);
            }
            $conn->prepare("UPDATE comandas SET valor_total = valor_total + ? WHERE id_comanda = ?")->execute([$total_novo, $id_comanda]);
            $conn->commit();
            $msg = "Comanda atualizada! " . count($itens) . " item(ns) adicionado(s)."; $tipo_msg = 'success';
        } catch (\Throwable $e) { $conn->rollBack(); $msg = 'Erro: ' . $e->getMessage(); $tipo_msg = 'danger'; }
    }
}

// -- POST: NOVA COMANDA --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'nova_comanda') {
    $numero_comanda = trim($_POST['numero_comanda'] ?? '');
    $observacao_cmd = trim($_POST['observacao_cmd'] ?? '');
    $itens          = json_decode(trim($_POST['itens_json'] ?? '[]'), true);
    $erros = [];
    if (empty($numero_comanda)) $erros[] = 'Informe o numero da comanda.';
    if (empty($itens))          $erros[] = 'Adicione ao menos um produto.';
    if (empty($erros)) {
        try {
            $conn->beginTransaction();
            $total = 0; $prods_db = [];
            foreach ($itens as $item) {
                if (!empty($item['is_avulso'])) {
                    $r = $conn->prepare("SELECT nome, preco FROM adicionais WHERE id_adicional = ? AND ativo = TRUE");
                    $r->execute([$item['id_adicional_avulso']]); $ad = $r->fetch();
                    if (!$ad) throw new \Exception("Adicional nao encontrado.");
                    $prods_db['avulso_'.$item['id_adicional_avulso']] = ['nome'=>$ad['nome'],'preco'=>$ad['preco'],'preco_promocional'=>null,'estoque'=>9999];
                    $total += floatval($ad['preco']) * $item['quantidade'];
                    continue;
                }
                $r = $conn->prepare("SELECT nome, preco, preco_promocional, estoque FROM produtos WHERE id_produto = ? AND ativo = TRUE");
                $r->execute([$item['id_produto']]); $p = $r->fetch();
                if (!$p) throw new \Exception("Produto nao encontrado.");
                if ($p['estoque'] < $item['quantidade']) throw new \Exception("Estoque insuficiente para \"{$p['nome']}\".");
                $prods_db[$item['id_produto']] = $p;
                $preco_base = (!empty($p['preco_promocional']) && floatval($p['preco_promocional']) > 0) ? floatval($p['preco_promocional']) : floatval($p['preco']);
                $sub = $preco_base * $item['quantidade']; $extras = 0;
                if (!empty($item['adicionais'])) foreach ($item['adicionais'] as $ad) $extras += floatval($ad['preco'] ?? 0) * $item['quantidade'];
                $total += $sub + $extras;
            }
            // Busca comanda existente aberta, filtrando por tenant
            if ($is_master) {
                $stmt_exist = $conn->prepare("SELECT id_comanda FROM comandas WHERE numero_comanda = ? AND status = 'aberta' LIMIT 1");
                $stmt_exist->execute([$numero_comanda]);
            } else {
                $stmt_exist = $conn->prepare("SELECT id_comanda FROM comandas WHERE numero_comanda = ? AND status = 'aberta' AND id_tenant = ? LIMIT 1");
                $stmt_exist->execute([$numero_comanda, $id_tenant]);
            }
            $comanda_exist = $stmt_exist->fetch(PDO::FETCH_ASSOC);
            if ($comanda_exist) {
                $id_comanda = $comanda_exist['id_comanda'];
                $conn->prepare("UPDATE comandas SET valor_total = valor_total + ? WHERE id_comanda = ?")->execute([$total, $id_comanda]);
            } else {
                $stmt = $conn->prepare("INSERT INTO comandas (numero_comanda, status, observacao, lancado_por, valor_total, criado_em, id_tenant) VALUES (?, 'aberta', ?, ?, ?, NOW(), ?) RETURNING id_comanda");
                $stmt->execute([$numero_comanda, $observacao_cmd, $nome_caixa, $total, $id_tenant]);
                $id_comanda = $stmt->fetch(PDO::FETCH_ASSOC)['id_comanda'];
            }
            foreach ($itens as $item) {
                if (!empty($item['is_avulso'])) {
                    $p = $prods_db['avulso_'.$item['id_adicional_avulso']];
                    $sub = floatval($p['preco']) * $item['quantidade'];
                    $conn->prepare("INSERT INTO comanda_itens (id_comanda, id_produto, nome_produto, preco_unitario, quantidade, subtotal, adicionais, observacao, lancado_por) VALUES (?, NULL, ?, ?, ?, ?, NULL, ?, ?)")
                         ->execute([$id_comanda, '[AVULSO] '.$p['nome'], $p['preco'], $item['quantidade'], $sub, $item['obs']??null, $nome_caixa]);
                    continue;
                }
                $p = $prods_db[$item['id_produto']];
                $preco_base = (!empty($p['preco_promocional']) && floatval($p['preco_promocional']) > 0) ? floatval($p['preco_promocional']) : floatval($p['preco']);
                $sub = $preco_base * $item['quantidade']; $extras = 0;
                if (!empty($item['adicionais'])) foreach ($item['adicionais'] as $ad) $extras += floatval($ad['preco'] ?? 0) * $item['quantidade'];
                $conn->prepare("INSERT INTO comanda_itens (id_comanda, id_produto, nome_produto, preco_unitario, quantidade, subtotal, adicionais, observacao, lancado_por) VALUES (?,?,?,?,?,?,?,?,?)")
                     ->execute([$id_comanda, $item['id_produto'], $p['nome'], $preco_base, $item['quantidade'], $sub + $extras, !empty($item['adicionais']) ? json_encode($item['adicionais']) : null, $item['obs'] ?? null, $nome_caixa]);
            }
            $conn->commit();

            try {
                require_once 'printer_dispatch.php';
                $stmt_imp = $conn->prepare("
                    SELECT ci.nome_produto, ci.quantidade, ci.observacao,
                    ci.adicionais,
                    p.id_categoria
                    FROM comanda_itens ci
                    LEFT JOIN produtos p ON p.id_produto = ci.id_produto
                    WHERE ci.id_comanda = ?
                    ORDER BY ci.criado_em
                ");
                $stmt_imp->execute([$id_comanda]);
                $itens_para_imprimir = $stmt_imp->fetchAll(PDO::FETCH_ASSOC);
                $mesa_exib = $observacao_cmd ?: '-';
                $resultados_imp = despacharParaImpressoras($conn, $id_comanda, $numero_comanda, $mesa_exib, $nome_caixa, $itens_para_imprimir);
                foreach ($resultados_imp as $r) {
                    $status = $r['sucesso'] ? 'OK' : 'FALHA';
                    error_log("Impressao [{$status}] -> {$r['impressora']} ({$r['ip']}) - {$r['itens_count']} item(ns)");
                }
            } catch (\Throwable $e) {
                error_log("Erro ao imprimir comanda #$numero_comanda: " . $e->getMessage());
            }

            $msg = "Comanda <strong>#$numero_comanda</strong> " . ($comanda_exist ? "atualizada" : "criada") . "! " . count($itens) . " item(ns).";
            $tipo_msg = 'success';

        } catch (\Throwable $e) { $conn->rollBack(); $msg = 'Erro: ' . $e->getMessage(); $tipo_msg = 'danger'; }
    } else { $msg = implode(' ', $erros); $tipo_msg = 'danger'; }
}

// ===============================================================
// DADOS PARA RENDERIZACAO
// ===============================================================
$sessao_atual = sessaoAtiva($conn);

$dados_sessao = null;
if ($sessao_atual) {
    $id_s = $sessao_atual['id_sessao'];
    $sm = $conn->prepare("SELECT * FROM caixa_movimentacoes WHERE id_sessao = ? ORDER BY criado_em DESC");
    $sm->execute([$id_s]);
    $movimentacoes = $sm->fetchAll(PDO::FETCH_ASSOC);

    // Filtro de tenant nas vendas por pagamento (renderizacao)
    if ($is_master) {
        $sv = $conn->query("SELECT forma_pagamento, COUNT(*) AS qtd, SUM(valor_total) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao_atual['aberto_em']}' GROUP BY forma_pagamento");
    } else {
        $sv = $conn->prepare("SELECT forma_pagamento, COUNT(*) AS qtd, SUM(valor_total) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao_atual['aberto_em']}' AND id_tenant = ? GROUP BY forma_pagamento");
        $sv->execute([$id_tenant]);
    }
    $vendas_pagamento = $sv->fetchAll(PDO::FETCH_ASSOC);

    // Filtro de tenant nos totais (renderizacao)
    if ($is_master) {
        $st = $conn->query("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao_atual['aberto_em']}'");
    } else {
        $st = $conn->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total FROM pedidos WHERE status = 'aprovado' AND data_pedido >= '{$sessao_atual['aberto_em']}' AND id_tenant = ?");
        $st->execute([$id_tenant]);
    }
    $totais_vendas = $st->fetch(PDO::FETCH_ASSOC);

    $total_suprimentos = 0; $total_sangrias = 0; $total_despesas = 0;
    foreach ($movimentacoes as $m) {
        if ($m['tipo'] === 'suprimento') $total_suprimentos += $m['valor'];
        if ($m['tipo'] === 'sangria')    $total_sangrias    += $m['valor'];
        if ($m['tipo'] === 'despesa')    $total_despesas    += $m['valor'];
    }
    $total_saidas   = $total_sangrias + $total_despesas;
    $total_vendido  = floatval($totais_vendas['total']);
    $saldo_esperado = floatval($sessao_atual['valor_inicial']) + $total_suprimentos + $total_vendido - $total_saidas;

    $dados_sessao = compact('movimentacoes','vendas_pagamento','totais_vendas','total_suprimentos','total_sangrias','total_despesas','total_saidas','total_vendido','saldo_esperado');
}

$agora_ts_banco = (int)$conn->query("SELECT EXTRACT(EPOCH FROM NOW())")->fetchColumn();

// Comandas abertas filtradas por tenant
try {
    $tf = tenantFiltroComandas($is_master, $id_tenant, 'c');
    $stmt = $conn->prepare("
        SELECT c.*,
               COUNT(ci.id_item) AS total_itens,
               EXTRACT(EPOCH FROM c.criado_em AT TIME ZONE 'America/Belem')::int AS criado_em_ts
        FROM comandas c
        LEFT JOIN comanda_itens ci ON ci.id_comanda = c.id_comanda
        WHERE c.status = 'aberta'
        {$tf['where']}
        GROUP BY c.id_comanda
        ORDER BY c.criado_em ASC
    ");
    $stmt->execute($tf['binds']);
    $comandas_abertas = $stmt->fetchAll();
} catch (\Throwable $e) { $comandas_abertas = []; }

// Historico filtrado por tenant
try {
    if ($sessao_atual) {
        if ($is_master) {
            $stmt = $conn->prepare("
                SELECT c.*, COUNT(ci.id_item) AS total_itens
                FROM comandas c
                LEFT JOIN comanda_itens ci ON ci.id_comanda = c.id_comanda
                WHERE c.status IN ('fechada', 'cancelada')
                  AND c.fechado_em >= ?
                GROUP BY c.id_comanda
                ORDER BY c.fechado_em DESC
            ");
            $stmt->execute([$sessao_atual['aberto_em']]);
        } else {
            $stmt = $conn->prepare("
                SELECT c.*, COUNT(ci.id_item) AS total_itens
                FROM comandas c
                LEFT JOIN comanda_itens ci ON ci.id_comanda = c.id_comanda
                WHERE c.status IN ('fechada', 'cancelada')
                  AND c.fechado_em >= ?
                  AND c.id_tenant = ?
                GROUP BY c.id_comanda
                ORDER BY c.fechado_em DESC
            ");
            $stmt->execute([$sessao_atual['aberto_em'], $id_tenant]);
        }
        $historico = $stmt->fetchAll();
    } else {
        $historico = [];
    }
} catch (\Throwable $e) { $historico = []; }

// Categorias filtradas por tenant
try {
    if ($is_master) {
        $categorias = $conn->query("
            SELECT c.id_categoria, c.nome, COUNT(p.id_produto) AS total
            FROM categorias c
            INNER JOIN produtos p ON p.id_categoria = c.id_categoria AND p.ativo = TRUE AND p.estoque > 0
            GROUP BY c.id_categoria, c.nome ORDER BY c.nome ASC
        ")->fetchAll();
    } else {
        $stmt_cat = $conn->prepare("
            SELECT c.id_categoria, c.nome, COUNT(p.id_produto) AS total
            FROM categorias c
            INNER JOIN produtos p ON p.id_categoria = c.id_categoria AND p.ativo = TRUE AND p.estoque > 0
                AND p.id_tenant = ?
            GROUP BY c.id_categoria, c.nome ORDER BY c.nome ASC
        ");
        $stmt_cat->execute([$id_tenant]);
        $categorias = $stmt_cat->fetchAll();
    }
} catch (\Throwable $e) { $categorias = []; }

// Historico de sessoes do dia
try {
    $stmt_hist_sess = $conn->query("
        SELECT * FROM caixa_sessoes
        WHERE DATE(aberto_em AT TIME ZONE 'America/Belem') = CURRENT_DATE AT TIME ZONE 'America/Belem'
        ORDER BY aberto_em DESC
    ");
    $historico_sessoes = $stmt_hist_sess->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $historico_sessoes = []; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Caixa — Comandas</title>
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; background: var(--dash-bg, #f1f5f9); min-height: 100vh; color: #0f172a; }
.cx-page { max-width: 1100px; margin: 0 auto; padding: 28px 20px 80px; }
.cx-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 10px; }
.cx-btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 50px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Sora', sans-serif; }
.cx-hero { background: linear-gradient(135deg, var(--primary,#2563eb) 0%, var(--primary-dark,#1e40af) 100%); border-radius: 18px; padding: 26px 28px; color: #fff; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.cx-hero h1 { font-size: 22px; font-weight: 800; }
.cx-hero p  { font-size: 13px; opacity: .8; margin-top: 4px; }
.cx-stat-num { font-size: 28px; font-weight: 800; line-height: 1; }
.cx-stat-lbl { font-size: 11px; opacity: .75; margin-top: 2px; }
.cx-stat-update { font-size: 10px; opacity: .6; margin-top: 3px; }
.cx-alert { padding: 14px 18px; border-radius: 14px; font-size: 14px; font-weight: 500; margin-bottom: 20px; border-left: 4px solid; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
.cx-alert.success { background: #ecfdf5; color: #065f46; border-color: #10b981; }
.cx-alert.danger  { background: #fef2f2; color: #7f1d1d; border-color: #ef4444; }
.cx-btn-imprimir  { padding: 9px 18px; background: #fff; color: #059669; border: 2px solid #059669; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Sora', sans-serif; white-space: nowrap; }
.cx-tabs { display: flex; gap: 6px; margin-bottom: 20px; background: #fff; border-radius: 16px; padding: 6px; border: 1.5px solid #e2e8f0; flex-wrap: wrap; }
.cx-tab-btn { flex: 1; min-width: 120px; padding: 10px 16px; border: none; border-radius: 11px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Sora', sans-serif; color: #64748b; background: transparent; transition: .15s; white-space: nowrap; }
.cx-tab-btn.ativa { background: var(--primary,#2563eb); color: #fff; }
.cx-tab-panel { display: none; }
.cx-tab-panel.ativo { display: block; }
.cx-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #ffffff !important; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; background: transparent !important; }
.cx-badge-count { background: var(--primary,#2563eb); color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 20px; }
.cx-caixa-status-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.cx-status-chip { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 50px; font-size: 14px; font-weight: 700; }
.cx-status-chip.aberto    { background: #dcfce7; color: #059669; border: 2px solid #86efac; }
.cx-status-chip.fechado   { background: #fee2e2; color: #dc2626; border: 2px solid #fca5a5; }
.cx-status-chip.sem-caixa { background: #fef9c3; color: #92400e; border: 2px solid #fde68a; }
.cx-status-dot { width: 10px; height: 10px; border-radius: 50%; }
.cx-status-dot.verde    { background: #059669; animation: pulse-green 1.5s infinite; }
.cx-status-dot.vermelho { background: #dc2626; }
.cx-status-dot.amarelo  { background: #f59e0b; }
@keyframes pulse-green { 0%,100%{box-shadow:0 0 0 0 rgba(5,150,105,.4)} 50%{box-shadow:0 0 0 6px rgba(5,150,105,0)} }
.cx-resumo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
.cx-resumo-card { background: #fff; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 16px 18px; }
.cx-resumo-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 6px; }
.cx-resumo-val { font-size: 20px; font-weight: 800; color: #0f172a; }
.cx-resumo-val.verde    { color: #059669; }
.cx-resumo-val.vermelho { color: #dc2626; }
.cx-resumo-val.azul     { color: var(--primary,#2563eb); }
.cx-resumo-sub { font-size: 11px; color: #94a3b8; margin-top: 3px; }
.cx-mov-lista { background: #fff; border-radius: 14px; border: 1.5px solid #e2e8f0; overflow: hidden; margin-bottom: 16px; }
.cx-mov-header { padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; display: flex; justify-content: space-between; align-items: center; }
.cx-mov-item { display: flex; align-items: center; gap: 12px; padding: 11px 16px; border-bottom: 1px solid #f8fafc; }
.cx-mov-item:last-child { border-bottom: none; }
.cx-mov-tipo { font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 20px; flex-shrink: 0; text-transform: uppercase; }
.cx-mov-tipo.sangria    { background: #fee2e2; color: #dc2626; }
.cx-mov-tipo.suprimento { background: #dcfce7; color: #059669; }
.cx-mov-tipo.despesa    { background: #fef3c7; color: #92400e; }
.cx-mov-desc { flex: 1; font-size: 13px; font-weight: 600; }
.cx-mov-hora { font-size: 11px; color: #94a3b8; }
.cx-mov-val  { font-size: 14px; font-weight: 800; white-space: nowrap; }
.cx-mov-val.saida   { color: #dc2626; }
.cx-mov-val.entrada { color: #059669; }
.cx-mov-vazio { padding: 20px; text-align: center; color: #94a3b8; font-size: 13px; }
.cx-pag-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; margin-bottom: 20px; }
.cx-pag-card  { background: #fff; border-radius: 12px; border: 1.5px solid #e2e8f0; padding: 14px 16px; }
.cx-pag-label { font-size: 11px; font-weight: 700; color: #94a3b8; margin-bottom: 4px; }
.cx-pag-val   { font-size: 16px; font-weight: 800; color: var(--primary,#2563eb); }
.cx-pag-qtd   { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.cx-diferenca-box { border-radius: 12px; padding: 14px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.cx-diferenca-box.sobrou { background: #dcfce7; border: 1.5px solid #86efac; }
.cx-diferenca-box.faltou { background: #fee2e2; border: 1.5px solid #fca5a5; }
.cx-diferenca-box.zerou  { background: #f1f5f9; border: 1.5px solid #cbd5e1; }
.cx-diferenca-label { font-size: 13px; font-weight: 700; }
.cx-diferenca-val   { font-size: 20px; font-weight: 800; }
.cx-sess-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 14px; overflow: hidden; border: 1.5px solid #e2e8f0; }
.cx-sess-table th { padding: 11px 16px; background: #f8fafc; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; text-align: left; border-bottom: 1px solid #f1f5f9; }
.cx-sess-table td { padding: 11px 16px; font-size: 13px; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.cx-sess-table tr:last-child td { border-bottom: none; }
.cx-sess-table tr.clicavel { cursor: pointer; transition: background .12s; }
.cx-sess-table tr.clicavel:hover td { background: #f0f9ff; }
.cx-badge-aberto  { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #059669; }
.cx-badge-fechado { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #e0e7ff; color: #3730a3; }
.cx-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 32px; }
.cx-comanda-card { background: #fff; border-radius: 16px; border: 1.5px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,.05); overflow: hidden; transition: box-shadow .2s, border-color .2s; cursor: pointer; }
.cx-comanda-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.1); border-color: var(--primary,#2563eb); }
.cx-comanda-card.urgente { border-color: #fca5a5; }
.cx-comanda-card.ativo { border-color: var(--primary,#2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
.cx-cmd-compact { padding: 20px 18px 16px; display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; }
.cx-cmd-compact-num   { font-size: 28px; font-weight: 800; color: var(--primary,#2563eb); line-height: 1; }
.cx-cmd-compact-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; }
.cx-cmd-compact-badge { font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #fef3c7; color: #92400e; margin-top: 4px; }
.cx-cmd-compact-badge.urgente { background: #fee2e2; color: #991b1b; }
.cx-cmd-compact-hint  { font-size: 10px; color: #94a3b8; padding: 8px 0 12px; border-top: 1px solid #f1f5f9; width: 100%; text-align: center; margin-top: 4px; }
.cx-cmd-mesa-badge { display: inline-flex; align-items: center; gap: 4px; background: #dbeafe; color: #1e40af; font-size: 10px; font-weight: 800; padding: 3px 10px; border-radius: 20px; margin-top: 4px; letter-spacing: .2px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cx-det-mesa-chip { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,.2); border: 1.5px solid rgba(255,255,255,.4); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; margin-top: 6px; }
.cx-detalhe-panel { background: #fff; border-radius: 16px; border: 1.5px solid var(--primary,#2563eb); box-shadow: 0 6px 24px rgba(37,99,235,.12); margin-bottom: 20px; overflow: hidden; animation: slideDown .2s ease; }
@keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
.cx-det-header { padding: 16px 20px; background: linear-gradient(135deg, var(--primary,#2563eb), var(--primary-dark,#1e40af)); color: #fff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.cx-det-header-left h3 { font-size: 18px; font-weight: 800; }
.cx-det-header-left p  { font-size: 12px; opacity: .8; margin-top: 3px; }
.cx-det-total { font-size: 20px; font-weight: 800; }
.cx-det-meta  { padding: 12px 20px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 16px; }
.cx-det-meta-item { font-size: 12px; color: #64748b; }
.cx-det-meta-item strong { color: #0f172a; font-weight: 700; }
.cx-det-itens { padding: 14px 20px; }
.cx-det-item  { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f8fafc; cursor: pointer; transition: background .12s; border-radius: 8px; }
.cx-det-item:last-child { border-bottom: none; }
.cx-det-item:hover { background: #f8fafc; }
.cx-det-item-qty  { min-width: 30px; height: 30px; background: var(--primary,#2563eb); color: #fff; border-radius: 8px; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
.cx-det-item-info { flex: 1; min-width: 0; }
.cx-det-item-nome { font-size: 14px; font-weight: 700; }
.cx-det-item-ads  { font-size: 12px; color: var(--primary,#2563eb); font-weight: 600; margin-top: 2px; }
.cx-det-item-obs  { font-size: 11px; color: #94a3b8; font-style: italic; margin-top: 2px; }
.cx-det-item-extra { display: none; margin-top: 6px; padding: 7px 10px; background: #eff6ff; border-radius: 8px; border-left: 3px solid var(--primary,#2563eb); }
.cx-det-item-sub  { font-size: 14px; font-weight: 800; color: #059669; white-space: nowrap; flex-shrink: 0; align-self: center; }
.cx-det-footer    { padding: 14px 20px; background: #f8fafc; border-top: 2px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.cx-det-footer-total { font-size: 18px; font-weight: 800; }
.cx-det-footer-total span { color: #059669; }
.cx-det-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.cx-btn-fechar-det    { padding: 10px 20px; background: linear-gradient(135deg,#059669,#047857); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; }
.cx-btn-add-item-det  { padding: 10px 16px; background: linear-gradient(135deg,var(--primary,#2563eb),var(--primary-dark,#1e40af)); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; }
.cx-btn-cancelar-det  { padding: 10px 14px; background: #fef2f2; color: #ef4444; border: 1.5px solid #fecaca; border-radius: 10px; font-size: 12px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; }
.cx-btn-fechar-painel { padding: 10px 14px; background: #f1f5f9; color: #64748b; border: none; border-radius: 10px; font-size: 12px; font-weight: 600; font-family: 'Sora', sans-serif; cursor: pointer; }
.cx-det-loading { padding: 24px; text-align: center; color: #94a3b8; font-size: 13px; }
.cx-hist-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 14px; overflow: hidden; border: 1.5px solid #e2e8f0; }
.cx-hist-table th { padding: 11px 16px; background: #f8fafc; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; text-align: left; border-bottom: 1px solid #f1f5f9; }
.cx-hist-table td { padding: 11px 16px; font-size: 13px; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.cx-hist-table tr:last-child td { border-bottom: none; }
.cx-hist-row { cursor: pointer; transition: background .12s; }
.cx-hist-row:hover td { background: #f0f9ff; }
.cx-badge-fechada   { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #059669; }
.cx-badge-cancelada { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #ef4444; }
.cx-vazio { text-align: center; padding: 48px 20px; color: #94a3b8; }
.cx-vazio-txt { font-size: 15px; font-weight: 600; }
.cx-vazio-sub { font-size: 13px; margin-top: 6px; }
.cx-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.cx-modal-overlay.show { display: flex; }
.cx-modal { background: #fff; border-radius: 20px; padding: 28px; width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,.2); max-height: 90vh; overflow-y: auto; }
.cx-modal h3 { font-size: 18px; font-weight: 800; margin-bottom: 6px; }
.cx-modal-sub { font-size: 13px; color: #64748b; margin-bottom: 20px; }
.cx-modal-itens { background: #f8fafc; border-radius: 12px; padding: 14px; margin-bottom: 18px; max-height: 200px; overflow-y: auto; }
.cx-modal-item  { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; border-bottom: 1px solid #f1f5f9; gap: 8px; }
.cx-modal-item:last-child { border-bottom: none; }
.cx-modal-total { display: flex; justify-content: space-between; font-size: 16px; font-weight: 800; color: #065f46; border-top: 2px solid #d1fae5; padding-top: 12px; margin-bottom: 18px; }
.cx-pag-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.cx-pag-pill { position: relative; }
.cx-pag-pill input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
.cx-pag-pill label { display: flex; align-items: center; gap: 8px; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; transition: .2s; font-family: 'Sora', sans-serif; }
.cx-pag-pill input:checked + label { border-color: #059669; background: #ecfdf5; color: #065f46; }
.cx-modal-btns { display: flex; gap: 10px; }
.cx-btn-confirmar    { flex: 1; padding: 13px; background: linear-gradient(135deg,#059669,#047857); color: #fff; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; }
.cx-btn-fechar-modal { padding: 13px 18px; background: #f1f5f9; color: #64748b; border: none; border-radius: 12px; font-size: 14px; font-weight: 600; font-family: 'Sora', sans-serif; cursor: pointer; }
.cx-btn-danger-modal { flex: 1; padding: 13px; background: linear-gradient(135deg,#dc2626,#b91c1c); color: #fff; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; }
.cx-prod-modal { max-width: 680px; }
.cx-prod-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.cx-prod-modal-header h3 { font-size: 17px; font-weight: 800; }
.cx-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.cx-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; }
.cx-field input, .cx-field textarea { padding: 10px 13px; border: 2px solid #e2e8f0; border-radius: 11px; font-size: 14px; font-family: 'Sora', sans-serif; outline: none; background: #fff; transition: .2s; }
.cx-field input:focus, .cx-field textarea:focus { border-color: var(--primary,#2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.cx-field textarea { resize: none; height: 58px; }
.cx-prod-modal-tabs { display: flex; gap: 6px; margin-bottom: 12px; }
.cx-prod-modal-tab  { flex: 1; padding: 9px 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; font-family: 'Sora', sans-serif; color: #64748b; background: #fff; transition: .15s; text-align: center; }
.cx-prod-modal-tab.ativa { background: var(--primary,#2563eb); border-color: var(--primary,#2563eb); color: #fff; }
.cx-cats-scroll { display: flex; gap: 7px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 12px; scrollbar-width: none; }
.cx-cats-scroll::-webkit-scrollbar { display: none; }
.cx-cat-pill { display: inline-flex; align-items: center; gap: 4px; padding: 6px 13px; border: 2px solid #e2e8f0; border-radius: 20px; font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer; white-space: nowrap; background: #fff; font-family: 'Sora', sans-serif; transition: .15s; flex-shrink: 0; }
.cx-cat-pill.ativa { background: var(--primary,#2563eb); border-color: var(--primary,#2563eb); color: #fff; }
.cx-prod-grid-modal { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; max-height: 240px; overflow-y: auto; margin-bottom: 14px; }
.cx-prod-card-modal { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; cursor: pointer; transition: border-color .15s; }
.cx-prod-card-modal:hover { border-color: var(--primary,#2563eb); background: #eff6ff; }
.cx-prod-card-modal .nome  { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
.cx-prod-card-modal .preco { font-size: 13px; font-weight: 800; color: var(--primary,#2563eb); }
.cx-prod-card-modal .preco-old { font-size: 11px; color: #94a3b8; text-decoration: line-through; }
.cx-prod-card-modal .estoque { font-size: 10px; color: #94a3b8; }
.cx-prod-promo-badge { display: inline-block; background: #dc2626; color: #fff; font-size: 9px; font-weight: 800; padding: 2px 5px; border-radius: 4px; margin-bottom: 3px; }
.cx-avul-grid-modal { display: flex; flex-direction: column; gap: 7px; max-height: 240px; overflow-y: auto; margin-bottom: 14px; }
.cx-avul-card-modal { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; cursor: pointer; transition: border-color .15s; }
.cx-avul-card-modal:hover { border-color: var(--primary,#2563eb); background: #eff6ff; }
.cx-avul-card-modal .avul-nome  { font-size: 13px; font-weight: 700; }
.cx-avul-card-modal .avul-sub   { font-size: 11px; color: #94a3b8; margin-top: 1px; }
.cx-avul-card-modal .avul-preco { font-size: 13px; font-weight: 800; color: #059669; white-space: nowrap; }
.cx-avul-card-modal .avul-btn   { padding: 5px 12px; background: var(--primary,#2563eb); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; flex-shrink: 0; }
.cx-avul-busca-wrap { position: relative; margin-bottom: 10px; }
.cx-avul-busca-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #94a3b8; font-weight: 700; }
.cx-avul-busca { width: 100%; padding: 9px 12px 9px 36px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 13px; font-family: 'Sora', sans-serif; outline: none; transition: .2s; }
.cx-avul-busca:focus { border-color: var(--primary,#2563eb); }
.cx-carrinho { background: #f8fafc; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; max-height: 180px; overflow-y: auto; }
.cx-carr-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.cx-carr-item:last-child { border-bottom: none; }
.cx-carr-nome { flex: 1; font-weight: 600; min-width: 0; }
.cx-carr-qty  { display: flex; align-items: center; gap: 4px; }
.cx-carr-qty-btn { width: 24px; height: 24px; border: none; background: #e2e8f0; border-radius: 6px; font-size: 14px; font-weight: 700; color: var(--primary,#2563eb); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.cx-carr-sub  { font-size: 13px; font-weight: 800; color: #059669; white-space: nowrap; }
.cx-carr-del  { width: 24px; height: 24px; background: #fef2f2; border: none; border-radius: 6px; color: #ef4444; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cx-carr-vazio { text-align: center; color: #94a3b8; font-size: 13px; padding: 10px 0; }
.cx-carr-total { display: flex; justify-content: space-between; font-size: 14px; font-weight: 800; padding-top: 8px; border-top: 2px solid #e2e8f0; margin-top: 4px; }
.cx-item-modal { max-width: 420px; }
.cx-adicional-item { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f8fafc; }
.cx-adicional-item:last-child { border-bottom: none; }
.cx-adicional-check { width: 22px; height: 22px; border: 2px solid #e2e8f0; border-radius: 6px; cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; transition: .15s; }
.cx-adicional-check.checked { background: var(--primary,#2563eb); border-color: var(--primary,#2563eb); }
.cx-qty-row  { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; }
.cx-qty-ctrl { display: flex; align-items: center; gap: 6px; background: #f1f5f9; border-radius: 12px; padding: 4px; }
.cx-qty-btn  { width: 36px; height: 36px; background: #fff; border: none; border-radius: 9px; font-size: 18px; font-weight: 700; color: var(--primary,#2563eb); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.cx-qty-num  { width: 38px; text-align: center; font-size: 16px; font-weight: 800; border: none; background: transparent; outline: none; font-family: 'Sora', sans-serif; }
.cx-item-obs-field { margin-top: 14px; }
.cx-item-obs-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; display: block; margin-bottom: 6px; }
.cx-item-obs-field textarea { width: 100%; padding: 9px 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 13px; font-family: 'Sora', sans-serif; outline: none; resize: none; height: 56px; transition: .2s; }
.cx-item-obs-field textarea:focus { border-color: var(--primary,#2563eb); }
.cx-avul-modal { max-width: 400px; }
.cx-hist-modal { max-width: 540px; }
.cx-hist-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
.cx-hist-modal-header h3 { font-size: 18px; font-weight: 800; }
.cx-hist-status-badge { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
.cx-hist-status-badge.fechada   { background: #dcfce7; color: #059669; }
.cx-hist-status-badge.cancelada { background: #fee2e2; color: #ef4444; }
.cx-hist-meta-block { background: #f8fafc; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 14px; }
.cx-hist-meta-block .mi { font-size: 12px; color: #64748b; }
.cx-hist-meta-block .mi strong { color: #0f172a; font-weight: 700; display: block; }
.cx-hist-itens-wrap { background: #f8fafc; border-radius: 12px; padding: 10px 14px; margin-bottom: 16px; max-height: 260px; overflow-y: auto; }
.cx-hist-item-linha { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.cx-hist-item-linha:last-child { border-bottom: none; }
.cx-hist-item-qty  { min-width: 26px; height: 26px; background: #64748b; color: #fff; border-radius: 6px; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
.cx-hist-item-info { flex: 1; min-width: 0; }
.cx-hist-item-nome { font-size: 13px; font-weight: 700; }
.cx-hist-item-ads  { font-size: 11px; color: #2563eb; font-weight: 600; margin-top: 2px; }
.cx-hist-item-obs  { font-size: 11px; color: #94a3b8; font-style: italic; margin-top: 2px; }
.cx-hist-item-sub  { font-size: 13px; font-weight: 800; color: #059669; white-space: nowrap; flex-shrink: 0; align-self: center; }
.cx-hist-total-row { display: flex; justify-content: space-between; font-size: 15px; font-weight: 800; padding-top: 10px; border-top: 2px solid #e2e8f0; margin-bottom: 4px; }
.cx-sess-modal { max-width: 560px; }
#cx-comprovante-print { display: none; }
@media print { body > * { display: none !important; } #cx-comprovante-print { display: block !important; font-family: 'Courier New', monospace; font-size: 12px; color: #000; width: 80mm; padding: 4mm; } }
@media (max-width: 600px) {
    .cx-page { padding: 16px 12px 60px; }
    .cx-hero { border-radius: 14px; padding: 18px; }
    .cx-hero h1 { font-size: 18px; }
    .cx-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .cx-resumo-grid { grid-template-columns: repeat(2, 1fr); }
    .cx-tabs { gap: 4px; }
    .cx-tab-btn { font-size: 11px; padding: 8px 10px; min-width: 80px; }
}
</style>
</head>
<body>
 
<div id="cx-comprovante-print">
<?php if ($comprovante): ?>
    <!-- CABEÇALHO DA EMPRESA -->
    <?php if (!empty($comprovante['nome_empresa'])): ?>
    <div style="text-align:center;font-weight:bold;font-size:15px;margin-bottom:2px;">
        <?= htmlspecialchars($comprovante['nome_empresa']) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($comprovante['cnpj'])): ?>
    <div style="text-align:center;font-size:11px;margin-bottom:1px;">
        CNPJ: <?= htmlspecialchars($comprovante['cnpj']) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($comprovante['endereco'])): ?>
    <div style="text-align:center;font-size:10px;margin-bottom:1px;">
        <?= htmlspecialchars($comprovante['endereco']) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($comprovante['telefone'])): ?>
    <div style="text-align:center;font-size:10px;margin-bottom:4px;">
        Tel: <?= htmlspecialchars($comprovante['telefone']) ?>
    </div>
    <?php endif; ?>

    <div style="border-top:2px solid #000;margin:5px 0;"></div>
    <div style="text-align:center;font-weight:bold;font-size:13px;margin-bottom:4px;">COMPROVANTE DE VENDA</div>
    <div style="border-top:1px dashed #888;margin:5px 0;"></div>

    <!-- DADOS DO PEDIDO -->
    <div style="display:flex;justify-content:space-between;margin:2px 0;font-size:12px;"><span>Pedido</span><span>#<?= $comprovante['id_pedido'] ?></span></div>
    <div style="display:flex;justify-content:space-between;margin:2px 0;font-size:12px;"><span>Comanda</span><span>#<?= htmlspecialchars($comprovante['numero_comanda']) ?></span></div>
    <div style="display:flex;justify-content:space-between;margin:2px 0;font-size:12px;"><span>Data</span><span><?= $comprovante['data'] ?></span></div>
    <?php if (!empty($comprovante['lancado_por'])): ?>
    <div style="display:flex;justify-content:space-between;margin:2px 0;font-size:12px;"><span>Lancado por</span><span><?= htmlspecialchars($comprovante['lancado_por']) ?></span></div>
    <?php endif; ?>
    <?php if ($comprovante['observacao']): ?>
    <div style="display:flex;justify-content:space-between;margin:2px 0;font-size:12px;"><span>Obs</span><span><?= htmlspecialchars($comprovante['observacao']) ?></span></div>
    <?php endif; ?>

    <div style="border-top:1px dashed #888;margin:5px 0;"></div>

    <!-- ITENS -->
    <?php foreach ($comprovante['itens'] as $it): ?>
    <div style="font-weight:bold;margin:4px 0 1px;font-size:12px;"><?= htmlspecialchars($it['nome']) ?></div>
    <div style="display:flex;justify-content:space-between;font-size:11px;color:#333;padding-left:4px;">
        <span><?= $it['quantidade'] ?>x R$ <?= number_format($it['preco'],2,',','.') ?></span>
        <span>R$ <?= number_format($it['subtotal'],2,',','.') ?></span>
    </div>
    <?php if (!empty($it['adicionais'])): foreach ($it['adicionais'] as $ad): ?>
    <div style="font-size:11px;color:#444;padding-left:8px;">
        + <?= htmlspecialchars($ad['nome']) ?><?= !empty($ad['preco'])&&$ad['preco']>0?' R$ '.number_format($ad['preco'],2,',','.').' ' :'gratis' ?>
    </div>
    <?php endforeach; endif; ?>
    <?php if (!empty($it['obs'])): ?>
    <div style="font-size:10px;color:#555;font-style:italic;padding-left:8px;">"<?= htmlspecialchars($it['obs']) ?>"</div>
    <?php endif; ?>
    <?php endforeach; ?>

    <div style="border-top:1px dashed #888;margin:5px 0;"></div>

    <!-- TOTAL -->
    <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:15px;margin:5px 0 3px;">
        <span>TOTAL</span><span>R$ <?= number_format($comprovante['total'],2,',','.') ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:12px;">
        <span>Pagamento</span><span><?= htmlspecialchars($comprovante['forma_pagamento']) ?></span>
    </div>

    <div style="border-top:2px solid #000;margin:5px 0;"></div>
    <div style="text-align:center;font-size:10px;color:#666;margin-top:10px;">VOLTE SEMPRE</div>
<?php endif; ?>
</div>
 
<div class="cx-page">
 
    <div class="cx-topbar">
        <?php if ($id_admin_real): ?>
        <a href="modo_restaurante.php" class="cx-btn-back">&#8592; Voltar</a>
        <?php else: ?>
        <a href="logout_vendedor.php" class="cx-btn-back">Sair</a>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" onclick="abrirNovaComanda()" style="display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:linear-gradient(135deg,var(--primary,#2563eb),var(--primary-dark,#1e40af));color:#fff;border:none;border-radius:50px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;">
                + Nova Comanda
            </button>
        </div>
    </div>
 
    <div class="cx-hero">
        <div>
            <h1>Caixa — Gestao Completa</h1>
            <p>Comandas, caixa, movimentacoes e relatorios</p>
        </div>
        <div style="display:flex;gap:20px;">
            <div style="text-align:center;">
                <div class="cx-stat-num" id="cx-stat-abertas"><?= count($comandas_abertas) ?></div>
                <div class="cx-stat-lbl">Comandas abertas</div>
                <div class="cx-stat-update" id="cx-stat-update">atualizado agora</div>
            </div>
            <div style="text-align:center;">
                <div class="cx-stat-num"><?= $sessao_atual ? '<span style="color:#86efac;">Aberto</span>' : '<span style="color:#fde68a;">Fechado</span>' ?></div>
                <div class="cx-stat-lbl">Status do caixa</div>
            </div>
        </div>
    </div>
 
    <?php if ($msg): ?>
    <div class="cx-alert <?= $tipo_msg ?>" id="cx-msg-alert">
        <span><?= $msg ?></span>
        <?php if ($tipo_msg === 'success' && $comprovante): ?>
        <button type="button" class="cx-btn-imprimir" onclick="imprimirComprovante()">Imprimir Comprovante</button>
        <?php endif; ?>
    </div>
    <script>setTimeout(function(){var el=document.getElementById('cx-msg-alert');if(el){el.style.transition='opacity .5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);}},6000);</script>
    <?php endif; ?>
 
    <div class="cx-tabs">
        <button class="cx-tab-btn ativa" onclick="trocarTab('comandas',this)">Comandas</button>
        <button class="cx-tab-btn"       onclick="trocarTab('caixa',this)">Caixa</button>
        <button class="cx-tab-btn"       onclick="trocarTab('relatorio',this)">Relatorio</button>
        <button class="cx-tab-btn"       onclick="trocarTab('historico_sess',this)">Historico Caixa</button>
    </div>
 
    <!-- ABA: COMANDAS -->
    <div class="cx-tab-panel ativo" id="tab-comandas">
        <div class="cx-section-title">
            Comandas Abertas
            <span class="cx-badge-count" id="cx-badge-count"><?= count($comandas_abertas) ?></span>
        </div>
 
        <div class="cx-detalhe-panel" id="cx-detalhe-panel" style="display:none;">
            <div class="cx-det-header">
                <div class="cx-det-header-left">
                    <h3 id="cx-det-titulo">Comanda —</h3>
                    <p id="cx-det-meta-header"></p>
                    <div id="cx-det-mesa-chip" style="display:none;" class="cx-det-mesa-chip">&#9632; <span id="cx-det-mesa-txt"></span></div>
                </div>
                <div><span class="cx-det-total" id="cx-det-total-header">R$ —</span></div>
            </div>
            <div class="cx-det-meta" id="cx-det-meta"></div>
            <div class="cx-det-itens" id="cx-det-itens"><div class="cx-det-loading">Carregando itens...</div></div>
            <div class="cx-det-footer">
                <div class="cx-det-footer-total">Total: <span id="cx-det-total-footer">R$ —</span></div>
                <div class="cx-det-btns">
                    <button type="button" class="cx-btn-fechar-painel" onclick="fecharPainel()">Fechar</button>
                    <button type="button" class="cx-btn-add-item-det"  onclick="abrirAdicionarItem()">+ Adicionar item</button>
                    <button type="button" class="cx-btn-cancelar-det"  onclick="cancelarComanda()">Cancelar</button>
                    <button type="button" class="cx-btn-fechar-det"    onclick="abrirModalPagamento()">Fechar Comanda</button>
                </div>
            </div>
        </div>
 
        <div id="cx-grid-wrapper">
            <?php if (empty($comandas_abertas)): ?>
            <div class="cx-vazio"><div class="cx-vazio-txt">Nenhuma comanda aberta</div><div class="cx-vazio-sub">As comandas lancadas pelos atendentes aparecerao aqui</div></div>
            <?php else: ?>
            <div class="cx-grid" id="cx-grid-comandas">
                <?php foreach ($comandas_abertas as $cmd):
                    $criado_ts_cmd = intval($cmd['criado_em_ts']);
                    $minutos = max(0, (int)(($agora_ts_banco - $criado_ts_cmd) / 60));
                    $urgente = $minutos >= 30;
                    if ($minutos < 1)      $tempo_label = 'Agora';
                    elseif ($minutos < 60) $tempo_label = $minutos . ' min';
                    else { $h = floor($minutos/60); $m = $minutos%60; $tempo_label = $h.'h'.($m>0?' '.$m.'min':''); }
                    $dataHora = date('d/m H:i', $criado_ts_cmd);
                    $mesa_cmd = $cmd['observacao'] ?? '';
                ?>
                <div class="cx-comanda-card <?= $urgente?'urgente':'' ?>"
                     onclick="abrirDetalhe(<?= $cmd['id_comanda'] ?>,'<?= htmlspecialchars(addslashes($cmd['numero_comanda'])) ?>',<?= $cmd['valor_total'] ?>,'<?= $dataHora ?>','<?= htmlspecialchars(addslashes($cmd['lancado_por']??'')) ?>','<?= htmlspecialchars(addslashes($mesa_cmd)) ?>')"
                     data-criado-ts="<?= $criado_ts_cmd ?>"
                     id="card-<?= $cmd['id_comanda'] ?>">
                    <div class="cx-cmd-compact">
                        <div class="cx-cmd-compact-label">Comanda</div>
                        <div class="cx-cmd-compact-num"><?= htmlspecialchars($cmd['numero_comanda']) ?></div>
                        <span class="cx-cmd-compact-badge <?= $urgente?'urgente':'' ?>"><?= $tempo_label ?></span>
                        <?php if (!empty($mesa_cmd)): ?>
                        <div class="cx-cmd-mesa-badge">&#9632; <?= htmlspecialchars(preg_match('/^\d+$/', $mesa_cmd) ? 'Mesa '.$mesa_cmd : $mesa_cmd) ?></div>
                        <?php endif; ?>
                        <div class="cx-cmd-compact-hint">Clique para ver detalhes</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
 
        <?php if (!empty($historico)): ?>
        <div class="cx-section-title" style="margin-top:8px;">Historico Recente de Comandas</div>
        <table class="cx-hist-table">
            <thead><tr><th>Comanda</th><th>Mesa</th><th>Lancado por</th><th>Itens</th><th>Total</th><th>Status</th><th>Fechado em</th></tr></thead>
            <tbody>
                <?php foreach ($historico as $h): ?>
                <tr class="cx-hist-row" onclick="abrirDetalheHistorico(<?= $h['id_comanda'] ?>)" title="Clique para ver detalhes">
                    <td><strong>#<?= htmlspecialchars($h['numero_comanda']) ?></strong><div style="font-size:10px;color:#94a3b8;margin-top:2px;"><?= date('d/m/Y H:i',strtotime($h['criado_em'])) ?></div></td>
                    <td><?php if (!empty($h['observacao'])): ?><span style="background:#dbeafe;color:#1e40af;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;"><?= htmlspecialchars($h['observacao']) ?></span><?php else: ?>—<?php endif; ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($h['lancado_por']??'—') ?></td>
                    <td><?= $h['total_itens'] ?></td>
                    <td><strong>R$ <?= number_format($h['valor_total'],2,',','.') ?></strong></td>
                    <td><span class="cx-badge-<?= $h['status'] ?>"><?= ucfirst($h['status']) ?></span></td>
                    <td><?= $h['fechado_em']?date('d/m H:i',strtotime($h['fechado_em'])):'—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
 
    <!-- ABA: CAIXA -->
    <div class="cx-tab-panel" id="tab-caixa">
        <div class="cx-caixa-status-bar">
            <?php if ($sessao_atual): ?>
            <div class="cx-status-chip aberto">
                <div class="cx-status-dot verde"></div>
                Caixa Aberto — por <?= htmlspecialchars($sessao_atual['aberto_por']) ?>
                desde <?= date('d/m H:i', strtotime($sessao_atual['aberto_em'])) ?>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="cx-btn-imprimir" onclick="abrirModalMovimentacao()" style="color:var(--primary,#2563eb);border-color:var(--primary,#2563eb);">+ Movimentacao</button>
                <button type="button" class="cx-btn-imprimir" onclick="abrirModalFecharCaixa()" style="color:#059669;border-color:#059669;">Fechar Caixa</button>
            </div>
            <?php else: ?>
            <div class="cx-status-chip sem-caixa">
                <div class="cx-status-dot amarelo"></div>
                Caixa fechado — Abra o caixa para comecar
            </div>
            <button type="button" class="cx-btn-imprimir" onclick="abrirModalAbrirCaixa()" style="color:#059669;border-color:#059669;padding:10px 22px;">Abrir Caixa</button>
            <?php endif; ?>
        </div>
 
        <?php if ($sessao_atual && $dados_sessao): ?>
        <div class="cx-resumo-grid">
            <div class="cx-resumo-card"><div class="cx-resumo-label">Troco inicial</div><div class="cx-resumo-val azul">R$ <?= number_format($sessao_atual['valor_inicial'],2,',','.') ?></div></div>
            <div class="cx-resumo-card"><div class="cx-resumo-label">Total vendido</div><div class="cx-resumo-val verde">R$ <?= number_format($dados_sessao['total_vendido'],2,',','.') ?></div><div class="cx-resumo-sub"><?= $dados_sessao['totais_vendas']['qtd'] ?> pedido(s)</div></div>
            <div class="cx-resumo-card"><div class="cx-resumo-label">Suprimentos</div><div class="cx-resumo-val verde">R$ <?= number_format($dados_sessao['total_suprimentos'],2,',','.') ?></div></div>
            <div class="cx-resumo-card"><div class="cx-resumo-label">Saidas (sangria + despesa)</div><div class="cx-resumo-val vermelho">R$ <?= number_format($dados_sessao['total_saidas'],2,',','.') ?></div></div>
            <div class="cx-resumo-card" style="border-color:var(--primary,#2563eb);border-width:2px;"><div class="cx-resumo-label">Saldo esperado em caixa</div><div class="cx-resumo-val azul">R$ <?= number_format($dados_sessao['saldo_esperado'],2,',','.') ?></div><div class="cx-resumo-sub">inicial + suprimentos + vendas - saidas</div></div>
            <?php if ($dados_sessao['totais_vendas']['qtd'] > 0): ?>
            <div class="cx-resumo-card"><div class="cx-resumo-label">Ticket medio</div><div class="cx-resumo-val">R$ <?= number_format($dados_sessao['total_vendido'] / max(1,$dados_sessao['totais_vendas']['qtd']),2,',','.') ?></div></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($dados_sessao['vendas_pagamento'])): ?>
        <div class="cx-section-title">Por Forma de Pagamento</div>
        <div class="cx-pag-lista">
            <?php foreach ($dados_sessao['vendas_pagamento'] as $vp): ?>
            <div class="cx-pag-card"><div class="cx-pag-label"><?= htmlspecialchars($vp['forma_pagamento']) ?></div><div class="cx-pag-val">R$ <?= number_format($vp['total'],2,',','.') ?></div><div class="cx-pag-qtd"><?= $vp['qtd'] ?> pedido(s)</div></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="cx-section-title">Movimentacoes da Sessao</div>
        <div class="cx-mov-lista">
            <div class="cx-mov-header"><span>Historico de movimentacoes</span><span style="color:#0f172a;"><?= count($dados_sessao['movimentacoes']) ?> registro(s)</span></div>
            <?php if (empty($dados_sessao['movimentacoes'])): ?><div class="cx-mov-vazio">Nenhuma movimentacao nesta sessao.</div><?php else: ?>
            <?php foreach ($dados_sessao['movimentacoes'] as $m): ?>
            <div class="cx-mov-item">
                <span class="cx-mov-tipo <?= $m['tipo'] ?>"><?= ucfirst($m['tipo']) ?></span>
                <div class="cx-mov-desc"><?= htmlspecialchars($m['descricao'] ?: '—') ?><div style="font-size:11px;color:#94a3b8;margin-top:1px;">por <?= htmlspecialchars($m['operador'] ?: '—') ?></div></div>
                <span class="cx-mov-hora"><?= date('H:i', strtotime($m['criado_em'])) ?></span>
                <span class="cx-mov-val <?= $m['tipo']==='suprimento'?'entrada':'saida' ?>"><?= $m['tipo']==='suprimento'?'+':'-' ?>R$ <?= number_format($m['valor'],2,',','.') ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="cx-vazio"><div class="cx-vazio-txt">Caixa nao aberto</div><div class="cx-vazio-sub">Abra o caixa para visualizar o resumo</div></div>
        <?php endif; ?>
    </div>
 
    <!-- ABA: RELATORIO -->
    <div class="cx-tab-panel" id="tab-relatorio">
        <?php if ($sessao_atual && $dados_sessao): ?>
        <div class="cx-resumo-grid" style="margin-bottom:24px;">
            <div class="cx-resumo-card"><div class="cx-resumo-label">Total vendido hoje</div><div class="cx-resumo-val verde">R$ <?= number_format($dados_sessao['total_vendido'],2,',','.') ?></div></div>
            <div class="cx-resumo-card"><div class="cx-resumo-label">Qtd de pedidos</div><div class="cx-resumo-val azul"><?= $dados_sessao['totais_vendas']['qtd'] ?></div></div>
            <div class="cx-resumo-card"><div class="cx-resumo-label">Ticket medio</div><div class="cx-resumo-val">R$ <?= number_format($dados_sessao['total_vendido'] / max(1,$dados_sessao['totais_vendas']['qtd']),2,',','.') ?></div></div>
            <div class="cx-resumo-card"><div class="cx-resumo-label">Comandas abertas</div><div class="cx-resumo-val"><?= count($comandas_abertas) ?></div></div>
        </div>
        <div class="cx-section-title">Vendas por Forma de Pagamento</div>
        <?php if (!empty($dados_sessao['vendas_pagamento'])): ?>
        <?php $total_geral_pag = array_sum(array_column($dados_sessao['vendas_pagamento'], 'total')); ?>
        <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;overflow:hidden;margin-bottom:24px;">
            <?php foreach ($dados_sessao['vendas_pagamento'] as $vp):
                $pct = $total_geral_pag > 0 ? ($vp['total'] / $total_geral_pag) * 100 : 0;
            ?>
            <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:14px;">
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($vp['forma_pagamento']) ?></div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;"><?= $vp['qtd'] ?> pedido(s) · <?= number_format($pct,1) ?>%</div>
                    <div style="margin-top:6px;height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;"><div style="width:<?= $pct ?>%;height:100%;background:var(--primary,#2563eb);border-radius:3px;"></div></div>
                </div>
                <div style="font-size:16px;font-weight:800;color:#059669;white-space:nowrap;">R$ <?= number_format($vp['total'],2,',','.') ?></div>
            </div>
            <?php endforeach; ?>
            <div style="padding:14px 18px;display:flex;justify-content:space-between;font-size:14px;font-weight:800;"><span>Total</span><span style="color:#059669;">R$ <?= number_format($total_geral_pag,2,',','.') ?></span></div>
        </div>
        <?php else: ?>
        <div class="cx-vazio"><div class="cx-vazio-txt">Sem vendas nesta sessao</div></div>
        <?php endif; ?>
        <div class="cx-section-title">Resumo Financeiro da Sessao</div>
        <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;overflow:hidden;margin-bottom:24px;">
            <?php $linhas = [['Troco inicial',$sessao_atual['valor_inicial'],'+','#059669'],['Suprimentos',$dados_sessao['total_suprimentos'],'+','#059669'],['Vendas realizadas',$dados_sessao['total_vendido'],'+','#059669'],['Sangrias',$dados_sessao['total_sangrias'],'-','#dc2626'],['Despesas',$dados_sessao['total_despesas'],'-','#dc2626']]; ?>
            <?php foreach ($linhas as [$label,$valor,$sinal,$cor]): ?>
            <div style="padding:12px 18px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;font-size:13px;"><span><?= $label ?></span><span style="font-weight:700;color:<?= $cor ?>;"><?= $sinal ?> R$ <?= number_format($valor,2,',','.') ?></span></div>
            <?php endforeach; ?>
            <div style="padding:14px 18px;display:flex;justify-content:space-between;font-size:15px;font-weight:800;background:#f8fafc;"><span>Saldo esperado em caixa</span><span style="color:var(--primary,#2563eb);">R$ <?= number_format($dados_sessao['saldo_esperado'],2,',','.') ?></span></div>
        </div>
        <?php else: ?>
        <div class="cx-vazio"><div class="cx-vazio-txt">Nenhuma sessao aberta</div><div class="cx-vazio-sub">Abra o caixa para ver o relatorio</div></div>
        <?php endif; ?>
    </div>
 
    <!-- ABA: HISTORICO SESSOES -->
    <div class="cx-tab-panel" id="tab-historico_sess">
        <div class="cx-section-title">Historico de Sessoes de Caixa</div>
        <?php if (empty($historico_sessoes)): ?>
        <div class="cx-vazio"><div class="cx-vazio-txt">Nenhuma sessao registrada ainda</div></div>
        <?php else: ?>
        <table class="cx-sess-table">
            <thead><tr><th>Operador</th><th>Abertura</th><th>Fechamento</th><th>Val. Inicial</th><th>Val. Contado</th><th>Diferenca</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($historico_sessoes as $s): ?>
                <tr class="clicavel" onclick="abrirModalSessao(<?= htmlspecialchars(json_encode($s)) ?>)">
                    <td><strong><?= htmlspecialchars($s['aberto_por']) ?></strong><?php if ($s['fechado_por']): ?><div style="font-size:10px;color:#94a3b8;">Fechou: <?= htmlspecialchars($s['fechado_por']) ?></div><?php endif; ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($s['aberto_em'])) ?></td>
                    <td><?= $s['fechado_em'] ? date('d/m/Y H:i', strtotime($s['fechado_em'])) : '—' ?></td>
                    <td>R$ <?= number_format($s['valor_inicial'],2,',','.') ?></td>
                    <td><?= $s['valor_contado'] !== null ? 'R$ '.number_format($s['valor_contado'],2,',','.') : '—' ?></td>
                    <td><?php if ($s['diferenca'] !== null): $dif=floatval($s['diferenca']);$cor=$dif>0?'#059669':($dif<0?'#dc2626':'#64748b');$sinal=$dif>0?'+':''; ?><strong style="color:<?= $cor ?>;"><?= $sinal ?>R$ <?= number_format($dif,2,',','.') ?></strong><?php else: ?>—<?php endif; ?></td>
                    <td><?= $s['status']==='aberto'?'<span class="cx-badge-aberto">Aberto</span>':'<span class="cx-badge-fechado">Fechado</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
 
</div><!-- /cx-page -->
<!-- MODAL: Abrir Caixa -->
<div class="cx-modal-overlay" id="cx-modal-abrir-caixa">
    <div class="cx-modal">
        <h3>Abrir Caixa</h3>
        <div class="cx-modal-sub">Informe o valor inicial (troco) para comecar o dia</div>
        <form method="POST">
            <input type="hidden" name="acao" value="abrir_caixa">
            <div class="cx-field"><label>Valor inicial (troco) *</label><input type="text" inputmode="numeric" name="valor_inicial" placeholder="0,00" autocomplete="off" required oninput="mascaraMoeda(this)"></div>
            <div class="cx-field"><label>Operador</label><input type="text" value="<?= htmlspecialchars($nome_caixa) ?>" disabled style="background:#f8fafc;color:#64748b;"></div>
            <div class="cx-field"><label>Observacao (opcional)</label><textarea name="obs_abertura" placeholder="Ex: inicio do turno..."></textarea></div>
            <div class="cx-modal-btns">
                <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalAbrirCaixa()">Cancelar</button>
                <button type="submit" class="cx-btn-confirmar">Abrir Caixa</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Movimentacao -->
<div class="cx-modal-overlay" id="cx-modal-mov">
    <div class="cx-modal">
        <h3>Registrar Movimentacao</h3>
        <div class="cx-modal-sub">Sangria, suprimento ou despesa</div>
        <form method="POST">
            <input type="hidden" name="acao" value="movimentacao">
            <input type="hidden" name="id_sessao" value="<?= $sessao_atual['id_sessao'] ?? 0 ?>">
            <div class="cx-field">
                <label>Tipo *</label>
                <div style="display:flex;gap:8px;">
                    <?php foreach (['sangria'=>'Sangria','suprimento'=>'Suprimento','despesa'=>'Despesa'] as $val=>$label): ?>
                    <label style="flex:1;display:flex;align-items:center;gap:6px;padding:10px;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;font-family:'Sora',sans-serif;transition:.15s;" id="lbl-mov-<?= $val ?>">
                        <input type="radio" name="tipo_mov" value="<?= $val ?>" onchange="destacarTipoMov()" style="accent-color:var(--primary,#2563eb);"><?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cx-field"><label>Descricao</label><input type="text" name="descricao_mov" placeholder="Ex: motoboy, gelo..."></div>
           <div class="cx-field"><label>Valor *</label><input type="text" name="valor_mov" inputmode="numeric" placeholder="0,00" autocomplete="off" required oninput="mascaraMoeda(this)"></div>
            <div class="cx-modal-btns">
                <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalMov()">Cancelar</button>
                <button type="submit" class="cx-btn-confirmar">Registrar</button>
            </div>
        </form>
    </div>
</div>
<!-- MODAL: Fechar Caixa -->
<div class="cx-modal-overlay" id="cx-modal-fechar-caixa">
    <div class="cx-modal" style="max-width:500px;">
        <h3>Fechar Caixa</h3>
        <div class="cx-modal-sub">Confira os valores antes de fechar</div>
        <?php if ($sessao_atual && $dados_sessao): ?>
        <div style="background:#f8fafc;border-radius:12px;padding:14px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid #f1f5f9;"><span>Total vendido (sistema)</span><span style="font-weight:700;color:#059669;">R$ <?= number_format($dados_sessao['total_vendido'],2,',','.') ?></span></div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid #f1f5f9;"><span>Suprimentos</span><span style="font-weight:700;color:#059669;">+ R$ <?= number_format($dados_sessao['total_suprimentos'],2,',','.') ?></span></div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid #f1f5f9;"><span>Saidas</span><span style="font-weight:700;color:#dc2626;">- R$ <?= number_format($dados_sessao['total_saidas'],2,',','.') ?></span></div>
            <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:800;padding-top:8px;"><span>Saldo esperado</span><span style="color:var(--primary,#2563eb);">R$ <?= number_format($dados_sessao['saldo_esperado'],2,',','.') ?></span></div>
        </div>
        <form method="POST">
            <input type="hidden" name="acao" value="fechar_caixa">
            <input type="hidden" name="id_sessao" value="<?= $sessao_atual['id_sessao'] ?>">
            <div class="cx-field">
                <label>Valor contado (dinheiro fisico) *</label>
                <input type="text" inputmode="numeric" name="valor_contado" id="in-val-contado" placeholder="0,00" autocomplete="off" required oninput="mascaraMoeda(this);calcDiferenca()">
            </div>
            <div id="cx-diferenca-preview" style="display:none;" class="cx-diferenca-box zerou">
                <span class="cx-diferenca-label" id="cx-dif-label">Sem diferenca</span>
                <span class="cx-diferenca-val" id="cx-dif-val">R$ 0,00</span>
            </div>
            <div class="cx-field">
                <label>Justificativa de diferenca (se houver)</label>
                <textarea name="justificativa" placeholder="Ex: troco dado, pagamento incompleto..."></textarea>
            </div>
            <div class="cx-modal-btns">
                <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalFecharCaixa()">Cancelar</button>
                <button type="submit" class="cx-btn-confirmar" style="background:linear-gradient(135deg,#059669,#047857);">Confirmar Fechamento</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<!-- MODAL: Reabrir Caixa -->
<div class="cx-modal-overlay" id="cx-modal-reabrir">
    <div class="cx-modal" style="max-width:380px;">
        <h3>Reabrir Sessao</h3>
        <div class="cx-modal-sub">Informe a senha do administrador para cancelar o fechamento.</div>
        <form method="POST">
            <input type="hidden" name="acao" value="reabrir_caixa">
            <input type="hidden" name="id_sessao_reabrir" id="hid-reabrir-sessao">
            <div class="cx-field"><label>Senha do Administrador *</label><input type="password" name="senha_reabrir" placeholder="Senha" required autocomplete="off"></div>
            <div class="cx-modal-btns">
                <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalReabrir()">Cancelar</button>
                <button type="submit" class="cx-btn-danger-modal">Confirmar Reabertura</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Detalhe sessao historico -->
<div class="cx-modal-overlay" id="cx-modal-sessao">
    <div class="cx-modal cx-sess-modal">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h3 id="cx-sess-titulo">Sessao</h3>
            <span id="cx-sess-status-badge"></span>
        </div>
        <div class="cx-hist-meta-block" id="cx-sess-meta"></div>
        <div id="cx-sess-diferenca" style="margin-bottom:14px;"></div>
        <div id="cx-sess-resumo" style="margin-bottom:14px;"></div>
        <div id="cx-sess-reabrir-wrap" style="display:none;margin-bottom:14px;">
            <button type="button" onclick="abrirModalReabrir()" style="padding:10px 18px;background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;width:100%;">Cancelar Fechamento (Reabrir)</button>
        </div>
        <div class="cx-modal-btns" style="gap:8px;">
            <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalSessao()">Fechar</button>
            <button type="button" id="cx-sess-btn-imprimir" onclick="imprimirRelatorioSessao()" style="flex:1;padding:13px;background:linear-gradient(135deg,#0369a1,#0284c7);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;display:none;">Imprimir Relatorio</button>
        </div>
    </div>
</div>

<!-- MODAL: Fechar comanda (pagamento) -->
<div class="cx-modal-overlay" id="cx-modal-pag">
    <div class="cx-modal">
        <h3>Fechar Comanda</h3>
        <div class="cx-modal-sub" id="cx-modal-sub">Comanda #—</div>
        <div class="cx-modal-itens" id="cx-modal-itens"></div>
        <div class="cx-modal-total"><span>Total</span><span id="cx-modal-total">R$ —</span></div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:10px;">Forma de Pagamento *</div>
        <div class="cx-pag-grid">
            <div class="cx-pag-pill"><input type="radio" name="cx_pag" id="cx-pix"      value="PIX"><label for="cx-pix">PIX</label></div>
            <div class="cx-pag-pill"><input type="radio" name="cx_pag" id="cx-credito"  value="Cartao de Credito"><label for="cx-credito">Credito</label></div>
            <div class="cx-pag-pill"><input type="radio" name="cx_pag" id="cx-debito"   value="Cartao de Debito"><label for="cx-debito">Debito</label></div>
            <div class="cx-pag-pill"><input type="radio" name="cx_pag" id="cx-dinheiro" value="Dinheiro"><label for="cx-dinheiro">Dinheiro</label></div>
        </div>
        <div class="cx-modal-btns">
            <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalPag()">Cancelar</button>
            <button type="button" class="cx-btn-confirmar"    onclick="confirmarFechamento()">Confirmar e Baixar Estoque</button>
        </div>
    </div>
</div>

<!-- MODAL: Produtos (adicionar / nova comanda) -->
<div class="cx-modal-overlay" id="cx-modal-prod">
    <div class="cx-modal cx-prod-modal">
        <div class="cx-prod-modal-header">
            <h3 id="cx-prod-modal-titulo">Adicionar Item</h3>
            <button type="button" onclick="fecharModalProd()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;">&#10005;</button>
        </div>
        <div id="cx-nova-comanda-fields" style="display:none;">
            <div style="display:flex;gap:10px;margin-bottom:12px;">
                <div class="cx-field" style="flex:0 0 140px;margin-bottom:0;"><label>Numero da comanda *</label><input type="text" id="cx-nova-num" placeholder="Ex: 42" maxlength="20" autocomplete="off"></div>
                <div class="cx-field" style="flex:1;margin-bottom:0;"><label>Mesa</label><input type="text" id="cx-nova-obs" placeholder="Ex: 20" oninput="autoPreencherMesa(this)" onblur="autoPreencherMesa(this)"></div>
            </div>
        </div>
        <div class="cx-prod-modal-tabs">
            <button class="cx-prod-modal-tab ativa" id="tab-btn-produtos" onclick="trocarTabModal('produtos')">Produtos</button>
            <button class="cx-prod-modal-tab" id="tab-btn-avulsos" onclick="trocarTabModal('avulsos')">Adicionais Avulsos</button>
        </div>
        <div id="cx-painel-produtos">
            <div class="cx-cats-scroll" id="cx-cats-prod">
                <button class="cx-cat-pill ativa" data-id="0" onclick="carregarCatProd(0,this)">Todos</button>
                <?php foreach ($categorias as $cat): ?>
                <button class="cx-cat-pill" data-id="<?= $cat['id_categoria'] ?>" onclick="carregarCatProd(<?= $cat['id_categoria'] ?>,this)"><?= htmlspecialchars($cat['nome']) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="cx-prod-grid-modal" id="cx-prod-grid-modal"><div style="grid-column:1/-1;text-align:center;color:#94a3b8;font-size:13px;padding:16px;">Carregando...</div></div>
        </div>
        <div id="cx-painel-avulsos" style="display:none;">
            <div style="font-size:12px;color:#64748b;margin-bottom:10px;padding:7px 11px;background:#fffbeb;border-radius:9px;border-left:3px solid #f59e0b;">Lance adicionais diretamente na comanda, sem produto base.</div>
            <div class="cx-avul-busca-wrap">
                <div class="cx-avul-busca-icon">Buscar</div>
                <input type="text" class="cx-avul-busca" id="cx-avul-busca" placeholder="Buscar adicional..." oninput="buscarAvulsoModal(this.value)">
            </div>
            <div class="cx-avul-grid-modal" id="cx-avul-grid-modal"><div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Carregando...</div></div>
        </div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:6px;">Itens selecionados</div>
        <div class="cx-carrinho" id="cx-carrinho-prod"><div class="cx-carr-vazio">Nenhum produto adicionado.</div></div>
        <div class="cx-modal-btns">
            <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalProd()">Cancelar</button>
            <button type="button" class="cx-btn-confirmar" onclick="confirmarProdutos()" id="cx-btn-confirmar-prod">Adicionar</button>
        </div>
    </div>
</div>

<!-- MODAL: Item produto -->
<div class="cx-modal-overlay" id="cx-modal-item">
    <div class="cx-modal cx-item-modal">
        <h3 id="cx-item-nome">Produto</h3>
        <div class="cx-modal-sub" id="cx-item-preco"></div>
        <div id="cx-item-adicionais"></div>
        <div class="cx-qty-row">
            <span style="font-size:13px;font-weight:700;">Quantidade</span>
            <div class="cx-qty-ctrl">
                <button type="button" class="cx-qty-btn" onclick="itemQty(-1)">&#8722;</button>
                <input type="number" class="cx-qty-num" id="cx-item-qty" value="1" min="1">
                <button type="button" class="cx-qty-btn" onclick="itemQty(+1)">+</button>
            </div>
        </div>
        <div class="cx-item-obs-field">
            <label>Observacao do item <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></label>
            <textarea id="cx-item-obs" placeholder="Ex: bem passado, sem molho..."></textarea>
        </div>
        <div class="cx-modal-btns" style="margin-top:14px;">
            <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalItem()">Cancelar</button>
            <button type="button" class="cx-btn-confirmar" onclick="confirmarItem()">+ Adicionar</button>
        </div>
    </div>
</div>

<!-- MODAL: Adicional avulso -->
<div class="cx-modal-overlay" id="cx-modal-avulso">
    <div class="cx-modal cx-avul-modal">
        <div style="display:inline-block;background:#fef3c7;color:#92400e;font-size:10px;font-weight:800;padding:3px 9px;border-radius:20px;margin-bottom:8px;letter-spacing:.4px;">ADICIONAL AVULSO</div>
        <h3 id="cx-avul-nome">Adicional</h3>
        <div class="cx-modal-sub" id="cx-avul-preco"></div>
        <div class="cx-qty-row">
            <span style="font-size:13px;font-weight:700;">Quantidade</span>
            <div class="cx-qty-ctrl">
                <button type="button" class="cx-qty-btn" onclick="avulQty(-1)">&#8722;</button>
                <input type="number" class="cx-qty-num" id="cx-avul-qty" value="1" min="1">
                <button type="button" class="cx-qty-btn" onclick="avulQty(+1)">+</button>
            </div>
        </div>
        <div class="cx-item-obs-field">
            <label>Observacao <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></label>
            <textarea id="cx-avul-obs" placeholder="Ex: bem passado, sem sal..."></textarea>
        </div>
        <div class="cx-modal-btns" style="margin-top:14px;">
            <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalAvulso()">Cancelar</button>
            <button type="button" class="cx-btn-confirmar" onclick="confirmarAvulso()">+ Adicionar</button>
        </div>
    </div>
</div>

<!-- MODAL: Historico comanda -->
<div class="cx-modal-overlay" id="cx-modal-hist">
    <div class="cx-modal cx-hist-modal">
        <div class="cx-hist-modal-header">
            <h3 id="cx-hist-titulo">Comanda #—</h3>
            <span class="cx-hist-status-badge" id="cx-hist-status-badge"></span>
        </div>
        <div style="font-size:13px;color:#64748b;margin-bottom:14px;" id="cx-hist-sub"></div>
        <div class="cx-hist-meta-block" id="cx-hist-meta"></div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:8px;">Itens</div>
        <div class="cx-hist-itens-wrap" id="cx-hist-itens"><div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Carregando...</div></div>
        <div class="cx-hist-total-row"><span>Total</span><span id="cx-hist-total" style="color:#059669;">R$ —</span></div>
        <div class="cx-modal-btns" style="margin-top:14px;"><button type="button" class="cx-btn-fechar-modal" onclick="fecharModalHist()" style="width:100%;">Fechar</button></div>
    </div>
</div>

<!-- MODAL: Senha de cancelamento -->
<div class="cx-modal-overlay" id="cx-modal-senha-cancel">
    <div class="cx-modal" style="max-width:360px;">
        <h3>Confirmar Cancelamento</h3>
        <div class="cx-modal-sub" id="cx-senha-cancel-desc">Informe a senha do administrador</div>
        <div class="cx-field">
            <label>Senha *</label>
            <input type="password" id="cx-input-senha-cancel" placeholder="Digite a senha"
                   inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                   onkeydown="if(event.key==='Enter')confirmarSenhaCancel()">
        </div>
        <div id="cx-senha-cancel-erro" style="display:none;color:#dc2626;font-size:12px;font-weight:600;margin-bottom:10px;">
            Senha incorreta. Tente novamente.
        </div>
        <div class="cx-modal-btns">
            <button type="button" class="cx-btn-fechar-modal" onclick="fecharModalSenhaCancel()">Cancelar</button>
            <button type="button" class="cx-btn-danger-modal" onclick="confirmarSenhaCancel()">Confirmar</button>
        </div>
    </div>
</div>

<!-- Forms ocultos -->
<form method="POST" id="cx-form-fechar"    style="display:none;"><input type="hidden" name="acao" value="fechar"><input type="hidden" name="id_comanda" id="hid-cmd"><input type="hidden" name="forma_pagamento" id="hid-pag"></form>
<form method="POST" id="cx-form-cancelar"  style="display:none;"><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id_comanda" id="hid-cmd-cancelar"></form>
<form method="POST" id="cx-form-add-itens" style="display:none;"><input type="hidden" name="acao" value="adicionar_itens"><input type="hidden" name="id_comanda" id="hid-cmd-add"><input type="hidden" name="itens_json" id="hid-itens-add"></form>
<form method="POST" id="cx-form-nova"      style="display:none;"><input type="hidden" name="acao" value="nova_comanda"><input type="hidden" name="numero_comanda" id="hid-nova-num"><input type="hidden" name="observacao_cmd" id="hid-nova-obs"><input type="hidden" name="itens_json" id="hid-nova-itens"></form>

<?php if ($comprovante): ?>
<script>window.addEventListener('load',function(){setTimeout(function(){if(confirm('Comanda fechada! Deseja imprimir o comprovante?'))imprimirComprovante();},300);});</script>
<?php endif; ?>

<script>
    function mascaraMoeda(el) {
    let v = el.value.replace(/\D/g, '');
    v = (parseInt(v || '0') / 100).toFixed(2);
    el.value = v.replace('.', ',');
}
/* ─── ESTADO GLOBAL ─── */
let cmdAtualId=null, cmdAtualNum=null, cmdAtualTotal=null;
let modoModal=null, carrinhoModal=[], produtoAtual=null, adicionaisSel={};
let sessaoReabrirId=null, sessaoDetalheCompleto=null;
let avulAtual=null, avulDebTimer;
let itemParaRemover_id=null, itemParaRemover_nome=null;
let cancelCallback=null;
const saldoEsperado=<?= $dados_sessao ? floatval($dados_sessao['saldo_esperado']) : 0 ?>;

function fmt(n){return parseFloat(n).toLocaleString('pt-BR',{minimumFractionDigits:2});}
function precoEfetivo(p){return(p.preco_promocional&&parseFloat(p.preco_promocional)>0)?parseFloat(p.preco_promocional):parseFloat(p.preco);}

/* ─── ABAS PRINCIPAIS ─── */
function trocarTab(id,btn){
    document.querySelectorAll('.cx-tab-btn').forEach(b=>b.classList.remove('ativa'));
    document.querySelectorAll('.cx-tab-panel').forEach(p=>p.classList.remove('ativo'));
    btn.classList.add('ativa');
    document.getElementById('tab-'+id).classList.add('ativo');
}

/* ─── ABAS MODAL PRODUTOS ─── */
function trocarTabModal(tab){
    document.getElementById('tab-btn-produtos').classList.toggle('ativa', tab==='produtos');
    document.getElementById('tab-btn-avulsos').classList.toggle('ativa', tab==='avulsos');
    document.getElementById('cx-painel-produtos').style.display = tab==='produtos' ? 'block' : 'none';
    document.getElementById('cx-painel-avulsos').style.display  = tab==='avulsos'  ? 'block' : 'none';
    if(tab==='avulsos'){ const b=document.getElementById('cx-avul-busca'); if(b)b.value=''; carregarAvulsoModal(''); }
}

/* ─── MODAIS CAIXA ─── */
function abrirModalAbrirCaixa(){ document.getElementById('cx-modal-abrir-caixa').classList.add('show'); }
function fecharModalAbrirCaixa(){ document.getElementById('cx-modal-abrir-caixa').classList.remove('show'); }
document.getElementById('cx-modal-abrir-caixa').addEventListener('click',function(e){if(e.target===this)fecharModalAbrirCaixa();});

function abrirModalMovimentacao(){ document.getElementById('cx-modal-mov').classList.add('show'); }
function fecharModalMov(){ document.getElementById('cx-modal-mov').classList.remove('show'); }
document.getElementById('cx-modal-mov').addEventListener('click',function(e){if(e.target===this)fecharModalMov();});
function destacarTipoMov(){
    ['sangria','suprimento','despesa'].forEach(t=>{
        const r=document.querySelector('input[name="tipo_mov"][value="'+t+'"]');
        const l=document.getElementById('lbl-mov-'+t);
        if(r&&l) l.style.borderColor=r.checked?'var(--primary,#2563eb)':'#e2e8f0';
    });
}

function abrirModalFecharCaixa(){ document.getElementById('cx-modal-fechar-caixa').classList.add('show'); }
function fecharModalFecharCaixa(){ document.getElementById('cx-modal-fechar-caixa').classList.remove('show'); }
document.getElementById('cx-modal-fechar-caixa').addEventListener('click',function(e){if(e.target===this)fecharModalFecharCaixa();});

function calcDiferenca(){
    const raw=(document.getElementById('in-val-contado').value||'0').replace(/\./g,'').replace(',','.');
const contado=parseFloat(raw)||0;
    const dif=contado-saldoEsperado;
    const box=document.getElementById('cx-diferenca-preview');
    box.style.display='flex';
    box.className='cx-diferenca-box '+(Math.abs(dif)<0.01?'zerou':dif>0?'sobrou':'faltou');
    document.getElementById('cx-dif-label').textContent=Math.abs(dif)<0.01?'Sem diferenca':dif>0?'Sobrou no caixa':'Faltou no caixa';
    const val=document.getElementById('cx-dif-val');
    val.textContent=(dif>=0?'+':'')+'R$ '+fmt(dif);
    val.style.color=Math.abs(dif)<0.01?'#64748b':dif>0?'#059669':'#dc2626';
}

function abrirModalReabrir(){ fecharModalSessao(); document.getElementById('hid-reabrir-sessao').value=sessaoReabrirId; document.getElementById('cx-modal-reabrir').classList.add('show'); }
function fecharModalReabrir(){ document.getElementById('cx-modal-reabrir').classList.remove('show'); }
document.getElementById('cx-modal-reabrir').addEventListener('click',function(e){if(e.target===this)fecharModalReabrir();});

/* ─── MODAL DETALHE SESSAO ─── */
async function abrirModalSessao(s){
    sessaoReabrirId=s.id_sessao; sessaoDetalheCompleto=null;
    document.getElementById('cx-sess-titulo').textContent='Sessao #'+s.id_sessao;
    document.getElementById('cx-sess-resumo').innerHTML='<div style="text-align:center;color:#94a3b8;font-size:13px;padding:8px 0;">Carregando dados...</div>';
    document.getElementById('cx-sess-btn-imprimir').style.display='none';
    const badge=document.getElementById('cx-sess-status-badge');
    badge.textContent=s.status==='aberto'?'Aberto':'Fechado';
    badge.className='cx-hist-status-badge '+(s.status==='aberto'?'fechada':'cancelada');
    const abertura=new Date(s.aberto_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const fechamento=s.fechado_em?new Date(s.fechado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—';
    let meta=`<div class="mi"><strong>Aberto por</strong>${s.aberto_por||'—'}</div>`;
    meta+=`<div class="mi"><strong>Abertura</strong>${abertura}</div>`;
    if(s.fechado_por) meta+=`<div class="mi"><strong>Fechado por</strong>${s.fechado_por}</div>`;
    meta+=`<div class="mi"><strong>Fechamento</strong>${fechamento}</div>`;
    meta+=`<div class="mi"><strong>Valor inicial</strong>R$ ${fmt(s.valor_inicial)}</div>`;
    if(s.valor_contado!==null&&s.valor_contado!==undefined) meta+=`<div class="mi"><strong>Valor contado</strong>R$ ${fmt(s.valor_contado)}</div>`;
    document.getElementById('cx-sess-meta').innerHTML=meta;
    const difBox=document.getElementById('cx-sess-diferenca');
    if(s.diferenca!==null&&s.diferenca!==undefined){
        const dif=parseFloat(s.diferenca);
        const cls=Math.abs(dif)<0.01?'zerou':dif>0?'sobrou':'faltou';
        const txt=Math.abs(dif)<0.01?'Sem diferenca':dif>0?'Sobrou':'Faltou';
        difBox.innerHTML=`<div class="cx-diferenca-box ${cls}"><div><div class="cx-diferenca-label">${txt}</div>${s.justificativa?`<div style="font-size:11px;color:#64748b;margin-top:3px;">${s.justificativa}</div>`:''}</div><span class="cx-diferenca-val" style="color:${Math.abs(dif)<0.01?'#64748b':dif>0?'#059669':'#dc2626'};">${dif>=0?'+':''}R$ ${fmt(dif)}</span></div>`;
    } else { difBox.innerHTML=''; }
    document.getElementById('cx-sess-reabrir-wrap').style.display=s.status==='fechado'?'block':'none';
    document.getElementById('cx-modal-sessao').classList.add('show');
    try {
        const res=await fetch('caixa.php?ajax_sessao=1&id_sessao='+s.id_sessao);
        const dados=await res.json();
        if(!dados||dados.erro){document.getElementById('cx-sess-resumo').innerHTML='';return;}
        sessaoDetalheCompleto=dados;
        let supri=0,sangr=0,desp=0;
        (dados.movimentacoes||[]).forEach(m=>{if(m.tipo==='suprimento')supri+=parseFloat(m.valor);if(m.tipo==='sangria')sangr+=parseFloat(m.valor);if(m.tipo==='despesa')desp+=parseFloat(m.valor);});
        const totalVendido=parseFloat(dados.total_vendido||0);
        const saldoEsp=parseFloat(dados.valor_inicial||s.valor_inicial)+supri+totalVendido-(sangr+desp);
        let html='<div style="background:#f8fafc;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0;margin-bottom:4px;">';
        html+='<div style="padding:8px 14px;background:#f1f5f9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;">Resumo Financeiro</div>';
        [['Valor inicial','+',fmt(s.valor_inicial),'#059669'],['Suprimentos','+',fmt(supri),'#059669'],['Vendas ('+dados.qtd_pedidos+' pedidos)','+',fmt(totalVendido),'#059669'],['Sangrias','-',fmt(sangr),'#dc2626'],['Despesas','-',fmt(desp),'#dc2626']].forEach(([l,sn,v,c])=>{html+=`<div style="display:flex;justify-content:space-between;padding:6px 14px;border-bottom:1px solid #f1f5f9;font-size:12px;"><span>${l}</span><span style="font-weight:700;color:${c};">${sn} R$ ${v}</span></div>`;});
        html+=`<div style="display:flex;justify-content:space-between;padding:8px 14px;font-size:13px;font-weight:800;"><span>Saldo esperado</span><span style="color:#2563eb;">R$ ${fmt(saldoEsp)}</span></div></div>`;
        if(dados.vendas_por_pagamento&&dados.vendas_por_pagamento.length){html+='<div style="background:#f8fafc;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0;">';html+='<div style="padding:8px 14px;background:#f1f5f9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;">Por Forma de Pagamento</div>';dados.vendas_por_pagamento.forEach(vp=>{html+=`<div style="display:flex;justify-content:space-between;padding:6px 14px;border-bottom:1px solid #f1f5f9;font-size:12px;"><span>${vp.forma_pagamento} <small style="color:#94a3b8;">(${vp.qtd}x)</small></span><span style="font-weight:700;color:#059669;">R$ ${fmt(vp.total)}</span></div>`;});html+='</div>';}
        document.getElementById('cx-sess-resumo').innerHTML=html;
        document.getElementById('cx-sess-btn-imprimir').style.display='block';
    } catch(e){document.getElementById('cx-sess-resumo').innerHTML='';}
}
function fecharModalSessao(){document.getElementById('cx-modal-sessao').classList.remove('show');sessaoDetalheCompleto=null;}
document.getElementById('cx-modal-sessao').addEventListener('click',function(e){if(e.target===this)fecharModalSessao();});

function imprimirRelatorioSessao(){
    const s=sessaoDetalheCompleto; if(!s) return;
    let supri=0,sangr=0,desp=0;
    (s.movimentacoes||[]).forEach(m=>{if(m.tipo==='suprimento')supri+=parseFloat(m.valor);if(m.tipo==='sangria')sangr+=parseFloat(m.valor);if(m.tipo==='despesa')desp+=parseFloat(m.valor);});
    const totalVendido=parseFloat(s.total_vendido||0), saldoEsp=parseFloat(s.valor_inicial)+supri+totalVendido-(sangr+desp);
    const dif=s.diferenca!==null&&s.diferenca!==undefined?parseFloat(s.diferenca):null;
    const abertura=new Date(s.aberto_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const fechamento=s.fechado_em?new Date(s.fechado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—';
    let mov_html='';
    if(s.movimentacoes&&s.movimentacoes.length){s.movimentacoes.forEach(m=>{const hora=new Date(m.criado_em).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});const sinal=m.tipo==='suprimento'?'+':'-';mov_html+=`<div style="display:flex;justify-content:space-between;font-size:10px;padding:2px 0;"><span>[${m.tipo.toUpperCase()}] ${m.descricao||'—'} (${hora})</span><span>${sinal} R$ ${fmt(m.valor)}</span></div>`;});}else{mov_html='<div style="font-size:10px;color:#888;">Nenhuma movimentacao.</div>';}
    let pag_html='';
    if(s.vendas_por_pagamento&&s.vendas_por_pagamento.length){s.vendas_por_pagamento.forEach(vp=>{pag_html+=`<div style="display:flex;justify-content:space-between;font-size:11px;padding:2px 0;"><span>${vp.forma_pagamento} (${vp.qtd}x)</span><span>R$ ${fmt(vp.total)}</span></div>`;});}else{pag_html='<div style="font-size:10px;color:#888;">Nenhuma venda.</div>';}
    const html=`<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:80mm auto;margin:0;}*{box-sizing:border-box;margin:0;padding:0;}body{font-family:'Courier New',monospace;font-size:11px;color:#000;background:#fff;padding:4mm;width:80mm;}.titulo{text-align:center;font-weight:bold;font-size:14px;margin-bottom:2px;}.sub{text-align:center;font-size:10px;margin-bottom:5px;}.linha{border-top:1px dashed #888;margin:5px 0;}.linha2{border-top:2px solid #000;margin:5px 0;}.row{display:flex;justify-content:space-between;padding:2px 0;font-size:11px;}.row-bold{display:flex;justify-content:space-between;padding:3px 0;font-size:12px;font-weight:bold;}.secao{font-weight:bold;font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:6px 0 3px;}</style></head><body><div class="titulo">RELATORIO DE CAIXA</div><div class="sub">Sessao #${s.id_sessao}</div><div class="linha2"></div><div class="row"><span>Operador</span><span>${s.aberto_por||'—'}</span></div><div class="row"><span>Abertura</span><span>${abertura}</span></div><div class="row"><span>Fechamento</span><span>${fechamento}</span></div>${s.fechado_por?`<div class="row"><span>Fechado por</span><span>${s.fechado_por}</span></div>`:''}<div class="linha"></div><div class="secao">Resumo Financeiro</div><div class="row"><span>Valor inicial</span><span>+ R$ ${fmt(s.valor_inicial)}</span></div><div class="row"><span>Suprimentos</span><span>+ R$ ${fmt(supri)}</span></div><div class="row"><span>Vendas (${s.qtd_pedidos} pedidos)</span><span>+ R$ ${fmt(totalVendido)}</span></div><div class="row"><span>Sangrias</span><span>- R$ ${fmt(sangr)}</span></div><div class="row"><span>Despesas</span><span>- R$ ${fmt(desp)}</span></div><div class="linha"></div><div class="row-bold"><span>Saldo esperado</span><span>R$ ${fmt(saldoEsp)}</span></div>${s.valor_contado!==null&&s.valor_contado!==undefined?`<div class="row-bold"><span>Valor contado</span><span>R$ ${fmt(s.valor_contado)}</span></div>`:''}${dif!==null?`<div class="row-bold"><span>Diferenca</span><span>${dif>=0?'+':''}R$ ${fmt(dif)}</span></div>`:''} ${s.justificativa?`<div style="font-size:10px;font-style:italic;margin-top:2px;">Obs: ${s.justificativa}</div>`:''}<div class="linha"></div><div class="secao">Vendas por Pagamento</div>${pag_html}<div class="linha"></div><div class="secao">Movimentacoes</div>${mov_html}<div class="linha2"></div><div style="text-align:center;font-size:9px;color:#666;margin-top:8px;">Documento sem valor fiscal</div></body></html>`;
    const janela=window.open('','_blank','width=420,height=700'); janela.document.write(html); janela.document.close(); janela.focus(); janela.onload=function(){janela.print();};
}

/* ─── ATUALIZACAO SILENCIOSA ─── */
let sessaoCaixaExpirada = false;
async function atualizarGrid(){
    if(sessaoCaixaExpirada) return;
    try{
        const res=await fetch('caixa.php?ajax_comandas=1');
        const contentType=res.headers.get('content-type')||'';
        if(!contentType.includes('application/json')){ sessaoCaixaExpirada=true; return; }
        const json=await res.json();
        const comandas=json.comandas||[], agoraTs=json.agora_ts||Math.floor(Date.now()/1000);
        document.getElementById('cx-stat-abertas').textContent=comandas.length;
        document.getElementById('cx-badge-count').textContent=comandas.length;
        document.getElementById('cx-stat-update').textContent='atualizado '+new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
        const wrapper=document.getElementById('cx-grid-wrapper');
        if(!comandas.length){wrapper.innerHTML='<div class="cx-vazio"><div class="cx-vazio-txt">Nenhuma comanda aberta</div><div class="cx-vazio-sub">As comandas lancadas pelos atendentes aparecerao aqui</div></div>';if(cmdAtualId)fecharPainel();return;}
        if(cmdAtualId&&!comandas.find(c=>c.id_comanda==cmdAtualId))fecharPainel();
        let grid=document.getElementById('cx-grid-comandas');
        if(!grid){wrapper.innerHTML='<div class="cx-grid" id="cx-grid-comandas"></div>';grid=document.getElementById('cx-grid-comandas');}
        const idsNoGrid=new Set(Array.from(document.querySelectorAll('.cx-comanda-card')).map(el=>el.id.replace('card-','')));
        idsNoGrid.forEach(id=>{if(!comandas.find(c=>String(c.id_comanda)===id)){const el=document.getElementById('card-'+id);if(el)el.remove();}});
        comandas.forEach(cmd=>{
            const criadoTs=parseInt(cmd.criado_em_ts);
            const minutos=Math.max(0,Math.round((agoraTs-criadoTs)/60));
            const urgente=minutos>=30;
            const dataHora=new Date(criadoTs*1000).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}).replace(',','');
            const lancador=(cmd.lancado_por||'').replace(/'/g,"\\'");
            const mesa=(cmd.observacao||'').replace(/'/g,"\\'");
            const numEsc=(cmd.numero_comanda||'').replace(/'/g,"\\'");
            let card=document.getElementById('card-'+cmd.id_comanda);
            if(!card){card=document.createElement('div');card.id='card-'+cmd.id_comanda;grid.appendChild(card);}
            const isAtivo=(cmdAtualId==cmd.id_comanda);
            card.className='cx-comanda-card'+(urgente?' urgente':'')+(isAtivo?' ativo':'');
            card.setAttribute('onclick',`abrirDetalhe(${cmd.id_comanda},'${numEsc}',${cmd.valor_total},'${dataHora}','${lancador}','${mesa}')`);
            const h=Math.floor(minutos/60),m=minutos%60;
            const tempoTxt=minutos<1?'Agora':minutos<60?minutos+' min':h+'h'+(m>0?' '+m+'min':'');
            const mesaExib=mesa?(/^\d+$/.test(mesa)?'Mesa '+mesa:mesa):'';
            const mesaBadge=mesaExib?`<div class="cx-cmd-mesa-badge">&#9632; ${mesaExib}</div>`:'';
            card.innerHTML=`<div class="cx-cmd-compact"><div class="cx-cmd-compact-label">Comanda</div><div class="cx-cmd-compact-num">${cmd.numero_comanda}</div><span class="cx-cmd-compact-badge ${urgente?'urgente':''}">${tempoTxt}</span>${mesaBadge}<div class="cx-cmd-compact-hint">Clique para ver detalhes</div></div>`;
            if(isAtivo&&cmd.valor_total!=cmdAtualTotal){cmdAtualTotal=cmd.valor_total;const f='R$ '+fmt(cmd.valor_total);document.getElementById('cx-det-total-header').textContent=f;document.getElementById('cx-det-total-footer').textContent=f;}
        });
    }catch(e){}
}

/* ─── PAINEL DETALHE COMANDA ─── */
async function abrirDetalhe(id,num,total,dataHora,lancador,mesa){
    document.querySelectorAll('.cx-comanda-card').forEach(c=>c.classList.remove('ativo'));
    const card=document.getElementById('card-'+id); if(card)card.classList.add('ativo');
    cmdAtualId=id; cmdAtualNum=num; cmdAtualTotal=total;
    document.getElementById('cx-det-titulo').textContent='Comanda #'+num;
    const f='R$ '+fmt(total);
    document.getElementById('cx-det-total-header').textContent=f;
    document.getElementById('cx-det-total-footer').textContent=f;
    const mesaChip=document.getElementById('cx-det-mesa-chip');
    const mesaTxt=document.getElementById('cx-det-mesa-txt');
    if(mesaChip&&mesaTxt){if(mesa){mesaChip.style.display='inline-flex';mesaTxt.textContent=mesa;}else{mesaChip.style.display='none';}}
    let metaHtml=`<div class="cx-det-meta-item"><strong>Abertura:</strong> ${dataHora}</div>`;
    if(lancador) metaHtml+=`<div class="cx-det-meta-item"><strong>Lancado por:</strong> ${lancador}</div>`;
    if(mesa)     metaHtml+=`<div class="cx-det-meta-item"><strong>Mesa:</strong> <span style="font-weight:800;color:#1e40af;">${mesa}</span></div>`;
    document.getElementById('cx-det-meta').innerHTML=metaHtml;
    document.getElementById('cx-det-meta-header').textContent=dataHora+(lancador?' · '+lancador:'')+(mesa?' · '+mesa:'');
    const painel=document.getElementById('cx-detalhe-panel');
    painel.style.display='block';
    painel.scrollIntoView({behavior:'smooth',block:'start'});
    await recarregarItensDetalhe();
}

/* ─── RECARREGAR ITENS ─── */
async function recarregarItensDetalhe(){
    const box = document.getElementById('cx-det-itens');
    box.innerHTML = '<div class="cx-det-loading">Carregando itens...</div>';
    try {
        const r = await fetch('caixa.php?itens_comanda=' + cmdAtualId);
        const itens = await r.json();
        if (!itens.length) { box.innerHTML = '<div class="cx-det-loading">Nenhum item.</div>'; return; }
        box.innerHTML = itens.map(it => {
            const nomeExib = (it.nome_produto||'').replace(/^\[AVULSO\]\s*/,'');
            const ads = it.adicionais&&it.adicionais.length ? `<div class="cx-det-item-ads">+ ${it.adicionais.map(a=>a.nome+(parseFloat(a.preco)>0?' (R$ '+fmt(a.preco)+')':'')).join(', ')}</div>` : '';
            const obsIt = it.observacao ? `<div class="cx-det-item-obs">"${it.observacao}"</div>` : '';
            const horario = it.criado_em ? new Date(it.criado_em).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}) : '';
            const lancador = it.lancado_por||'';
            const nomeEsc = nomeExib.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
            return `<div class="cx-det-item" onclick="toggleItemExtra(this)">
                <div class="cx-det-item-qty">${it.quantidade}</div>
                <div class="cx-det-item-info">
                    <div class="cx-det-item-nome">${nomeExib}</div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:1px;">${it.quantidade}x R$ ${fmt(it.preco_unitario)}</div>
                    ${ads}${obsIt}
                    <div class="cx-det-item-extra" style="display:none;">
                        ${horario?`<div style="font-size:11px;color:#64748b;"><strong>Horario:</strong> ${horario}</div>`:''}
                        ${lancador?`<div style="font-size:11px;color:#64748b;margin-top:2px;"><strong>Lancado por:</strong> ${lancador}</div>`:''}
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                    <div class="cx-det-item-sub">R$ ${fmt(it.subtotal)}</div>
                    <button type="button"
                        onclick="event.stopPropagation();pedirSenhaRemoverItem(${it.id_item},'${nomeEsc}')"
                        style="padding:3px 8px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:6px;font-size:10px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;">
                        Remover
                    </button>
                </div>
            </div>`;
        }).join('');
    } catch(e) {
        box.innerHTML = '<div class="cx-det-loading" style="color:#ef4444;">Erro ao carregar.</div>';
    }
}

function toggleItemExtra(el){
    const extra = el.querySelector('.cx-det-item-extra');
    if(extra) extra.style.display = extra.style.display==='block'?'none':'block';
}

function fecharPainel(){
    document.getElementById('cx-detalhe-panel').style.display='none';
    document.querySelectorAll('.cx-comanda-card').forEach(c=>c.classList.remove('ativo'));
    cmdAtualId=cmdAtualNum=cmdAtualTotal=null;
}

/* ─── MODAL SENHA CANCELAMENTO ─── */
function pedirSenhaRemoverItem(idItem, nomeItem) {
    itemParaRemover_id = idItem;
    itemParaRemover_nome = nomeItem;
    cancelCallback = null;
    document.getElementById('cx-senha-cancel-desc').textContent = 'Remover "' + nomeItem + '" da comanda? Esta acao nao pode ser desfeita.';
    document.getElementById('cx-input-senha-cancel').value = '';
    document.getElementById('cx-senha-cancel-erro').style.display = 'none';
    document.getElementById('cx-modal-senha-cancel').classList.add('show');
    setTimeout(function(){ document.getElementById('cx-input-senha-cancel').focus(); }, 100);
}

function cancelarComanda() {
    if (!cmdAtualId) return;
    itemParaRemover_id = null;
    itemParaRemover_nome = null;
    cancelCallback = function() {
        document.getElementById('hid-cmd-cancelar').value = cmdAtualId;
        document.getElementById('cx-form-cancelar').submit();
    };
    document.getElementById('cx-senha-cancel-desc').textContent = 'Cancelar comanda #' + cmdAtualNum + '? Esta acao nao pode ser desfeita.';
    document.getElementById('cx-input-senha-cancel').value = '';
    document.getElementById('cx-senha-cancel-erro').style.display = 'none';
    document.getElementById('cx-modal-senha-cancel').classList.add('show');
    setTimeout(function(){ document.getElementById('cx-input-senha-cancel').focus(); }, 100);
}

function fecharModalSenhaCancel() {
    document.getElementById('cx-modal-senha-cancel').classList.remove('show');
    cancelCallback = null;
    itemParaRemover_id = null;
    itemParaRemover_nome = null;
}

document.getElementById('cx-modal-senha-cancel').addEventListener('click', function(e) {
    if (e.target === this) fecharModalSenhaCancel();
});

async function confirmarSenhaCancel() {
    const senha = document.getElementById('cx-input-senha-cancel').value.trim();
    if (!senha) {
        document.getElementById('cx-senha-cancel-erro').textContent = 'Informe a senha.';
        document.getElementById('cx-senha-cancel-erro').style.display = 'block';
        return;
    }
    const fd = new FormData();
    fd.append('senha', senha);
    const res = await fetch('caixa.php?verificar_senha_cancel=1', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) {
        document.getElementById('cx-senha-cancel-erro').textContent = 'Senha incorreta. Tente novamente.';
        document.getElementById('cx-senha-cancel-erro').style.display = 'block';
        document.getElementById('cx-input-senha-cancel').value = '';
        document.getElementById('cx-input-senha-cancel').focus();
        return;
    }
    // Senha correta
    document.getElementById('cx-modal-senha-cancel').classList.remove('show');
    if (itemParaRemover_id !== null) {
        // Remover item da comanda
        const idItem = itemParaRemover_id;
        itemParaRemover_id = null;
        itemParaRemover_nome = null;
        const fd2 = new FormData();
        fd2.append('id_item', idItem);
        const res2 = await fetch('caixa.php?cancelar_item=1', { method: 'POST', body: fd2 });
        const data2 = await res2.json();
        if (data2.ok) {
            await recarregarItensDetalhe();
            const r2 = await fetch('caixa.php?ajax_comandas=1');
            const j2 = await r2.json();
            const cmd = (j2.comandas||[]).find(c => c.id_comanda == cmdAtualId);
            if (cmd) {
                cmdAtualTotal = cmd.valor_total;
                const f = 'R$ ' + fmt(cmd.valor_total);
                document.getElementById('cx-det-total-header').textContent = f;
                document.getElementById('cx-det-total-footer').textContent = f;
            }
        } else {
            alert('Erro: ' + (data2.msg || 'Nao foi possivel remover.'));
        }
    } else if (cancelCallback) {
        const cb = cancelCallback;
        cancelCallback = null;
        cb();
    }
}

/* ─── MODAL PAGAMENTO ─── */
function abrirModalPagamento(){
    if(!cmdAtualId) return;
    document.getElementById('cx-modal-sub').textContent='Comanda #'+cmdAtualNum;
    document.getElementById('cx-modal-total').textContent='R$ '+fmt(cmdAtualTotal);
    document.querySelectorAll('input[name="cx_pag"]').forEach(r=>r.checked=false);
    const box=document.getElementById('cx-modal-itens');
    const itensEl=document.getElementById('cx-det-itens');
    if(itensEl){const itens=itensEl.querySelectorAll('.cx-det-item');box.innerHTML=itens.length?Array.from(itens).map(el=>{const nome=el.querySelector('.cx-det-item-nome')?.textContent||'';const sub=el.querySelector('.cx-det-item-sub')?.textContent||'';const qty=el.querySelector('.cx-det-item-qty')?.textContent||'';return`<div class="cx-modal-item"><span>${qty}x ${nome}</span><span>${sub}</span></div>`;}).join(''):'<div style="color:#94a3b8;text-align:center;font-size:13px;">—</div>';}
    document.getElementById('cx-modal-pag').classList.add('show');
}
function fecharModalPag(){document.getElementById('cx-modal-pag').classList.remove('show');}
document.getElementById('cx-modal-pag').addEventListener('click',function(e){if(e.target===this)fecharModalPag();});
function confirmarFechamento(){const pag=document.querySelector('input[name="cx_pag"]:checked');if(!pag){alert('Selecione a forma de pagamento.');return;}document.getElementById('hid-cmd').value=cmdAtualId;document.getElementById('hid-pag').value=pag.value;document.getElementById('cx-form-fechar').submit();}

/* ─── MODAL PRODUTOS ─── */
async function abrirModalProd(modo){
    modoModal=modo; carrinhoModal=[];
    document.getElementById('cx-prod-modal-titulo').textContent=modo==='nova'?'Nova Comanda':'Adicionar Item a Comanda #'+cmdAtualNum;
    document.getElementById('cx-nova-comanda-fields').style.display=modo==='nova'?'block':'none';
    document.getElementById('cx-btn-confirmar-prod').textContent=modo==='nova'?'Criar Comanda':'Adicionar a Comanda';
    if(modo==='nova'){document.getElementById('cx-nova-num').value='';document.getElementById('cx-nova-obs').value='';}
    renderCarrinhoModal();
    trocarTabModal('produtos');
    document.getElementById('cx-modal-prod').classList.add('show');
    document.querySelectorAll('.cx-cat-pill').forEach(p=>p.classList.remove('ativa'));
    const pill0=document.querySelector('.cx-cat-pill[data-id="0"]');
    if(pill0) pill0.classList.add('ativa');
    await carregarCatProd(0, pill0);
}
function abrirAdicionarItem(){if(!cmdAtualId)return;abrirModalProd('adicionar');}
function abrirNovaComanda(){abrirModalProd('nova');}
function fecharModalProd(){document.getElementById('cx-modal-prod').classList.remove('show');modoModal=null;carrinhoModal=[];}
document.getElementById('cx-modal-prod').addEventListener('click',function(e){if(e.target===this)fecharModalProd();});

async function carregarCatProd(id,btn){
    document.querySelectorAll('.cx-cat-pill').forEach(p=>p.classList.remove('ativa'));
    if(btn)btn.classList.add('ativa');
    const grid=document.getElementById('cx-prod-grid-modal');
    grid.innerHTML='<div style="grid-column:1/-1;text-align:center;color:#94a3b8;font-size:13px;padding:16px;">Carregando...</div>';
    try{
        const prods=await(await fetch('caixa.php?categoria='+id)).json();
        if(!prods.length){grid.innerHTML='<div style="grid-column:1/-1;text-align:center;color:#94a3b8;font-size:13px;padding:16px;">Nenhum produto.</div>';return;}
        grid.innerHTML=prods.map(p=>{
            const temPromo=p.preco_promocional&&parseFloat(p.preco_promocional)>0;
            const precoShow=temPromo?parseFloat(p.preco_promocional):parseFloat(p.preco);
            const desconto=temPromo?Math.round(((p.preco-p.preco_promocional)/p.preco)*100):0;
            return `<div class="cx-prod-card-modal" onclick="abrirModalItem(${JSON.stringify(p).replace(/"/g,'&quot;')})">
                ${temPromo?`<div class="cx-prod-promo-badge">-${desconto}% PROMO</div>`:''}
                <div class="nome">${p.nome}</div>
                <div class="preco">R$ ${fmt(precoShow)}</div>
                ${temPromo?`<div class="preco-old">R$ ${fmt(p.preco)}</div>`:''}
                <div class="estoque">${p.estoque} em estoque</div>
            </div>`;
        }).join('');
    }catch(e){grid.innerHTML='<div style="grid-column:1/-1;text-align:center;color:#ef4444;font-size:13px;padding:16px;">Erro ao carregar.</div>';}
}

/* ─── ADICIONAIS AVULSOS ─── */
async function carregarAvulsoModal(q){
    const grid=document.getElementById('cx-avul-grid-modal');
    if(!grid) return;
    grid.innerHTML='<div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Carregando...</div>';
    try{
        const resp=await fetch('caixa.php?adicionais_avulsos='+encodeURIComponent(q));
        if(!resp.ok) throw new Error('HTTP '+resp.status);
        const ads=await resp.json();
        if(!Array.isArray(ads)||!ads.length){ grid.innerHTML='<div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Nenhum adicional cadastrado ou ativo.</div>'; return; }
        grid.innerHTML=ads.map(a=>{
            const ds=JSON.stringify(a).replace(/"/g,'&quot;');
            return `<div class="cx-avul-card-modal" onclick="abrirModalAvulso(${ds})">
                <div><div class="avul-nome">${a.nome}</div><div class="avul-sub">Adicional avulso</div></div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="avul-preco">${parseFloat(a.preco)>0?'R$ '+fmt(a.preco):'Gratis'}</span>
                    <button class="avul-btn" onclick="event.stopPropagation();abrirModalAvulso(${ds})">+ Add</button>
                </div>
            </div>`;
        }).join('');
    }catch(e){ grid.innerHTML='<div style="text-align:center;color:#ef4444;font-size:13px;padding:12px;">Erro ao carregar.</div>'; }
}
function buscarAvulsoModal(val){ clearTimeout(avulDebTimer); avulDebTimer=setTimeout(()=>carregarAvulsoModal(val.trim()),280); }
function abrirModalAvulso(ad){
    avulAtual=ad;
    document.getElementById('cx-avul-nome').textContent=ad.nome;
    document.getElementById('cx-avul-preco').textContent=parseFloat(ad.preco)>0?'R$ '+fmt(ad.preco)+' · Adicional avulso':'Gratis · Adicional avulso';
    document.getElementById('cx-avul-qty').value=1;
    document.getElementById('cx-avul-obs').value='';
    document.getElementById('cx-modal-avulso').classList.add('show');
}
function fecharModalAvulso(){document.getElementById('cx-modal-avulso').classList.remove('show');avulAtual=null;}
document.getElementById('cx-modal-avulso').addEventListener('click',function(e){if(e.target===this)fecharModalAvulso();});
function avulQty(delta){const inp=document.getElementById('cx-avul-qty');inp.value=Math.max(1,parseInt(inp.value||1)+delta);}
function confirmarAvulso(){
    if(!avulAtual) return;
    const qty=parseInt(document.getElementById('cx-avul-qty').value)||1;
    const obs=document.getElementById('cx-avul-obs').value.trim();
    const preco=parseFloat(avulAtual.preco)||0;
    carrinhoModal.push({id_produto:null,id_adicional_avulso:avulAtual.id_adicional,nome:avulAtual.nome,preco,estoque:9999,quantidade:qty,adicionais:[],obs,subtotal:preco*qty,is_avulso:true});
    fecharModalAvulso();
    renderCarrinhoModal();
}

/* ─── MODAL ITEM PRODUTO ─── */
async function abrirModalItem(prod){
    produtoAtual=prod; adicionaisSel={};
    document.getElementById('cx-item-nome').textContent=prod.nome;
    document.getElementById('cx-item-qty').value=1;
    document.getElementById('cx-item-obs').value='';
    const temPromo=prod.preco_promocional&&parseFloat(prod.preco_promocional)>0;
    const precoShow=temPromo?parseFloat(prod.preco_promocional):parseFloat(prod.preco);
    document.getElementById('cx-item-preco').innerHTML=temPromo
        ?`R$ ${fmt(precoShow)} <span style="text-decoration:line-through;font-size:11px;opacity:.6;">R$ ${fmt(prod.preco)}</span> · ${prod.estoque} em estoque`
        :`R$ ${fmt(precoShow)} · ${prod.estoque} em estoque`;
    const lista=document.getElementById('cx-item-adicionais');
    lista.innerHTML='<div style="color:#94a3b8;font-size:13px;padding:4px 0;">Verificando adicionais...</div>';
    document.getElementById('cx-modal-item').classList.add('show');
    try{
        const ads=await(await fetch('caixa.php?adicionais='+prod.id_produto)).json();
        lista.innerHTML=ads.length?'<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:8px;">Adicionais</div>'+ads.map(ad=>`
            <div class="cx-adicional-item">
                <div class="cx-adicional-check" id="add-chk-${ad.id_adicional}" onclick="toggleAd(${ad.id_adicional},'${ad.nome.replace(/'/g,"\\'")}',${ad.preco})"></div>
                <span style="flex:1;font-size:14px;font-weight:600;">${ad.nome}</span>
                <span style="font-size:13px;font-weight:700;color:#059669;">${parseFloat(ad.preco)>0?'+R$ '+fmt(ad.preco):'Gratis'}</span>
            </div>`).join(''):'';
    }catch(e){lista.innerHTML='';}
}
function toggleAd(id,nome,preco){const el=document.getElementById('add-chk-'+id);if(adicionaisSel[id]){delete adicionaisSel[id];el.classList.remove('checked');el.textContent='';}else{adicionaisSel[id]={id_adicional:id,nome,preco};el.classList.add('checked');el.textContent='v';}}
function itemQty(delta){const inp=document.getElementById('cx-item-qty');inp.value=Math.max(1,Math.min(produtoAtual.estoque,parseInt(inp.value||1)+delta));}
function fecharModalItem(){document.getElementById('cx-modal-item').classList.remove('show');produtoAtual=null;adicionaisSel={};}
document.getElementById('cx-modal-item').addEventListener('click',function(e){if(e.target===this)fecharModalItem();});
function confirmarItem(){
    if(!produtoAtual) return;
    const qty=parseInt(document.getElementById('cx-item-qty').value)||1;
    const obs=document.getElementById('cx-item-obs').value.trim();
    const ads=Object.values(adicionaisSel);
    const extras=ads.reduce((s,a)=>s+parseFloat(a.preco),0);
    const preco=precoEfetivo(produtoAtual);
    const subtotal=(preco+extras)*qty;
    const idx=carrinhoModal.findIndex(i=>i.id_produto==produtoAtual.id_produto&&JSON.stringify(i.adicionais)===JSON.stringify(ads)&&i.obs===obs);
    if(idx>=0){carrinhoModal[idx].quantidade+=qty;carrinhoModal[idx].subtotal=(carrinhoModal[idx].preco+extras)*carrinhoModal[idx].quantidade;}
    else carrinhoModal.push({id_produto:produtoAtual.id_produto,nome:produtoAtual.nome,preco,estoque:produtoAtual.estoque,quantidade:qty,adicionais:ads,obs,subtotal,is_avulso:false});
    fecharModalItem();
    renderCarrinhoModal();
}

/* ─── CARRINHO MODAL ─── */
function renderCarrinhoModal(){
    const box=document.getElementById('cx-carrinho-prod');
    if(!carrinhoModal.length){box.innerHTML='<div class="cx-carr-vazio">Nenhum produto adicionado.</div>';return;}
    const total=carrinhoModal.reduce((s,i)=>s+i.subtotal,0);
    box.innerHTML=carrinhoModal.map((it,idx)=>`
        <div class="cx-carr-item">
            <span class="cx-carr-nome">${it.nome}${it.adicionais&&it.adicionais.length?`<small style="color:#2563eb;"> +${it.adicionais.map(a=>a.nome).join(', ')}</small>`:''}${it.obs?`<small style="color:#94a3b8;font-style:italic;"> "${it.obs}"</small>`:''}</span>
            <div class="cx-carr-qty">
                <button class="cx-carr-qty-btn" onclick="carrQty(${idx},-1)">&#8722;</button>
                <span style="font-size:13px;font-weight:700;min-width:20px;text-align:center;">${it.quantidade}</span>
                <button class="cx-carr-qty-btn" onclick="carrQty(${idx},+1)">+</button>
            </div>
            <span class="cx-carr-sub">R$ ${fmt(it.subtotal)}</span>
            <button class="cx-carr-del" onclick="carrDel(${idx})">&#10005;</button>
        </div>`).join('')+`<div class="cx-carr-total"><span>Total</span><span style="color:#059669;">R$ ${fmt(total)}</span></div>`;
}
function carrQty(idx,delta){carrinhoModal[idx].quantidade=Math.max(1,carrinhoModal[idx].quantidade+delta);const extras=(carrinhoModal[idx].adicionais||[]).reduce((s,a)=>s+parseFloat(a.preco),0);carrinhoModal[idx].subtotal=(carrinhoModal[idx].preco+extras)*carrinhoModal[idx].quantidade;renderCarrinhoModal();}
function carrDel(idx){carrinhoModal.splice(idx,1);renderCarrinhoModal();}
function confirmarProdutos(){
    if(!carrinhoModal.length){alert('Adicione ao menos um item.');return;}
    if(modoModal==='adicionar'){
        document.getElementById('hid-cmd-add').value=cmdAtualId;
        document.getElementById('hid-itens-add').value=JSON.stringify(carrinhoModal);
        document.getElementById('cx-form-add-itens').submit();
    }else{
        const num=document.getElementById('cx-nova-num').value.trim();
        autoPreencherMesa(document.getElementById('cx-nova-obs'));
        const obs=document.getElementById('cx-nova-obs').value.trim();
        if(!num){alert('Informe o numero da comanda.');document.getElementById('cx-nova-num').focus();return;}
        document.getElementById('hid-nova-num').value=num;
        document.getElementById('hid-nova-obs').value=obs;
        document.getElementById('hid-nova-itens').value=JSON.stringify(carrinhoModal);
        document.getElementById('cx-form-nova').submit();
    }
}

/* ─── MODAL HISTORICO COMANDA ─── */
async function abrirDetalheHistorico(id){
    document.getElementById('cx-hist-titulo').textContent='Comanda #—';
    document.getElementById('cx-hist-status-badge').textContent='';
    document.getElementById('cx-hist-itens').innerHTML='<div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Carregando...</div>';
    document.getElementById('cx-hist-total').textContent='R$ —';
    document.getElementById('cx-modal-hist').classList.add('show');
    try{
        const data=await(await fetch('caixa.php?detalhe_historico='+id)).json();
        if(!data){document.getElementById('cx-hist-itens').innerHTML='<div style="text-align:center;color:#ef4444;font-size:13px;padding:12px;">Erro ao carregar.</div>';return;}
        document.getElementById('cx-hist-titulo').textContent='Comanda #'+data.numero_comanda;
        const badge=document.getElementById('cx-hist-status-badge');
        badge.textContent=data.status==='fechada'?'Fechada':'Cancelada';
        badge.className='cx-hist-status-badge '+data.status;
        const abertura=new Date(data.criado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
        const fechamento=data.fechado_em?new Date(data.fechado_em).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—';
        document.getElementById('cx-hist-sub').textContent=data.observacao||'';
        let metaHtml=`<div class="mi"><strong>Abertura</strong>${abertura}</div>`;
        if(data.lancado_por)metaHtml+=`<div class="mi"><strong>Lancado por</strong>${data.lancado_por}</div>`;
        metaHtml+=`<div class="mi"><strong>${data.status==='fechada'?'Fechamento':'Cancelamento'}</strong>${fechamento}</div>`;
        document.getElementById('cx-hist-meta').innerHTML=metaHtml;
        document.getElementById('cx-hist-total').textContent='R$ '+fmt(data.valor_total);
        if(!data.itens||!data.itens.length){document.getElementById('cx-hist-itens').innerHTML='<div style="text-align:center;color:#94a3b8;font-size:13px;padding:12px;">Nenhum item.</div>';return;}
        document.getElementById('cx-hist-itens').innerHTML=data.itens.map(it=>{
            const nomeExib = it.nome_produto ? it.nome_produto.replace(/^\[AVULSO\]\s*/, '') : '';
            const ads=it.adicionais&&it.adicionais.length?`<div class="cx-hist-item-ads">+ ${it.adicionais.map(a=>{const p=parseFloat(a.preco)>0?' (R$ '+fmt(a.preco)+')':'';return a.nome+p;}).join(', ')}</div>`:'';
            const obs=it.observacao?`<div class="cx-hist-item-obs">"${it.observacao}"</div>`:'';
            return `<div class="cx-hist-item-linha">
                <div class="cx-hist-item-qty">${it.quantidade}</div>
                <div class="cx-hist-item-info">
                    <div class="cx-hist-item-nome">${nomeExib}</div>
                    <div style="font-size:10px;color:#94a3b8;">${it.quantidade}x R$ ${fmt(it.preco_unitario)}</div>
                    ${ads}${obs}
                    ${it.lancado_por?`<div style="font-size:10px;color:#94a3b8;margin-top:2px;">por ${it.lancado_por}</div>`:''}
                </div>
                <div class="cx-hist-item-sub">R$ ${fmt(it.subtotal)}</div>
            </div>`;
        }).join('');
    }catch(e){document.getElementById('cx-hist-itens').innerHTML='<div style="text-align:center;color:#ef4444;font-size:13px;padding:12px;">Erro.</div>';}
}
function fecharModalHist(){document.getElementById('cx-modal-hist').classList.remove('show');}
document.getElementById('cx-modal-hist').addEventListener('click',function(e){if(e.target===this)fecharModalHist();});

/* ─── IMPRIMIR ─── */
function imprimirComprovante(){
    var cp=document.getElementById('cx-comprovante-print');if(!cp)return;
    var janela=window.open('','_blank','width=420,height=650');
    janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:80mm auto;margin:0;}*{box-sizing:border-box;margin:0;padding:0;}body{font-family:"Courier New",monospace;font-size:12px;color:#000;background:#fff;padding:4mm;width:80mm;}</style></head><body>'+cp.innerHTML+'</body></html>');
    janela.document.close();janela.focus();janela.onload=function(){janela.print();};
}

function autoPreencherMesa(input) {
    const val = input.value.trim();
    if (/^\d+$/.test(val)) { input.value = 'Mesa ' + val; }
}

const intervaloCaixa = setInterval(()=>{
    if(sessaoCaixaExpirada){clearInterval(intervaloCaixa);return;}
    atualizarGrid();
},15000);
</script>
</body>
</html>
