<?php
// Credenciais do Cloudinary — pegue em https://console.cloudinary.com
define('CLOUDINARY_CLOUD_NAME',    'dbmhzqykt');
define('CLOUDINARY_UPLOAD_PRESET', 'hwtwawgg'); // nome do preset unsigned criado no painel

/**
 * Upload sem assinatura (unsigned) — mais simples e sem risco de erro de assinatura.
 * Requer um Upload Preset do tipo "Unsigned" criado no painel do Cloudinary.
 */
function cloudinary_upload(string $file_path, string $pasta = 'produtos'): string {
    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/upload';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'file'          => new CURLFile($file_path),
            'upload_preset' => CLOUDINARY_UPLOAD_PRESET,
            'folder'        => $pasta,
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
