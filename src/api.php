<?php

declare(strict_types=1);

const API_BASE_URL = 'https://jsonplaceholder.typicode.com';

function api_get(string $path): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: php-pruefstand/1.0\r\n",
        ],
    ]);

    $rawResponse = @file_get_contents(API_BASE_URL . $path, false, $context);
    $statusCode = 0;

    foreach ($http_response_header ?? [] as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches) === 1) {
            $statusCode = (int) $matches[1];
            break;
        }
    }

    if ($rawResponse === false || $statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException(
            'Die externe Datenquelle ist derzeit nicht erreichbar (HTTP ' . $statusCode . ').'
        );
    }

    $data = json_decode($rawResponse, true);

    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Die externe Datenquelle hat kein gültiges JSON geliefert.');
    }

    return $data;
}
