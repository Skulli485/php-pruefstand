<?php

declare(strict_types=1);

const DATABASE_PATH = __DIR__ . '/../data/serienpruefstand.sqlite';

function execute_statement(PDO $pdo, string $sql, array $parameters = []): PDOStatement
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement;
}

function database_driver(PDO $pdo): string
{
    return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function uses_postgres(PDO $pdo): bool
{
    return database_driver($pdo) === 'pgsql';
}

function postgres_connection(string $databaseUrl): PDO
{
    $parts = parse_url($databaseUrl);

    if ($parts === false || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)) {
        throw new RuntimeException('DATABASE_URL ist keine gültige Postgres-Verbindungsadresse.');
    }

    $host = (string) ($parts['host'] ?? '');
    $databaseName = ltrim((string) ($parts['path'] ?? ''), '/');
    $user = rawurldecode((string) ($parts['user'] ?? ''));
    $password = rawurldecode((string) ($parts['pass'] ?? ''));
    $port = filter_var($parts['port'] ?? 5432, FILTER_VALIDATE_INT);

    if ($host === '' || $databaseName === '' || $user === '' || $port === false) {
        throw new RuntimeException('DATABASE_URL enthält nicht alle benötigten Postgres-Zugangsdaten.');
    }

    parse_str((string) ($parts['query'] ?? ''), $query);
    $sslMode = is_string($query['sslmode'] ?? null) ? $query['sslmode'] : 'require';
    $allowedSslModes = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];

    if (!in_array($sslMode, $allowedSslModes, true)) {
        $sslMode = 'require';
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $host,
        $port,
        $databaseName,
        $sslMode
    );

    return new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function database(): PDO
{
    static $pdo = null;

    if (!($pdo instanceof PDO)) {
        $databaseUrl = getenv('DATABASE_URL');

        if (is_string($databaseUrl) && trim($databaseUrl) !== '') {
            $pdo = postgres_connection(trim($databaseUrl));
        } else {
            if (getenv('VERCEL') === '1') {
                throw new RuntimeException(
                    'Auf Vercel fehlt DATABASE_URL für die persistente Postgres-Datenbank.'
                );
            }

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
    }

    return $pdo;
}

function insert_and_return_id(PDO $pdo, string $sql, array $parameters): int
{
    if (uses_postgres($pdo)) {
        $id = execute_statement($pdo, $sql . ' RETURNING id', $parameters)->fetchColumn();
    } else {
        execute_statement($pdo, $sql, $parameters);
        $id = $pdo->lastInsertId();
    }

    $id = filter_var($id, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        throw new RuntimeException('Die neue Datenbank-ID konnte nicht ermittelt werden.');
    }

    return $id;
}

function create_schema(PDO $pdo): void
{
    if (uses_postgres($pdo)) {
        create_postgres_schema($pdo);
        return;
    }

    create_sqlite_schema($pdo);
}

function ensure_schema(PDO $pdo): void
{
    $requiredTables = [
        'shows',
        'episodes',
        'genres',
        'show_genre',
        'movies',
        'movie_genre',
        'movie_provider',
    ];
    $quotedTableNames = implode(
        ', ',
        array_map(static fn(string $table): string => $pdo->quote($table), $requiredTables)
    );

    if (uses_postgres($pdo)) {
        $existingTableCount = (int) execute_statement(
            $pdo,
            'SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = current_schema()
                AND table_name IN (' . $quotedTableNames . ')'
        )->fetchColumn();
    } else {
        $existingTableCount = (int) execute_statement(
            $pdo,
            'SELECT COUNT(*) FROM sqlite_master
            WHERE type = \'table\' AND name IN (' . $quotedTableNames . ')'
        )->fetchColumn();
    }

    if ($existingTableCount !== count($requiredTables)) {
        create_schema($pdo);
    }
}

function create_sqlite_schema(PDO $pdo): void
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
        'CREATE TABLE IF NOT EXISTS movies (
            id INTEGER PRIMARY KEY,
            external_id INTEGER UNIQUE,
            title TEXT NOT NULL,
            original_title TEXT NOT NULL DEFAULT \'\',
            original_language TEXT NOT NULL DEFAULT \'\',
            status TEXT NOT NULL DEFAULT \'\',
            release_date TEXT,
            overview TEXT NOT NULL,
            poster_url TEXT NOT NULL DEFAULT \'\',
            source_url TEXT NOT NULL DEFAULT \'\',
            provider_link TEXT NOT NULL DEFAULT \'\',
            runtime INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS movie_genre (
            movie_id INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
            genre_id INTEGER NOT NULL REFERENCES genres(id),
            PRIMARY KEY (movie_id, genre_id)
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS movie_provider (
            movie_id INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
            provider_id INTEGER NOT NULL,
            provider_name TEXT NOT NULL,
            provider_logo_url TEXT NOT NULL DEFAULT \'\',
            offer_type TEXT NOT NULL,
            display_priority INTEGER NOT NULL DEFAULT 0,
            country_code TEXT NOT NULL DEFAULT \'DE\',
            PRIMARY KEY (movie_id, provider_id, offer_type)
        )'
    );

    create_indexes($pdo);
}

function create_postgres_schema(PDO $pdo): void
{
    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS shows (
            id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
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
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    execute_statement(
        $pdo,
        'ALTER TABLE shows ADD COLUMN IF NOT EXISTS official_site_url TEXT NOT NULL DEFAULT \'\''
    );
    execute_statement(
        $pdo,
        'ALTER TABLE shows ADD COLUMN IF NOT EXISTS distribution_name TEXT NOT NULL DEFAULT \'\''
    );
    execute_statement(
        $pdo,
        'ALTER TABLE shows ADD COLUMN IF NOT EXISTS distribution_type TEXT NOT NULL DEFAULT \'\''
    );
    execute_statement(
        $pdo,
        'ALTER TABLE shows ADD COLUMN IF NOT EXISTS distribution_country TEXT NOT NULL DEFAULT \'\''
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS episodes (
            id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
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
            id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
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
        'CREATE TABLE IF NOT EXISTS movies (
            id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            external_id INTEGER UNIQUE,
            title TEXT NOT NULL,
            original_title TEXT NOT NULL DEFAULT \'\',
            original_language TEXT NOT NULL DEFAULT \'\',
            status TEXT NOT NULL DEFAULT \'\',
            release_date TEXT,
            overview TEXT NOT NULL,
            poster_url TEXT NOT NULL DEFAULT \'\',
            source_url TEXT NOT NULL DEFAULT \'\',
            provider_link TEXT NOT NULL DEFAULT \'\',
            runtime INTEGER,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS movie_genre (
            movie_id INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
            genre_id INTEGER NOT NULL REFERENCES genres(id),
            PRIMARY KEY (movie_id, genre_id)
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS movie_provider (
            movie_id INTEGER NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
            provider_id INTEGER NOT NULL,
            provider_name TEXT NOT NULL,
            provider_logo_url TEXT NOT NULL DEFAULT \'\',
            offer_type TEXT NOT NULL,
            display_priority INTEGER NOT NULL DEFAULT 0,
            country_code TEXT NOT NULL DEFAULT \'DE\',
            PRIMARY KEY (movie_id, provider_id, offer_type)
        )'
    );

    create_indexes($pdo);
}

function create_indexes(PDO $pdo): void
{
    execute_statement(
        $pdo,
        'CREATE INDEX IF NOT EXISTS idx_episodes_show_id ON episodes(show_id)'
    );
    execute_statement(
        $pdo,
        'CREATE INDEX IF NOT EXISTS idx_show_genre_genre_id ON show_genre(genre_id)'
    );
    execute_statement(
        $pdo,
        'CREATE INDEX IF NOT EXISTS idx_movie_genre_genre_id ON movie_genre(genre_id)'
    );
    execute_statement(
        $pdo,
        'CREATE INDEX IF NOT EXISTS idx_movie_provider_movie_id ON movie_provider(movie_id)'
    );
}
