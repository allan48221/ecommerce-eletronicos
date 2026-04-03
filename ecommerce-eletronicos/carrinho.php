<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!empty($_COOKIE['carrinho']) && empty($_SESSION['carrinho'])) {
    $decoded = json_decode(base64_decode($_COOKIE['carrinho']), true);
    if (is_array($decoded)) $_SESSION['carrinho'] = $decoded;
}


if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Converte formato antigo (int) para novo (array) se necessario
foreach ($_SESSION['carrinho'] as $pid => $val) {
    if (!is_array($val)) {
        $_SESSION['carrinho'][$pid] = ['quantidade' => intval($val), 'adicionais' => []];
    }
}

$subtotal       = 0;
$itens_carrinho = [];

if (!empty($_SESSION['carrinho'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['carrinho'])));
    $stmt = $conn->query("SELECT * FROM produtos WHERE id_produto IN ($ids) AND ativo = TRUE");
    $produtos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($produtos_db as $produto) {
        $pid        = $produto['id_produto'];
        $item_sess  = $_SESSION['carrinho'][$pid];
        $quantidade = $item_sess['quantidade'];
        $adicionais = $item_sess['adicionais'] ?? [];

        $preco_base     = floatval($produto['preco_promocional'] ?: $produto['preco']);
        $preco_extras   = array_sum(array_column($adicionais, 'preco'));
        $preco_unitario = $preco_base + $preco_extras;
        $subtotal_item  = $preco_unitario * $quantidade;
        $subtotal      += $subtotal_item;

        $itens_carrinho[] = [
            'produto'        => $produto,
            'quantidade'     => $quantidade,
            'preco_base'     => $preco_base,
            'preco_unitario' => $preco_unitario,
            'subtotal'       => $subtotal_item,
            'adicionais'     => $adicionais,
        ];
    }
}

$total_carrinho = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
if ($total_carrinho == 0 && !empty($_SESSION['carrinho'])) {
    $total_carrinho = array_sum($_SESSION['carrinho']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title>Carrinho de Compras</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <style>
        .cart-sticky-bottom { display: none; }

        .adicionais-lista-carr {
            margin-top: 3px;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }
        .adicional-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 6px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
            line-height: 1.5;
        }
        .adicional-tag .tag-preco { color: #059669; }

        /* ── MOBILE ── */
        @media (max-width: 768px) {

            .container { padding: 0 !important; }
            body { background: #f5f6f7 !important; }
            .card {
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: #f5f6f7 !important;
            }

            /* Header — só "Carrinho" centralizado, sem seta */
            .mobile-header {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 12px 14px;
                background: var(--primary);
                position: sticky;
                top: 0;
                z-index: 100;
            }
            .mobile-header .btn-seta { display: none !important; }
            .mobile-header h1 {
                font-size: 1rem;
                font-weight: 700;
                margin: 0;
                color: #fff;
                text-align: center;
            }
            .mobile-header .badge-qtd { display: none; }

            .card > .btn-seta,
            .card > .card-title { display: none; }

            .carrinho-layout {
                display: block !important;
                padding-bottom: 70px;
            }

            .carrinho-itens {
                padding: 8px 10px;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            /* Card de item */
            .carrinho-item {
                background: #fff;
                border-radius: 10px;
                padding: 8px 10px;
                display: flex;
                align-items: flex-start;
                gap: 9px;
                position: relative;
                border: 0.5px solid rgba(0,0,0,0.07);
            }

            .carrinho-item img {
                width: 52px;
                height: 52px;
                object-fit: cover;
                border-radius: 7px;
                border: 0.5px solid rgba(0,0,0,0.07);
                flex-shrink: 0;
            }

            .carrinho-info { flex: 1; min-width: 0; }

            .carrinho-info h3 {
                font-size: 0.78rem;
                font-weight: 700;
                color: var(--dark);
                margin: 0 0 1px;
                line-height: 1.3;
                padding-right: 18px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .carrinho-info > p { margin: 0; }

            .carrinho-acoes {
                display: flex !important;
                align-items: center;
                gap: 8px;
                margin-top: 5px;
            }

            .quantidade-control {
                display: flex;
                align-items: center;
                background: #f0f0f0;
                border-radius: 50px;
                padding: 1px;
            }
            .quantidade-control button {
                width: 22px;
                height: 22px;
                border: none;
                background: #fff;
                border-radius: 50%;
                font-size: 0.85rem;
                font-weight: 700;
                color: var(--primary);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                line-height: 1;
                padding: 0;
                -webkit-tap-highlight-color: transparent;
                transition: background 0.1s, color 0.1s;
            }
            .quantidade-control button:active { background: var(--primary); color: #fff; }
            .quantidade-control input {
                width: 24px;
                text-align: center;
                border: none;
                background: transparent;
                font-size: 0.75rem;
                font-weight: 700;
                color: var(--dark);
                pointer-events: none;
                -moz-appearance: textfield;
            }
            .quantidade-control input::-webkit-outer-spin-button,
            .quantidade-control input::-webkit-inner-spin-button { -webkit-appearance: none; }

            .preco-mobile {
                font-size: 0.82rem;
                font-weight: 700;
                color: var(--primary);
                margin-left: auto;
                white-space: nowrap;
            }

            .btn-remover-mobile {
                position: absolute;
                top: 0px;
                right: 7px;
                background: none;
                border: none;
                cursor: pointer;
                padding: 1px 3px;
                color: #c8c8c8;
                font-size: 1.2rem;
                line-height: 1;
                -webkit-tap-highlight-color: transparent;
                transition: color 0.15s;
            }
            .btn-remover-mobile:active { color: #ef4444; }

            .btn-remover-desktop,
            .carrinho-resumo,
            .preco-desktop { display: none !important; }

            .carrinho-acoes > div { display: none !important; }
            .carrinho-acoes > .quantidade-control { display: flex !important; }
            .carrinho-acoes > .preco-mobile { display: block !important; }

            .link-continuar {
                text-align: center;
                padding: 5px 0 2px;
            }
            .link-continuar a {
                font-size: 0.72rem;
                color: var(--gray);
                text-decoration: underline;
            }

            .cart-sticky-bottom {
                display: block;
                position: fixed;
                bottom: 0; left: 0; right: 0;
                z-index: 200;
                background: #fff;
                padding: 8px 12px 12px;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.09);
                border-top: 0.5px solid rgba(0,0,0,0.08);
            }
            .sticky-total-row {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                margin-bottom: 7px;
            }
            .sticky-total-label { font-size: 0.7rem; color: var(--gray); }
            .sticky-total-valor { font-size: 0.98rem; font-weight: 800; color: var(--dark); }
            .sticky-btn-finalizar {
                display: block;
                width: 100%;
                padding: 11px;
                background: var(--primary);
                color: #fff;
                text-align: center;
                border-radius: 9px;
                font-size: 0.85rem;
                font-weight: 700;
                text-decoration: none;
                -webkit-tap-highlight-color: transparent;
                transition: opacity 0.15s;
            }
            .sticky-btn-finalizar:active { opacity: 0.82; }

            .carrinho-vazio-mobile {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 3rem 1.5rem;
                text-align: center;
                min-height: 55vh;
            }
            .carrinho-vazio-mobile .emoji { font-size: 2.5rem; margin-bottom: 0.7rem; }
            .carrinho-vazio-mobile h3 { font-size: 0.95rem; color: var(--gray); margin-bottom: 0.3rem; }
            .carrinho-vazio-mobile p  { font-size: 0.8rem; color: var(--gray); margin-bottom: 1.4rem; }
            .carrinho-vazio-mobile a {
                padding: 10px 26px;
                background: var(--primary);
                color: #fff;
                border-radius: 9px;
                font-weight: 700;
                text-decoration: none;
                font-size: 0.85rem;
            }

            .desktop-vazio { display: none !important; }
            .carrinho-itens > div:last-of-type { display: none; }
        }

        /* ── DESKTOP ── */
        @media (min-width: 769px) {
            .mobile-header { display: none; }
            .btn-remover-mobile { display: none; }
            .cart-sticky-bottom { display: none !important; }
            .link-continuar { display: none; }
            .carrinho-vazio-mobile { display: none !important; }
            .preco-mobile { display: none; }
        }
    </style>
</head>
<body>

    <!-- Cabeçalho mobile sticky -->
    <div class="mobile-header">
        <a href="index.php" class="btn-seta">&#8592;</a>
        <h1>Meu Carrinho</h1>
        <?php if (!empty($itens_carrinho)): ?>
        <span class="badge-qtd"><?= $total_carrinho ?> <?= $total_carrinho == 1 ? 'item' : 'itens' ?></span>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="card">
            <!-- Desktop: seta e título originais -->
            <a href="index.php" class="btn-seta">&#8592;</a>
            <h1 class="card-title">Meu Carrinho</h1>

            <?php if (empty($itens_carrinho)): ?>
                <!-- VAZIO -->
                <div class="carrinho-vazio-mobile">
                    <div class="emoji">&#128722;</div>
                    <h3>Seu carrinho está vazio</h3>
                    <p>Adicione produtos e volte aqui!</p>
                    <a href="index.php">Continuar Comprando</a>
                </div>

                <!-- Desktop vazio (original) -->
                <div style="text-align:center;padding:3rem;display:none;" class="desktop-vazio">
                    <p style="font-size:3rem;margin-bottom:1rem;">&#128722;</p>
                    <h3 style="color:var(--gray);margin-bottom:1rem;">Seu carrinho esta vazio</h3>
                    <p style="color:var(--gray);margin-bottom:2rem;">Adicione produtos e volte aqui!</p>
                    <a href="index.php" class="btn btn-primary" style="width:auto;display:inline-block;">
                        Continuar Comprando
                    </a>
                </div>

            <?php else: ?>
                <div class="carrinho-layout">

                    <!-- ITENS -->
                    <div class="carrinho-itens">
                        <?php foreach ($itens_carrinho as $item): ?>
                            <?php $produto = $item['produto']; ?>
                            <div class="carrinho-item">

                                <!-- Botão remover mobile (canto superior direito) -->
                                <button type="button"
                                        class="btn-remover-mobile"
                                        onclick="removerDoCarrinho(<?= $produto['id_produto'] ?>)"
                                        aria-label="Remover item">
                                    &#10005;
                                </button>

                                <img src="uploads/<?= $produto['imagem'] ?: 'placeholder.jpg' ?>"
                                     alt="<?= htmlspecialchars($produto['nome']) ?>"
                                     onerror="this.src='uploads/placeholder.jpg'">

                                <div class="carrinho-info">
                                    <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                                    <p style="color:var(--gray);font-size:0.88rem;margin-bottom:0.4rem;">
                                        <?= htmlspecialchars(trim($produto['marca'] . ' ' . $produto['modelo'])) ?>
                                    </p>

                                    <!-- Adicionais selecionados -->
                                    <?php if (!empty($item['adicionais'])): ?>
                                    <div class="adicionais-lista-carr">
                                        <?php foreach ($item['adicionais'] as $ad): ?>
                                        <span class="adicional-tag">
                                            + <?= htmlspecialchars($ad['nome']) ?>
                                            <?php if ($ad['preco'] > 0): ?>
                                            <span class="tag-preco">
                                                R$ <?= number_format($ad['preco'], 2, ',', '.') ?>
                                            </span>
                                            <?php endif; ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Preço: desktop usa o padrão, mobile usa classe própria -->
                                    <p style="color:var(--primary);font-size:1.2rem;font-weight:700;margin-top:6px;" class="preco-desktop">
                                        R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                                        <?php if (!empty($item['adicionais'])): ?>
                                        <span style="font-size:0.75rem;color:var(--gray);font-weight:400;">
                                            (base R$ <?= number_format($item['preco_base'], 2, ',', '.') ?> + adicionais)
                                        </span>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="carrinho-acoes">
                                    <div class="quantidade-control">
                                        <button type="button" onclick="atualizarQuantidade(<?= $produto['id_produto'] ?>, 'diminuir')">-</button>
                                        <input type="number"
                                               value="<?= $item['quantidade'] ?>"
                                               min="1"
                                               max="<?= $produto['estoque'] ?>"
                                               data-produto="<?= $produto['id_produto'] ?>"
                                               readonly>
                                        <button type="button" onclick="atualizarQuantidade(<?= $produto['id_produto'] ?>, 'aumentar')">+</button>
                                    </div>

                                    <!-- Preço mobile (dentro das ações) -->
                                    <span class="preco-mobile">
                                        R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                                    </span>

                                    <!-- Subtotal + label (desktop) -->
                                    <div>
                                        <p style="color:var(--gray);font-size:0.82rem;margin-bottom:0.2rem;">Subtotal</p>
                                        <p style="font-size:1.3rem;font-weight:700;color:var(--dark);">
                                            R$ <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                        </p>
                                    </div>

                                    <!-- Botão remover desktop -->
                                    <button type="button"
                                            onclick="removerDoCarrinho(<?= $produto['id_produto'] ?>)"
                                            class="btn btn-danger btn-remover-desktop"
                                            style="width:auto;">
                                        Remover
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Link continuar comprando (mobile) -->
                        <div class="link-continuar">
                            <a href="index.php">&#8592; Continuar comprando</a>
                        </div>

                        <!-- Link continuar comprando (desktop) -->
                        <div style="margin-top:1.5rem;">
                            <a href="index.php" class="btn btn-secondary" style="width:auto;display:inline-block;">
                                Continuar Comprando
                            </a>
                        </div>
                    </div>

                    <!-- RESUMO (desktop lateral) -->
                    <div class="carrinho-resumo">
                        <div class="resumo-pedido">
                            <h3 style="margin-bottom:1.5rem;color:var(--dark);">Resumo do Pedido</h3>

                            <div class="resumo-linha">
                                <span>Subtotal:</span>
                                <span>R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                            </div>

                            <div class="resumo-linha" style="border-top:2px solid var(--dark);padding-top:1rem;">
                                <strong>Total:</strong>
                                <strong class="resumo-total" id="valor-total">
                                    R$ <?= number_format($subtotal, 2, ',', '.') ?>
                                </strong>
                            </div>

                            <input type="hidden" id="subtotal-input" value="<?= $subtotal ?>">
                            <input type="hidden" id="frete-input"    value="0">
                            <input type="hidden" id="total-input"    value="<?= $subtotal ?>">

                            <a href="finalizar_compra.php" class="btn btn-primary btn-block" style="margin-top:1.5rem;">
                                Finalizar Compra
                            </a>
                        </div>
                    </div>

                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── STICKY BOTTOM BAR (mobile, só quando tem itens) ── -->
    <?php if (!empty($itens_carrinho)): ?>
    <div class="cart-sticky-bottom">
        <div class="sticky-total-row">
            <span class="sticky-total-label">Total do pedido</span>
            <span class="sticky-total-valor" id="sticky-total">
                R$ <?= number_format($subtotal, 2, ',', '.') ?>
            </span>
        </div>
        <a href="finalizar_compra.php" class="sticky-btn-finalizar">
            Finalizar Compra &rarr;
        </a>
    </div>
    <?php endif; ?>

    <script src="js/main.js"></script>

    <script>
    // Mantém sticky bar sincronizado com o total quando quantidade muda
    (function() {
        const observer = new MutationObserver(function() {
            const totalInput = document.getElementById('total-input');
            const stickyTotal = document.getElementById('sticky-total');
            if (totalInput && stickyTotal) {
                const val = parseFloat(totalInput.value) || 0;
                stickyTotal.textContent = 'R$ ' + val.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });

        const totalInput = document.getElementById('total-input');
        if (totalInput) {
            observer.observe(totalInput, { attributes: true, attributeFilter: ['value'] });
        }

        // Também verifica #valor-total para sincronizar
        const valorTotal = document.getElementById('valor-total');
        if (valorTotal) {
            const obs2 = new MutationObserver(function() {
                const stickyTotal = document.getElementById('sticky-total');
                if (stickyTotal) {
                    stickyTotal.textContent = valorTotal.textContent.trim();
                }
            });
            obs2.observe(valorTotal, { childList: true, subtree: true, characterData: true });
        }
    })();

    // Esconder o vazio desktop no mobile e vice-versa
    (function() {
        const isMobile = window.innerWidth <= 768;
        const vazioMobile = document.querySelector('.carrinho-vazio-mobile');
        const vazioDesktop = document.querySelector('.desktop-vazio');
        if (vazioMobile && vazioDesktop) {
            if (isMobile) {
                vazioDesktop.style.display = 'none';
                vazioMobile.style.display = 'flex';
            } else {
                vazioMobile.style.display = 'none';
                vazioDesktop.style.display = 'block';
            }
        }
        // No mobile, esconder o preco-desktop dentro de carrinho-info
        // (já controlado por media query, mas garantindo)
    })();
    </script>
</body>
</html>
