<?php

declare(strict_types=1);

function route_parameters(string $pattern, string $path): ?array
{
    $patternParts = $pattern === '/' ? [] : explode('/', trim($pattern, '/'));
    $pathParts = $path === '/' ? [] : explode('/', trim($path, '/'));

    if (count($patternParts) !== count($pathParts)) {
        return null;
    }

    $parameters = [];

    foreach ($patternParts as $index => $patternPart) {
        $pathPart = rawurldecode($pathParts[$index]);

        if (str_starts_with($patternPart, '{') && str_ends_with($patternPart, '}')) {
            $name = trim($patternPart, '{}');

            if ($name === '' || $pathPart === '') {
                return null;
            }

            if ($name === 'id' && preg_match('/^\d+$/D', $pathPart) !== 1) {
                return null;
            }

            $parameters[$name] = $pathPart;
            continue;
        }

        if ($patternPart !== $pathPart) {
            return null;
        }
    }

    return $parameters;
}
function dispatch(array $routes, string $method, string $path): void
{
    $pathExists = false;
    $allowedMethods = [];

    foreach ($routes as $route) {
        $parameters = route_parameters($route['path'], $path);

        if ($parameters === null) {
            continue;
        }

        $pathExists = true;
        $allowedMethods[] = $route['method'];

        if ($route['method'] === $method) {
            $route['handler']($parameters);
            return;
        }
    }

    if ($pathExists) {
        http_response_code(405);
        header('Allow: ' . implode(', ', array_unique($allowedMethods)));
        page_start('Methode nicht erlaubt');
        echo '<h1>Methode nicht erlaubt</h1>';
        echo '<p>Diese Route unterstützt die verwendete HTTP-Methode nicht.</p>';
        page_end();
        return;
    }

    not_found();
}
