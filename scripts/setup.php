<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/import.php';

try {
    $pdo = database();
    create_initial_schema($pdo);
    $result = import_initial_data($pdo);

    echo 'Datenbank eingerichtet.' . PHP_EOL;
    echo 'Autoren importiert: ' . $result['authors'] . PHP_EOL;
    echo 'Beiträge importiert: ' . $result['posts'] . PHP_EOL;
    echo 'Übersprungen: ' . $result['skipped'] . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Einrichtung fehlgeschlagen: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
