<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/import.php';

try {
    $pdo = database();
    create_initial_schema($pdo);
    create_comments_schema($pdo);
    $initialResult = import_initial_data($pdo);
    $commentsResult = import_comments($pdo);

    echo 'Datenbank eingerichtet.' . PHP_EOL;
    echo 'Autoren importiert: ' . $initialResult['authors'] . PHP_EOL;
    echo 'Beiträge importiert: ' . $initialResult['posts'] . PHP_EOL;
    echo 'Kommentare importiert: ' . $commentsResult['comments'] . PHP_EOL;
    echo 'Übersprungen: ' . ($initialResult['skipped'] + $commentsResult['skipped']) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Einrichtung fehlgeschlagen: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
