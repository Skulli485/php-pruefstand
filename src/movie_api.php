<?php

declare(strict_types=1);

const TMDB_API_BASE_URL = 'https://api.themoviedb.org/3';
const TMDB_IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/w500';

class TmdbConfigurationException extends RuntimeException
{
}

function tmdb_is_configured(): bool
{
    return tmdb_credentials() !== null;
}

function tmdb_credentials(): ?array
{
    foreach (['TMDB_API_TOKEN', 'TMDB_API_READ_TOKEN'] as $variableName) {
        $token = getenv($variableName);

        if (!is_string($token) || trim($token) === '') {
            continue;
        }

        $token = trim($token);

        if (preg_match('/^[a-f0-9]{32}$/Di', $token) === 1) {
            return ['type' => 'api_key', 'value' => $token];
        }

        $token = preg_replace('/^Bearer\s+/i', '', $token) ?? $token;

        return ['type' => 'bearer', 'value' => $token];
    }

    $apiKey = getenv('TMDB_API_KEY');

    if (is_string($apiKey) && preg_match('/^[a-f0-9]{32}$/Di', trim($apiKey)) === 1) {
        return ['type' => 'api_key', 'value' => trim($apiKey)];
    }

    return null;
}

function tmdb_get(string $path, array $query = []): array
{
    $credentials = tmdb_credentials();

    if ($credentials === null) {
        throw new TmdbConfigurationException(
            'Für die automatische Filmsuche fehlen TMDB-Zugangsdaten.'
        );
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Die PHP-cURL-Erweiterung fehlt.');
    }

    if ($credentials['type'] === 'api_key') {
        $query['api_key'] = $credentials['value'];
    }

    $url = TMDB_API_BASE_URL . $path;

    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $handle = curl_init($url);

    if ($handle === false) {
        throw new RuntimeException('Der TMDB-Abruf konnte nicht vorbereitet werden.');
    }

    $headers = ['Accept: application/json'];

    if ($credentials['type'] === 'bearer') {
        $headers[] = 'Authorization: Bearer ' . $credentials['value'];
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'film-und-serienpruefstand/1.0',
    ]);

    $rawResponse = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if (!is_string($rawResponse) || $statusCode < 200 || $statusCode >= 300) {
        if ($statusCode === 401) {
            throw new TmdbConfigurationException('Der TMDB-API-Token ist ungültig.');
        }

        throw new RuntimeException(
            'TMDB ist derzeit nicht erreichbar (HTTP ' . $statusCode . ').'
        );
    }

    $data = json_decode($rawResponse, true);

    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('TMDB hat kein gültiges JSON geliefert.');
    }

    return $data;
}

function tmdb_text(mixed $value, string $fallback = ''): string
{
    if (!is_string($value)) {
        return $fallback;
    }

    $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text));

    return is_string($text) && $text !== '' ? $text : $fallback;
}

function tmdb_date(mixed $value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
}

function tmdb_positive_integer(mixed $value): ?int
{
    $integer = filter_var($value, FILTER_VALIDATE_INT);

    return $integer !== false && $integer > 0 ? $integer : null;
}

function tmdb_image_url(mixed $path): string
{
    if (!is_string($path) || preg_match('#^/[a-zA-Z0-9._-]+$#D', $path) !== 1) {
        return '';
    }

    return TMDB_IMAGE_BASE_URL . $path;
}

function tmdb_https_url(mixed $value): string
{
    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $scheme = parse_url($value, PHP_URL_SCHEME);

    return is_string($scheme) && strtolower($scheme) === 'https' ? $value : '';
}

function tmdb_movie_genres(): array
{
    static $genres = null;

    if (is_array($genres)) {
        return $genres;
    }

    $response = tmdb_get('/genre/movie/list', ['language' => 'de-DE']);
    $genres = [];

    foreach ($response['genres'] ?? [] as $genre) {
        if (!is_array($genre)) {
            continue;
        }

        $id = tmdb_positive_integer($genre['id'] ?? null);
        $name = tmdb_text($genre['name'] ?? '');

        if ($id !== null && $name !== '') {
            $genres[$id] = $name;
        }
    }

    return $genres;
}

function search_tmdb_movies(string $query): array
{
    $response = tmdb_get('/search/movie', [
        'query' => $query,
        'language' => 'de-DE',
        'region' => 'DE',
        'include_adult' => 'false',
        'page' => 1,
    ]);
    $genreMap = tmdb_movie_genres();
    $movies = [];

    foreach (array_slice($response['results'] ?? [], 0, 8) as $movie) {
        if (!is_array($movie) || ($movie['adult'] ?? false) === true) {
            continue;
        }

        $externalId = tmdb_positive_integer($movie['id'] ?? null);
        $title = tmdb_text($movie['title'] ?? '');

        if ($externalId === null || $title === '') {
            continue;
        }

        $genres = [];

        foreach ($movie['genre_ids'] ?? [] as $genreId) {
            $genreId = tmdb_positive_integer($genreId);

            if ($genreId !== null && isset($genreMap[$genreId])) {
                $genres[] = $genreMap[$genreId];
            }
        }

        $movies[] = [
            'external_id' => $externalId,
            'title' => $title,
            'original_title' => tmdb_text($movie['original_title'] ?? ''),
            'original_language' => tmdb_text($movie['original_language'] ?? ''),
            'release_date' => tmdb_date($movie['release_date'] ?? null),
            'overview' => tmdb_text(
                $movie['overview'] ?? '',
                'Für diesen Film ist bei TMDB noch keine deutsche Beschreibung verfügbar.'
            ),
            'poster_url' => tmdb_image_url($movie['poster_path'] ?? null),
            'source_url' => 'https://www.themoviedb.org/movie/' . $externalId,
            'genres' => $genres,
        ];
    }

    return $movies;
}

function tmdb_movie_details(int $externalId): array
{
    if ($externalId < 1) {
        throw new InvalidArgumentException('Die TMDB-ID muss positiv sein.');
    }

    $movie = tmdb_get('/movie/' . $externalId, ['language' => 'de-DE']);
    $returnedId = tmdb_positive_integer($movie['id'] ?? null);
    $title = tmdb_text($movie['title'] ?? '');

    if ($returnedId !== $externalId || $title === '') {
        throw new RuntimeException('TMDB hat keinen vollständigen Filmdatensatz geliefert.');
    }

    $overview = tmdb_text($movie['overview'] ?? '');

    if ($overview === '') {
        $englishMovie = tmdb_get('/movie/' . $externalId, ['language' => 'en-US']);
        $overview = tmdb_text(
            $englishMovie['overview'] ?? '',
            'Für diesen Film ist bei TMDB noch keine Beschreibung verfügbar.'
        );
    }

    $genres = [];

    foreach ($movie['genres'] ?? [] as $genre) {
        if (!is_array($genre)) {
            continue;
        }

        $name = tmdb_text($genre['name'] ?? '');

        if ($name !== '') {
            $genres[] = $name;
        }
    }

    $providerResponse = tmdb_get('/movie/' . $externalId . '/watch/providers');
    $germanProviders = is_array($providerResponse['results']['DE'] ?? null)
        ? $providerResponse['results']['DE']
        : [];
    $providers = [];
    $offerTypes = ['flatrate', 'free', 'ads', 'rent', 'buy'];

    foreach ($offerTypes as $offerType) {
        foreach ($germanProviders[$offerType] ?? [] as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $providerId = tmdb_positive_integer($provider['provider_id'] ?? null);
            $providerName = tmdb_text($provider['provider_name'] ?? '');

            if ($providerId === null || $providerName === '') {
                continue;
            }

            $providers[] = [
                'provider_id' => $providerId,
                'provider_name' => $providerName,
                'provider_logo_url' => tmdb_image_url($provider['logo_path'] ?? null),
                'offer_type' => $offerType,
                'display_priority' => max(0, (int) ($provider['display_priority'] ?? 0)),
                'country_code' => 'DE',
            ];
        }
    }

    return [
        'external_id' => $externalId,
        'title' => $title,
        'original_title' => tmdb_text($movie['original_title'] ?? ''),
        'original_language' => tmdb_text($movie['original_language'] ?? ''),
        'status' => tmdb_text($movie['status'] ?? ''),
        'release_date' => tmdb_date($movie['release_date'] ?? null),
        'overview' => $overview,
        'poster_url' => tmdb_image_url($movie['poster_path'] ?? null),
        'source_url' => 'https://www.themoviedb.org/movie/' . $externalId,
        'provider_link' => tmdb_https_url($germanProviders['link'] ?? null),
        'runtime' => tmdb_positive_integer($movie['runtime'] ?? null),
        'genres' => array_values(array_unique($genres)),
        'providers' => $providers,
    ];
}
