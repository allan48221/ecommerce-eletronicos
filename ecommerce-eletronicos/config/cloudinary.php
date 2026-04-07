<?php
// Credenciais do Cloudinary — pegue em https://console.cloudinary.com
define('CLOUDINARY_CLOUD_NAME', 'dbmhzqykt');
define('CLOUDINARY_API_KEY',    '767492381377124');
define('CLOUDINARY_API_SECRET', '<your_api_secret>');

function cloudinary_upload(string $file_path, string $pasta = 'produtos'): string {
    $timestamp = time();

    // SEM barra no public_id — pasta vai no parametro "folder"
    $public_id = uniqid() . '_' . $timestamp;

    // Assinatura: parametros em ordem ALFABETICA, separados por &
    // folder vem antes de public_id que vem antes de timestamp
    $string_to_sign = 'folder=' . $pasta
                    . '&public_id=' . $public_id
                    . '&timestamp=' . $timestamp
                    . CLOUDINARY_API_SECRET;

    $signature = sha1($string_to_sign);

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
            'folder'     => $pasta,
            'signature'  => $signature,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('Erro cURL: ' . $curl_error);
    }

    if ($http_code !== 200) {
        throw new Exception('Cloudinary upload falhou (HTTP ' . $http_code . '): ' . $response);
    }

    $data = json_decode($response, true);
    if (empty($data['secure_url'])) {
        throw new Exception('Cloudinary nao retornou URL: ' . $response);
    }

    return $data['secure_url'];
}
