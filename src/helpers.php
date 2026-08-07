<?php

declare(strict_types=1);

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
        '/beitraege' => 'Beiträge',
        '/resonanz' => 'Resonanz',
    ];
    ?>
    <!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · Redaktionsprüfstand</title>
        <link rel="stylesheet" href="/styles.css">
    </head>
    <body>
        <header class="site-header">
            <a class="brand" href="/">Redaktionsprüfstand</a>
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
        <footer class="site-footer">PHP · PDO · SQLite · Eigener Router</footer>
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
