<?php

declare(strict_types=1);

const DATABASE_PATH = __DIR__ . '/../data/serienpruefstand.sqlite';

function execute_statement(PDO $pdo, string $sql, array $parameters = []): PDOStatement
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement;
}

function database(): PDO
{
    static $pdo = null;

    if (!($pdo instanceof PDO)) {
        $pdo = new PDO(
            'sqlite:' . DATABASE_PATH,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        execute_statement($pdo, 'PRAGMA foreign_keys = ON');
    }

    return $pdo;
}

function create_schema(PDO $pdo): void
{
    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS shows (
            id INTEGER PRIMARY KEY,
            external_id INTEGER UNIQUE,
            name TEXT NOT NULL,
            language TEXT NOT NULL,
            status TEXT NOT NULL,
            premiered TEXT,
            summary TEXT NOT NULL,
            image_url TEXT NOT NULL DEFAULT \'\',
            source_url TEXT NOT NULL DEFAULT \'\',
            official_site_url TEXT NOT NULL DEFAULT \'\',
            distribution_name TEXT NOT NULL DEFAULT \'\',
            distribution_type TEXT NOT NULL DEFAULT \'\',
            distribution_country TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $showColumns = array_column(
        execute_statement($pdo, 'PRAGMA table_info(shows)')->fetchAll(),
        'name'
    );
    $showColumnMigrations = [
        'official_site_url' => 'ALTER TABLE shows ADD COLUMN official_site_url TEXT NOT NULL DEFAULT \'\'',
        'distribution_name' => 'ALTER TABLE shows ADD COLUMN distribution_name TEXT NOT NULL DEFAULT \'\'',
        'distribution_type' => 'ALTER TABLE shows ADD COLUMN distribution_type TEXT NOT NULL DEFAULT \'\'',
        'distribution_country' => 'ALTER TABLE shows ADD COLUMN distribution_country TEXT NOT NULL DEFAULT \'\'',
    ];

    foreach ($showColumnMigrations as $column => $sql) {
        if (!in_array($column, $showColumns, true)) {
            execute_statement($pdo, $sql);
        }
    }

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS episodes (
            id INTEGER PRIMARY KEY,
            external_id INTEGER NOT NULL UNIQUE,
            show_id INTEGER NOT NULL REFERENCES shows(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            season INTEGER,
            number INTEGER,
            airdate TEXT,
            runtime INTEGER,
            summary TEXT NOT NULL DEFAULT \'\'
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS genres (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS show_genre (
            show_id INTEGER NOT NULL REFERENCES shows(id) ON DELETE CASCADE,
            genre_id INTEGER NOT NULL REFERENCES genres(id),
            PRIMARY KEY (show_id, genre_id)
        )'
    );

    execute_statement(
        $pdo,
        'CREATE INDEX IF NOT EXISTS idx_episodes_show_id ON episodes(show_id)'
    );
    execute_statement(
        $pdo,
        'CREATE INDEX IF NOT EXISTS idx_show_genre_genre_id ON show_genre(genre_id)'
    );
}
