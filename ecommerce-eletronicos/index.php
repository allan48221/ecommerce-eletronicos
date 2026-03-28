<?php
require_once 'config/database.php';
require_once 'config/tema.php';
require_once 'empresa_helper.php';
$emp = getDadosEmpresa($conn);

if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

foreach ($_SESSION['carrinho'] as $pid => $val) {
    if (!is_array($val)) {
        $_SESSION['carrinho'][$pid] = ['quantidade' => intval($val), 'adicionais' => []];
    }
}

$categoria_filtro = '';
if (isset($_GET['categoria']) && $_GET['categoria'] != 'todos') {
    $id_categoria = intval($_GET['categoria']);
    $categoria_filtro = " AND p.id_categoria = $id_categoria";
}

$sql_destaques = "SELECT p.*, c.nome as categoria_nome 
                  FROM produtos p 
                  LEFT JOIN categorias c ON p.id_categoria = c.id_categoria 
                  WHERE p.destaque = TRUE AND p.ativo = TRUE 
                  ORDER BY p.data_cadastro DESC 
                  LIMIT 6";
$stmt_destaques = $conn->query($sql_destaques);
$destaques      = $stmt_destaques->fetchAll();

$sql_produtos  = "SELECT p.*, c.nome as categoria_nome 
                  FROM produtos p 
                  LEFT JOIN categorias c ON p.id_categoria = c.id_categoria 
                  WHERE p.ativo = TRUE $categoria_filtro
                  ORDER BY p.data_cadastro DESC";
$stmt_produtos  = $conn->query($sql_produtos);
$todos_produtos = $stmt_produtos->fetchAll();

$total_carrinho = 0;
foreach ($_SESSION['carrinho'] as $item) {
    $total_carrinho += is_array($item) ? ($item['quantidade'] ?? 0) : intval($item);
}

// ── Prepara dados do footer ──────────────────────────────────
$formas_pag    = [];
if (!empty($emp['formas_pagamento'])) {
    $formas_pag = array_filter(array_map('trim', explode(',', $emp['formas_pagamento'])));
}
$wpp_numero    = preg_replace('/\D/', '', $emp['whatsapp'] ?? $emp['celular'] ?? '');
$insta_handle  = ltrim($emp['instagram'] ?? '', '@/');
$insta_handle  = preg_replace('/^.*instagram\.com\//', '', $insta_handle);
$cidade_uf     = trim(($emp['cidade'] ?? '') . ($emp['uf'] ? ' - ' . $emp['uf'] : ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($emp['nome_fantasia'] ?: $emp['nome_empresa'] ?: 'Loja') ?></title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <style>
        #modal-cc-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.50);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            animation: ccOverlayIn 0.28s ease;
        }
        @keyframes ccOverlayIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        #modal-cc {
            background: #fff;
            border-radius: 18px;
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,0.22);
            animation: ccModalIn 0.38s cubic-bezier(.22,.68,0,1.2);
        }
        @keyframes ccModalIn {
            from { opacity: 0; transform: translateY(28px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .cc-header {
            background: var(--primary, #3b5bdb);
            color: #fff;
            padding: 26px 52px 22px 26px;
            position: relative;
        }
        .cc-header h2 {
            margin: 0 0 5px;
            font-size: 1.7rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.2px;
        }
        .cc-header p {
            margin: 0;
            font-size: 0.88rem;
            color: rgba(255,255,255,0.88);
        }
        #cc-close {
            position: absolute;
            top: 13px;
            right: 14px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.20);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.18s, transform 0.15s;
            line-height: 1;
        }
        #cc-close:hover { background: rgba(255,255,255,0.38); transform: scale(1.12); }
        .cc-body { padding: 8px 22px 24px; }
        .cc-step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        .cc-step:last-child { border-bottom: none; }
        .cc-icon {
            width: 52px; height: 52px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 22px;
        }
        .cc-icon.c1 { background: rgba(59,91,219,0.10); }
        .cc-icon.c2 { background: #dcfce7; }
        .cc-icon.c3 { background: #fff7ed; }
        .cc-icon.c4 { background: #f3e8ff; }
        .cc-text { flex: 1; padding-top: 2px; }
        .cc-title-row { display: flex; align-items: center; gap: 7px; margin-bottom: 3px; }
        .cc-num {
            width: 21px; height: 21px; border-radius: 50%; color: #fff;
            font-size: 11px; font-weight: 800;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .cc-num.n1 { background: var(--primary, #3b5bdb); }
        .cc-num.n2 { background: #22c55e; }
        .cc-num.n3 { background: #f97316; }
        .cc-num.n4 { background: #a855f7; }
        .cc-step-title { font-weight: 700; font-size: 0.95rem; color: #1a1a2e; }
        .cc-step-desc  { margin: 0; font-size: 0.82rem; color: #666; line-height: 1.45; }
        @media (max-width: 480px) {
            #modal-cc      { max-width: 92vw; }
            .cc-header     { padding: 12px 40px 10px 14px; }
            .cc-header h2  { font-size: 1.1rem; margin-bottom: 2px; }
            .cc-header p   { font-size: 0.75rem; }
            #cc-close      { width: 24px; height: 24px; font-size: 13px; top: 10px; right: 10px; }
            .cc-body       { padding: 2px 12px 12px; }
            .cc-step       { padding: 9px 0; gap: 10px; }
            .cc-icon       { width: 36px; height: 36px; }
            .cc-num        { width: 17px; height: 17px; font-size: 9px; }
            .cc-step-title { font-size: 0.82rem; }
            .cc-step-desc  { font-size: 0.73rem; }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="main-nav">
            <div class="container">
                <div class="menu-toggle" onclick="toggleSidebar()">&#9776;</div>
                <a href="index.php" class="logo logo-mobile-right">Logo da empresa</a>

                <a href="carrinho.php" id="carrinho-mobile-btn" class="carrinho-mobile-btn">
                    <svg width="29" height="29" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="9" cy="21" r="1" stroke="var(--primary)" stroke-width="2"/>
                        <circle cx="20" cy="21" r="1" stroke="var(--primary)" stroke-width="2"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.66a2 2 0 001.99-1.61L23 6H6"
                              stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php if ($total_carrinho > 0): ?>
                    <span id="carrinho-mobile-badge" style="position:absolute;top:-6px;right:-8px;background:var(--danger,#ef4444);color:white;border-radius:50%;min-width:18px;height:18px;font-size:.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;padding:0 3px;line-height:1;"><?= $total_carrinho ?></span>
                    <?php else: ?>
                    <span id="carrinho-mobile-badge" style="position:absolute;top:-6px;right:-8px;background:var(--danger,#ef4444);color:white;border-radius:50%;min-width:18px;height:18px;font-size:.65rem;font-weight:800;display:none;align-items:center;justify-content:center;padding:0 3px;line-height:1;"></span>
                    <?php endif; ?>
                </a>

                <div class="search-bar">
                    <div class="search-form-inline">
                        <div class="search-input-group">
                            <span class="search-icon-inline"></span>
                            <input type="text" id="search-input" name="q"
                                   placeholder="Buscar produtos, marcas..."
                                   onkeyup="if(event.key !== 'Enter') buscarProdutos(this.value)"
                                   autocomplete="off">
                            <button type="button" class="search-btn-inline" onclick="executarBusca()">
                                <span class="search-btn-text">Buscar</span>
                                <span class="search-btn-arrow">&#8594;</span>
                            </button>
                        </div>
                        <div id="search-results" class="search-results"></div>
                    </div>
                </div>

                <nav>
                    <ul>
                        <?php if (isset($_SESSION['id_cliente'])): ?>
                            <li><a href="#"><?= htmlspecialchars($_SESSION['nome_cliente']); ?></a></li>
                            <li><a href="logout.php">Sair</a></li>
                        <?php elseif (isset($_SESSION['id_admin'])): ?>
                            <li><a href="logout.php">Sair</a></li>
                        <?php endif; ?>
                        <li>
                            <a href="carrinho.php" class="btn-carrinho">
                                Carrinho
                                <?php if ($total_carrinho > 0): ?>
                                    <span class="cart-badge"><?= $total_carrinho; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- SIDEBAR MOBILE -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <h3>Menu</h3>
            <button class="close-sidebar" onclick="toggleSidebar()">&#215;</button>
        </div>
        <ul>
            <li><a href="index.php">Home</a></li>
            <?php if (isset($_SESSION['id_admin'])): ?>
                <li><a href="admin.php">Admin</a></li>
                <li><a href="cadastro_produto.php">Cadastrar Produto</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['id_cliente'])): ?>
                <li><a href="perfil.php">Meu Perfil</a></li>
                <li><a href="logout.php">Sair</a></li>
            <?php endif; ?>
            <li><a href="carrinho.php">Carrinho</a></li>
        </ul>
    </aside>
    <div id="overlay" onclick="toggleSidebar()"></div>

    <!-- MODAL "COMO COMPRAR" -->
    <div id="modal-cc-overlay" role="dialog" aria-modal="true" aria-labelledby="cc-titulo">
        <div id="modal-cc">
            <div class="cc-header">
                <h2 id="cc-titulo">Como Comprar</h2>
                <p>Siga estes passos simples para finalizar sua compra</p>
                <button id="cc-close" aria-label="Fechar">&#10005;</button>
            </div>
            <div class="cc-body">
                <div class="cc-step">
                    <div class="cc-icon c1">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" stroke="var(--primary,#3b5bdb)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="3" y1="6" x2="21" y2="6" stroke="var(--primary,#3b5bdb)" stroke-width="2"/>
                            <path d="M16 10a4 4 0 01-8 0" stroke="var(--primary,#3b5bdb)" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="cc-text">
                        <div class="cc-title-row">
                            <span class="cc-num n1">1</span>
                            <span class="cc-step-title">Escolha seu produto</span>
                        </div>
                        <p class="cc-step-desc">Navegue pela loja e encontre o que você procura</p>
                    </div>
                </div>
                <div class="cc-step">
                    <div class="cc-icon c2">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                            <circle cx="9" cy="21" r="1" stroke="#22c55e" stroke-width="2"/>
                            <circle cx="20" cy="21" r="1" stroke="#22c55e" stroke-width="2"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.66a2 2 0 001.99-1.61L23 6H6" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="cc-text">
                        <div class="cc-title-row">
                            <span class="cc-num n2">2</span>
                            <span class="cc-step-title">Adicione ao carrinho</span>
                        </div>
                        <p class="cc-step-desc">Clique no botão para adicionar itens ao seu carrinho</p>
                    </div>
                </div>
                <div class="cc-step">
                    <div class="cc-icon c3">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <polyline points="14 2 14 8 20 8" stroke="#f97316" stroke-width="2" stroke-linecap="round"/>
                            <line x1="16" y1="13" x2="8" y2="13" stroke="#f97316" stroke-width="2" stroke-linecap="round"/>
                            <line x1="16" y1="17" x2="8" y2="17" stroke="#f97316" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="cc-text">
                        <div class="cc-title-row">
                            <span class="cc-num n3">3</span>
                            <span class="cc-step-title">Vá até o carrinho</span>
                        </div>
                        <p class="cc-step-desc">Revise os produtos selecionados</p>
                    </div>
                </div>
                <div class="cc-step">
                    <div class="cc-icon c4">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="#a855f7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="cc-text">
                        <div class="cc-title-row">
                            <span class="cc-num n4">4</span>
                            <span class="cc-step-title">Finalize no WhatsApp</span>
                        </div>
                        <p class="cc-step-desc">Preencha seus dados e clique em <strong>Finalizar no WhatsApp</strong> da loja!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var overlay = document.getElementById('modal-cc-overlay');
            if (sessionStorage.getItem('ccVisto') === '1') {
                overlay.style.display = 'none';
            }
            function fechar() {
                overlay.style.transition = 'opacity 0.22s';
                overlay.style.opacity = '0';
                setTimeout(function () { overlay.style.display = 'none'; }, 230);
                sessionStorage.setItem('ccVisto', '1');
            }
            document.getElementById('cc-close').addEventListener('click', fechar);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) fechar();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') fechar();
            });
        })();
    </script>

    <div class="container">

        <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
            <div class="alert alert-success">Você saiu do sistema com sucesso!</div>
        <?php endif; ?>

        <div class="card">
            <h1 style="text-align:center;font-size:2.5rem;margin-bottom:1rem;">
                Os Melhores Eletrônicos do Brasil
            </h1>
            <p style="text-align:center;font-size:1.2rem;color:var(--gray);">
                Smartphones, Notebooks, Tablets e muito mais com os melhores preços!
            </p>
        </div>

        <!-- PRODUTOS EM DESTAQUE -->
        <?php if (count($destaques) > 0): ?>
        <div class="card">
            <h2 class="card-title">Produtos em Destaque</h2>
            <div class="produtos-grid">
                <?php foreach ($destaques as $produto): ?>
                    <div class="produto-card">
                        <?php if ($produto['destaque']): ?>
                            <span class="badge-destaque">DESTAQUE</span>
                        <?php endif; ?>
                        <?php if (!empty($produto['preco_promocional']) && $produto['preco_promocional'] > 0): ?>
                            <span class="badge-promocao">
                                -<?= round((($produto['preco'] - $produto['preco_promocional']) / $produto['preco']) * 100); ?>%
                            </span>
                        <?php endif; ?>
                        <img src="uploads/<?= $produto['imagem'] ?: 'placeholder.jpg'; ?>"
                             alt="<?= htmlspecialchars($produto['nome']); ?>"
                             class="produto-imagem" style="cursor:pointer;"
                             onclick="verProduto(<?= $produto['id_produto']; ?>)"
                             onerror="this.src='uploads/placeholder.jpg'">
                        <div class="produto-info">
                            <span class="produto-categoria"><?= htmlspecialchars($produto['categoria_nome']); ?></span>
                            <h3 class="produto-nome" style="cursor:pointer;" onclick="verProduto(<?= $produto['id_produto']; ?>)">
                                <?= htmlspecialchars($produto['nome']); ?>
                            </h3>
                            <div class="produto-preco">
                                <?php if (!empty($produto['preco_promocional']) && $produto['preco_promocional'] > 0): ?>
                                    <span class="preco-atual">R$ <?= number_format($produto['preco_promocional'], 2, ',', '.'); ?></span>
                                    <span class="preco-antigo">R$ <?= number_format($produto['preco'], 2, ',', '.'); ?></span>
                                <?php else: ?>
                                    <span class="preco-atual">R$ <?= number_format($produto['preco'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($produto['estoque'] > 0): ?>
                                <button onclick="adicionarAoCarrinho(<?= $produto['id_produto']; ?>, this)" class="btn btn-primary btn-block">
                                    Adicionar ao Carrinho
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-block" disabled>Indisponível</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- TODOS OS PRODUTOS -->
        <div class="card">
            <h2 class="card-title">Todos os Produtos</h2>
            <div class="produtos-grid">
                <?php foreach ($todos_produtos as $produto): ?>
                    <div class="produto-card">
                        <?php if ($produto['destaque']): ?>
                            <span class="badge-destaque">DESTAQUE</span>
                        <?php endif; ?>
                        <?php if (!empty($produto['preco_promocional']) && $produto['preco_promocional'] > 0): ?>
                            <span class="badge-promocao">
                                -<?= round((($produto['preco'] - $produto['preco_promocional']) / $produto['preco']) * 100); ?>%
                            </span>
                        <?php endif; ?>
                        <img src="uploads/<?= $produto['imagem'] ?: 'placeholder.jpg'; ?>"
                             alt="<?= htmlspecialchars($produto['nome']); ?>"
                             class="produto-imagem" style="cursor:pointer;"
                             onclick="verProduto(<?= $produto['id_produto']; ?>)"
                             onerror="this.src='uploads/placeholder.jpg'">
                        <div class="produto-info">
                            <span class="produto-categoria"><?= htmlspecialchars($produto['categoria_nome']); ?></span>
                            <h3 class="produto-nome" style="cursor:pointer;" onclick="verProduto(<?= $produto['id_produto']; ?>)">
                                <?= htmlspecialchars($produto['nome']); ?>
                            </h3>
                            <p class="produto-descricao"><?= htmlspecialchars($produto['descricao']); ?></p>
                            <div class="produto-preco">
                                <?php if (!empty($produto['preco_promocional']) && $produto['preco_promocional'] > 0): ?>
                                    <span class="preco-atual">R$ <?= number_format($produto['preco_promocional'], 2, ',', '.'); ?></span>
                                    <span class="preco-antigo">R$ <?= number_format($produto['preco'], 2, ',', '.'); ?></span>
                                <?php else: ?>
                                    <span class="preco-atual">R$ <?= number_format($produto['preco'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($produto['estoque'] > 0): ?>
                                <button onclick="adicionarAoCarrinho(<?= $produto['id_produto']; ?>, this)" class="btn btn-primary btn-block">
                                    Adicionar ao Carrinho
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-block" disabled>Indisponível</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- fim .container -->

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">

                <!-- Coluna 1: Nome + descricao -->
                <div class="footer-section">
                    <h3><?= htmlspecialchars($emp['nome_fantasia'] ?: $emp['nome_empresa'] ?: 'Nome da Empresa') ?></h3>
                    <?php if (!empty($emp['descricao_loja'])): ?>
                    <p style="color:rgba(255,255,255,0.75);line-height:1.6;font-size:0.88rem;">
                        <?= htmlspecialchars($emp['descricao_loja']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Coluna 2: Atendimento -->
                <div class="footer-section">
                    <h3>Atendimento</h3>
                    <ul>
                        <?php if (!empty($wpp_numero)): ?>
                        <li>
                            <a href="tel:+55<?= $wpp_numero ?>">
                                <?= htmlspecialchars($emp['celular'] ?: $emp['telefone'] ?: '') ?>
                            </a>
                        </li>
                        <?php elseif (!empty($emp['telefone'])): ?>
                        <li><a href="tel:<?= htmlspecialchars($emp['telefone']) ?>"><?= htmlspecialchars($emp['telefone']) ?></a></li>
                        <?php endif; ?>

                        <?php if (!empty($emp['email'])): ?>
                        <li><a href="mailto:<?= htmlspecialchars($emp['email']) ?>"><?= htmlspecialchars($emp['email']) ?></a></li>
                        <?php endif; ?>

                        <?php if (!empty($emp['horario_atendimento'])): ?>
                        <li><?= htmlspecialchars($emp['horario_atendimento']) ?></li>
                        <?php endif; ?>

                        <?php if (!empty($cidade_uf)): ?>
                        <li><?= htmlspecialchars($cidade_uf) ?>, Brasil</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Coluna 3: Redes Sociais -->
                <div class="footer-section">
                    <h3>Redes Sociais</h3>
                    <div class="footer-social" style="flex-direction:column;gap:0.75rem;">

                        <?php if (!empty($insta_handle)): ?>
                        <a href="https://instagram.com/<?= htmlspecialchars($insta_handle) ?>" target="_blank"
                           class="social-icon" title="Instagram"
                           style="display:flex;align-items:center;gap:10px;font-size:0.9rem;color:rgba(255,255,255,0.75);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                <circle cx="12" cy="12" r="4"/>
                                <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>
                            </svg>
                            @<?= htmlspecialchars($insta_handle) ?>
                        </a>
                        <?php endif; ?>

                        <?php if (!empty($wpp_numero)): ?>
                        <a href="https://wa.me/55<?= $wpp_numero ?>" target="_blank"
                           class="social-icon" title="WhatsApp"
                           style="display:flex;align-items:center;gap:10px;font-size:0.9rem;color:rgba(255,255,255,0.75);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
                            </svg>
                            WhatsApp
                        </a>
                        <?php endif; ?>

                        <?php if (empty($insta_handle) && empty($wpp_numero)): ?>
                        <p style="font-size:0.82rem;color:rgba(255,255,255,0.4);font-style:italic;">
                            Nenhuma rede social cadastrada
                        </p>
                        <?php endif; ?>

                    </div>
                </div>

            </div><!-- /footer-grid -->

            <div class="footer-bottom">

                <?php if (!empty($formas_pag)): ?>
                <div class="payment-methods">
                    <?php foreach ($formas_pag as $fp): ?>
                    <span class="payment-icon"><?= htmlspecialchars($fp) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <p style="margin-top:2rem;">
                    &copy; <?= date('Y') ?>
                    <?= htmlspecialchars($emp['nome_fantasia'] ?: $emp['nome_empresa'] ?: 'Nome da Empresa') ?>.
                    Todos os direitos reservados.
                    <?php if (!empty($emp['cnpj'])): ?>
                    | CNPJ: <?= htmlspecialchars($emp['cnpj_formatado']) ?>
                    <?php endif; ?>
                </p>

                <div style="margin-top:1.5rem;padding-bottom:1rem;display:flex;align-items:center;justify-content:center;gap:10px;opacity:0.75;">
                    <span style="color:rgba(228,218,218,0.8);font-size:0.85rem;">Desenvolvido por</span>
                    <img src="clickzapp-logo.png" alt="ClickZapp" style="height:56px;background:white;border-radius:13px;padding:4px 7px;">
                </div>

            </div><!-- /footer-bottom -->
        </div><!-- /container -->

        <div style="text-align:center;padding:1rem 0 7rem;font-size:1rem;">
            <a href="login.php" style="color:var(--gray);text-decoration:none;">
                Area Administrativa
            </a>
        </div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>