<?php

declare(strict_types=1);

const DATABASE_PATH = __DIR__ . '/../data/pruefstand.sqlite';

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

function create_initial_schema(PDO $pdo): void
{
    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS authors (
            id INTEGER PRIMARY KEY,
            external_id INTEGER UNIQUE,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            city TEXT NOT NULL,
            company TEXT NOT NULL
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY,
            external_id INTEGER UNIQUE,
            author_id INTEGER NOT NULL REFERENCES authors(id),
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
}

function create_comments_schema(PDO $pdo): void
{
    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY,
            external_id INTEGER NOT NULL UNIQUE,
            post_id INTEGER NOT NULL REFERENCES posts(id),
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            body TEXT NOT NULL
        )'
    );
}

function create_tags_schema(PDO $pdo): void
{
    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE
        )'
    );

    execute_statement(
        $pdo,
        'CREATE TABLE IF NOT EXISTS post_tag (
            post_id INTEGER NOT NULL REFERENCES posts(id),
            tag_id INTEGER NOT NULL REFERENCES tags(id),
            PRIMARY KEY (post_id, tag_id)
        )'
    );
}
