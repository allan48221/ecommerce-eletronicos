<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (empty($_SESSION['carrinho'])) {
    header('Location: carrinho.php');
    exit;
}

// Converte formato antigo se necessario
foreach ($_SESSION['carrinho'] as $pid => $val) {
    if (!is_array($val)) {
        $_SESSION['carrinho'][$pid] = ['quantidade' => intval($val), 'adicionais' => []];
    }
}

$mensagem      = '';
$tipo_mensagem = '';
$subtotal      = 0;
$itens_carrinho = [];

$ids = implode(',', array_map('intval', array_keys($_SESSION['carrinho'])));
$result = $conn->query("SELECT * FROM produtos WHERE id_produto IN ($ids) AND ativo = TRUE");

while ($produto = $result->fetch()) {
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

$total_carrinho = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title>Finalizar Compra</title>
    <link rel="stylesheet" href="css/style.css">
    <?= aplicar_tema($conn) ?>
    <link rel="icon" type="image/png" href="favicon.png?v=1">
    <style>
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-top: 2rem;
        }

        .resumo-pedido {
            position: sticky;
            top: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-height: calc(100vh - 4rem);
            overflow-y: auto;
        }

        .item-resumo {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .item-resumo img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.5rem;
            flex-shrink: 0;
        }

        .item-info { flex: 1; }
        .item-nome  { font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
        .item-qtd   { color: #666; font-size: 0.85rem; }
        .item-preco { color: var(--primary); font-weight: bold; margin-top: 0.3rem; }

        /* Adicionais no resumo */
        .item-adicionais {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 5px;
        }
        .item-adicional-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .item-adicional-tag .ap {
            color: #059669;
        }

        .resumo-linha {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            color: var(--dark);
        }
        .resumo-linha:not(:last-child) { border-bottom: 1px solid #e5e7eb; }
        .resumo-total { font-size: 1.5rem; color: var(--primary); font-weight: bold; }

        @media (max-width: 768px) {
            .checkout-container { grid-template-columns: 1fr; }
            .resumo-pedido { position: static; max-height: none; }
            .container { padding: 0 12px !important; }
            h1 { font-size: 1.3rem !important; }
            .card { padding: 1rem !important; border-radius: 0.8rem !important; }
            .card-title { font-size: 1rem !important; margin-bottom: 1rem !important; }
            .form-control { font-size: 0.9rem !important; padding: 0.6rem 0.7rem !important; }
            label { font-size: 0.8rem !important; }
            .resumo-pedido { padding: 1rem !important; }
            .item-resumo { gap: 0.6rem !important; }
            .item-resumo img { width: 50px !important; height: 50px !important; }
            .item-nome { font-size: 0.8rem !important; }
            .item-qtd  { font-size: 0.75rem !important; }
            .item-preco { font-size: 0.85rem !important; }
            .resumo-total { font-size: 1.2rem !important; }
            .btn { font-size: 0.9rem !important; padding: 0.8rem !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="margin-bottom:1.5rem;color:white;">Finalizar Compra</h1>

        <form method="POST" action="">
            <div class="checkout-container">

                <!-- FORMULARIO -->
                <div>
                    <div class="card">
                        <h2 class="card-title">Dados Pessoais</h2>

                        <div class="form-group">
                            <label for="nome">Nome *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="form-group">
                            <label for="cpf">CPF *</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" maxlength="14" required>
                        </div>
                        <div class="form-group">
                            <label for="telefone">Telefone *</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" maxlength="15" required>
                        </div>
                    </div>

                    <div class="card">
                        <h2 class="card-title">Pagamento</h2>

                        <div class="form-group">
                            <label for="forma_pagamento">Forma de Pagamento *</label>
                            <select class="form-control" id="forma_pagamento" name="forma_pagamento" required>
                                <option value="">Selecione...</option>
                                <option value="Cartao de Credito">Cartao de Credito</option>
                                <option value="Cartao de Debito">Cartao de Debito</option>
                                <option value="PIX">PIX</option>
                                <option value="Boleto">Boleto Bancario</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="observacoes">Observacoes</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
                                placeholder="Alguma informacao adicional..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- RESUMO -->
                <div>
                    <div class="resumo-pedido">
                        <h3 style="margin-bottom:1.5rem;color:var(--dark);">Resumo do Pedido</h3>

                        <?php foreach ($itens_carrinho as $item): ?>
                        <div class="item-resumo">
                            <img src="uploads/<?= $item['produto']['imagem'] ?: 'placeholder.jpg' ?>"
                                 alt="<?= htmlspecialchars($item['produto']['nome']) ?>"
                                 onerror="this.src='uploads/placeholder.jpg'">
                            <div class="item-info">
                                <div class="item-nome"><?= htmlspecialchars($item['produto']['nome']) ?></div>
                                <div class="item-qtd">Quantidade: <?= $item['quantidade'] ?></div>

                                <?php if (!empty($item['adicionais'])): ?>
                                <div class="item-adicionais">
                                    <?php foreach ($item['adicionais'] as $ad): ?>
                                    <span class="item-adicional-tag">
                                        + <?= htmlspecialchars($ad['nome']) ?>
                                        <?php if ($ad['preco'] > 0): ?>
                                        <span class="ap">R$ <?= number_format($ad['preco'], 2, ',', '.') ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <div class="item-preco">
                                    R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                                    <?php if ($item['quantidade'] > 1): ?>
                                    <span style="color:#888;font-size:0.8rem;font-weight:400;">
                                        x <?= $item['quantidade'] ?> =
                                        <strong>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></strong>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="resumo-linha" style="border-top:2px solid var(--dark);padding-top:1rem;margin-top:0.5rem;">
                            <strong style="font-size:1.1rem;">Total:</strong>
                            <strong class="resumo-total" id="valor-total">
                                R$ <?= number_format($subtotal, 2, ',', '.') ?>
                            </strong>
                        </div>

                        <input type="hidden" id="subtotal-input" value="<?= $subtotal ?>">
                        <input type="hidden" id="frete-input"    name="valor_frete" value="0">
                        <input type="hidden" id="total-input"    name="valor_total" value="<?= $subtotal ?>">

                        <button type="button" id="btn-whatsapp"
                                onclick="finalizarPedidoWhatsApp()"
                                class="btn btn-primary btn-block"
                                style="margin-top:1.5rem;font-size:1.1rem;padding:1rem;background:#25D366;border-color:#25D366;">
                            Finalizar no WhatsApp da loja
                        </button>

                        <a href="carrinho.php" class="btn btn-secondary btn-block" style="margin-top:0.5rem;">
                            Voltar ao Carrinho
                        </a>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <script src="js/scripts.js"></script>
    <script>
    var subtotal = <?= floatval($subtotal) ?>;

    // Itens com adicionais para o WhatsApp
    var itensParaPedido = <?= json_encode(array_map(function($item) {
        return [
            'id_produto'     => $item['produto']['id_produto'],
            'nome_produto'   => $item['produto']['nome'],
            'quantidade'     => $item['quantidade'],
            'preco_base'     => $item['preco_base'],
            'preco_unitario' => $item['preco_unitario'],
            'subtotal'       => $item['subtotal'],
            'adicionais'     => $item['adicionais'],
        ];
    }, $itens_carrinho), JSON_UNESCAPED_UNICODE) ?>;

    function finalizarPedidoWhatsApp() {
        var campos = ['nome', 'cpf', 'telefone', 'forma_pagamento'];
        for (var i = 0; i < campos.length; i++) {
            var el = document.getElementById(campos[i]);
            if (!el || !el.value.trim()) {
                alert('Por favor, preencha todos os campos obrigatorios!');
                if (el) el.focus();
                return;
            }
        }

        var nome      = document.getElementById('nome').value;
        var cpf       = document.getElementById('cpf').value;
        var telefone  = document.getElementById('telefone').value;
        var pagamento = document.getElementById('forma_pagamento').value;
        var obs       = document.getElementById('observacoes').value;
        var total     = parseFloat(document.getElementById('total-input').value || subtotal);

        var btn = document.getElementById('btn-whatsapp');
        btn.disabled = true;
        btn.textContent = 'Processando...';

        fetch('salvar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nome: nome,
                cpf: cpf,
                telefone: telefone,
                forma_pagamento: pagamento,
                observacoes: obs,
                valor_produtos: subtotal,
                valor_total: total,
                itens: itensParaPedido
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                // Monta mensagem WhatsApp com adicionais
                var produtos = itensParaPedido.map(function(i) {
                    var linha = '*' + i.nome_produto + '*\n';
                    linha += '   Qtd: ' + i.quantidade + 'x';

                    if (i.adicionais && i.adicionais.length > 0) {
                        var nomes_ad = i.adicionais.map(function(a) { return a.nome; }).join(', ');
                        linha += '\n   Adicionais: ' + nomes_ad;
                    }

                    linha += '\n   Preco unit.: R$ ' + i.preco_unitario.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    linha += '\n   Subtotal: R$ '    + i.subtotal.toLocaleString('pt-BR',       {minimumFractionDigits: 2});
                    return linha;
                }).join('\n\n');

                var mensagem =
                    'NOVO PEDIDO #' + res.id_pedido + '\n' +
                    '─────────────────────\n' +
                    'DADOS PESSOAIS\n' +
                    'Nome: '     + nome     + '\n' +
                    'CPF: '      + cpf      + '\n' +
                    'Telefone: ' + telefone + '\n\n' +
                    'PRODUTOS\n' +
                    produtos    + '\n\n' +
                    'PAGAMENTO\n' +
                    'Forma: ' + pagamento +
                    (obs ? '\nObs: ' + obs : '') + '\n\n' +
                    'TOTAL: R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});

                var url = 'https://wa.me/5591984305884?text=' + encodeURIComponent(mensagem);
                window.open(url, '_blank');

                setTimeout(function() { window.location.href = 'index.php'; }, 1500);
            } else {
                alert('Erro ao registrar pedido: ' + (res.msg || 'Tente novamente.'));
                btn.disabled = false;
                btn.textContent = 'Finalizar no WhatsApp da loja';
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('Erro de conexao com o servidor.');
            btn.disabled = false;
            btn.textContent = 'Finalizar no WhatsApp da loja';
        });
    }
    </script>
</body>
</html>