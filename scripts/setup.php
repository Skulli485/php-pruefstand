<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/import.php';

try {
    $pdo = database();
    create_schema($pdo);
    $showResult = import_shows($pdo);
    $episodeResult = import_episodes($pdo, $showResult['show_ids']);

    echo 'Seriendatenbank eingerichtet.' . PHP_EOL;
    echo 'Serien importiert: ' . $showResult['shows'] . PHP_EOL;
    echo 'Genres verknüpft: ' . $showResult['genre_relations'] . PHP_EOL;
    echo 'Episoden importiert: ' . $episodeResult['episodes'] . PHP_EOL;
    echo 'Übersprungen: ' . ($showResult['skipped'] + $episodeResult['skipped']) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Einrichtung fehlgeschlagen: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
