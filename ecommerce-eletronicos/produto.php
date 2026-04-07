<?php
// database.php já chama session_start() — não repetir
require_once 'config/database.php';
require_once 'config/tema.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$sql = "SELECT p.*, c.nome as categoria_nome
        FROM produtos p
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        WHERE p.id_produto = ? AND p.ativo = TRUE";

$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    header('Location: index.php');
    exit;
}

$session_id = session_id();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
try {
    $stmt_view = $conn->prepare("INSERT INTO produto_views (id_produto, session_id, ip) VALUES (?, ?, ?)");
    $stmt_view->execute([$id, $session_id, $ip]);
} catch (\Throwable $e) {}

$stmt_imgs = $conn->prepare("SELECT imagem FROM produto_imagens WHERE id_produto = ? ORDER BY id");
$stmt_imgs->execute([$id]);
$imagens = $stmt_imgs->fetchAll(PDO::FETCH_COLUMN);
if (empty($imagens)) {
    $imagens = [$produto['imagem'] ?: 'placeholder.jpg'];
}

$total_carrinho = 0;
if (!empty($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $item) {
        $total_carrinho += is_array($item) ? ($item['quantidade'] ?? 0) : intval($item);
    }
}

$preco_atual  = $produto['preco_promocional'] ?: $produto['preco'];
$tem_promocao = !empty($produto['preco_promocional']);
$desconto     = $tem_promocao ? round((($produto['preco'] - $produto['preco_promocional']) / $produto['preco']) * 100) : 0;

$adicionais_produto = [];
try {
    $stmt_ad = $conn->prepare("
        SELECT a.id_adicional, a.nome, a.preco
        FROM produto_adicionais pa
        JOIN adicionais a ON a.id_adicional = pa.id_adicional
        WHERE pa.id_produto = ? AND a.ativo = TRUE
        ORDER BY a.nome
    ");
    $stmt_ad->execute([$id]);
    $adicionais_produto = $stmt_ad->fetchAll();
} catch (\Throwable $e) {
    $adicionais_produto = [];
}

$tem_adicionais = !empty($adicionais_produto);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($produto['nome']) ?> - TechStore</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <style>
        /* ══════════════════════════════════════
           DESKTOP — sem alteração
        ══════════════════════════════════════ */
        .produto-detalhe-wrapper {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .produto-detalhe-card {
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.10);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 480px;
            animation: entradaCard 0.45s ease-out;
        }

        @keyframes entradaCard {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .produto-detalhe-imagem-area {
            position: relative;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            min-height: 420px;
            gap: 1rem;
        }

        .galeria-principal {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .produto-detalhe-img {
            max-width: 100%;
            max-height: 360px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 0.5rem;
            transition: opacity 0.3s ease, transform 0.4s ease;
        }

        .produto-detalhe-img:hover { transform: scale(1.04); }

        .galeria-seta {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.92);
            border: none;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, transform 0.2s;
            z-index: 2;
        }

        .galeria-seta:hover { background: white; transform: translateY(-50%) scale(1.1); }
        .galeria-seta.prev  { left: 0.5rem; }
        .galeria-seta.next  { right: 0.5rem; }
        .galeria-seta.hidden { visibility: hidden; }

        .galeria-thumbs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .galeria-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.4rem;
            border: 2px solid transparent;
            cursor: pointer;
            transition: border-color 0.2s, transform 0.2s;
            opacity: 0.65;
        }

        .galeria-thumb:hover { opacity: 1; transform: scale(1.07); }
        .galeria-thumb.ativa { border-color: var(--primary); opacity: 1; }
        .galeria-contador { font-size: 0.8rem; color: var(--gray); }

        .badge-destaque-detalhe {
            position: absolute; top: 1.25rem; left: 1.25rem;
            background: var(--danger); color: white;
            padding: 0.4rem 1rem; border-radius: 2rem;
            font-size: 0.82rem; font-weight: 700; letter-spacing: 0.5px; z-index: 3;
        }

        .badge-desconto-detalhe {
            position: absolute; top: 1.25rem; right: 1.25rem;
            background: var(--secondary); color: white;
            padding: 0.4rem 1rem; border-radius: 2rem;
            font-size: 0.82rem; font-weight: 700; z-index: 3;
        }

        .produto-detalhe-info {
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 1.25rem;
        }

        .produto-detalhe-categoria {
            display: inline-block;
            background: var(--light);
            color: var(--primary);
            padding: 0.3rem 0.9rem;
            border-radius: 2rem;
            font-size: 0.83rem;
            font-weight: 600;
            width: fit-content;
        }

        .produto-detalhe-nome {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.25;
        }

        .produto-detalhe-descricao {
            color: var(--gray);
            font-size: 1rem;
            line-height: 1.7;
        }

        .produto-detalhe-preco-area {
            background: #f0f4ff;
            border-radius: 1rem;
            padding: 1.2rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .produto-detalhe-preco-principal {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .produto-detalhe-preco-antigo {
            font-size: 1rem;
            color: var(--gray);
            text-decoration: line-through;
        }

        .produto-detalhe-economia {
            font-size: 0.9rem;
            color: var(--secondary);
            font-weight: 600;
        }

        .produto-detalhe-acoes {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn-adicionar-detalhe {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            border: none;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }

        .btn-adicionar-detalhe:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37,99,235,0.5);
        }

        .btn-adicionar-detalhe:active  { transform: translateY(0); }
        .btn-adicionar-detalhe:disabled {
            background: #ccc; cursor: not-allowed;
            box-shadow: none; transform: none;
        }

        .adicionais-section {
            margin-top: 1.25rem;
            border-top: 2px solid #f1f5f9;
            padding-top: 1.25rem;
        }

        .adicionais-titulo {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }

        .adicionais-grid {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .adicional-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.18s;
            user-select: none;
        }

        .adicional-item:hover       { border-color: var(--primary); background: #f0f4ff; }
        .adicional-item.selecionado { border-color: var(--primary); background: #dbeafe; }

        .adicional-item input[type=checkbox] {
            width: 18px; height: 18px;
            accent-color: var(--primary);
            cursor: pointer; flex-shrink: 0;
        }

        .adicional-item-nome  { flex: 1; font-size: 0.92rem; font-weight: 600; color: #1e293b; }
        .adicional-item-preco { font-size: 0.88rem; font-weight: 800; color: #059669; white-space: nowrap; }

        .preco-total-linha {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0.8rem;
            background: var(--primary);
            border-radius: 0.6rem;
            margin-top: 0.75rem;
        }

        .preco-total-label { font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,.85); }
        .preco-total-valor { font-size: 1.3rem; font-weight: 800; color: white; }

        /* Desktop: esconde seta dentro do acoes, usa a do topo */
        .produto-detalhe-acoes .btn-seta { display: none; }

        /* ══════════════════════════════════════
           MOBILE — fontes reduzidas, imagem intacta
        ══════════════════════════════════════ */
        @media (max-width: 768px) {
            .produto-detalhe-wrapper { padding: 0; margin: 0; }

            .produto-detalhe-card {
                grid-template-columns: 1fr;
                border-radius: 0;
                box-shadow: none;
                min-height: unset;
                padding-bottom: 90px;
            }

            /* ── Imagem: sem alteração ── */
            .produto-detalhe-imagem-area {
                padding: 0; background: transparent;
            }
            .produto-detalhe-img {
                width: 100%; height: auto;
                max-height: none; border-radius: 0;
            }
            .galeria-thumb    { width: 40px; height: 40px; }
            .galeria-seta     { width: 28px; height: 28px; font-size: 0.82rem; }
            .galeria-contador { font-size: 0.68rem; }

            /* ── Badges ── */
            .badge-destaque-detalhe,
            .badge-desconto-detalhe {
                font-size: 0.6rem;
                padding: 0.18rem 0.5rem;
                top: 0.4rem;
            }
            .badge-destaque-detalhe { left: 0.4rem; }
            .badge-desconto-detalhe { right: 0.4rem; }

            /* ── Info geral ── */
            .produto-detalhe-info { padding: 0.75rem 0.85rem; gap: 0.5rem; }

            /* ── Categoria ── */
            .produto-detalhe-categoria {
                font-size: 0.68rem;
                padding: 0.18rem 0.6rem;
            }

            /* ── Nome ── */
            .produto-detalhe-nome {
                font-size: 0.9rem;
                margin-top: 0.35rem !important;
            }

            /* ── Descrição ── */
            .produto-detalhe-descricao {
                font-size: 0.72rem;
                line-height: 1.5;
                margin-top: 0.4rem !important;
            }

            /* ── Preço ── */
            .produto-detalhe-preco-area {
                padding: 0.55rem 0.7rem;
                border-radius: 0.6rem;
                gap: 0.12rem;
            }
            .produto-detalhe-preco-principal { font-size: 1.2rem; }
            .produto-detalhe-preco-antigo    { font-size: 0.68rem; }
            .produto-detalhe-economia        { font-size: 0.65rem; }

            /* ── Adicionais ── */
            .adicionais-section { margin-top: 0.65rem; padding-top: 0.65rem; }
            .adicionais-titulo  { font-size: 0.72rem; margin-bottom: 0.45rem; }
            .adicionais-grid    { gap: 0.3rem; }
            .adicional-item {
                padding: 0.45rem 0.65rem;
                border-radius: 0.5rem;
                border-width: 1.5px;
                gap: 0.5rem;
            }
            .adicional-item input[type=checkbox] { width: 14px; height: 14px; }
            .adicional-item-nome  { font-size: 0.74rem; }
            .adicional-item-preco { font-size: 0.7rem; }
            .preco-total-linha  { padding: 0.4rem 0.6rem; border-radius: 0.45rem; margin-top: 0.45rem; }
            .preco-total-label  { font-size: 0.67rem; }
            .preco-total-valor  { font-size: 0.95rem; }

            /* ── Barra rodapé: seta + botão lado a lado ── */
            .produto-detalhe-acoes {
                position: fixed !important;
                bottom: 0 !important; left: 0 !important; right: 0 !important;
                padding: 0.6rem 0.75rem;
                background: white;
                box-shadow: 0 -3px 16px rgba(0,0,0,0.10);
                z-index: 100;
                display: flex !important;
                flex-direction: row !important;
                align-items: center;
                gap: 0.5rem;
            }
            .btn-adicionar-detalhe {
                flex: 1;
                padding: 0.72rem;
                font-size: 0.85rem;
                border-radius: 0.6rem;
            }

            /* Esconde seta do topo, mostra a do rodapé */
            .card > .btn-seta { display: none !important; }
            .produto-detalhe-acoes .btn-seta {
                display: flex !important;
                position: static !important;
                flex-shrink: 0;
                width: 42px !important;
                height: 42px !important;
                background: #f1f5f9 !important;
                border-radius: 0.6rem !important;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                text-decoration: none;
                color: var(--dark);
                box-shadow: none !important;
                border: 1px solid #e2e8f0;
            }
        }
    </style>
</head>
<body>

    <aside id="sidebar">
        <div class="sidebar-header">
            <h3>Menu</h3>
            <button class="close-sidebar" onclick="toggleSidebar()">x</button>
        </div>
        <ul>
            <li><a href="index.php">Home</a></li>
            <?php if (isset($_SESSION['id_admin'])): ?>
                <li><a href="admin.php">Admin</a></li>
                <li><a href="cadastro_produto.php">Cadastrar Produto</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['id_cliente'])): ?>
                <li><a href="logout.php">Sair</a></li>
            <?php endif; ?>
            <li><a href="carrinho.php">Carrinho</a></li>
        </ul>
    </aside>
    <div id="overlay" onclick="toggleSidebar()"></div>

    <div class="produto-detalhe-wrapper">
        <div class="card">
            <a href="index.php" class="btn-seta">&#8592;</a>

            <div class="produto-detalhe-card">

                <!-- GALERIA -->
                <div class="produto-detalhe-imagem-area">
                    <?php if ($produto['destaque']): ?>
                        <span class="badge-destaque-detalhe">DESTAQUE</span>
                    <?php endif; ?>
                    <?php if ($tem_promocao): ?>
                        <span class="badge-desconto-detalhe">-<?= $desconto ?>%</span>
                    <?php endif; ?>

                    <div class="galeria-principal">
                        <?php if (count($imagens) > 1): ?>
                            <button class="galeria-seta prev hidden" id="btn-prev" onclick="mudarImagem(-1)">&#8592;</button>
                        <?php endif; ?>

                        <img id="img-principal"
                             src="<?= img_src($imagens[0]) ?>"
                             alt="<?= htmlspecialchars($produto['nome']) ?>"
                             class="produto-detalhe-img"
                             onerror="this.src='uploads/placeholder.jpg'">

                        <?php if (count($imagens) > 1): ?>
                            <button class="galeria-seta next" id="btn-next" onclick="mudarImagem(1)">&#8594;</button>
                        <?php endif; ?>
                    </div>

                    <?php if (count($imagens) > 1): ?>
                        <div class="galeria-thumbs">
                            <?php foreach ($imagens as $i => $img): ?>
                                <img src="<?= img_src($img) ?>"
                                     alt="Foto <?= $i + 1 ?>"
                                     class="galeria-thumb <?= $i === 0 ? 'ativa' : '' ?>"
                                     onclick="irParaImagem(<?= $i ?>)"
                                     onerror="this.style.display='none'">
                            <?php endforeach; ?>
                        </div>
                        <span class="galeria-contador" id="galeria-contador">
                            1 / <?= count($imagens) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- INFORMAÇÕES -->
                <div class="produto-detalhe-info">
                    <div>
                        <?php if ($produto['categoria_nome']): ?>
                            <span class="produto-detalhe-categoria"><?= htmlspecialchars($produto['categoria_nome']) ?></span>
                        <?php endif; ?>

                        <h1 class="produto-detalhe-nome" style="margin-top: 0.75rem;">
                            <?= htmlspecialchars($produto['nome']) ?>
                        </h1>

                        <?php if (!empty($produto['descricao'])): ?>
                            <p class="produto-detalhe-descricao" style="margin-top: 1rem;">
                                <?= nl2br(htmlspecialchars($produto['descricao'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- PREÇO -->
                    <div class="produto-detalhe-preco-area">
                        <?php if ($tem_promocao): ?>
                            <div class="produto-detalhe-preco-antigo">
                                De R$ <?= number_format($produto['preco'], 2, ',', '.') ?>
                            </div>
                            <div class="produto-detalhe-preco-principal" id="preco-base-display">
                                R$ <?= number_format($produto['preco_promocional'], 2, ',', '.') ?>
                            </div>
                            <div class="produto-detalhe-economia">
                                Voce economiza R$ <?= number_format($produto['preco'] - $produto['preco_promocional'], 2, ',', '.') ?>
                            </div>
                        <?php else: ?>
                            <div class="produto-detalhe-preco-principal" id="preco-base-display">
                                R$ <?= number_format($produto['preco'], 2, ',', '.') ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($tem_adicionais): ?>
                        <div class="preco-total-linha" id="preco-total-linha" style="display:none;">
                            <span class="preco-total-label">Total com adicionais</span>
                            <span class="preco-total-valor" id="preco-total-valor">R$ 0,00</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ADICIONAIS -->
                    <?php if ($tem_adicionais): ?>
                    <div class="adicionais-section">
                        <div class="adicionais-titulo">Adicionais (opcional)</div>
                        <div class="adicionais-grid" id="adicionais-grid">
                            <?php foreach ($adicionais_produto as $ad): ?>
                            <label class="adicional-item" id="wrap-<?= $ad['id_adicional'] ?>">
                                <input type="checkbox"
                                       class="adicional-check"
                                       data-id="<?= $ad['id_adicional'] ?>"
                                       data-preco="<?= floatval($ad['preco']) ?>"
                                       data-nome="<?= htmlspecialchars($ad['nome'], ENT_QUOTES) ?>"
                                       onchange="calcularTotal()">
                                <span class="adicional-item-nome"><?= htmlspecialchars($ad['nome']) ?></span>
                                <span class="adicional-item-preco">
                                    <?= $ad['preco'] > 0
                                        ? '+ R$ ' . number_format($ad['preco'], 2, ',', '.')
                                        : 'Gratis' ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- BOTÕES -->
                    <div class="produto-detalhe-acoes">
                        <a href="index.php" class="btn-seta">&#8592;</a>
                        <?php if ($produto['estoque'] > 0): ?>
                            <button class="btn-adicionar-detalhe"
                                    id="btn-carrinho"
                                    onclick="adicionarEIrCarrinho(<?= $produto['id_produto'] ?>)">
                                Adicionar ao Carrinho
                            </button>
                        <?php else: ?>
                            <button class="btn-adicionar-detalhe" disabled>
                                Produto Indisponivel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
        const imagens   = <?= json_encode(array_values($imagens)) ?>;
        let indiceAtual = 0;

        function irParaImagem(index) {
            indiceAtual = index;
            var imgPrincipal = document.getElementById('img-principal');
            imgPrincipal.style.opacity = '0';
            setTimeout(function() {
                imgPrincipal.src = imagens[indiceAtual].startsWith('http') ? imagens[indiceAtual] : 'uploads/' + imagens[indiceAtual];
                imgPrincipal.style.opacity = '1';
            }, 150);
            document.querySelectorAll('.galeria-thumb').forEach(function(t, i) {
                t.classList.toggle('ativa', i === indiceAtual);
            });
            var contador = document.getElementById('galeria-contador');
            if (contador) contador.textContent = (indiceAtual + 1) + ' / ' + imagens.length;
            var btnPrev = document.getElementById('btn-prev');
            var btnNext = document.getElementById('btn-next');
            if (btnPrev) btnPrev.classList.toggle('hidden', indiceAtual === 0);
            if (btnNext) btnNext.classList.toggle('hidden', indiceAtual === imagens.length - 1);
        }

        function mudarImagem(direcao) {
            var novo = indiceAtual + direcao;
            if (novo >= 0 && novo < imagens.length) irParaImagem(novo);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft')  mudarImagem(-1);
            if (e.key === 'ArrowRight') mudarImagem(1);
        });

        var precoBase     = <?= floatval($preco_atual) ?>;
        var temAdicionais = <?= $tem_adicionais ? 'true' : 'false' ?>;

        function calcularTotal() {
            if (!temAdicionais) return;
            var extras = 0;
            var selecionados = [];
            document.querySelectorAll('.adicional-check').forEach(function(cb) {
                var wrap = document.getElementById('wrap-' + cb.getAttribute('data-id'));
                if (cb.checked) {
                    extras += parseFloat(cb.getAttribute('data-preco'));
                    selecionados.push(cb.getAttribute('data-nome'));
                    if (wrap) wrap.classList.add('selecionado');
                } else {
                    if (wrap) wrap.classList.remove('selecionado');
                }
            });
            var total      = precoBase + extras;
            var linhaTotal = document.getElementById('preco-total-linha');
            var valorTotal = document.getElementById('preco-total-valor');
            if (extras > 0 && linhaTotal && valorTotal) {
                linhaTotal.style.display = 'flex';
                valorTotal.textContent   = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            } else if (linhaTotal) {
                linhaTotal.style.display = 'none';
            }
            var btn = document.getElementById('btn-carrinho');
            if (btn) {
                btn.textContent = selecionados.length > 0
                    ? 'Adicionar ao Carrinho (' + selecionados.length + ' adicional)'
                    : 'Adicionar ao Carrinho';
            }
        }

        function adicionarEIrCarrinho(idProduto) {
            var btn = document.getElementById('btn-carrinho');
            if (btn) { btn.disabled = true; btn.textContent = 'Adicionando...'; }
            var adicionaisSelecionados = [];
            document.querySelectorAll('.adicional-check:checked').forEach(function(cb) {
                adicionaisSelecionados.push({
                    id:    parseInt(cb.getAttribute('data-id')),
                    nome:  cb.getAttribute('data-nome'),
                    preco: parseFloat(cb.getAttribute('data-preco'))
                });
            });
            var body = 'id_produto=' + idProduto + '&quantidade=1&acao=adicionar';
            if (adicionaisSelecionados.length > 0) {
                body += '&adicionais=' + encodeURIComponent(JSON.stringify(adicionaisSelecionados));
            }
            fetch('api/adicionar_carrinho.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (btn) {
                        btn.textContent      = 'Adicionado!';
                        btn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                    }
                    var badge = document.querySelector('.cart-badge');
                    if (badge) {
                        badge.textContent = data.total_itens;
                    } else {
                        var btnCarrinho = document.querySelector('.btn-carrinho');
                        if (btnCarrinho) {
                            var span = document.createElement('span');
                            span.className   = 'cart-badge';
                            span.textContent = data.total_itens;
                            btnCarrinho.appendChild(span);
                        }
                    }
                    setTimeout(function() {
                        if (btn) {
                            btn.disabled         = false;
                            btn.style.background = '';
                            calcularTotal();
                        }
                    }, 2000);
                } else {
                    if (btn) { btn.disabled = false; btn.textContent = 'Adicionar ao Carrinho'; }
                    alert(data.message || 'Erro ao adicionar produto');
                }
            })
            .catch(function(err) {
                console.error('Erro fetch:', err);
                if (btn) { btn.disabled = false; btn.textContent = 'Adicionar ao Carrinho'; }
                alert('Erro ao adicionar produto ao carrinho');
            });
        }
    </script>
</body>
</html>
