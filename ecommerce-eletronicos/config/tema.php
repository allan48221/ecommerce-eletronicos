<?php
/**
 * config/tema.php
 * Lê as cores do banco e retorna o bloco <style> com as variáveis CSS do tema.
 * Inclua este arquivo em TODAS as páginas, logo após o <link> do style.css.
 *
 * Uso: require_once 'config/tema.php'; echo aplicar_tema($conn);
 */

function aplicar_tema($conn) {
    // Valores padrão (caso a tabela ainda não exista)
    $defaults = [
        'cor_primary'      => '#2563eb',
        'cor_primary_dark' => '#1e40af',
        'cor_secondary'    => '#10b981',
        'cor_danger'       => '#ef4444',
        'cor_header_grad1' => '#1e40af',
        'cor_header_grad2' => '#7c3aed',
        'cor_fundo'        => '#f1f5f9',
    ];

    $cores = $defaults;

    $result = @$conn->query("SELECT chave, valor FROM configuracoes");
    if ($result) {
        while ($row = $result->fetch()) {
            $cores[$row['chave']] = htmlspecialchars($row['valor']);
        }
    }

    // Sanitiza — aceita apenas valores hex ou rgb válidos
    foreach ($cores as $k => $v) {
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) && !preg_match('/^rgb/', $v)) {
            $cores[$k] = $defaults[$k] ?? '#2563eb';
        }
    }

    return "
<style>
    :root {
        --primary:      {$cores['cor_primary']} !important;
        --primary-dark: {$cores['cor_primary_dark']} !important;
        --secondary:    {$cores['cor_secondary']} !important;
        --danger:       {$cores['cor_danger']} !important;

        /* aliases usados em outras telas */
        --brand:        {$cores['cor_primary']} !important;
        --brand-dark:   {$cores['cor_primary_dark']} !important;
        --purple:       {$cores['cor_header_grad2']} !important;
        --success:      {$cores['cor_secondary']} !important;
        --dash-bg:      {$cores['cor_fundo']} !important;
        --warning:      #f59e0b !important;
    }

    body:not(.fundo-branco) {
    background: {$cores['cor_fundo']} !important;

    }

    /* Gradiente do header admin */
    .admin-header {
        background: linear-gradient(135deg, {$cores['cor_header_grad1']} 0%, {$cores['cor_header_grad2']} 100%) !important;
    }

    /* Gradiente da sidebar */
    #sidebar .sidebar-header {
        background: linear-gradient(135deg, {$cores['cor_header_grad1']} 0%, {$cores['cor_header_grad2']} 100%) !important;
    }

    /* Footer */
    .footer {
        background: linear-gradient(135deg, {$cores['cor_header_grad1']} 0%, {$cores['cor_header_grad2']} 100%) !important;
    }

    /* Botão carrinho */
    .navbar nav a.btn-carrinho {
        background: {$cores['cor_primary']} !important;
    }
    .navbar nav a.btn-carrinho:hover {
        background: {$cores['cor_primary_dark']} !important;
    }

    /* Botões primários */
    .btn-primary, .btn-adicionar-detalhe, .btn-adicionar-imagem {
        background: linear-gradient(135deg, {$cores['cor_primary']}, {$cores['cor_primary_dark']}) !important;
    }

    /* Logo gradiente */
    .navbar .logo {
        background: linear-gradient(135deg, {$cores['cor_primary']}, {$cores['cor_secondary']}) !important;
        -webkit-background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
    }
</style>
";
}
?>