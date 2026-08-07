<?php

declare(strict_types=1);

const CSRF_COOKIE_NAME = 'serienpruefstand_csrf';
const FLASH_COOKIE_NAME = 'serienpruefstand_flash';

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
function request_text(array $source, string $key): string
{
    $value = $source[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

function uses_https(): bool
{
    $forwardedProtocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

    return ($_SERVER['HTTPS'] ?? '') === 'on'
        || (is_string($forwardedProtocol) && strtolower($forwardedProtocol) === 'https');
}

function set_app_cookie(string $name, string $value, int $expires = 0): void
{
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => uses_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if ($expires !== 0 && $expires < time()) {
        unset($_COOKIE[$name]);
    } else {
        $_COOKIE[$name] = $value;
    }
}

function csrf_token(): string
{
    $token = $_COOKIE[CSRF_COOKIE_NAME] ?? null;

    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        set_app_cookie(CSRF_COOKIE_NAME, $token, time() + 60 * 60 * 24 * 30);
    }

    return $token;
}

function valid_csrf_token(mixed $token): bool
{
    $storedToken = $_COOKIE[CSRF_COOKIE_NAME] ?? null;

    return is_string($storedToken)
        && preg_match('/^[a-f0-9]{64}$/D', $storedToken) === 1
        && is_string($token)
        && hash_equals($storedToken, $token);
}

function rotate_csrf_token(): void
{
    set_app_cookie(
        CSRF_COOKIE_NAME,
        bin2hex(random_bytes(32)),
        time() + 60 * 60 * 24 * 30
    );
}

function set_flash_message(string $message): void
{
    $message = mb_substr($message, 0, 500);
    $payload = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payload, csrf_token());

    set_app_cookie(FLASH_COOKIE_NAME, $payload . '.' . $signature, time() + 120);
}

function take_flash_message(): string
{
    $flash = $_COOKIE[FLASH_COOKIE_NAME] ?? '';
    set_app_cookie(FLASH_COOKIE_NAME, '', time() - 3600);

    if (!is_string($flash) || !str_contains($flash, '.')) {
        return '';
    }

    [$payload, $signature] = explode('.', $flash, 2);
    $expectedSignature = hash_hmac('sha256', $payload, csrf_token());

    if (!hash_equals($expectedSignature, $signature)) {
        return '';
    }

    $padding = (4 - strlen($payload) % 4) % 4;
    $message = base64_decode(strtr($payload . str_repeat('=', $padding), '-_', '+/'), true);

    return is_string($message) ? $message : '';
}

function excerpt(string $text, int $length = 150): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length)) . ' …';
}

function page_start(string $title, string $activeNavigation = ''): void
{
    $navigation = [
        '/' => 'Übersicht',
        '/serien' => 'Serien',
        '/episoden' => 'Episoden',
        '/serien/neu' => 'Neue Serie',
        '/filme' => 'Filme',
        '/filme/neu' => 'Neuer Film',
    ];
    ?>
    <!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · Film- &amp; Serienprüfstand</title>
        <link rel="stylesheet" href="/styles.css">
    </head>
    <body>
        <header class="site-header">
            <a class="brand" href="/">Film- &amp; Serienprüfstand</a>
            <div class="measure" aria-hidden="true"></div>
            <nav aria-label="Hauptnavigation">
                <?php foreach ($navigation as $path => $label): ?>
                    <a
                        href="<?= e($path) ?>"
                        <?= $activeNavigation === $path ? 'aria-current="page"' : '' ?>
                    ><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
        </header>
        <main class="page-shell">
    <?php
}

function page_end(): void
{
    ?>
        </main>
        <footer class="site-footer">
            <p>
                PHP · PDO · SQL · Seriendaten von
                <a href="https://www.tvmaze.com/api">TVmaze</a>
            </p>
            <div class="tmdb-credit">
                <a href="https://www.themoviedb.org" target="_blank" rel="noopener noreferrer">
                    <img src="/images/tmdb-logo.svg" alt="TMDB">
                </a>
                <p>
                    Dieses Produkt nutzt die TMDB-API, wird jedoch nicht von TMDB unterstützt
                    oder zertifiziert. Anbieterinformationen: JustWatch über TMDB.
                </p>
            </div>
        </footer>
    </body>
    </html>
    <?php
}

function not_found(string $message = 'Die angeforderte Seite wurde nicht gefunden.'): void
{
    http_response_code(404);
    page_start('Nicht gefunden');
    ?>
    <section class="empty-state">
        <p class="status-code">404</p>
        <h1>Nicht gefunden</h1>
        <p><?= e($message) ?></p>
        <a class="text-link" href="/">Zur Übersicht</a>
    </section>
    <?php
    page_end();
}

function forbidden(string $message = 'Diese Aktion ist nicht erlaubt.'): void
{
    http_response_code(403);
    page_start('Zugriff verweigert');
    ?>
    <section class="empty-state">
        <p class="status-code">403</p>
        <h1>Zugriff verweigert</h1>
        <p><?= e($message) ?></p>
        <a class="text-link" href="/serien">Zurück zu allen Serien</a>
    </section>
    <?php
    page_end();
}
