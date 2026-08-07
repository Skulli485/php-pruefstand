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

function import_comments(PDO $pdo): array
{
    $comments = api_get('/comments');

    $postIdsStatement = $pdo->prepare(
        'SELECT id, external_id FROM posts WHERE external_id IS NOT NULL'
    );
    $postIdsStatement->execute();
    $postIds = [];

    foreach ($postIdsStatement->fetchAll() as $row) {
        $postIds[(int) $row['external_id']] = (int) $row['id'];
    }

    $commentStatement = $pdo->prepare(
        'INSERT INTO comments (external_id, post_id, name, email, body)
        VALUES (:external_id, :post_id, :name, :email, :body)
        ON CONFLICT(external_id) DO UPDATE SET
            post_id = excluded.post_id,
            name = excluded.name,
            email = excluded.email,
            body = excluded.body'
    );

    $importedComments = 0;
    $skippedComments = 0;

    $pdo->beginTransaction();

    try {
        foreach ($comments as $comment) {
            $externalId = filter_var($comment['id'] ?? null, FILTER_VALIDATE_INT);
            $externalPostId = filter_var($comment['postId'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($comment['name'] ?? ''));
            $email = trim((string) ($comment['email'] ?? ''));
            $body = trim((string) ($comment['body'] ?? ''));

            if (
                $externalId === false
                || $externalPostId === false
                || !isset($postIds[$externalPostId])
                || $name === ''
                || $email === ''
                || $body === ''
            ) {
                $skippedComments++;
                continue;
            }

            $commentStatement->execute([
                'external_id' => $externalId,
                'post_id' => $postIds[$externalPostId],
                'name' => $name,
                'email' => $email,
                'body' => $body,
            ]);
            $importedComments++;
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    return [
        'comments' => $importedComments,
        'skipped' => $skippedComments,
    ];
}

function seed_post_tags(PDO $pdo): array
{
    $tagData = [
        ['name' => 'PHP', 'slug' => 'php'],
        ['name' => 'Datenbanken', 'slug' => 'datenbanken'],
        ['name' => 'Sicherheit', 'slug' => 'sicherheit'],
        ['name' => 'Routing', 'slug' => 'routing'],
    ];

    $tagPlan = [
        1 => ['PHP', 'Routing'],
        2 => ['Datenbanken', 'Sicherheit'],
        3 => ['PHP', 'Datenbanken'],
        4 => ['Routing', 'Sicherheit'],
        5 => ['PHP', 'Sicherheit', 'Routing'],
    ];

    $tagStatement = $pdo->prepare(
        'INSERT INTO tags (name, slug)
        VALUES (:name, :slug)
        ON CONFLICT(name) DO UPDATE SET slug = excluded.slug'
    );
    $relationStatement = $pdo->prepare(
        'INSERT OR IGNORE INTO post_tag (post_id, tag_id)
        VALUES (:post_id, :tag_id)'
    );

    $pdo->beginTransaction();

    try {
        foreach ($tagData as $tag) {
            $tagStatement->execute($tag);
        }

        $tagRows = execute_statement($pdo, 'SELECT id, name FROM tags')->fetchAll();
        $postRows = execute_statement(
            $pdo,
            'SELECT id, external_id FROM posts WHERE external_id IS NOT NULL'
        )->fetchAll();
        $tagIds = [];
        $postIds = [];

        foreach ($tagRows as $tag) {
            $tagIds[(string) $tag['name']] = (int) $tag['id'];
        }

        foreach ($postRows as $post) {
            $postIds[(int) $post['external_id']] = (int) $post['id'];
        }

        $relationCount = 0;

        foreach ($tagPlan as $externalPostId => $tagNames) {
            if (!isset($postIds[$externalPostId])) {
                continue;
            }

            foreach ($tagNames as $tagName) {
                if (!isset($tagIds[$tagName])) {
                    continue;
                }

                $relationStatement->execute([
                    'post_id' => $postIds[$externalPostId],
                    'tag_id' => $tagIds[$tagName],
                ]);
                $relationCount += $relationStatement->rowCount();
            }
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    return [
        'tags' => count($tagData),
        'relations_created' => $relationCount,
    ];
}
