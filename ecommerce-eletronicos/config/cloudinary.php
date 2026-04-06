<?php
// ─── Credenciais do Cloudinary ───────────────────────────────
// Pegue esses valores em: https://console.cloudinary.com
define('CLOUDINARY_CLOUD_NAME', 'dbmhzqykt');
define('CLOUDINARY_API_KEY',    '767492381377124');
define('CLOUDINARY_API_SECRET', '<your_api_secret>');

/**
 * Faz upload de um arquivo (path local ou blob) para o Cloudinary.
 * Retorna a URL segura da imagem ou lança Exception em caso de erro.
 */
function cloudinary_upload(string $file_path, string $pasta = 'produtos'): string {
    $timestamp  = time();
    $public_id  = $pasta . '/' . uniqid() . '_' . $timestamp;

    $params_to_sign = [
        'public_id' => $public_id,
        'timestamp' => $timestamp,
    ];
    ksort($params_to_sign);
    $query_string = http_build_query($params_to_sign);
    $signature    = sha1($query_string . CLOUDINARY_API_SECRET);

    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/upload';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'file'       => new CURLFile($file_path),
            'api_key'    => CLOUDINARY_API_KEY,
            'timestamp'  => $timestamp,
            'public_id'  => $public_id,
            'signature'  => $signature,
        ],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception('Cloudinary upload falhou (HTTP ' . $http_code . '): ' . $response);
    }

    $data = json_decode($response, true);
    if (empty($data['secure_url'])) {
        throw new Exception('Cloudinary nao retornou URL: ' . $response);
    }

    return $data['secure_url'];
}
