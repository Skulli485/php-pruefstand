<?php

declare(strict_types=1);

const API_BASE_URL = 'https://api.tvmaze.com';

function api_get(string $path): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Die PHP-cURL-Erweiterung fehlt.');
    }

    $handle = curl_init(API_BASE_URL . $path);

    if ($handle === false) {
        throw new RuntimeException('Der TVmaze-Abruf konnte nicht vorbereitet werden.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'serienpruefstand/1.0',
    ]);

    $rawResponse = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if (!is_string($rawResponse) || $statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException(
            'TVmaze ist derzeit nicht erreichbar (HTTP ' . $statusCode . ').'
        );
    }

    $data = json_decode($rawResponse, true);

    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('TVmaze hat kein gültiges JSON geliefert.');
    }

    return $data;
}
