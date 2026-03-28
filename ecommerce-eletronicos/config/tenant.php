<?php
// ============================================================
//  config/tenant.php
//  Inclua este arquivo NO TOPO de todo database.php
//  ANTES de qualquer outro código.
//
//  O que ele faz:
//   1. Detecta o subdomínio da requisição
//   2. Busca o tenant no banco
//   3. Valida a licença (plano ativo e dentro da validade)
//   4. Carrega as telas permitidas na sessão
//   5. Bloqueia acesso se licença inválida/expirada
// ============================================================

// ── Detectar subdomínio ──────────────────────────────────────
function detectar_subdominio(): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = explode(':', $host)[0]; // remove porta se houver

    $partes = explode('.', $host);

    // localhost ou domínio simples sem subdomínio → tenant principal
    if (count($partes) <= 1 || $host === 'localhost') {
        return 'principal';
    }

    return $partes[0]; // ex: "empresanova" de "empresanova.seusite.com"
}
// ── Carregar tenant na sessão ────────────────────────────────
function carregar_tenant(PDO $conn): void {
    // Se já carregou nesta sessão, não busca de novo
    if (!empty($_SESSION['tenant_carregado'])) {
        return;
    }

    $subdominio = detectar_subdominio();

    // Busca tenant + licença + plano em uma query só
    $stmt = $conn->prepare("
        SELECT
            et.id_tenant,
            et.nome         AS tenant_nome,
            et.subdominio,
            et.ativo        AS tenant_ativo,
            l.id_licenca,
            l.id_plano,
            l.data_vencimento,
            l.ativo         AS licenca_ativa,
            p.nome          AS plano_nome
        FROM empresas_tenants et
        LEFT JOIN licencas l
            ON l.id_tenant = et.id_tenant
            AND l.ativo = TRUE
            AND l.data_vencimento >= CURRENT_DATE
        LEFT JOIN planos p ON p.id_plano = l.id_plano
        WHERE et.subdominio = ?
          AND et.ativo = TRUE
        ORDER BY l.data_vencimento DESC
        LIMIT 1
    ");
    $stmt->execute([$subdominio]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        // Empresa não encontrada ou inativa
        _bloquear_acesso('empresa_nao_encontrada', $subdominio);
    }

    if (empty($tenant['id_licenca'])) {
        // Empresa existe mas sem licença válida
        _bloquear_acesso('licenca_invalida', $subdominio);
    }

    // Busca quais telas o plano libera
    $stmt2 = $conn->prepare("
        SELECT t.chave
        FROM plano_telas pt
        JOIN telas t ON t.id_tela = pt.id_tela
        WHERE pt.id_plano = ?
          AND t.ativo = TRUE
    ");
    $stmt2->execute([$tenant['id_plano']]);
    $telas = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    // Salva na sessão
    $_SESSION['id_tenant']          = $tenant['id_tenant'];
    $_SESSION['tenant_nome']        = $tenant['tenant_nome'];
    $_SESSION['tenant_subdominio']  = $tenant['subdominio'];
    $_SESSION['id_plano']           = $tenant['id_plano'];
    $_SESSION['plano_nome']         = $tenant['plano_nome'];
    $_SESSION['plano_vencimento']   = $tenant['data_vencimento'];
    $_SESSION['telas_permitidas']   = $telas;
    $_SESSION['tenant_carregado']   = true;
}

// ── Verificar acesso a uma tela específica ───────────────────
// Use no topo de cada página protegida:
//   verificar_acesso('caixa');
function verificar_acesso(string $chave_tela): void {
    if (empty($_SESSION['telas_permitidas'])) {
        header('Location: sem_acesso.php?motivo=sem_plano');
        exit;
    }
    if (!in_array($chave_tela, $_SESSION['telas_permitidas'], true)) {
        header('Location: sem_acesso.php?motivo=tela_bloqueada&tela=' . urlencode($chave_tela));
        exit;
    }
}

// ── Bloquear acesso (tela de erro) ──────────────────────────
function _bloquear_acesso(string $motivo, string $subdominio = ''): void {
    // Destrói sessão por segurança
    session_destroy();

    $msgs = [
        'empresa_nao_encontrada' => 'Sistema não encontrado para este endereço.',
        'licenca_invalida'       => 'Licença expirada ou inativa. Entre em contato com o suporte.',
    ];
    $msg = $msgs[$motivo] ?? 'Acesso não autorizado.';

    // Redireciona para página de bloqueio ou exibe mensagem direta
    if (file_exists(__DIR__ . '/../licenca_bloqueada.php')) {
        $_GET['motivo'] = $motivo;
        header('Location: /licenca_bloqueada.php?motivo=' . urlencode($motivo));
        exit;
    }

    // Fallback: exibe mensagem inline simples
    http_response_code(403);
    die("
    <!DOCTYPE html><html lang='pt-BR'><head>
    <meta charset='UTF-8'><title>Acesso Bloqueado</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;
    justify-content:center;min-height:100vh;background:#f1f5f9;margin:0}
    .box{background:#fff;padding:40px;border-radius:12px;text-align:center;
    box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:400px}
    h2{color:#dc2626;margin-bottom:12px}p{color:#64748b}</style>
    </head><body><div class='box'>
    <h2>⚠ Acesso Bloqueado</h2>
    <p>$msg</p>
    <p style='font-size:12px;margin-top:20px;color:#94a3b8'>
        Subdomínio: <code>$subdominio</code>
    </p>
    </div></body></html>");
}

// ── Helper: retorna id_tenant da sessão ─────────────────────
function tenant_id(): ?int {
    return $_SESSION['id_tenant'] ?? null;
}

// ── Helper: verifica se tela está liberada (sem redirecionar) 
function tela_liberada(string $chave): bool {
    return in_array($chave, $_SESSION['telas_permitidas'] ?? [], true);
}