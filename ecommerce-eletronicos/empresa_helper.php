<?php
/**
 * empresa_helper.php
 * Inclua este arquivo nas telas que precisam dos dados da empresa.
 * Uso: require_once 'empresa_helper.php';
 * Depois use: $emp['cnpj'], $emp['nome_empresa'], etc.
 */
function getDadosEmpresa(PDO $conn): array {
    $id_tenant = $_SESSION['id_tenant'] ?? null;
    try {
        $stmt = $conn->prepare("SELECT * FROM empresa WHERE id_tenant = ? LIMIT 1");
        $stmt->execute([$id_tenant]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            // Formata CNPJ: 00.000.000/0001-00
            if (!empty($r['cnpj']) && strlen($r['cnpj']) === 14) {
                $r['cnpj_formatado'] = preg_replace(
                    '/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/',
                    '$1.$2.$3/$4-$5',
                    $r['cnpj']
                );
            } else {
                $r['cnpj_formatado'] = $r['cnpj'] ?? '';
            }
            // Endereço completo
            $partes = array_filter([
                $r['endereco']    ?? '',
                $r['numero']      ? 'nº ' . $r['numero'] : '',
                $r['complemento'] ?? '',
                $r['bairro']      ?? '',
                $r['cidade']      ?? '',
                $r['uf']          ?? '',
            ]);
            $r['endereco_completo'] = implode(', ', $partes);
            return $r;
        }
    } catch (\Throwable $e) {}
    // Retorna array vazio com todas as chaves para não quebrar o código
    return [
        'nome_empresa'      => '',
        'nome_fantasia'     => '',
        'cnpj'              => '',
        'cnpj_formatado'    => '',
        'telefone'          => '',
        'celular'           => '',
        'email'             => '',
        'nome_responsavel'  => '',
        'cep'               => '',
        'endereco'          => '',
        'numero'            => '',
        'complemento'       => '',
        'bairro'            => '',
        'cidade'            => '',
        'uf'                => '',
        'site'              => '',
        'instagram'         => '',
        'whatsapp'          => '',
        'horario_atendimento' => '',
        'formas_pagamento'  => '',
        'descricao_loja'    => '',
        'logo'              => '',
        'endereco_completo' => '',
    ];
}  
if (!function_exists('img_src')) {
    function img_src(string $path): string {
        if (empty($path)) return '';
        // Se já é uma URL completa (Cloudinary), usa direto
        if (str_starts_with($path, 'http')) return htmlspecialchars($path);
        // Fallback para arquivos locais antigos (legado)
        return 'uploads/' . htmlspecialchars($path);
    }
}       
