<?php

declare(strict_types=1);

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/database.php';

function import_initial_data(PDO $pdo): array
{
    $authors = api_get('/users');
    $posts = api_get('/posts');

    $authorStatement = $pdo->prepare(
        'INSERT INTO authors (external_id, name, email, city, company)
        VALUES (:external_id, :name, :email, :city, :company)
        ON CONFLICT(external_id) DO UPDATE SET
            name = excluded.name,
            email = excluded.email,
            city = excluded.city,
            company = excluded.company'
    );

    $postStatement = $pdo->prepare(
        'INSERT INTO posts (external_id, author_id, title, body)
        VALUES (:external_id, :author_id, :title, :body)
        ON CONFLICT(external_id) DO UPDATE SET
            author_id = excluded.author_id,
            title = excluded.title,
            body = excluded.body'
    );

    $importedAuthors = 0;
    $importedPosts = 0;
    $skippedRows = 0;

    $pdo->beginTransaction();

    try {
        foreach ($authors as $author) {
            $externalId = filter_var($author['id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($author['name'] ?? ''));
            $email = trim((string) ($author['email'] ?? ''));
            $city = trim((string) ($author['address']['city'] ?? ''));
            $company = trim((string) ($author['company']['name'] ?? ''));

            if ($externalId === false || $externalId < 1 || $name === '' || $email === '') {
                $skippedRows++;
                continue;
            }

            $authorStatement->execute([
                'external_id' => $externalId,
                'name' => $name,
                'email' => $email,
                'city' => $city,
                'company' => $company,
            ]);
            $importedAuthors++;
        }

        $authorIdsStatement = $pdo->prepare(
            'SELECT id, external_id FROM authors WHERE external_id IS NOT NULL'
        );
        $authorIdsStatement->execute();
        $authorIds = [];

        foreach ($authorIdsStatement->fetchAll() as $row) {
            $authorIds[(int) $row['external_id']] = (int) $row['id'];
        }

        foreach ($posts as $post) {
            $externalId = filter_var($post['id'] ?? null, FILTER_VALIDATE_INT);
            $externalAuthorId = filter_var($post['userId'] ?? null, FILTER_VALIDATE_INT);
            $title = trim((string) ($post['title'] ?? ''));
            $body = trim((string) ($post['body'] ?? ''));

            if (
                $externalId === false
                || $externalAuthorId === false
                || !isset($authorIds[$externalAuthorId])
                || $title === ''
                || $body === ''
            ) {
                $skippedRows++;
                continue;
            }

            $postStatement->execute([
                'external_id' => $externalId,
                'author_id' => $authorIds[$externalAuthorId],
                'title' => $title,
                'body' => $body,
            ]);
            $importedPosts++;
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    return [
        'authors' => $importedAuthors,
        'posts' => $importedPosts,
        'skipped' => $skippedRows,
    ];
}
