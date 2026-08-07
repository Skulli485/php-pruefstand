<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/router.php';
require_once __DIR__ . '/../src/routes.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = is_string($path) && $path !== '' ? $path : '/';

if (PHP_SAPI === 'cli-server' && $path === '/styles.css') {
    return false;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $pdo = database();
    $routes = [
        ['method' => 'GET', 'path' => '/', 'handler' => fn(array $_parameters) => show_home($pdo)],
        ['method' => 'GET', 'path' => '/serien', 'handler' => fn(array $_parameters) => show_series($pdo)],
        ['method' => 'GET', 'path' => '/episoden', 'handler' => fn(array $_parameters) => show_episode_analysis($pdo)],
        ['method' => 'GET', 'path' => '/serien/neu', 'handler' => fn(array $_parameters) => show_new_series_form($pdo)],
        ['method' => 'POST', 'path' => '/serien/neu', 'handler' => fn(array $_parameters) => handle_new_series($pdo)],
        ['method' => 'GET', 'path' => '/serien/{id}', 'handler' => fn(array $parameters) => show_series_detail($pdo, $parameters)],
    ];

    dispatch($routes, $method, $path);
} catch (Throwable $error) {
    error_log($error->__toString());
    http_response_code(500);
    page_start('Interner Fehler');
    echo '<section class="empty-state">';
    echo '<p class="status-code">500</p>';
    echo '<h1>Der Prüfstand konnte nicht geladen werden.</h1>';
    echo '<p>Bitte führe zuerst <code>php scripts/setup.php</code> aus oder prüfe das Serverprotokoll.</p>';
    echo '</section>';
    page_end();
}
