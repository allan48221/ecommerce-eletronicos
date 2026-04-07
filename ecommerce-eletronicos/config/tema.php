<?php
function aplicar_tema($conn) {
    $id_tenant = $_SESSION['id_tenant'] ?? null;

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

    $result = @$conn->prepare("SELECT chave, valor FROM configuracoes WHERE id_tenant IS NOT DISTINCT FROM ?");
    if ($result && $result->execute([$id_tenant])) {
        while ($row = $result->fetch()) {
            $cores[$row['chave']] = htmlspecialchars($row['valor']);
        }
    }

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
    .admin-header {
        background: linear-gradient(135deg, {$cores['cor_header_grad1']} 0%, {$cores['cor_header_grad2']} 100%) !important;
    }
    #sidebar .sidebar-header {
        background: linear-gradient(135deg, {$cores['cor_header_grad1']} 0%, {$cores['cor_header_grad2']} 100%) !important;
    }
    .footer {
        background: linear-gradient(135deg, {$cores['cor_header_grad1']} 0%, {$cores['cor_header_grad2']} 100%) !important;
    }
    .navbar nav a.btn-carrinho {
        background: {$cores['cor_primary']} !important;
    }
    .navbar nav a.btn-carrinho:hover {
        background: {$cores['cor_primary_dark']} !important;
    }
    .btn-primary, .btn-adicionar-detalhe, .btn-adicionar-imagem {
        background: linear-gradient(135deg, {$cores['cor_primary']}, {$cores['cor_primary_dark']}) !important;
    }
    .navbar .logo {
        background: linear-gradient(135deg, {$cores['cor_primary']}, {$cores['cor_secondary']}) !important;
        -webkit-background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
    }
</style>
";
}
?>
