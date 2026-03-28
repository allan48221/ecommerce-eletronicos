<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (isset($_GET['ver_sessao'])) {
    header('Content-Type: application/json');
    echo json_encode(['sessao' => $_SESSION]);
    exit;
}
$tipo_staff = $_SESSION['tipo_staff'] ?? null;
$is_admin   = isset($_SESSION['id_admin']) && is_numeric($_SESSION['id_admin']);

if (!$is_admin && $tipo_staff !== 'atendente') {
    header('Location: login.php');
    exit;
}

$is_admin    = $is_admin;
$is_vendedor = false;

// ── ADICIONAIS DO PRODUTO (AJAX) ──
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
    } catch (\Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// ── LISTAR ADICIONAIS AVULSOS (AJAX) ──
if (isset($_GET['adicionais_avulsos'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['adicionais_avulsos'] ?? '');
    try {
        if ($q !== '') {
            $stmt = $conn->prepare("
                SELECT id_adicional, nome, preco
                FROM adicionais
                WHERE ativo = TRUE AND nome ILIKE ?
                ORDER BY nome ASC
                LIMIT 40
            ");
            $stmt->execute(['%' . $q . '%']);
        } else {
            $stmt = $conn->query("
                SELECT id_adicional, nome, preco
                FROM adicionais
                WHERE ativo = TRUE
                ORDER BY nome ASC
                LIMIT 40
            ");
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (\Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// ── BUSCA DE PRODUTOS (AJAX) ──
if (isset($_GET['buscar'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['buscar']) . '%';
    try {
        $stmt = $conn->prepare("
            SELECT p.id_produto, p.nome, p.marca, p.modelo, p.preco, p.preco_promocional, p.estoque, p.imagem,
                   c.nome AS categoria_nome
            FROM produtos p
            LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.ativo = TRUE AND p.estoque > 0
              AND (p.nome ILIKE ? OR p.marca ILIKE ? OR p.modelo ILIKE ?)
            ORDER BY p.nome ASC
            LIMIT 20
        ");
        $stmt->execute([$q, $q, $q]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (\Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// ── PRODUTOS POR CATEGORIA (AJAX) ──
if (isset($_GET['categoria'])) {
    header('Content-Type: application/json');
    $id_cat = intval($_GET['categoria']);
    if ($id_cat === 0) {
        $stmt = $conn->query("
            SELECT p.id_produto, p.nome, p.marca, p.modelo, p.preco, p.preco_promocional, p.estoque, p.imagem,
                   c.nome AS categoria_nome
            FROM produtos p
            LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.ativo = TRUE AND p.estoque > 0
            ORDER BY p.nome ASC
            LIMIT 60
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT p.id_produto, p.nome, p.marca, p.modelo, p.preco, p.preco_promocional, p.estoque, p.imagem,
                   c.nome AS categoria_nome
            FROM produtos p
            LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.ativo = TRUE AND p.estoque > 0 AND p.id_categoria = ?
            ORDER BY p.nome ASC
            LIMIT 60
        ");
        $stmt->execute([$id_cat]);
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── COMANDAS ABERTAS (AJAX) ──
if (isset($_GET['minhas_comandas'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->query("
            SELECT c.id_comanda, c.numero_comanda, c.observacao, c.lancado_por, c.valor_total, c.criado_em,
                   COUNT(ci.id_item) AS total_itens
            FROM comandas c
            LEFT JOIN comanda_itens ci ON ci.id_comanda = c.id_comanda
            WHERE c.status = 'aberta'
            GROUP BY c.id_comanda
            ORDER BY c.criado_em DESC
        ");
        $comandas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($comandas as &$cmd) {
            $si = $conn->prepare("SELECT * FROM comanda_itens WHERE id_comanda = ? ORDER BY criado_em");
            $si->execute([$cmd['id_comanda']]);
            $itens = $si->fetchAll(PDO::FETCH_ASSOC);
            foreach ($itens as &$it) {
                $it['adicionais'] = $it['adicionais'] ? json_decode($it['adicionais'], true) : [];
            }
            $cmd['itens'] = $itens;
        }
        echo json_encode($comandas);
    } catch (\Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// ── NOME DO USUÁRIO LOGADO ──
$nome_lancador = 'Desconhecido';
if (isset($_SESSION['id_staff']) && is_numeric($_SESSION['id_staff'])) {
    $nome = $_SESSION['nome_staff'] ?? null;
    $tipo = $_SESSION['tipo_staff'] ?? null;
    if ($nome) {
        $nome_lancador = $nome . ($tipo ? ' (' . ucfirst($tipo) . ')' : '');
    } else {
        try {
            $r = $conn->prepare("SELECT nome, tipo FROM staff WHERE id_staff = ? LIMIT 1");
            $r->execute([$_SESSION['id_staff']]);
            $row = $r->fetch(PDO::FETCH_ASSOC);
            if ($row) $nome_lancador = $row['nome'] . ' (' . ucfirst($row['tipo']) . ')';
        } catch (\Throwable $e) {}
    }
} elseif (isset($_SESSION['id_admin']) && is_numeric($_SESSION['id_admin'])) {
    try {
        $r = $conn->prepare("SELECT nome FROM admins WHERE id_admin = ? LIMIT 1");
        $r->execute([$_SESSION['id_admin']]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) $nome_lancador = $row['nome'];
    } catch (\Throwable $e) {}
} elseif (isset($_SESSION['nome_admin'])) {
    $nome_lancador = $_SESSION['nome_admin'];
}

// ── LANCAR COMANDA (POST) ──
$msg  = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_comanda = trim($_POST['numero_comanda'] ?? '');
    $observacao_cmd = trim($_POST['observacao_cmd'] ?? '');
    $itens_json     = trim($_POST['itens_json']     ?? '[]');
    $itens          = json_decode($itens_json, true);

    $erros = [];
    if (empty($numero_comanda))             $erros[] = 'Informe o numero da comanda.';
    if (empty($itens) || !is_array($itens)) $erros[] = 'Adicione ao menos um produto.';

    $prods_db = [];
    if (empty($erros)) {
        foreach ($itens as $item) {
            // ── Item avulso (apenas adicional, sem produto base) ──
            if (!empty($item['is_avulso']) && !empty($item['id_adicional_avulso'])) {
                $r = $conn->prepare("SELECT nome, preco FROM adicionais WHERE id_adicional = ? AND ativo = TRUE");
                $r->execute([$item['id_adicional_avulso']]);
                $ad = $r->fetch();
                if (!$ad) { $erros[] = "Adicional #{$item['id_adicional_avulso']} nao encontrado."; break; }
                $prods_db['avulso_' . $item['id_adicional_avulso']] = [
                    'nome'              => $ad['nome'],
                    'preco'             => $ad['preco'],
                    'preco_promocional' => null,
                    'estoque'           => 9999
                ];
                continue;
            }
            // ── Item de produto normal ──
            $r = $conn->prepare("SELECT nome, preco, preco_promocional, estoque FROM produtos WHERE id_produto = ? AND ativo = TRUE");
            $r->execute([$item['id_produto']]);
            $p = $r->fetch();
            if (!$p) { $erros[] = "Produto #{$item['id_produto']} nao encontrado."; break; }
            if ($p['estoque'] < $item['quantidade']) { $erros[] = "Estoque insuficiente para \"{$p['nome']}\"."; break; }
            $prods_db[$item['id_produto']] = $p;
        }
    }

    if (empty($erros)) {
        try {
            $conn->beginTransaction();
            $total = 0;

            // Calcula total
            foreach ($itens as $item) {
                if (!empty($item['is_avulso'])) {
                    $key = 'avulso_' . $item['id_adicional_avulso'];
                    $total += floatval($prods_db[$key]['preco']) * $item['quantidade'];
                    continue;
                }
                $p = $prods_db[$item['id_produto']];
                $preco_base = (!empty($p['preco_promocional']) && floatval($p['preco_promocional']) > 0)
                    ? floatval($p['preco_promocional']) : floatval($p['preco']);
                $sub = $preco_base * $item['quantidade'];
                $extras = 0;
                if (!empty($item['adicionais'])) {
                    foreach ($item['adicionais'] as $ad) $extras += floatval($ad['preco'] ?? 0) * $item['quantidade'];
                }
                $total += $sub + $extras;
            }

            $stmt_exist = $conn->prepare("SELECT id_comanda FROM comandas WHERE numero_comanda = ? AND status = 'aberta' LIMIT 1");
            $stmt_exist->execute([$numero_comanda]);
            $comanda_exist = $stmt_exist->fetch(PDO::FETCH_ASSOC);

            if ($comanda_exist) {
                $id_comanda = $comanda_exist['id_comanda'];
                $conn->prepare("UPDATE comandas SET valor_total = valor_total + ? WHERE id_comanda = ?")->execute([$total, $id_comanda]);
            } else {
                $stmt = $conn->prepare("INSERT INTO comandas (numero_comanda, status, observacao, lancado_por, valor_total, criado_em) VALUES (?, 'aberta', ?, ?, ?, NOW()) RETURNING id_comanda");
                $stmt->execute([$numero_comanda, $observacao_cmd, $nome_lancador, $total]);
                $id_comanda = $stmt->fetch(PDO::FETCH_ASSOC)['id_comanda'];
            }

            // Insere itens
            foreach ($itens as $item) {
                // ── Avulso ──
                if (!empty($item['is_avulso'])) {
                    $key = 'avulso_' . $item['id_adicional_avulso'];
                    $p   = $prods_db[$key];
                    $sub = floatval($p['preco']) * $item['quantidade'];
                    $conn->prepare("
                        INSERT INTO comanda_itens
                            (id_comanda, id_produto, nome_produto, preco_unitario, quantidade, subtotal, adicionais, observacao, lancado_por)
                        VALUES (?, NULL, ?, ?, ?, ?, NULL, ?, ?)
                    ")->execute([
                        $id_comanda,
                        '[AVULSO] ' . $p['nome'],
                        $p['preco'],
                        $item['quantidade'],
                        $sub,
                        $item['obs'] ?? null,
                        $nome_lancador
                    ]);
                    continue;
                }
                // ── Produto normal ──
                $p = $prods_db[$item['id_produto']];
                $preco_base = (!empty($p['preco_promocional']) && floatval($p['preco_promocional']) > 0)
                    ? floatval($p['preco_promocional']) : floatval($p['preco']);
                $sub = $preco_base * $item['quantidade'];
                $extras = 0;
                if (!empty($item['adicionais'])) {
                    foreach ($item['adicionais'] as $ad) $extras += floatval($ad['preco'] ?? 0) * $item['quantidade'];
                }
                $adicionais_json = !empty($item['adicionais']) ? json_encode($item['adicionais']) : null;
                $conn->prepare("INSERT INTO comanda_itens (id_comanda, id_produto, nome_produto, preco_unitario, quantidade, subtotal, adicionais, observacao, lancado_por) VALUES (?,?,?,?,?,?,?,?,?)")
                     ->execute([$id_comanda, $item['id_produto'], $p['nome'], $preco_base, $item['quantidade'], $sub + $extras, $adicionais_json, $item['obs'] ?? null, $nome_lancador]);
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

    $resultados_imp = despacharParaImpressoras(
        $conn,
        $id_comanda,
        $numero_comanda,
        $observacao_cmd ?: '-',
        $nome_lancador,
        $itens_para_imprimir
    );

    foreach ($resultados_imp as $r) {
        $status = $r['sucesso'] ? 'OK' : 'FALHA';
        error_log("Impressão [{$status}] → {$r['impressora']} ({$r['ip']}) — {$r['itens_count']} item(ns)");
    }

} catch (\Throwable $e) {
    error_log("Erro ao imprimir comanda #$numero_comanda: " . $e->getMessage());
}
            $acao_log   = $comanda_exist ? 'comanda_atualizada' : 'comanda_lancada';
            $titulo_log = $comanda_exist ? "Comanda #$numero_comanda atualizada pelo atendente" : "Comanda #$numero_comanda lancada";
            registrar_log($conn, $acao_log, $titulo_log, count($itens).' item(ns) — R$ '.number_format($total,2,',','.'), $total, $id_comanda, $nome_lancador);
            $msg = "Comanda <strong>#$numero_comanda</strong> ".($comanda_exist?"atualizada":"lancada")."! ".count($itens)." item(ns) adicionados — R$ ".number_format($total,2,',','.');
        } catch (\Throwable $e) {
            $conn->rollBack();
            $msg  = 'Erro: ' . $e->getMessage();
            $tipo = 'danger';
        }
    } else {
        $msg  = implode(' ', $erros);
        $tipo = 'danger';
    }
}

// ── CARREGA CATEGORIAS ──
try {
    $categorias = $conn->query("
        SELECT c.id_categoria, c.nome, COUNT(p.id_produto) AS total
        FROM categorias c
        INNER JOIN produtos p ON p.id_categoria = c.id_categoria AND p.ativo = TRUE AND p.estoque > 0
        GROUP BY c.id_categoria, c.nome ORDER BY c.nome ASC
    ")->fetchAll();
} catch (\Throwable $e) { $categorias = []; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
<title>Atendente — Lancar Comanda</title>
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --primary:#2563eb; --primary-dark:#1d4ed8;--light:#ffffff;
    --success:#059669; --danger:#dc2626; --surface:#ffffff; --surface-2:#f8fafc;
    --border:#e2e8f0; --border-2:#cbd5e1; --text-1:#0f172a; --text-2:#475569; --text-3:#94a3b8;
    --radius-sm:8px; --radius-md:12px; --radius-lg:16px; --radius-xl:20px;
    --shadow-sm:0 1px 3px rgba(0,0,0,.08); --shadow-md:0 4px 12px rgba(0,0,0,.10);
    --font:'DM Sans',sans-serif; --font-mono:'DM Mono',monospace;
}
body { font-family:var(--font); background:#ffffff; color:var(--text-1); min-height:100vh; overflow-x:hidden; }

/* TOPBAR */
.at-topbar { display:flex; align-items:center; justify-content:space-between; padding:0 14px; height:46px; background:var(--surface); border-bottom:1.5px solid var(--border); position:sticky; top:0; z-index:400; box-shadow:var(--shadow-sm); }
.at-topbar-brand { display:flex; align-items:center; gap:8px; }
.at-topbar-brand h1 { font-size:13px; font-weight:700; color:var(--text-1); letter-spacing:-.3px; }
.at-topbar-brand p  { font-size:11px; color:var(--text-3); display:none; }
.at-topbar-right    { display:flex; align-items:center; gap:8px; }
.at-user-chip { font-size:11px; font-weight:600; color:var(--text-2); background:var(--surface-2); border:1.5px solid var(--border); padding:4px 10px; border-radius:20px; display:none; }
.at-btn-back { display:inline-flex; align-items:center; gap:4px; padding:5px 11px; background:var(--surface-2); color:var(--text-2); border:1.5px solid var(--border); border-radius:20px; font-size:11px; font-weight:600; text-decoration:none; font-family:var(--font); transition:background .15s,border-color .15s; }
.at-btn-back:hover { background:var(--border); border-color:var(--border-2); }

/* ALERT */
.at-alert { padding:10px 13px; font-size:13px; font-weight:500; line-height:1.4; border-left:4px solid; border-radius:var(--radius-md); animation:slideDown .25s ease; }
.at-alert.success { background:#ecfdf5; color:#065f46; border-color:var(--success); }
.at-alert.danger  { background:#fef2f2; color:#7f1d1d; border-color:var(--danger); }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* BADGE */
.at-badge { background:var(--primary); color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; margin-left:6px; }

/* INPUTS */
.at-input { width:100%; padding:8px 10px; border:2px solid var(--border); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); outline:none; background:var(--surface); color:var(--text-1); transition:border-color .2s,box-shadow .2s; }
.at-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.at-search { width:100%; padding:8px 10px 8px 34px; border:2px solid var(--border); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); outline:none; background:var(--surface); color:var(--text-1); transition:border-color .2s,box-shadow .2s; }
.at-search:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.at-search-ico { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:11px; color:var(--text-3); pointer-events:none; font-family:var(--font); font-weight:700; }

/* SEARCH RESULTS */
.at-search-results { position:absolute; top:calc(100% + 5px); left:0; right:0; background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); box-shadow:var(--shadow-md); display:none; max-height:220px; overflow-y:auto; z-index:100; }
.at-search-results.show { display:block; }
.at-res-item { display:flex; align-items:center; gap:8px; padding:8px 11px; cursor:pointer; border-bottom:1px solid var(--surface-2); transition:background .1s; }
.at-res-item:last-child { border-bottom:none; }
.at-res-item:hover { background:var(--primary-light); }
.at-res-thumb { width:34px; height:34px; object-fit:cover; border-radius:var(--radius-sm); border:1.5px solid var(--border); flex-shrink:0; }
.at-res-nome  { font-size:12px; font-weight:700; }
.at-res-sub   { font-size:10px; color:var(--text-3); margin-top:1px; }
.at-res-preco { font-size:12px; font-weight:700; color:var(--primary); white-space:nowrap; flex-shrink:0; }
.at-no-res    { padding:16px; text-align:center; color:var(--text-3); font-size:12px; }

/* CATEGORIAS */
.at-cats { display:flex; gap:5px; overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; padding-bottom:2px; }
.at-cats::-webkit-scrollbar { display:none; }
.at-cat-pill { display:inline-flex; align-items:center; gap:3px; padding:5px 10px; border:1.5px solid var(--border); border-radius:20px; font-size:11px; font-weight:600; color:var(--text-2); cursor:pointer; white-space:nowrap; background:var(--surface); font-family:var(--font); transition:background .15s,border-color .15s,color .15s; flex-shrink:0; -webkit-tap-highlight-color:transparent; }
.at-cat-pill:hover { border-color:var(--primary); color:var(--primary); }
.at-cat-pill.ativa { background:var(--primary); border-color:var(--primary); color:#fff; }
.at-cat-count { font-size:9px; font-weight:700; opacity:.75; }

/* GRID PRODUTOS */
.at-prod-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
.at-prod-card { background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); overflow:hidden; cursor:pointer; transition:border-color .15s,box-shadow .15s,transform .1s; display:flex; flex-direction:column; }
.at-prod-card:hover  { border-color:var(--primary); box-shadow:0 4px 14px rgba(37,99,235,.15); }
.at-prod-card:active { transform:scale(.98); }
.at-prod-img  { width:100%; height:76px; object-fit:cover; background:var(--surface-2); display:block; flex-shrink:0; }
.at-prod-body { padding:6px 8px 8px; display:flex; flex-direction:column; flex:1; }
.at-prod-nome { font-size:11px; font-weight:700; line-height:1.3; margin-bottom:1px; }
.at-prod-sub  { font-size:9px; color:var(--text-3); margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.at-prod-promo-badge { display:inline-block; background:#dc2626; color:#fff; font-size:8px; font-weight:800; padding:1px 4px; border-radius:4px; margin-bottom:2px; letter-spacing:.3px; }
.at-prod-preco-area  { min-height:36px; display:flex; flex-direction:column; justify-content:flex-end; }
.at-prod-preco     { font-size:12px; font-weight:700; color:var(--primary); font-family:var(--font-mono); line-height:1.2; }
.at-prod-preco-old { font-size:9px; color:var(--text-3); text-decoration:line-through; font-family:var(--font-mono); }
.at-prod-footer { display:flex; align-items:flex-end; justify-content:space-between; gap:4px; margin-top:auto; padding-top:5px; }
.at-prod-btn { padding:4px 8px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-sm); font-size:10px; font-weight:700; font-family:var(--font); cursor:pointer; white-space:nowrap; flex-shrink:0; -webkit-tap-highlight-color:transparent; transition:background .15s; align-self:flex-end; }
.at-prod-btn:hover  { background:var(--primary-dark); }
.at-prod-btn:active { opacity:.85; }
.at-grid-loading, .at-grid-vazio { text-align:center; padding:26px 14px; color:var(--text-3); font-size:13px; grid-column:1/-1; }
/* ADICIONAL AVULSO */
.avul-card { display:flex; align-items:center; justify-content:space-between; background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); padding:9px 11px; cursor:pointer; transition:border-color .15s,box-shadow .15s,transform .1s; gap:8px; }
.avul-card:hover  { border-color:var(--primary); box-shadow:0 4px 14px rgba(37,99,235,.12); }
.avul-card:active { transform:scale(.98); }
.avul-nome  { font-size:12px; font-weight:700; flex:1; min-width:0; }
.avul-preco { font-size:12px; font-weight:700; color:var(--success); white-space:nowrap; font-family:var(--font-mono); }
.avul-btn   { padding:5px 10px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-sm); font-size:10px; font-weight:700; font-family:var(--font); cursor:pointer; white-space:nowrap; flex-shrink:0; transition:background .15s; }
.avul-btn:hover  { background:var(--primary-dark); }
.avul-lista-grid { display:flex; flex-direction:column; gap:6px; }

/* CARRINHO */
.at-cart-vazio { text-align:center; color:var(--text-3); font-size:12px; padding:14px 0; }
.at-cart-item  { display:flex; align-items:flex-start; gap:8px; padding:7px 0; border-bottom:1px solid var(--surface-2); animation:fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateX(-6px)} to{opacity:1;transform:translateX(0)} }
.at-cart-item:last-of-type { border-bottom:none; }
.at-ci-thumb { width:32px; height:32px; object-fit:cover; border-radius:var(--radius-sm); border:1.5px solid var(--border); flex-shrink:0; margin-top:1px; }
.at-ci-thumb.avulso { display:flex; align-items:center; justify-content:center; background:var(--primary-light); font-size:14px; border-color:#bfdbfe; }
.at-ci-info  { flex:1; min-width:0; }
.at-ci-nome  { font-size:11px; font-weight:700; line-height:1.3; }
.at-ci-avulso-tag { display:inline-block; background:#fef3c7; color:#92400e; font-size:8px; font-weight:800; padding:1px 5px; border-radius:4px; margin-top:2px; letter-spacing:.3px; }
.at-ci-adicionais { font-size:10px; color:var(--primary); margin-top:2px; font-weight:600; }
.at-ci-obs   { font-size:10px; color:var(--text-3); margin-top:2px; font-style:italic; }
.at-ci-bottom { display:flex; align-items:center; justify-content:space-between; margin-top:6px; }
.at-ci-qty   { display:flex; align-items:center; background:var(--surface-2); border:1.5px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; }
.at-ci-qty-btn { width:24px; height:24px; background:transparent; border:none; font-size:13px; font-weight:700; color:var(--primary); cursor:pointer; display:flex; align-items:center; justify-content:center; font-family:var(--font); transition:background .1s; }
.at-ci-qty-btn:hover { background:var(--primary-light); }
.at-ci-qty-num { width:24px; text-align:center; font-size:12px; font-weight:700; border:none; background:transparent; outline:none; font-family:var(--font-mono); color:var(--text-1); border-left:1px solid var(--border); border-right:1px solid var(--border); }
.at-ci-preco { font-size:12px; font-weight:700; color:var(--success); font-family:var(--font-mono); }
.at-ci-del   { width:24px; height:24px; background:#fef2f2; border:1.5px solid #fecaca; border-radius:var(--radius-sm); color:var(--danger); font-size:11px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; flex-shrink:0; margin-left:6px; transition:background .1s; }
.at-ci-del:hover { background:#fee2e2; }
.at-cart-total { display:flex; justify-content:space-between; align-items:center; padding:10px 0 0; border-top:2px solid var(--border); margin-top:4px; font-size:13px; font-weight:700; color:var(--text-1); }
.at-cart-total-val { font-family:var(--font-mono); color:var(--success); }

/* BOTÕES */
.at-btn-limpar  { padding:4px 10px; background:#fef2f2; color:var(--danger); border:1.5px solid #fecaca; border-radius:20px; font-size:10px; font-weight:700; cursor:pointer; font-family:var(--font); transition:background .1s; }
.at-btn-limpar:hover { background:#fee2e2; }
.at-btn-refresh { padding:5px 10px; background:var(--primary-light); color:var(--primary); border:1.5px solid #bfdbfe; border-radius:20px; font-size:10px; font-weight:700; cursor:pointer; font-family:var(--font); transition:background .1s; }
.at-btn-refresh:hover { background:#dbeafe; }

/* SKELETON */
.at-cmd-skeleton { height:44px; background:linear-gradient(90deg,var(--surface-2) 25%,var(--border) 50%,var(--surface-2) 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; border-radius:var(--radius-md); margin-bottom:6px; }
@keyframes shimmer { to{background-position:-200% 0} }

/* COMANDAS ABERTAS */
.at-cmd-aberta-card { background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); overflow:hidden; margin-bottom:6px; }
@keyframes fadeSlideIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.at-cmd-aberta-header { display:flex; align-items:center; justify-content:space-between; padding:8px 11px; background:var(--surface-2); border-bottom:1px solid var(--border); cursor:pointer; -webkit-tap-highlight-color:transparent; }
.at-cmd-aberta-num      { font-size:12px; font-weight:700; color:var(--primary); font-family:var(--font-mono); }
.at-cmd-aberta-meta     { font-size:10px; color:var(--text-3); margin-top:1px; }
.at-cmd-aberta-lancador { font-size:10px; color:var(--text-2); font-weight:600; margin-top:1px; }
.at-cmd-mesa-badge { display:inline-flex; align-items:center; gap:3px; background:#dbeafe; color:#1e40af; font-size:10px; font-weight:800; padding:2px 7px; border-radius:20px; margin-top:3px; letter-spacing:.2px; }
.at-cmd-aberta-valor    { font-size:12px; font-weight:700; color:var(--success); white-space:nowrap; font-family:var(--font-mono); }
.at-cmd-aberta-seta     { font-size:10px; color:var(--text-3); margin-left:8px; transition:transform .2s; font-family:var(--font); font-weight:700; }
.at-cmd-aberta-card.aberto .at-cmd-aberta-seta { transform:rotate(180deg); }
.at-cmd-aberta-body     { display:none; padding:8px 11px 10px; }
.at-cmd-aberta-card.aberto .at-cmd-aberta-body { display:block; }
.at-cmd-aberta-obs-geral { font-size:11px; color:#92400e; font-style:italic; margin-bottom:8px; padding:5px 8px; background:#fffbeb; border-radius:var(--radius-sm); border-left:3px solid #f59e0b; }
.at-cmd-item-linha { display:flex; align-items:flex-start; gap:8px; padding:6px 0; border-bottom:1px solid var(--surface-2); }
.at-cmd-item-linha:last-child { border-bottom:none; }
.at-cmd-item-qty  { min-width:22px; height:22px; background:var(--primary); color:#fff; border-radius:5px; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; font-family:var(--font-mono); }
.at-cmd-item-qty.avulso-qty { background:#f59e0b; }
.at-cmd-item-info { flex:1; min-width:0; }
.at-cmd-item-nome { font-size:12px; font-weight:700; }
.at-cmd-item-ads  { font-size:10px; color:var(--primary); font-weight:600; margin-top:1px; }
.at-cmd-item-obs  { font-size:10px; color:var(--text-3); font-style:italic; margin-top:1px; }
.at-cmd-item-sub  { font-size:12px; font-weight:700; color:var(--success); white-space:nowrap; flex-shrink:0; align-self:center; font-family:var(--font-mono); }
.at-cmd-aberta-total { display:flex; justify-content:space-between; align-items:center; padding:8px 0 0; border-top:2px solid var(--border); margin-top:3px; font-size:13px; font-weight:700; }
.at-cmd-aberta-vazio { text-align:center; color:var(--text-3); font-size:12px; padding:16px 0; }
.at-cmd-item-detalhe { display:none; margin-top:4px; padding:5px 7px; background:var(--primary-light); border-radius:5px; border-left:3px solid var(--primary); }
.at-cmd-item-detalhe.visivel { display:block; }

/* MODAL */
.at-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:flex-end; justify-content:center; backdrop-filter:blur(2px); }
.at-modal-overlay.show { display:flex; }
.at-modal { background:var(--surface); border-radius:var(--radius-xl) var(--radius-xl) 0 0; padding:14px 14px calc(14px + env(safe-area-inset-bottom)); width:100%; max-width:600px; max-height:88vh; overflow-y:auto; box-shadow:0 -8px 40px rgba(0,0,0,.2); animation:slideUp .25s ease; }
@keyframes slideUp { from{transform:translateY(40px);opacity:0} to{transform:translateY(0);opacity:1} }
.at-modal-drag { width:34px; height:4px; background:var(--border); border-radius:4px; margin:0 auto 10px; }
.at-modal h3   { font-size:14px; font-weight:700; margin-bottom:2px; letter-spacing:-.3px; }
.at-modal-sub  { font-size:12px; color:var(--text-2); margin-bottom:10px; font-family:var(--font-mono); }
.at-adicional-titulo { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-2); margin-bottom:6px; }
.at-adicional-item   { display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--surface-2); }
.at-adicional-item:last-child { border-bottom:none; }
.at-adicional-check  { width:20px; height:20px; border:2px solid var(--border); border-radius:5px; cursor:pointer; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; transition:background .15s,border-color .15s; -webkit-tap-highlight-color:transparent; }
.at-adicional-check.checked { background:var(--primary); border-color:var(--primary); }
.at-adicional-nome   { flex:1; font-size:13px; font-weight:600; }
.at-adicional-preco  { font-size:12px; font-weight:700; color:var(--success); white-space:nowrap; font-family:var(--font-mono); }
.at-modal-sep { border:none; border-top:1px solid var(--border); margin:10px 0; }
.at-modal-obs label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-2); display:block; margin-bottom:5px; }
.at-modal-obs textarea { width:100%; padding:8px 10px; border:2px solid var(--border); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); outline:none; resize:none; height:54px; transition:border-color .2s; color:var(--text-1); }
.at-modal-obs textarea:focus { border-color:var(--primary); }
.at-modal-qty-row   { display:flex; align-items:center; justify-content:space-between; margin-top:10px; }
.at-modal-qty-label { font-size:12px; font-weight:700; }
.at-modal-qty { display:flex; align-items:center; background:var(--surface-2); border:1.5px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; }
.at-modal-qty-btn { width:32px; height:32px; background:transparent; border:none; font-size:16px; font-weight:700; color:var(--primary); cursor:pointer; display:flex; align-items:center; justify-content:center; font-family:var(--font); transition:background .1s; }
.at-modal-qty-btn:hover { background:var(--primary-light); }
.at-modal-qty-num { width:36px; text-align:center; font-size:14px; font-weight:700; border:none; background:transparent; outline:none; font-family:var(--font-mono); border-left:1px solid var(--border); border-right:1px solid var(--border); }
.at-modal-btns { display:flex; gap:8px; margin-top:12px; }
.at-btn-add-item { flex:1; padding:11px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-md); font-size:13px; font-weight:700; font-family:var(--font); cursor:pointer; transition:background .15s; }
.at-btn-add-item:hover { background:var(--primary-dark); }
.at-btn-cancel-modal { padding:11px 14px; background:var(--surface-2); color:var(--text-2); border:1.5px solid var(--border); border-radius:var(--radius-md); font-size:13px; font-weight:600; font-family:var(--font); cursor:pointer; }

/* MOBILE TABS */
.at-tab-bar { display:flex; background:var(--surface); border-bottom:1.5px solid var(--border); position:sticky; top:46px; z-index:300; }
.at-tab { flex:1; padding:8px 4px; text-align:center; font-size:11px; font-weight:700; color:var(--text-3); border-bottom:3px solid transparent; cursor:pointer; font-family:var(--font); transition:color .15s,border-color .15s; -webkit-tap-highlight-color:transparent; user-select:none; }
.at-tab.ativa { color:var(--primary); border-color:var(--primary); background:var(--primary-light); }
.at-tab-badge { display:inline-block; background:var(--primary); color:#fff; font-size:9px; font-weight:700; padding:1px 5px; border-radius:20px; margin-left:3px; vertical-align:middle; }
.at-tab-badge.hidden { display:none; }
.at-tab-panel { display:none; background:#fff; }
.at-tab-panel.ativo { display:block; background:#fff; }

.at-comanda-sticky { background:var(--surface); border-bottom:1.5px solid var(--border); padding:7px 10px; display:flex; gap:6px; }
.at-comanda-sticky .at-field { flex:1; display:flex; flex-direction:column; gap:3px; }
.at-comanda-sticky label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-3); }
.at-comanda-sticky input { padding:6px 8px; border:1.5px solid var(--border); border-radius:var(--radius-sm); font-size:13px; font-family:var(--font); outline:none; background:var(--surface); color:var(--text-1); transition:border-color .2s; }
.at-comanda-sticky input:focus { border-color:var(--primary); }
.at-comanda-num-field { flex:0 0 100px; }
.at-panel-produtos { padding:8px 10px; display:flex; flex-direction:column; gap:7px; }
.at-search-pos { position:relative; }
.at-panel-carrinho { padding:10px 10px 90px; background:#fff !important; }
#panel-carrinho { background:#fff !important; }
#at-cart-body { background:#fff; border-radius:12px; }
.at-panel-avulsos { padding:8px 10px; display:flex; flex-direction:column; gap:7px; }
.at-panel-avulsos .avul-info-tip { font-size:11px; color:var(--text-3); padding:6px 10px; background:#fffbeb; border-radius:var(--radius-sm); border-left:3px solid #f59e0b; }
.at-panel-comandas { padding:8px 10px; }
.at-panel-comandas-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.at-panel-comandas-header h3 { font-size:13px; font-weight:700; }

.at-fab { position:fixed; bottom:0; left:0; right:0; z-index:300; padding:8px 10px calc(8px + env(safe-area-inset-bottom)); background:var(--surface); border-top:1.5px solid var(--border); box-shadow:0 -4px 20px rgba(0,0,0,.1); display:none; }
.at-fab.show { display:block; }
.at-fab-btn { width:100%; padding:11px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-md); font-size:13px; font-weight:700; font-family:var(--font); cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 14px rgba(37,99,235,.35); transition:background .15s; }
.at-fab-btn:hover  { background:var(--primary-dark); }
.at-fab-btn:active { transform:scale(.99); }

/* DESKTOP — mantido igual ao original */
.at-desktop-layout { display:none; height:calc(100vh - 56px); overflow:hidden; }
.at-desk-col-prods { flex:1; display:flex; flex-direction:column; border-right:1.5px solid var(--border); overflow:hidden; min-width:0; }
.at-desk-col-cart  { width:300px; flex-shrink:0; display:flex; flex-direction:column; overflow:hidden; background:var(--surface); }
.at-desk-col-head  { padding:12px 16px; background:var(--surface-2); border-bottom:1.5px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.at-desk-col-head h2 { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--text-2); }
.at-desk-filtros { padding:12px 16px; border-bottom:1.5px solid var(--border); display:flex; flex-direction:column; gap:8px; flex-shrink:0; background:var(--surface); }
.at-desk-grid-wrap { flex:1; overflow-y:auto; padding:14px 16px; background:var(--surface-2); }
.at-desk-prod-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.at-desk-comanda-area { padding:14px; border-bottom:1.5px solid var(--border); display:flex; flex-direction:column; gap:8px; flex-shrink:0; background:var(--surface); }
.at-desk-comanda-area label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-3); display:block; margin-bottom:4px; }
.at-desk-cart-items  { flex:1; overflow-y:auto; padding:10px 14px; }
.at-desk-cart-footer { padding:12px 14px calc(12px + env(safe-area-inset-bottom)); border-top:1.5px solid var(--border); background:var(--surface-2); flex-shrink:0; }
.at-desk-total { display:flex; justify-content:space-between; align-items:center; font-size:15px; font-weight:700; margin-bottom:10px; }
.at-desk-total-val { font-family:var(--font-mono); color:var(--success); }
.at-desk-launch-btn { width:100%; padding:13px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-md); font-size:14px; font-weight:700; font-family:var(--font); cursor:pointer; transition:background .15s; }
.at-desk-launch-btn:hover  { background:var(--primary-dark); }
.at-desk-launch-btn:active { opacity:.9; }
.at-desk-avulsos-box { padding:10px 0 4px; display:none; flex-direction:column; gap:8px; }
.at-desk-avulsos-box.aberto { display:flex; }
.at-desk-avulsos-box .avul-lista-compact { display:flex; flex-direction:column; gap:5px; max-height:200px; overflow-y:auto; margin-top:4px; }
.at-desk-comandas-wrap { flex-shrink:0; border-top:1.5px solid var(--border); display:flex; flex-direction:column; }
.at-desk-cmd-toggle { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; cursor:pointer; background:var(--surface-2); border-bottom:1px solid var(--border); flex-shrink:0; -webkit-tap-highlight-color:transparent; }
.at-desk-cmd-toggle span { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-2); }
#desk-cmd-seta { font-size:11px; font-weight:700; color:var(--text-3); transition:transform .2s; display:inline-block; }
.at-desk-cmd-body { overflow-y:auto; max-height:260px; padding:8px 14px 10px; }
.at-desk-cmd-body.fechado { display:none; }

@media (min-width:768px) { .at-user-chip { display:inline-flex; } .at-topbar-brand p { display:block; } }
@media (min-width:900px) { .at-mobile-layout { display:none !important; } .at-tab-bar { display:none !important; } .at-fab { display:none !important; } .at-desktop-layout { display:flex; } }
@media (max-width:899px) { .at-desktop-layout { display:none !important; } .at-mobile-layout { display:block; } }
@media (max-width:480px) { .at-prod-grid { grid-template-columns:repeat(2,1fr); gap:6px; } .at-prod-img { height:66px; } .at-prod-nome { font-size:10px; } .at-prod-preco { font-size:11px; } .at-comanda-num-field { flex:0 0 90px; } }
@media (min-width:900px) and (max-width:1200px) { .at-desk-col-cart { width:260px; } .at-desk-prod-grid { grid-template-columns:repeat(2,1fr); } }
@media (min-width:1400px) { .at-desk-prod-grid { grid-template-columns:repeat(4,1fr); } .at-desk-col-cart { width:320px; } }
</style><style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --primary:#2563eb; --primary-dark:#1d4ed8;--light:#ffffff;
    --success:#059669; --danger:#dc2626; --surface:#ffffff; --surface-2:#f8fafc;
    --border:#e2e8f0; --border-2:#cbd5e1; --text-1:#0f172a; --text-2:#475569; --text-3:#94a3b8;
    --radius-sm:8px; --radius-md:12px; --radius-lg:16px; --radius-xl:20px;
    --shadow-sm:0 1px 3px rgba(0,0,0,.08); --shadow-md:0 4px 12px rgba(0,0,0,.10);
    --font:'DM Sans',sans-serif; --font-mono:'DM Mono',monospace;
}
body { font-family:var(--font); background:#ffffff; color:var(--text-1); min-height:100vh; overflow-x:hidden; }

/* TOPBAR */
.at-topbar { display:flex; align-items:center; justify-content:space-between; padding:0 20px; height:56px; background:var(--surface); border-bottom:1.5px solid var(--border); position:sticky; top:0; z-index:400; box-shadow:var(--shadow-sm); }
.at-topbar-brand { display:flex; align-items:center; gap:10px; }
.at-topbar-brand h1 { font-size:16px; font-weight:700; color:var(--text-1); letter-spacing:-.3px; }
.at-topbar-brand p  { font-size:12px; color:var(--text-3); display:none; }
.at-topbar-right    { display:flex; align-items:center; gap:10px; }
.at-user-chip { font-size:12px; font-weight:600; color:var(--text-2); background:var(--surface-2); border:1.5px solid var(--border); padding:5px 12px; border-radius:20px; display:none; }
.at-btn-back { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; background:var(--surface-2); color:var(--text-2); border:1.5px solid var(--border); border-radius:20px; font-size:12px; font-weight:600; text-decoration:none; font-family:var(--font); transition:background .15s,border-color .15s; }
.at-btn-back:hover { background:var(--border); border-color:var(--border-2); }

/* ALERT */
.at-alert { padding:13px 16px; font-size:14px; font-weight:500; line-height:1.4; border-left:4px solid; border-radius:var(--radius-md); animation:slideDown .25s ease; }
.at-alert.success { background:#ecfdf5; color:#065f46; border-color:var(--success); }
.at-alert.danger  { background:#fef2f2; color:#7f1d1d; border-color:var(--danger); }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* BADGE */
.at-badge { background:var(--primary); color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; margin-left:6px; }

/* INPUTS */
.at-input { width:100%; padding:10px 12px; border:2px solid var(--border); border-radius:var(--radius-sm); font-size:14px; font-family:var(--font); outline:none; background:var(--surface); color:var(--text-1); transition:border-color .2s,box-shadow .2s; }
.at-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.at-search { width:100%; padding:10px 12px 10px 36px; border:2px solid var(--border); border-radius:var(--radius-sm); font-size:14px; font-family:var(--font); outline:none; background:var(--surface); color:var(--text-1); transition:border-color .2s,box-shadow .2s; }
.at-search:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.at-search-ico { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:var(--text-3); pointer-events:none; font-family:var(--font); font-weight:700; }

/* SEARCH RESULTS */
.at-search-results { position:absolute; top:calc(100% + 6px); left:0; right:0; background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); box-shadow:var(--shadow-md); display:none; max-height:260px; overflow-y:auto; z-index:100; }
.at-search-results.show { display:block; }
.at-res-item { display:flex; align-items:center; gap:10px; padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--surface-2); transition:background .1s; }
.at-res-item:last-child { border-bottom:none; }
.at-res-item:hover { background:var(--primary-light); }
.at-res-thumb { width:40px; height:40px; object-fit:cover; border-radius:var(--radius-sm); border:1.5px solid var(--border); flex-shrink:0; }
.at-res-nome  { font-size:13px; font-weight:700; }
.at-res-sub   { font-size:11px; color:var(--text-3); margin-top:1px; }
.at-res-preco { font-size:13px; font-weight:700; color:var(--primary); white-space:nowrap; flex-shrink:0; }
.at-no-res    { padding:20px; text-align:center; color:var(--text-3); font-size:13px; }

/* CATEGORIAS */
.at-cats { display:flex; gap:6px; overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; padding-bottom:2px; }
.at-cats::-webkit-scrollbar { display:none; }
.at-cat-pill { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1.5px solid var(--border); border-radius:20px; font-size:12px; font-weight:600; color:var(--text-2); cursor:pointer; white-space:nowrap; background:var(--surface); font-family:var(--font); transition:background .15s,border-color .15s,color .15s; flex-shrink:0; -webkit-tap-highlight-color:transparent; }
.at-cat-pill:hover { border-color:var(--primary); color:var(--primary); }
.at-cat-pill.ativa { background:var(--primary); border-color:var(--primary); color:#fff; }
.at-cat-count { font-size:10px; font-weight:700; opacity:.75; }

/* GRID PRODUTOS */
.at-prod-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
.at-prod-card { background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); overflow:hidden; cursor:pointer; transition:border-color .15s,box-shadow .15s,transform .1s; }
.at-prod-card:hover  { border-color:var(--primary); box-shadow:0 4px 14px rgba(37,99,235,.15); }
.at-prod-card:active { transform:scale(.98); }
.at-prod-img  { width:100%; height:96px; object-fit:cover; background:var(--surface-2); display:block; }
.at-prod-body { padding:9px 10px 10px; }
.at-prod-nome { font-size:13px; font-weight:700; line-height:1.3; margin-bottom:2px; }
.at-prod-sub  { font-size:10px; color:var(--text-3); margin-bottom:7px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.at-prod-footer { display:flex; align-items:flex-end; justify-content:space-between; gap:6px; }
.at-prod-preco     { font-size:14px; font-weight:700; color:var(--primary); font-family:var(--font-mono); }
.at-prod-preco-old { font-size:10px; color:var(--text-3); text-decoration:line-through; font-family:var(--font-mono); margin-top:1px; }
.at-prod-estoque   { font-size:10px; color:var(--text-3); margin-top:2px; }
.at-prod-promo-badge { display:inline-block; background:#dc2626; color:#fff; font-size:9px; font-weight:800; padding:2px 5px; border-radius:4px; margin-bottom:3px; letter-spacing:.3px; }
.at-prod-btn { padding:6px 11px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-sm); font-size:11px; font-weight:700; font-family:var(--font); cursor:pointer; white-space:nowrap; flex-shrink:0; -webkit-tap-highlight-color:transparent; transition:background .15s; }
.at-prod-btn:hover  { background:var(--primary-dark); }
.at-prod-btn:active { opacity:.85; }
.at-grid-loading, .at-grid-vazio { text-align:center; padding:32px 16px; color:var(--text-3); font-size:14px; grid-column:1/-1; }

/* ADICIONAL AVULSO */
.avul-card { display:flex; align-items:center; justify-content:space-between; background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); padding:11px 13px; cursor:pointer; transition:border-color .15s,box-shadow .15s,transform .1s; gap:10px; }
.avul-card:hover  { border-color:var(--primary); box-shadow:0 4px 14px rgba(37,99,235,.12); }
.avul-card:active { transform:scale(.98); }
.avul-nome  { font-size:13px; font-weight:700; flex:1; min-width:0; }
.avul-preco { font-size:13px; font-weight:700; color:var(--success); white-space:nowrap; font-family:var(--font-mono); }
.avul-btn   { padding:6px 12px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-sm); font-size:11px; font-weight:700; font-family:var(--font); cursor:pointer; white-space:nowrap; flex-shrink:0; transition:background .15s; }
.avul-btn:hover  { background:var(--primary-dark); }
.avul-lista-grid { display:flex; flex-direction:column; gap:8px; }

/* CARRINHO */
.at-cart-vazio { text-align:center; color:var(--text-3); font-size:13px; padding:16px 0; }
.at-cart-item  { display:flex; align-items:flex-start; gap:10px; padding:11px 0; border-bottom:1px solid var(--surface-2); animation:fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateX(-6px)} to{opacity:1;transform:translateX(0)} }
.at-cart-item:last-of-type { border-bottom:none; }
.at-ci-thumb { width:40px; height:40px; object-fit:cover; border-radius:var(--radius-sm); border:1.5px solid var(--border); flex-shrink:0; margin-top:1px; }
.at-ci-thumb.avulso { display:flex; align-items:center; justify-content:center; background:var(--primary-light); font-size:18px; border-color:#bfdbfe; }
.at-ci-info  { flex:1; min-width:0; }
.at-ci-nome  { font-size:13px; font-weight:700; line-height:1.3; }
.at-ci-avulso-tag { display:inline-block; background:#fef3c7; color:#92400e; font-size:9px; font-weight:800; padding:2px 6px; border-radius:4px; margin-top:2px; letter-spacing:.3px; }
.at-ci-adicionais { font-size:11px; color:var(--primary); margin-top:2px; font-weight:600; }
.at-ci-obs   { font-size:11px; color:var(--text-3); margin-top:2px; font-style:italic; }
.at-ci-bottom { display:flex; align-items:center; justify-content:space-between; margin-top:7px; }
.at-ci-qty   { display:flex; align-items:center; background:var(--surface-2); border:1.5px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; }
.at-ci-qty-btn { width:28px; height:28px; background:transparent; border:none; font-size:15px; font-weight:700; color:var(--primary); cursor:pointer; display:flex; align-items:center; justify-content:center; font-family:var(--font); transition:background .1s; }
.at-ci-qty-btn:hover { background:var(--primary-light); }
.at-ci-qty-num { width:28px; text-align:center; font-size:13px; font-weight:700; border:none; background:transparent; outline:none; font-family:var(--font-mono); color:var(--text-1); border-left:1px solid var(--border); border-right:1px solid var(--border); }
.at-ci-preco { font-size:13px; font-weight:700; color:var(--success); font-family:var(--font-mono); }
.at-ci-del   { width:28px; height:28px; background:#fef2f2; border:1.5px solid #fecaca; border-radius:var(--radius-sm); color:var(--danger); font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; flex-shrink:0; margin-left:8px; transition:background .1s; }
.at-ci-del:hover { background:#fee2e2; }
.at-cart-total { display:flex; justify-content:space-between; align-items:center; padding:12px 0 0; border-top:2px solid var(--border); margin-top:6px; font-size:16px; font-weight:700; color:var(--text-1); }
.at-cart-total-val { font-family:var(--font-mono); color:var(--success); }

/* BOTÕES */
.at-btn-limpar  { padding:5px 12px; background:#fef2f2; color:var(--danger); border:1.5px solid #fecaca; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font); transition:background .1s; }
.at-btn-limpar:hover { background:#fee2e2; }
.at-btn-refresh { padding:6px 12px; background:var(--primary-light); color:var(--primary); border:1.5px solid #bfdbfe; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font); transition:background .1s; }
.at-btn-refresh:hover { background:#dbeafe; }

/* SKELETON */
.at-cmd-skeleton { height:52px; background:linear-gradient(90deg,var(--surface-2) 25%,var(--border) 50%,var(--surface-2) 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; border-radius:var(--radius-md); margin-bottom:8px; }
@keyframes shimmer { to{background-position:-200% 0} }

/* COMANDAS ABERTAS */
.at-cmd-aberta-card { background:var(--surface); border:1.5px solid var(--border); border-radius:var(--radius-md); overflow:hidden; margin-bottom:8px; }
@keyframes fadeSlideIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.at-cmd-aberta-header { display:flex; align-items:center; justify-content:space-between; padding:11px 14px; background:var(--surface-2); border-bottom:1px solid var(--border); cursor:pointer; -webkit-tap-highlight-color:transparent; }
.at-cmd-aberta-num      { font-size:14px; font-weight:700; color:var(--primary); font-family:var(--font-mono); }
.at-cmd-aberta-meta     { font-size:11px; color:var(--text-3); margin-top:2px; }
.at-cmd-aberta-lancador { font-size:11px; color:var(--text-2); font-weight:600; margin-top:1px; }
.at-cmd-mesa-badge { display:inline-flex; align-items:center; gap:4px; background:#dbeafe; color:#1e40af; font-size:11px; font-weight:800; padding:3px 9px; border-radius:20px; margin-top:4px; letter-spacing:.2px; }
.at-cmd-aberta-valor    { font-size:14px; font-weight:700; color:var(--success); white-space:nowrap; font-family:var(--font-mono); }
.at-cmd-aberta-seta     { font-size:11px; color:var(--text-3); margin-left:10px; transition:transform .2s; font-family:var(--font); font-weight:700; }
.at-cmd-aberta-card.aberto .at-cmd-aberta-seta { transform:rotate(180deg); }
.at-cmd-aberta-body     { display:none; padding:10px 14px 12px; }
.at-cmd-aberta-card.aberto .at-cmd-aberta-body { display:block; }
.at-cmd-aberta-obs-geral { font-size:12px; color:#92400e; font-style:italic; margin-bottom:10px; padding:7px 10px; background:#fffbeb; border-radius:var(--radius-sm); border-left:3px solid #f59e0b; }
.at-cmd-item-linha { display:flex; align-items:flex-start; gap:10px; padding:8px 0; border-bottom:1px solid var(--surface-2); }
.at-cmd-item-linha:last-child { border-bottom:none; }
.at-cmd-item-qty  { min-width:26px; height:26px; background:var(--primary); color:#fff; border-radius:6px; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; font-family:var(--font-mono); }
.at-cmd-item-qty.avulso-qty { background:#f59e0b; }
.at-cmd-item-info { flex:1; min-width:0; }
.at-cmd-item-nome { font-size:13px; font-weight:700; }
.at-cmd-item-ads  { font-size:11px; color:var(--primary); font-weight:600; margin-top:2px; }
.at-cmd-item-obs  { font-size:11px; color:var(--text-3); font-style:italic; margin-top:2px; }
.at-cmd-item-sub  { font-size:13px; font-weight:700; color:var(--success); white-space:nowrap; flex-shrink:0; align-self:center; font-family:var(--font-mono); }
.at-cmd-aberta-total { display:flex; justify-content:space-between; align-items:center; padding:10px 0 0; border-top:2px solid var(--border); margin-top:4px; font-size:14px; font-weight:700; }
.at-cmd-aberta-vazio { text-align:center; color:var(--text-3); font-size:13px; padding:20px 0; }
.at-cmd-item-detalhe { display:none; margin-top:5px; padding:6px 9px; background:var(--primary-light); border-radius:6px; border-left:3px solid var(--primary); }
.at-cmd-item-detalhe.visivel { display:block; }

/* MODAL */
.at-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:flex-end; justify-content:center; backdrop-filter:blur(2px); }
.at-modal-overlay.show { display:flex; }
.at-modal { background:var(--surface); border-radius:var(--radius-xl) var(--radius-xl) 0 0; padding:20px 18px calc(20px + env(safe-area-inset-bottom)); width:100%; max-width:600px; max-height:88vh; overflow-y:auto; box-shadow:0 -8px 40px rgba(0,0,0,.2); animation:slideUp .25s ease; }
@keyframes slideUp { from{transform:translateY(40px);opacity:0} to{transform:translateY(0);opacity:1} }
.at-modal-drag { width:40px; height:4px; background:var(--border); border-radius:4px; margin:0 auto 14px; }
.at-modal h3   { font-size:17px; font-weight:700; margin-bottom:3px; letter-spacing:-.3px; }
.at-modal-sub  { font-size:13px; color:var(--text-2); margin-bottom:14px; font-family:var(--font-mono); }
.at-adicional-titulo { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-2); margin-bottom:8px; }
.at-adicional-item   { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--surface-2); }
.at-adicional-item:last-child { border-bottom:none; }
.at-adicional-check  { width:22px; height:22px; border:2px solid var(--border); border-radius:6px; cursor:pointer; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; transition:background .15s,border-color .15s; -webkit-tap-highlight-color:transparent; }
.at-adicional-check.checked { background:var(--primary); border-color:var(--primary); }
.at-adicional-nome   { flex:1; font-size:14px; font-weight:600; }
.at-adicional-preco  { font-size:13px; font-weight:700; color:var(--success); white-space:nowrap; font-family:var(--font-mono); }
.at-modal-sep { border:none; border-top:1px solid var(--border); margin:14px 0; }
.at-modal-obs label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-2); display:block; margin-bottom:6px; }
.at-modal-obs textarea { width:100%; padding:10px 12px; border:2px solid var(--border); border-radius:var(--radius-sm); font-size:14px; font-family:var(--font); outline:none; resize:none; height:64px; transition:border-color .2s; color:var(--text-1); }
.at-modal-obs textarea:focus { border-color:var(--primary); }
.at-modal-qty-row   { display:flex; align-items:center; justify-content:space-between; margin-top:12px; }
.at-modal-qty-label { font-size:13px; font-weight:700; }
.at-modal-qty { display:flex; align-items:center; background:var(--surface-2); border:1.5px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; }
.at-modal-qty-btn { width:40px; height:40px; background:transparent; border:none; font-size:18px; font-weight:700; color:var(--primary); cursor:pointer; display:flex; align-items:center; justify-content:center; font-family:var(--font); transition:background .1s; }
.at-modal-qty-btn:hover { background:var(--primary-light); }
.at-modal-qty-num { width:44px; text-align:center; font-size:16px; font-weight:700; border:none; background:transparent; outline:none; font-family:var(--font-mono); border-left:1px solid var(--border); border-right:1px solid var(--border); }
.at-modal-btns { display:flex; gap:10px; margin-top:16px; }
.at-btn-add-item { flex:1; padding:14px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-md); font-size:15px; font-weight:700; font-family:var(--font); cursor:pointer; transition:background .15s; }
.at-btn-add-item:hover { background:var(--primary-dark); }
.at-btn-cancel-modal { padding:14px 18px; background:var(--surface-2); color:var(--text-2); border:1.5px solid var(--border); border-radius:var(--radius-md); font-size:14px; font-weight:600; font-family:var(--font); cursor:pointer; }

/* MOBILE TABS */
.at-tab-bar { display:flex; background:var(--surface); border-bottom:1.5px solid var(--border); position:sticky; top:56px; z-index:300; }
.at-tab { flex:1; padding:11px 6px; text-align:center; font-size:12px; font-weight:700; color:var(--text-3); border-bottom:3px solid transparent; cursor:pointer; font-family:var(--font); transition:color .15s,border-color .15s; -webkit-tap-highlight-color:transparent; user-select:none; }
.at-tab.ativa { color:var(--primary); border-color:var(--primary); background:var(--primary-light); }
.at-tab-badge { display:inline-block; background:var(--primary); color:#fff; font-size:9px; font-weight:700; padding:1px 5px; border-radius:20px; margin-left:4px; vertical-align:middle; }
.at-tab-badge.hidden { display:none; }
.at-tab-panel { display:none; background:#fff; }
.at-tab-panel.ativo { display:block; background:#fff; }

.at-comanda-sticky { background:var(--surface); border-bottom:1.5px solid var(--border); padding:10px 14px; display:flex; gap:8px; }
.at-comanda-sticky .at-field { flex:1; display:flex; flex-direction:column; gap:4px; }
.at-comanda-sticky label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-3); }
.at-comanda-sticky input { padding:8px 10px; border:1.5px solid var(--border); border-radius:var(--radius-sm); font-size:15px; font-family:var(--font); outline:none; background:var(--surface); color:var(--text-1); transition:border-color .2s; }
.at-comanda-sticky input:focus { border-color:var(--primary); }
.at-comanda-num-field { flex:0 0 120px; }
.at-panel-produtos { padding:12px 14px; display:flex; flex-direction:column; gap:10px; }
.at-search-pos { position:relative; }
.at-panel-carrinho { padding:12px 14px 100px; background:#fff !important; }
#panel-carrinho { background:#fff !important; }
#at-cart-body { background:#fff; border-radius:12px; }
.at-panel-avulsos { padding:12px 14px; display:flex; flex-direction:column; gap:10px; }
.at-panel-avulsos .avul-info-tip { font-size:12px; color:var(--text-3); padding:8px 12px; background:#fffbeb; border-radius:var(--radius-sm); border-left:3px solid #f59e0b; }
.at-panel-comandas { padding:12px 14px; }
.at-panel-comandas-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.at-panel-comandas-header h3 { font-size:14px; font-weight:700; }

.at-fab { position:fixed; bottom:0; left:0; right:0; z-index:300; padding:10px 14px calc(10px + env(safe-area-inset-bottom)); background:var(--surface); border-top:1.5px solid var(--border); box-shadow:0 -4px 20px rgba(0,0,0,.1); display:none; }
.at-fab.show { display:block; }
.at-fab-btn { width:100%; padding:14px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-md); font-size:15px; font-weight:700; font-family:var(--font); cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 14px rgba(37,99,235,.35); transition:background .15s; }
.at-fab-btn:hover  { background:var(--primary-dark); }
.at-fab-btn:active { transform:scale(.99); }

/* DESKTOP */
.at-desktop-layout { display:none; height:calc(100vh - 56px); overflow:hidden; }
.at-desk-col-prods { flex:1; display:flex; flex-direction:column; border-right:1.5px solid var(--border); overflow:hidden; min-width:0; }
.at-desk-col-cart  { width:300px; flex-shrink:0; display:flex; flex-direction:column; overflow:hidden; background:var(--surface); }
.at-desk-col-head  { padding:12px 16px; background:var(--surface-2); border-bottom:1.5px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.at-desk-col-head h2 { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--text-2); }
.at-desk-filtros { padding:12px 16px; border-bottom:1.5px solid var(--border); display:flex; flex-direction:column; gap:8px; flex-shrink:0; background:var(--surface); }
.at-desk-grid-wrap { flex:1; overflow-y:auto; padding:14px 16px; background:var(--surface-2); }
.at-desk-prod-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.at-desk-comanda-area { padding:14px; border-bottom:1.5px solid var(--border); display:flex; flex-direction:column; gap:8px; flex-shrink:0; background:var(--surface); }
.at-desk-comanda-area label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-3); display:block; margin-bottom:4px; }
.at-desk-cart-items  { flex:1; overflow-y:auto; padding:10px 14px; }
.at-desk-cart-footer { padding:12px 14px calc(12px + env(safe-area-inset-bottom)); border-top:1.5px solid var(--border); background:var(--surface-2); flex-shrink:0; }
.at-desk-total { display:flex; justify-content:space-between; align-items:center; font-size:15px; font-weight:700; margin-bottom:10px; }
.at-desk-total-val { font-family:var(--font-mono); color:var(--success); }
.at-desk-launch-btn { width:100%; padding:13px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-md); font-size:14px; font-weight:700; font-family:var(--font); cursor:pointer; transition:background .15s; }
.at-desk-launch-btn:hover  { background:var(--primary-dark); }
.at-desk-launch-btn:active { opacity:.9; }

/* Painel avulsos desktop */
.at-desk-avulsos-box { padding:10px 0 4px; display:none; flex-direction:column; gap:8px; }
.at-desk-avulsos-box.aberto { display:flex; }
.at-desk-avulsos-box .avul-lista-compact { display:flex; flex-direction:column; gap:5px; max-height:200px; overflow-y:auto; margin-top:4px; }

/* Comandas desktop */
.at-desk-comandas-wrap { flex-shrink:0; border-top:1.5px solid var(--border); display:flex; flex-direction:column; }
.at-desk-cmd-toggle { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; cursor:pointer; background:var(--surface-2); border-bottom:1px solid var(--border); flex-shrink:0; -webkit-tap-highlight-color:transparent; }
.at-desk-cmd-toggle span { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-2); }
#desk-cmd-seta { font-size:11px; font-weight:700; color:var(--text-3); transition:transform .2s; display:inline-block; }
.at-desk-cmd-body { overflow-y:auto; max-height:260px; padding:8px 14px 10px; }
.at-desk-cmd-body.fechado { display:none; }

@media (min-width:768px) { .at-user-chip { display:inline-flex; } .at-topbar-brand p { display:block; } }
@media (min-width:900px) { .at-mobile-layout { display:none !important; } .at-tab-bar { display:none !important; } .at-fab { display:none !important; } .at-desktop-layout { display:flex; } }
@media (max-width:899px) { .at-desktop-layout { display:none !important; } .at-mobile-layout { display:block; } }
@media (max-width:480px) { .at-prod-grid { grid-template-columns:repeat(2,1fr); gap:8px; } .at-prod-img { height:82px; } .at-prod-nome { font-size:12px; } .at-prod-preco { font-size:13px; } .at-comanda-num-field { flex:0 0 100px; } }
@media (min-width:900px) and (max-width:1200px) { .at-desk-col-cart { width:260px; } .at-desk-prod-grid { grid-template-columns:repeat(2,1fr); } }
@media (min-width:1400px) { .at-desk-prod-grid { grid-template-columns:repeat(4,1fr); } .at-desk-col-cart { width:320px; } }
/* TOGGLE FOTOS */
.at-foto-toggle {
    width:42px; height:24px; background:#e2e8f0; border-radius:20px;
    position:relative; cursor:pointer; transition:background .25s;
    flex-shrink:0;
}
.at-foto-toggle::after {
    content:''; position:absolute; top:3px; left:3px;
    width:18px; height:18px; background:#fff; border-radius:50%;
    box-shadow:0 1px 4px rgba(0,0,0,.2); transition:transform .25s;
}
.at-foto-toggle.ativo { background:#22c55e; }
.at-foto-toggle.ativo::after { transform:translateX(18px); }

/* Quando fotos estão ocultas */
.sem-fotos .at-prod-img { display:none; }
</style>
</head>
<body class="fundo-branco">

<div class="at-topbar">
    <div class="at-topbar-brand">
        <div>
            <h1>Lancar Comanda</h1>
            <p>Selecione os produtos e envie para o caixa</p>
        </div>
    </div>
    <div class="at-topbar-right">
        <span class="at-user-chip"><?= htmlspecialchars($nome_lancador) ?></span>
        <?php if ($is_admin): ?>
            <a href="modo_restaurante.php" class="at-btn-back">Voltar</a>
        <?php else: ?>
            <a href="logout_vendedor.php" class="at-btn-back">Sair</a>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ MOBILE ═══ -->
<div class="at-mobile-layout">
    <?php if ($msg): ?>
    <div style="padding:10px 14px;">
        <div class="at-alert <?= $tipo ?>" id="at-msg-alert"><?= $msg ?></div>
    </div>
    <script>setTimeout(function(){var el=document.getElementById('at-msg-alert');if(el){el.style.transition='opacity .5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);}},6000);</script>
    <?php endif; ?>

    <div class="at-comanda-sticky">
        <div class="at-field at-comanda-num-field">
            <label>N. Comanda *</label>
            <input type="text" id="at-num-comanda" placeholder="Ex: 42" maxlength="20" autocomplete="off">
        </div>
        <div class="at-field" style="flex:1;">
            <label>N. Mesa</label>
            <input type="text" id="at-obs-comanda" placeholder="Ex: Mesa 5, Varanda..." maxlength="30" autocomplete="off">
        </div>
    </div>

    <div class="at-tab-bar">
        <div class="at-tab ativa" data-tab="produtos" onclick="trocarAba('produtos')">Produtos</div>
        <div class="at-tab" data-tab="avulsos" onclick="trocarAba('avulsos')">Avulsos</div>
        <div class="at-tab" data-tab="carrinho" onclick="trocarAba('carrinho')">Carrinho <span class="at-tab-badge hidden" id="mob-badge-cart">0</span></div>
        <div class="at-tab" data-tab="comandas" onclick="trocarAba('comandas')">Abertas <span class="at-tab-badge hidden" id="mob-badge-cmd">0</span></div>
    </div>


   <!-- PAINEL PRODUTOS -->
    <div class="at-tab-panel ativo" id="panel-produtos">
        <div class="at-panel-produtos">
            <div style="display:flex;align-items:center;gap:8px;">
                <div class="at-search-pos" style="flex:1;">
                    <div class="at-search-ico">Buscar</div>
                    <input type="text" id="at-search" class="at-search" placeholder="Buscar por nome, marca ou modelo..." autocomplete="off" style="padding-left:58px;">
                    <div class="at-search-results" id="at-search-results"></div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:center;gap:2px;flex-shrink:0;">
                    <span style="font-size:9px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Fotos</span>
                    <div class="at-foto-toggle" id="mob-foto-toggle" onclick="toggleFotos()"></div>
                </div>
            </div>
            <?php if (!empty($categorias)): ?>
            <div class="at-cats" id="at-cats">
                <button class="at-cat-pill ativa" data-id="0" onclick="selecionarCategoria(0,this)">Todos</button>
                <?php foreach ($categorias as $cat): ?>
                <button class="at-cat-pill" data-id="<?= $cat['id_categoria'] ?>" onclick="selecionarCategoria(<?= $cat['id_categoria'] ?>,this)">
                    <?= htmlspecialchars($cat['nome']) ?> <span class="at-cat-count">(<?= $cat['total'] ?>)</span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div id="at-prod-grid" class="at-prod-grid"><div class="at-grid-loading">Carregando produtos...</div></div>
        </div>
    </div>

    <!-- PAINEL AVULSOS -->
    <div class="at-tab-panel" id="panel-avulsos">
        <div class="at-panel-avulsos">
            <div class="avul-info-tip">
                Lance adicionais diretamente na comanda, sem produto base. Ex: ovo frito avulso, porcao extra, etc.
            </div>
            <div class="at-search-pos">
                <div class="at-search-ico">Buscar</div>
                <input type="text" id="avul-search" class="at-search"
                       placeholder="Buscar adicional..." autocomplete="off"
                       style="padding-left:58px;"
                       oninput="buscarAvulso(this.value)">
            </div>
            <div id="avul-lista" class="avul-lista-grid">
                <div class="at-grid-loading">Carregando adicionais...</div>
            </div>
        </div>
    </div>

    <!-- PAINEL CARRINHO -->
    <div class="at-tab-panel" id="panel-carrinho">
        <div class="at-panel-carrinho">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="font-size:14px;font-weight:700;">Itens <span class="at-badge" id="at-badge">0</span></div>
                <button type="button" class="at-btn-limpar" onclick="limparCarrinho()">Limpar tudo</button>
            </div>
            <div id="at-cart-body"><div class="at-cart-vazio">Nenhum produto adicionado ainda.</div></div>
        </div>
    </div>

    <!-- PAINEL COMANDAS ABERTAS -->
    <div class="at-tab-panel" id="panel-comandas">
        <div class="at-panel-comandas">
            <div class="at-panel-comandas-header">
                <h3>Comandas Abertas <span class="at-badge" id="at-badge-cmd">0</span></h3>
                <button type="button" class="at-btn-refresh" onclick="carregarComandasAbertas()">Atualizar</button>
            </div>
            <div id="mob-cmd-abertas-body">
                <div class="at-cmd-skeleton"></div>
                <div class="at-cmd-skeleton" style="width:70%;"></div>
            </div>
        </div>
    </div>

    <div class="at-fab" id="at-fab">
        <button type="button" class="at-fab-btn" onclick="lancarComanda()">Lancar Comanda para o Caixa</button>
    </div>
</div>

<!-- ═══ DESKTOP ═══ -->
<div class="at-desktop-layout">
    <div class="at-desk-col-prods">
        <div class="at-desk-col-head">
    <h2>Produtos</h2>
    <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:11px;color:var(--text-3);font-weight:600;">Fotos</span>
        <div class="at-foto-toggle" id="desk-foto-toggle" onclick="toggleFotos()" title="Mostrar/ocultar fotos"></div>
        <span class="at-badge" id="desk-badge-prod" style="font-size:11px;">—</span>
    </div>
</div>
        <div class="at-desk-filtros">
            <div class="at-search-pos">
                <div class="at-search-ico">Buscar</div>
                <input type="text" id="desk-search" class="at-search" placeholder="Buscar por nome, marca ou modelo..." autocomplete="off" style="padding-left:58px;" oninput="deskSearch(this.value)">
                <div class="at-search-results" id="desk-search-results"></div>
            </div>
            <?php if (!empty($categorias)): ?>
            <div class="at-cats" id="desk-cats">
                <button class="at-cat-pill ativa" data-id="0" onclick="deskCategoria(0,this)">Todos</button>
                <?php foreach ($categorias as $cat): ?>
                <button class="at-cat-pill" data-id="<?= $cat['id_categoria'] ?>" onclick="deskCategoria(<?= $cat['id_categoria'] ?>,this)">
                    <?= htmlspecialchars($cat['nome']) ?> <span class="at-cat-count">(<?= $cat['total'] ?>)</span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <!-- Botão avulsos desktop -->
            <div style="display:flex;align-items:center;gap:8px;">
                <button class="at-cat-pill" id="desk-btn-avulsos" onclick="toggleDeskAvulsos()"
                        style="background:var(--primary-light);border-color:#bfdbfe;color:var(--primary);font-weight:700;">
                    + Adicional Avulso
                </button>
            </div>
            <!-- Painel avulsos desktop -->
            <div class="at-desk-avulsos-box" id="desk-avulsos-box">
                <div style="font-size:12px;color:var(--text-3);padding:2px 0 4px;">
                    Lance adicionais sem produto base diretamente na comanda.
                </div>
                <div class="at-search-pos">
                    <div class="at-search-ico">Buscar</div>
                    <input type="text" id="desk-avul-search" class="at-search"
                           placeholder="Buscar adicional..." autocomplete="off"
                           style="padding-left:58px;"
                           oninput="buscarAvulsoDesk(this.value)">
                </div>
                <div class="avul-lista-compact" id="desk-avul-lista"></div>
            </div>
        </div>
        <div class="at-desk-grid-wrap">
            <div id="desk-prod-grid" class="at-desk-prod-grid"><div class="at-grid-loading">Carregando produtos...</div></div>
        </div>
    </div>

    <div class="at-desk-col-cart">
        <div class="at-desk-col-head"><h2>Comanda</h2></div>

        <div class="at-desk-comanda-area">
            <?php if ($msg): ?>
            <div class="at-alert <?= $tipo ?>" id="desk-msg-alert"><?= $msg ?></div>
            <script>setTimeout(function(){var el=document.getElementById('desk-msg-alert');if(el){el.style.transition='opacity .5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);}},6000);</script>
            <?php endif; ?>
            <div>
                <label>N. Comanda *</label>
                <input type="text" class="at-input" id="desk-num-comanda" placeholder="Ex: 42" maxlength="20" autocomplete="off">
            </div>
            <div>
                <label>N. Mesa</label>
                <input type="text" class="at-input" id="desk-obs-comanda" placeholder="Ex: Mesa 5, Varanda..." maxlength="30" autocomplete="off">
            </div>
        </div>

        <div class="at-desk-col-head" style="flex-shrink:0;">
            <h2>Itens <span class="at-badge" id="desk-badge-cart">0</span></h2>
            <button type="button" class="at-btn-limpar" onclick="limparCarrinho()">Limpar</button>
        </div>

        <div class="at-desk-cart-items" id="desk-cart-body">
            <div class="at-cart-vazio">Nenhum produto adicionado ainda.</div>
        </div>

        <div class="at-desk-comandas-wrap">
            <div class="at-desk-cmd-toggle" onclick="toggleDeskComandas()">
                <span>Abertas <span class="at-badge" id="desk-badge-cmd">0</span></span>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="button" class="at-btn-refresh" onclick="event.stopPropagation();carregarComandasAbertas()" style="font-size:10px;padding:4px 9px;">Atualizar</button>
                    <span id="desk-cmd-seta">V</span>
                </div>
            </div>
            <div class="at-desk-cmd-body fechado" id="desk-cmd-abertas-body">
                <div class="at-cmd-skeleton"></div>
                <div class="at-cmd-skeleton" style="width:70%;"></div>
            </div>
        </div>

        <div class="at-desk-cart-footer">
            <div class="at-desk-total">
                <span>Total</span>
                <span class="at-desk-total-val" id="desk-total-val">R$ 0,00</span>
            </div>
            <button type="button" class="at-desk-launch-btn" onclick="lancarComanda()">Lancar para o Caixa</button>
        </div>
    </div>
</div>

<!-- MODAL PRODUTO -->
<div class="at-modal-overlay" id="at-modal">
    <div class="at-modal">
        <div class="at-modal-drag"></div>
        <h3 id="at-modal-title">Produto</h3>
        <div class="at-modal-sub" id="at-modal-preco"></div>
        <div id="at-adicionais-lista"></div>
        <hr class="at-modal-sep">
        <div class="at-modal-obs">
            <label>Observacao do item <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></label>
            <textarea id="at-modal-obs-item" placeholder="Ex: bem passado, sem molho..."></textarea>
        </div>
        <div class="at-modal-qty-row">
            <span class="at-modal-qty-label">Quantidade</span>
            <div class="at-modal-qty">
                <button type="button" class="at-modal-qty-btn" onclick="modalQty(-1)">-</button>
                <input type="number" class="at-modal-qty-num" id="at-modal-qty" value="1" min="1">
                <button type="button" class="at-modal-qty-btn" onclick="modalQty(+1)">+</button>
            </div>
        </div>
        <div class="at-modal-btns">
            <button type="button" class="at-btn-cancel-modal" onclick="fecharModal()">Cancelar</button>
            <button type="button" class="at-btn-add-item" onclick="confirmarItem()">Adicionar ao Carrinho</button>
        </div>
    </div>
</div>

<!-- MODAL ADICIONAL AVULSO -->
<div class="at-modal-overlay" id="at-modal-avulso">
    <div class="at-modal">
        <div class="at-modal-drag"></div>
        <div style="display:inline-block;background:#fef3c7;color:#92400e;font-size:10px;font-weight:800;padding:3px 9px;border-radius:20px;margin-bottom:8px;letter-spacing:.4px;">ADICIONAL AVULSO</div>
        <h3 id="avul-modal-title">Adicional</h3>
        <div class="at-modal-sub" id="avul-modal-preco"></div>
        <hr class="at-modal-sep">
        <div class="at-modal-obs">
            <label>Observacao <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></label>
            <textarea id="avul-modal-obs" placeholder="Ex: bem passado, sem sal, extra crocante..."></textarea>
        </div>
        <div class="at-modal-qty-row">
            <span class="at-modal-qty-label">Quantidade</span>
            <div class="at-modal-qty">
                <button type="button" class="at-modal-qty-btn" onclick="avulQty(-1)">-</button>
                <input type="number" class="at-modal-qty-num" id="avul-modal-qty" value="1" min="1">
                <button type="button" class="at-modal-qty-btn" onclick="avulQty(+1)">+</button>
            </div>
        </div>
        <div class="at-modal-btns">
            <button type="button" class="at-btn-cancel-modal" onclick="fecharModalAvulso()">Cancelar</button>
            <button type="button" class="at-btn-add-item" onclick="confirmarAvulso()">Adicionar ao Carrinho</button>
        </div>
    </div>
</div>

<form method="POST" id="at-form" style="display:none;">
    <input type="hidden" name="numero_comanda" id="hid-num">
    <input type="hidden" name="observacao_cmd" id="hid-obs">
    <input type="hidden" name="itens_json"     id="hid-itens">
</form>

<script>
let carrinho = [], debTimer, debDeskTimer, produtoAtual = null, adicionaisSelecionados = {};
let deskComandasAberto = false;
let avulAtual = null, avulDebTimer, deskAvulsosAberto = false;

/* ═══════════════════════════════════
   ABAS MOBILE
═══════════════════════════════════ */
function trocarAba(tab) {
    document.querySelectorAll('.at-tab').forEach(t=>t.classList.remove('ativa'));
    document.querySelectorAll('.at-tab-panel').forEach(p=>p.classList.remove('ativo'));
    document.querySelector('.at-tab[data-tab="'+tab+'"]').classList.add('ativa');
    document.getElementById('panel-'+tab).classList.add('ativo');
    // Carrega avulsos ao abrir a aba pela primeira vez
    if (tab === 'avulsos') {
        const lista = document.getElementById('avul-lista');
        if (lista && lista.querySelector('.at-grid-loading')) {
            carregarAvulsos('', 'avul-lista');
        }
    }
}

/* ═══════════════════════════════════
   UTILITÁRIOS
═══════════════════════════════════ */
function fmt(n) { return parseFloat(n).toLocaleString('pt-BR',{minimumFractionDigits:2}); }
function precoEfetivo(p) { return (p.preco_promocional && parseFloat(p.preco_promocional)>0) ? parseFloat(p.preco_promocional) : parseFloat(p.preco); }
function totalCarrinho() { return carrinho.reduce((s,i)=>s+i.subtotal,0); }

/* ═══════════════════════════════════
   CARRINHO
═══════════════════════════════════ */
function renderCarrinho() {
    const total=totalCarrinho(), qtd=carrinho.reduce((s,i)=>s+i.quantidade,0);
    const bm=document.getElementById('at-badge'), bt=document.getElementById('mob-badge-cart'), bd=document.getElementById('desk-badge-cart');
    if(bm) bm.textContent=qtd;
    if(bt){bt.textContent=qtd;bt.className=qtd?'at-tab-badge':'at-tab-badge hidden';}
    if(bd) bd.textContent=qtd;
    const dv=document.getElementById('desk-total-val'); if(dv) dv.textContent='R$ '+fmt(total);
    const fab=document.getElementById('at-fab'); if(fab) fab.className=carrinho.length?'at-fab show':'at-fab';

    const html=carrinho.length
        ? carrinho.map((item,idx)=>{
            const isAvulso = item.is_avulso;
            const thumbHtml = isAvulso
                ? `<div class="at-ci-thumb avulso">+</div>`
                : `<img class="at-ci-thumb" src="uploads/${item.imagem||'placeholder.jpg'}" onerror="this.src='uploads/placeholder.jpg'">`;
            return `
            <div class="at-cart-item">
                ${thumbHtml}
                <div class="at-ci-info">
                    <div class="at-ci-nome">${item.nome}</div>

                    ${item.adicionais.length?`<div class="at-ci-adicionais">+ ${item.adicionais.map(a=>a.nome).join(', ')}</div>`:''}
                    ${item.obs?`<div class="at-ci-obs">"${item.obs}"</div>`:''}
                    <div class="at-ci-bottom">
                        <div class="at-ci-qty">
                            <button class="at-ci-qty-btn" onclick="alterarQtyItem(${idx},-1)">-</button>
                            <span class="at-ci-qty-num">${item.quantidade}</span>
                            <button class="at-ci-qty-btn" onclick="alterarQtyItem(${idx},+1)">+</button>
                        </div>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <span class="at-ci-preco">R$ ${fmt(item.subtotal)}</span>
                            <button class="at-ci-del" onclick="removerItem(${idx})">X</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('')+`<div class="at-cart-total"><span>Total</span><span class="at-cart-total-val">R$ ${fmt(total)}</span></div>`
        : '<div class="at-cart-vazio">Nenhum produto adicionado ainda.</div>';

    const mob=document.getElementById('at-cart-body'), desk=document.getElementById('desk-cart-body');
    if(mob)  mob.innerHTML=html;
    if(desk) desk.innerHTML=html;
}

function removerItem(idx){carrinho.splice(idx,1);renderCarrinho();}
function limparCarrinho(){if(!carrinho.length)return;if(!confirm('Limpar todos os itens?'))return;carrinho=[];renderCarrinho();}
function alterarQtyItem(idx,delta){
    carrinho[idx].quantidade=Math.max(1,carrinho[idx].quantidade+delta);
    const extras=carrinho[idx].adicionais.reduce((s,a)=>s+parseFloat(a.preco),0);
    carrinho[idx].subtotal=(carrinho[idx].preco+extras)*carrinho[idx].quantidade;
    renderCarrinho();
}

/* ═══════════════════════════════════
   PRODUTOS — busca e categorias
═══════════════════════════════════ */
async function selecionarCategoria(id,btn){
    document.querySelectorAll('#at-cats .at-cat-pill').forEach(p=>p.classList.remove('ativa'));
    if(btn)btn.classList.add('ativa');fecharBusca();document.getElementById('at-search').value='';
    const grid=document.getElementById('at-prod-grid');grid.innerHTML='<div class="at-grid-loading">Carregando...</div>';
    try{renderGrid(await(await fetch('atendente.php?categoria='+id)).json(),grid);}
    catch(e){grid.innerHTML='<div class="at-grid-vazio">Erro ao carregar produtos.</div>';}
}
document.getElementById('at-search').addEventListener('input',function(){
    clearTimeout(debTimer);const q=this.value.trim();
    if(q.length<2){fecharBusca();return;}
    debTimer=setTimeout(()=>buscarMob(q),280);
});
async function buscarMob(q){try{renderBusca(await(await fetch('atendente.php?buscar='+encodeURIComponent(q))).json(),'at-search-results');}catch(e){}}

async function deskCategoria(id,btn){
    document.querySelectorAll('#desk-cats .at-cat-pill').forEach(p=>p.classList.remove('ativa'));
    if(btn)btn.classList.add('ativa');fecharBuscaDesk();document.getElementById('desk-search').value='';
    const grid=document.getElementById('desk-prod-grid');grid.innerHTML='<div class="at-grid-loading">Carregando...</div>';
    try{const prods=await(await fetch('atendente.php?categoria='+id)).json();const badge=document.getElementById('desk-badge-prod');if(badge)badge.textContent=prods.length;renderGrid(prods,grid);}
    catch(e){grid.innerHTML='<div class="at-grid-vazio">Erro ao carregar produtos.</div>';}
}
let debDeskTimerInner;
function deskSearch(val){
    clearTimeout(debDeskTimerInner);const q=val.trim();if(q.length<2){fecharBuscaDesk();return;}
    debDeskTimerInner=setTimeout(async()=>{try{renderBusca(await(await fetch('atendente.php?buscar='+encodeURIComponent(q))).json(),'desk-search-results');}catch(e){}},280);
}
function fecharBuscaDesk(){const el=document.getElementById('desk-search-results');if(el)el.classList.remove('show');}
function fecharBusca(){const el=document.getElementById('at-search-results');if(el)el.classList.remove('show');}
document.addEventListener('click',e=>{
    if(!e.target.closest('#desk-search')&&!e.target.closest('#desk-search-results'))fecharBuscaDesk();
    if(!e.target.closest('#at-search')&&!e.target.closest('#at-search-results'))fecharBusca();
});

function renderGrid(prods,grid){
    if(!prods.length){grid.innerHTML='<div class="at-grid-vazio">Nenhum produto nesta categoria.</div>';return;}
    grid.innerHTML=prods.map(p=>{
        const temPromo=p.preco_promocional&&parseFloat(p.preco_promocional)>0;
        const precoShow=temPromo?parseFloat(p.preco_promocional):parseFloat(p.preco);
        const desconto=temPromo?Math.round(((p.preco-p.preco_promocional)/p.preco)*100):0;
        return `<div class="at-prod-card" onclick="abrirModal(${JSON.stringify(p).replace(/"/g,'&quot;')})">
            <img class="at-prod-img" src="uploads/${p.imagem||'placeholder.jpg'}" onerror="this.src='uploads/placeholder.jpg'" loading="lazy">
            <div class="at-prod-body">
                <div class="at-prod-nome">${p.nome}</div>
                <div class="at-prod-sub">${[p.marca,p.modelo].filter(Boolean).join(' · ')||'&nbsp;'}</div>
                <div class="at-prod-preco-area">
                    ${temPromo?`<div class="at-prod-promo-badge">-${desconto}% PROMO</div>`:''}
                    <div class="at-prod-preco">R$ ${fmt(precoShow)}</div>
                    ${temPromo?`<div class="at-prod-preco-old">R$ ${fmt(p.preco)}</div>`:'<div style="height:13px;"></div>'}
                   
                </div>
                <div class="at-prod-footer">
                    <div></div>
                    <button class="at-prod-btn" onclick="event.stopPropagation();abrirModal(${JSON.stringify(p).replace(/"/g,'&quot;')})">Adicionar</button>
                </div>
            </div>
        </div>`;
    }).join('');
}

function renderBusca(items,containerId){
    const box=document.getElementById(containerId);
    if(!items.length){box.innerHTML='<div class="at-no-res">Nenhum produto encontrado</div>';box.classList.add('show');return;}
    box.innerHTML=items.map(p=>{
        const temPromo=p.preco_promocional&&parseFloat(p.preco_promocional)>0;
        const precoShow=temPromo?parseFloat(p.preco_promocional):parseFloat(p.preco);
        return `<div class="at-res-item" onclick="abrirModal(${JSON.stringify(p).replace(/"/g,'&quot;')});document.getElementById('${containerId}').classList.remove('show');document.querySelectorAll('#at-search,#desk-search').forEach(el=>el.value='')">
            <img class="at-res-thumb" src="uploads/${p.imagem||'placeholder.jpg'}" onerror="this.src='uploads/placeholder.jpg'">
            <div style="flex:1;min-width:0;"><div class="at-res-nome">${p.nome}</div><div class="at-res-sub">${[p.marca,p.modelo].filter(Boolean).join(' · ')} · ${p.estoque} un.</div></div>
            <div><div class="at-res-preco">R$ ${fmt(precoShow)}</div>${temPromo?`<div style="font-size:10px;color:var(--text-3);text-decoration:line-through;text-align:right;">R$ ${fmt(p.preco)}</div>`:''}</div>
        </div>`;
    }).join('');
    box.classList.add('show');
}

/* ═══════════════════════════════════
   MODAL PRODUTO
═══════════════════════════════════ */
async function abrirModal(prod){
    fecharBusca();fecharBuscaDesk();
    produtoAtual=prod;adicionaisSelecionados={};
    document.getElementById('at-modal-title').textContent=prod.nome;
    const temPromo=prod.preco_promocional&&parseFloat(prod.preco_promocional)>0;
    const precoShow=temPromo?parseFloat(prod.preco_promocional):parseFloat(prod.preco);
    document.getElementById('at-modal-preco').innerHTML=temPromo
        ?`R$ ${fmt(precoShow)} <span style="text-decoration:line-through;font-size:11px;opacity:.6;">R$ ${fmt(prod.preco)}</span> · ${prod.estoque} em estoque`
        :`R$ ${fmt(precoShow)} · ${prod.estoque} em estoque`;
    document.getElementById('at-modal-qty').value=1;
    document.getElementById('at-modal-obs-item').value='';
    const lista=document.getElementById('at-adicionais-lista');
    lista.innerHTML='<div style="color:var(--text-3);font-size:13px;padding:4px 0;">Verificando adicionais...</div>';
    try{
        const ads=await(await fetch('atendente.php?adicionais='+prod.id_produto)).json();
        lista.innerHTML=ads.length
            ?'<div class="at-adicional-titulo">Adicionais disponiveis</div>'+ads.map(ad=>`
                <div class="at-adicional-item">
                    <div class="at-adicional-check" id="chk-${ad.id_adicional}" onclick="toggleAdicional(${ad.id_adicional},'${ad.nome.replace(/'/g,"\\'")}',${ad.preco})"></div>
                    <span class="at-adicional-nome">${ad.nome}</span>
                    <span class="at-adicional-preco">${parseFloat(ad.preco)>0?'+R$ '+fmt(ad.preco):'Gratis'}</span>
                </div>`).join('')
            :'';
    }catch(e){lista.innerHTML='';}
    document.getElementById('at-modal').classList.add('show');
}
function toggleAdicional(id,nome,preco){
    const el=document.getElementById('chk-'+id);
    if(adicionaisSelecionados[id]){delete adicionaisSelecionados[id];el.classList.remove('checked');el.textContent='';}
    else{adicionaisSelecionados[id]={id_adicional:id,nome,preco};el.classList.add('checked');el.textContent='V';}
}
function modalQty(delta){const inp=document.getElementById('at-modal-qty');inp.value=Math.max(1,Math.min(produtoAtual.estoque,parseInt(inp.value||1)+delta));}
function fecharModal(){document.getElementById('at-modal').classList.remove('show');produtoAtual=null;adicionaisSelecionados={};}
document.getElementById('at-modal').addEventListener('click',function(e){if(e.target===this)fecharModal();});

function confirmarItem(){
    if(!produtoAtual)return;
    const qty=parseInt(document.getElementById('at-modal-qty').value)||1;
    const obs=document.getElementById('at-modal-obs-item').value.trim();
    const ads=Object.values(adicionaisSelecionados);
    const extras=ads.reduce((s,a)=>s+parseFloat(a.preco),0);
    const precoBase=precoEfetivo(produtoAtual);
    const subtotal=(precoBase+extras)*qty;
    const idx=carrinho.findIndex(i=>i.id_produto==produtoAtual.id_produto&&JSON.stringify(i.adicionais)===JSON.stringify(ads)&&i.obs===obs);
    if(idx>=0){carrinho[idx].quantidade+=qty;carrinho[idx].subtotal=(carrinho[idx].preco+extras)*carrinho[idx].quantidade;}
    else carrinho.push({id_produto:produtoAtual.id_produto,nome:produtoAtual.nome,imagem:produtoAtual.imagem,preco:precoBase,estoque:produtoAtual.estoque,quantidade:qty,adicionais:ads,obs,subtotal,is_avulso:false});
    fecharModal();renderCarrinho();
}

/* ═══════════════════════════════════
   ADICIONAIS AVULSOS
═══════════════════════════════════ */
async function carregarAvulsos(q, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    try {
        const ads = await (await fetch('atendente.php?adicionais_avulsos=' + encodeURIComponent(q))).json();
        if (!ads.length) {
            container.innerHTML = '<div style="text-align:center;color:var(--text-3);font-size:13px;padding:20px;">Nenhum adicional encontrado.</div>';
            return;
        }
        if (containerId === 'avul-lista') {
            // Mobile: cards em coluna
            container.innerHTML = ads.map(a => `
                <div class="avul-card" onclick="abrirModalAvulso(${JSON.stringify(a).replace(/"/g,'&quot;')})">
                    <div>
                        <div class="avul-nome">${a.nome}</div>
                        <div style="font-size:11px;color:var(--text-3);margin-top:2px;">Adicional avulso</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="avul-preco">${parseFloat(a.preco) > 0 ? 'R$ ' + fmt(a.preco) : 'Gratis'}</span>
                        <button class="avul-btn" onclick="event.stopPropagation();abrirModalAvulso(${JSON.stringify(a).replace(/"/g,'&quot;')})">+ Add</button>
                    </div>
                </div>`).join('');
        } else {
            // Desktop: lista compacta
            container.innerHTML = ads.map(a => `
                <div class="avul-card" onclick="abrirModalAvulso(${JSON.stringify(a).replace(/"/g,'&quot;')})">
                    <div class="avul-nome">${a.nome}</div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="avul-preco">${parseFloat(a.preco) > 0 ? 'R$ ' + fmt(a.preco) : 'Gratis'}</span>
                        <button class="avul-btn" onclick="event.stopPropagation();abrirModalAvulso(${JSON.stringify(a).replace(/"/g,'&quot;')})">+</button>
                    </div>
                </div>`).join('');
        }
    } catch(e) {
        container.innerHTML = '<div style="text-align:center;color:var(--text-3);font-size:13px;padding:16px;">Erro ao carregar.</div>';
    }
}

function buscarAvulso(val) {
    clearTimeout(avulDebTimer);
    avulDebTimer = setTimeout(() => carregarAvulsos(val.trim(), 'avul-lista'), 280);
}

function buscarAvulsoDesk(val) {
    clearTimeout(avulDebTimer);
    avulDebTimer = setTimeout(() => carregarAvulsos(val.trim(), 'desk-avul-lista'), 280);
}

function toggleDeskAvulsos() {
    deskAvulsosAberto = !deskAvulsosAberto;
    const box = document.getElementById('desk-avulsos-box');
    const btn = document.getElementById('desk-btn-avulsos');
    if (box) {
        box.classList.toggle('aberto', deskAvulsosAberto);
        if (btn) btn.style.background = deskAvulsosAberto ? 'var(--primary)' : 'var(--primary-light)';
        if (btn) btn.style.color = deskAvulsosAberto ? '#fff' : 'var(--primary)';
        if (deskAvulsosAberto) {
            const lista = document.getElementById('desk-avul-lista');
            if (lista && !lista.children.length) carregarAvulsos('', 'desk-avul-lista');
        }
    }
}

function abrirModalAvulso(ad) {
    avulAtual = ad;
    document.getElementById('avul-modal-title').textContent = ad.nome;
    document.getElementById('avul-modal-preco').textContent =
        parseFloat(ad.preco) > 0 ? 'R$ ' + fmt(ad.preco) + ' · Adicional avulso' : 'Gratis · Adicional avulso';
    document.getElementById('avul-modal-qty').value = 1;
    document.getElementById('avul-modal-obs').value = '';
    document.getElementById('at-modal-avulso').classList.add('show');
}

function fecharModalAvulso() {
    document.getElementById('at-modal-avulso').classList.remove('show');
    avulAtual = null;
}
document.getElementById('at-modal-avulso').addEventListener('click', function(e) {
    if (e.target === this) fecharModalAvulso();
});

function avulQty(delta) {
    const inp = document.getElementById('avul-modal-qty');
    inp.value = Math.max(1, parseInt(inp.value || 1) + delta);
}

function confirmarAvulso() {
    if (!avulAtual) return;
    const qty = parseInt(document.getElementById('avul-modal-qty').value) || 1;
    const obs = document.getElementById('avul-modal-obs').value.trim();
    const preco = parseFloat(avulAtual.preco) || 0;
    const subtotal = preco * qty;

    carrinho.push({
        id_produto: null,
        id_adicional_avulso: avulAtual.id_adicional,
        nome: avulAtual.nome,
        imagem: null,
        preco: preco,
        estoque: 9999,
        quantidade: qty,
        adicionais: [],
        obs: obs,
        subtotal: subtotal,
        is_avulso: true
    });

    fecharModalAvulso();
    renderCarrinho();

    // Vai para o carrinho no mobile para o atendente ver
    const isMobile = window.innerWidth < 900;
    if (isMobile) trocarAba('carrinho');
}

/* ═══════════════════════════════════
   LANÇAR COMANDA
═══════════════════════════════════ */
function getNumComanda(){const m=document.getElementById('at-num-comanda'),d=document.getElementById('desk-num-comanda');return(m&&m.value.trim())||(d&&d.value.trim())||'';}
function getObsComanda(){const m=document.getElementById('at-obs-comanda'),d=document.getElementById('desk-obs-comanda');return(m&&m.value.trim())||(d&&d.value.trim())||'';}
function lancarComanda(){
    const num=getNumComanda(),obs=getObsComanda();
    if(!num){alert('Informe o numero da comanda.');return;}
    if(!carrinho.length){alert('Adicione ao menos um produto.');return;}
    if(!confirm('Lancar comanda #'+num+' com '+carrinho.length+' item(ns)?'))return;
    document.getElementById('hid-num').value=num;
    document.getElementById('hid-obs').value=obs;
    document.getElementById('hid-itens').value=JSON.stringify(carrinho);
    document.getElementById('at-form').submit();
}

/* ═══════════════════════════════════
   COMANDAS ABERTAS
═══════════════════════════════════ */
function renderComandasHtml(comandas, prefixo) {
    if (!comandas.length) return '<div class="at-cmd-aberta-vazio">Nenhuma comanda aberta no momento.</div>';
    return comandas.map((cmd, ci) => {
        const minutos = Math.round((Date.now() - new Date(cmd.criado_em).getTime()) / 60000);
        const tempo   = minutos < 1 ? 'Agora mesmo' : minutos < 60 ? `${minutos} min atras` : `${(minutos/60).toFixed(1)}h atras`;
        const dtObj   = new Date(cmd.criado_em);
        const dataFmt = dtObj.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'});
        const horaFmt = dtObj.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
        const lancador = cmd.lancado_por || '—';
        const mesa = cmd.observacao ? cmd.observacao : null;
        const cardId = `${prefixo}-cmd-${ci}`;

        const itensHtml = cmd.itens.map((it, ii) => {
            const isAvulso = it.nome_produto && it.nome_produto.startsWith('[AVULSO]');
            const nomeExibir = isAvulso ? it.nome_produto.replace('[AVULSO] ', '') : it.nome_produto;
            const ads = it.adicionais && it.adicionais.length
                ? `<div class="at-cmd-item-ads">+ ${it.adicionais.map(a=>a.nome).join(', ')}</div>` : '';
            const obs = it.observacao
                ? `<div class="at-cmd-item-obs">"${it.observacao}"</div>` : '';
            const horario    = it.criado_em ? new Date(it.criado_em).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}) : '';
            const dataItem   = it.criado_em ? new Date(it.criado_em).toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'}) : '';
            const lancadorIt = it.lancado_por || '';
            const temDetalhe = horario || lancadorIt;
            const detId = `${prefixo}-det-${ci}-${ii}`;
            const detalheHtml = temDetalhe ? `
                <div class="at-cmd-item-detalhe" id="${detId}">
                    ${horario    ? `<div style="font-size:11px;color:var(--text-2);"><strong>Horario:</strong> ${dataItem} as ${horario}</div>` : ''}
                    ${lancadorIt ? `<div style="font-size:11px;color:var(--text-2);margin-top:2px;"><strong>Lancado por:</strong> ${lancadorIt}</div>` : ''}
                </div>` : '';
            const clickInfo = temDetalhe ? `onclick="toggleDetalheItem('${detId}')" style="cursor:pointer;"` : '';
            return `<div class="at-cmd-item-linha">
                <div class="at-cmd-item-qty ${isAvulso ? 'avulso-qty' : ''}">${it.quantidade}</div>
                <div class="at-cmd-item-info" ${clickInfo}>
                    <div class="at-cmd-item-nome">${nomeExibir}</div>
                    <div style="font-size:10px;color:var(--text-3);">${it.quantidade}x R$ ${fmt(it.preco_unitario||0)}</div>
                    ${ads}${obs}${detalheHtml}
                    ${temDetalhe ? `<div style="font-size:10px;color:var(--text-3);margin-top:3px;">Clique para ver detalhes</div>` : ''}
                </div>
                <div class="at-cmd-item-sub">R$ ${fmt(it.subtotal)}</div>
            </div>`;
        }).join('');

        const obsGeral = '';
        return `
        <div class="at-cmd-aberta-card" id="${cardId}">
            <div class="at-cmd-aberta-header" onclick="toggleComanda('${cardId}')">
                <div>
                    <div class="at-cmd-aberta-num">Comanda #${cmd.numero_comanda}</div>
                    <div class="at-cmd-aberta-meta">${dataFmt} as ${horaFmt} · ${tempo} · ${cmd.total_itens} item(ns)</div>
                    <div class="at-cmd-aberta-lancador">${lancador}</div>
                    ${mesa ? `<div class="at-cmd-mesa-badge">&#9632; Mesa: ${mesa}</div>` : ''}
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span class="at-cmd-aberta-valor">R$ ${fmt(cmd.valor_total)}</span>
                    <span class="at-cmd-aberta-seta">V</span>
                </div>
            </div>
            <div class="at-cmd-aberta-body">
                ${obsGeral}${itensHtml}
                <div class="at-cmd-aberta-total">
                    <span>Total</span>
                    <span style="color:var(--success);">R$ ${fmt(cmd.valor_total)}</span>
                </div>
            </div>
        </div>`;
    }).join('');
}

function toggleComanda(cardId) {
    document.getElementById(cardId).classList.toggle('aberto');
}

function toggleDetalheItem(detId) {
    const el = document.getElementById(detId);
    if (el) el.classList.toggle('visivel');
}

async function carregarComandasAbertas() {
    if (sessaoExpirada) return;

    const mobBody  = document.getElementById('mob-cmd-abertas-body');
    const deskBody = document.getElementById('desk-cmd-abertas-body');
    const mobBadge    = document.getElementById('at-badge-cmd');
    const mobTabBadge = document.getElementById('mob-badge-cmd');
    const deskBadge   = document.getElementById('desk-badge-cmd');

    try {
        const res = await fetch('atendente.php?minhas_comandas=1');
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            sessaoExpirada = true;
            return;
        }
        const comandas = await res.json();
        const count = comandas.length;
        if (mobBadge)    mobBadge.textContent = count;
        if (mobTabBadge) { mobTabBadge.textContent=count; mobTabBadge.className=count?'at-tab-badge':'at-tab-badge hidden'; }
        if (deskBadge)   deskBadge.textContent = count;

        // Salva quais cards estão abertos antes de atualizar
        function salvarEstado(container) {
            const estado = { cards: {}, detalhes: {} };
            if (!container) return estado;
            container.querySelectorAll('.at-cmd-aberta-card').forEach(card => {
                estado.cards[card.id] = card.classList.contains('aberto');
            });
            container.querySelectorAll('.at-cmd-item-detalhe').forEach(det => {
                estado.detalhes[det.id] = det.classList.contains('visivel');
            });
            return estado;
        }

        // Restaura o estado após atualizar
        function restaurarEstado(container, estado) {
            if (!container) return;
            container.querySelectorAll('.at-cmd-aberta-card').forEach(card => {
                if (estado.cards[card.id]) card.classList.add('aberto');
            });
            container.querySelectorAll('.at-cmd-item-detalhe').forEach(det => {
                if (estado.detalhes[det.id]) det.classList.add('visivel');
            });
        }

        const estadoMob  = salvarEstado(mobBody);
        const estadoDesk = salvarEstado(deskBody);

        const htmlMob  = renderComandasHtml(comandas, 'mob');
        const htmlDesk = renderComandasHtml(comandas, 'desk');

        if (mobBody)  { mobBody.innerHTML  = htmlMob;  restaurarEstado(mobBody,  estadoMob);  }
        if (deskBody) { deskBody.innerHTML = htmlDesk; restaurarEstado(deskBody, estadoDesk); }

    } catch(e) {
        // ignora silenciosamente
    }
}

function toggleDeskComandas() {
    deskComandasAberto = !deskComandasAberto;
    const body = document.getElementById('desk-cmd-abertas-body');
    const seta = document.getElementById('desk-cmd-seta');
    if (body) body.classList.toggle('fechado', !deskComandasAberto);
    if (seta) seta.style.transform = deskComandasAberto ? 'rotate(180deg)' : 'rotate(0deg)';
}

/* ═══════════════════════════════════
   INICIALIZAÇÃO
═══════════════════════════════════ */
selecionarCategoria(0, document.querySelector('#at-cats .at-cat-pill[data-id="0"]'));
deskCategoria(0, document.querySelector('#desk-cats .at-cat-pill[data-id="0"]'));
let sessaoExpirada = false;
carregarComandasAbertas();
const intervaloComandas = setInterval(() => {
    if (sessaoExpirada) { clearInterval(intervaloComandas); return; }
    carregarComandasAbertas();
}, 15000);

/* ═══════════════════════════════════
   TOGGLE FOTOS
═══════════════════════════════════ */
let fotosAtivas = false;

function toggleFotos() {
    fotosAtivas = !fotosAtivas;

    // Atualiza os toggles visual
    document.querySelectorAll('.at-foto-toggle').forEach(el => {
        el.classList.toggle('ativo', fotosAtivas);
    });

    // Aplica/remove classe no grid mobile e desktop
    const grids = [
        document.getElementById('at-prod-grid'),
        document.getElementById('desk-prod-grid')
    ];
    grids.forEach(g => {
        if (!g) return;
        g.classList.toggle('sem-fotos', !fotosAtivas);
    });
}

// Inicializa toggles como ATIVO (fotos visíveis)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.at-foto-toggle').forEach(el => el.classList.remove('ativo'));
    document.querySelectorAll('#at-prod-grid, #desk-prod-grid').forEach(g => {
        if (g) g.classList.add('sem-fotos');
    });
});
</script>
</body>
</html>