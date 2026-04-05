<?php
require_once 'config/database.php';
require_once 'config/tema.php';

if (!isset($_SESSION['id_admin'])) {
    header('Location: login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Modo Restaurante</title>
<?= aplicar_tema($conn) ?>
<link rel="icon" type="image/png" href="favicon.png?v=1">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sora', sans-serif; background: var(--dash-bg, #f1f5f9); min-height: 100vh; color: #0f172a; }
.mr-page { max-width: 700px; margin: 0 auto; padding: 32px 20px 80px; }
.mr-topbar { display: flex; align-items: center; margin-bottom: 28px; }
.mr-btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #fff; color: #475569; border: 1.5px solid #e2e8f0; border-radius: 50px; font-size: 14px; font-weight: 600; text-decoration: none; font-family: 'Sora', sans-serif; }

/* ── HERO ── */
.mr-hero { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); border-radius: 22px; padding: 32px; color: #fff; margin-bottom: 28px; position: relative; overflow: hidden; }
.mr-hero::before { content: ''; position: absolute; right: -30px; top: -30px; width: 180px; height: 180px; background: rgba(255,255,255,.08); border-radius: 50%; }
.mr-hero::after  { content: ''; position: absolute; left: -20px; bottom: -40px; width: 140px; height: 140px; background: rgba(255,255,255,.05); border-radius: 50%; }
.mr-hero h1 { font-size: 26px; font-weight: 800; margin-bottom: 8px; }
.mr-hero p  { font-size: 14px; opacity: .85; line-height: 1.6; max-width: 420px; }
.mr-hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.2); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 14px; }

/* ── TOGGLE ── */
.mr-toggle-card { background: #fff; border-radius: 18px; border: 1.5px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,.06); padding: 24px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.mr-toggle-info h2 { font-size: 16px; font-weight: 800; }
.mr-toggle-info p  { font-size: 13px; color: #64748b; margin-top: 4px; line-height: 1.5; }
.mr-toggle { position: relative; width: 56px; height: 30px; flex-shrink: 0; }
.mr-toggle input { opacity: 0; width: 0; height: 0; }
.mr-toggle-slider { position: absolute; inset: 0; background: #e2e8f0; border-radius: 30px; cursor: pointer; transition: .3s; }
.mr-toggle-slider::before { content: ''; position: absolute; width: 22px; height: 22px; left: 4px; bottom: 4px; background: #fff; border-radius: 50%; transition: .3s; box-shadow: 0 2px 6px rgba(0,0,0,.2); }
.mr-toggle input:checked + .mr-toggle-slider { background: #f97316; }
.mr-toggle input:checked + .mr-toggle-slider::before { transform: translateX(26px); }

/* ── MÓDULOS ── */
.mr-modulos { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.mr-modulo { background: #fff; border-radius: 18px; border: 1.5px solid #e2e8f0; padding: 24px 20px; text-decoration: none; color: #0f172a; transition: .2s; box-shadow: 0 2px 8px rgba(0,0,0,.05); display: flex; flex-direction: column; gap: 10px; }
.mr-modulo:hover { box-shadow: 0 8px 24px rgba(0,0,0,.12); transform: translateY(-2px); }
.mr-modulo-icon { font-size: 32px; }
.mr-modulo-titulo { font-size: 15px; font-weight: 800; }
.mr-modulo-desc   { font-size: 12px; color: #64748b; line-height: 1.5; }
.mr-modulo-badge  { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 20px; margin-top: 4px; width: fit-content; }
.mr-modulo.caixa  { border-color: #d1fae5; }
.mr-modulo.caixa .mr-modulo-badge { background: #dcfce7; color: #059669; }
.mr-modulo.atend  { border-color: #dbeafe; }
.mr-modulo.atend .mr-modulo-badge { background: #dbeafe; color: #1e40af; }

/* ── INFO ── */
.mr-info { background: #fff; border-radius: 16px; border: 1.5px solid #e2e8f0; padding: 20px; }
.mr-info h3 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #64748b; margin-bottom: 12px; }
.mr-info-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f8fafc; font-size: 13px; }
.mr-info-item:last-child { border-bottom: none; }
.mr-info-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.mr-info-txt { color: #475569; line-height: 1.5; }

@media (max-width: 480px) {
    .mr-modulos { grid-template-columns: 1fr; }
    .mr-hero h1 { font-size: 22px; }
}
</style>
</head>
<body>

<div class="mr-page">

    <div class="mr-topbar">
        <a href="admin.php" class="mr-btn-back">&#8592; Voltar ao Admin</a>
    </div>

    <div class="mr-hero">
        <h1>&#127859; Modo Restaurante</h1>
        <p>Gerencie comandas, atendimento de mesa e caixa — tudo integrado ao seu estoque.</p>
        <div class="mr-hero-badge">
            &#9679; <?= $stats['total'] ?> comanda(s) aberta(s) · R$ <?= number_format($stats['soma'], 2, ',', '.') ?> em aberto
        </div>
        <?php endif; ?>
    </div>

    <!-- Módulos -->
    <div class="mr-modulos">

        <a href="caixa.php" class="mr-modulo caixa">
            <div class="mr-modulo-icon">&#127963;</div>
            <div>
                <div class="mr-modulo-titulo">Caixa</div>
                <div class="mr-modulo-desc">Veja as comandas abertas, dê baixa no estoque e feche o pagamento.</div>
            </div>
            <div class="mr-modulo-badge">
                <?php if ($stats['total'] > 0): ?>
                    &#128308; <?= $stats['total'] ?> aberta(s)
                <?php else: ?>
                    &#128994; Sem comandas
                <?php endif; ?>
            </div>
        </a>

        <a href="atendente.php" class="mr-modulo atend">
            <div class="mr-modulo-icon">&#128203;</div>
            <div>
                <div class="mr-modulo-titulo">Atendente</div>
                <div class="mr-modulo-desc">Lance produtos na comanda com adicionais, observações e número da comanda.</div>
            </div>
            <div class="mr-modulo-badge">&#43; Nova Comanda</div>
        </a>

    </div>

    <!-- Fluxo -->
    <div class="mr-info">
        <h3>Como funciona o fluxo</h3>
        <div class="mr-info-item">
            <span class="mr-info-icon">1️⃣</span>
            <span class="mr-info-txt"><strong>Atendente</strong> acessa a tela de atendente, busca os produtos, seleciona adicionais e lança a comanda com o número.</span>
        </div>
        <div class="mr-info-item">
            <span class="mr-info-icon">2️⃣</span>
            <span class="mr-info-txt"><strong>Caixa</strong> vê as comandas abertas em tempo real, com timer mostrando quanto tempo cada uma está aberta.</span>
        </div>
        <div class="mr-info-item">
            <span class="mr-info-icon">3️⃣</span>
            <span class="mr-info-txt"><strong>Ao fechar</strong> a comanda, o caixa seleciona a forma de pagamento, o estoque é baixado automaticamente e um pedido é gerado no sistema.</span>
        </div>
        <div class="mr-info-item">
            <span class="mr-info-icon">&#128203;</span>
            <span class="mr-info-txt"><strong>Adicionais</strong> por produto podem ser cadastrados no painel de produtos — eles aparecem automaticamente na tela do atendente.</span>
        </div>
    </div>

</div>

</body>
</html>
