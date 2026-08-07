<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

function show_home(PDO $pdo): void
{
    $authorCount = execute_statement($pdo, 'SELECT COUNT(*) FROM authors')->fetchColumn();
    $postCount = execute_statement($pdo, 'SELECT COUNT(*) FROM posts')->fetchColumn();
    $commentCount = execute_statement($pdo, 'SELECT COUNT(*) FROM comments')->fetchColumn();

    page_start('Übersicht', '/');
    ?>
    <section class="intro">
        <h1>Texte, Beziehungen und sichere Abfragen.</h1>
        <p>
            Der Redaktionsprüfstand macht externe Beiträge lokal auswertbar –
            mit einem eigenen Router, PDO und SQLite.
        </p>
        <a class="button" href="/beitraege">Beiträge prüfen</a>
    </section>

    <section class="summary" aria-label="Datenbestand">
        <div>
            <strong><?= (int) $authorCount ?></strong>
            <span>Autoren</span>
        </div>
        <div>
            <strong><?= (int) $postCount ?></strong>
            <span>Beiträge</span>
        </div>
        <div>
            <strong><?= (int) $commentCount ?></strong>
            <span>Kommentare</span>
        </div>
    </section>
    <?php
    page_end();
}
function show_posts(PDO $pdo): void
{
    $search = request_text($_GET, 'q');
    $searchPattern = '%' . $search . '%';
    $posts = execute_statement(
        $pdo,
        'SELECT p.id, p.title, p.body, a.name AS author_name
        FROM posts p
        JOIN authors a ON a.id = p.author_id
        WHERE p.title LIKE :title OR p.body LIKE :body
        ORDER BY p.id
        LIMIT 50',
        [
            'title' => $searchPattern,
            'body' => $searchPattern,
        ]
    )->fetchAll();

    page_start('Beiträge', '/beitraege');
    ?>
    <section class="list-heading">
        <h1>Beiträge im Prüfstand</h1>
        <form class="search-form" method="get" action="/beitraege">
            <label for="q">Beiträge durchsuchen</label>
            <div>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="<?= e($search) ?>"
                    placeholder="Suchbegriff eingeben …"
                >
                <button type="submit">Suchen</button>
            </div>
        </form>
        <p class="result-count"><?= count($posts) ?> Treffer</p>
    </section>

    <?php if ($posts === []): ?>
        <p class="empty-state">Für diesen Suchbegriff wurden keine Beiträge gefunden.</p>
    <?php else: ?>
        <ol class="post-list">
            <?php foreach ($posts as $post): ?>
                <li>
                    <span class="row-number" aria-hidden="true"></span>
                    <div class="post-title">
                        <h2><?= e($post['title']) ?></h2>
                        <p>Beitrag <?= (int) $post['id'] ?></p>
                    </div>
                    <p class="post-author"><?= e($post['author_name']) ?></p>
                    <p class="post-excerpt"><?= e(excerpt((string) $post['body'])) ?></p>
                    <a class="text-link" href="/beitraege/<?= (int) $post['id'] ?>">Details ansehen</a>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php
    page_end();
}

function show_resonance(PDO $pdo): void
{
    $posts = execute_statement(
        $pdo,
        'SELECT p.id, p.title, a.name AS author_name,
            COUNT(c.id) AS comment_count
        FROM posts p
        JOIN authors a ON a.id = p.author_id
        LEFT JOIN comments c ON c.post_id = p.id
        GROUP BY p.id, p.title, a.name
        ORDER BY comment_count DESC, p.id ASC'
    )->fetchAll();

    $commentCount = array_sum(
        array_map(
            fn(array $post): int => (int) $post['comment_count'],
            $posts
        )
    );

    page_start('Resonanz', '/resonanz');
    ?>
    <section class="analysis-heading">
        <h1>Resonanz der Beiträge</h1>
        <p>
            <?= count($posts) ?> Beiträge und <?= $commentCount ?> Kommentare,
            gemeinsam ausgewertet über einen SQL-JOIN.
        </p>
    </section>

    <div class="table-scroll">
        <table class="analysis-table">
            <thead>
                <tr>
                    <th scope="col">Beitrag</th>
                    <th scope="col">Autor</th>
                    <th scope="col">Kommentare</th>
                    <th scope="col">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td><?= e($post['title']) ?></td>
                        <td><?= e($post['author_name']) ?></td>
                        <td class="number-cell"><?= (int) $post['comment_count'] ?></td>
                        <td>
                            <a class="text-link" href="/beitraege/<?= (int) $post['id'] ?>">
                                Beitrag ansehen
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    page_end();
}

function show_new_post_form(PDO $pdo): void
{
    $authors = execute_statement(
        $pdo,
        'SELECT id, name FROM authors ORDER BY name'
    )->fetchAll();
    $tags = execute_statement(
        $pdo,
        'SELECT id, name FROM tags ORDER BY name'
    )->fetchAll();

    page_start('Neuer Beitrag', '/beitraege/neu');
    ?>
    <section class="form-heading">
        <h1>Neuen Beitrag anlegen</h1>
        <p>Der Beitrag wird lokal in SQLite gespeichert.</p>
    </section>

    <div class="form-layout">
        <form class="entry-form" method="post" action="/beitraege/neu">
            <div class="form-field">
                <label for="author_id">Autor</label>
                <select id="author_id" name="author_id" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?= (int) $author['id'] ?>">
                            <?= e($author['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="title">Titel</label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    required
                    minlength="5"
                    maxlength="150"
                >
            </div>

            <div class="form-field">
                <label for="body">Inhalt</label>
                <textarea
                    id="body"
                    name="body"
                    rows="10"
                    required
                    minlength="20"
                    maxlength="5000"
                ></textarea>
            </div>

            <fieldset class="form-field">
                <legend>Schlagwörter</legend>
                <div class="checkbox-list">
                    <?php foreach ($tags as $tag): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="tag_ids[]"
                                value="<?= (int) $tag['id'] ?>"
                            >
                            <span><?= e($tag['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit">Beitrag speichern</button>
                <a class="text-link" href="/beitraege">Abbrechen</a>
            </div>
        </form>

        <aside class="form-guidance">
            <h2>Eingabe prüfen</h2>
            <dl>
                <div>
                    <dt>Pflichtfelder</dt>
                    <dd>Autor, Titel, Inhalt und mindestens ein Schlagwort.</dd>
                </div>
                <div>
                    <dt>Titel</dt>
                    <dd>5 bis 150 Zeichen.</dd>
                </div>
                <div>
                    <dt>Inhalt</dt>
                    <dd>20 bis 5000 Zeichen.</dd>
                </div>
            </dl>
        </aside>
    </div>
    <?php
    page_end();
}

function show_post(PDO $pdo, array $parameters): void
{
    $id = filter_var($parameters['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        not_found('Die Beitrags-ID ist ungültig.');
        return;
    }

    $post = execute_statement(
        $pdo,
        'SELECT p.id, p.title, p.body, p.created_at,
            a.id AS author_id, a.name AS author_name, a.email, a.city, a.company
        FROM posts p
        JOIN authors a ON a.id = p.author_id
        WHERE p.id = :id',
        ['id' => $id]
    )->fetch();

    if ($post === false) {
        not_found('Dieser Beitrag existiert nicht.');
        return;
    }

    $tags = execute_statement(
        $pdo,
        'SELECT t.id, t.name, t.slug
        FROM posts p
        JOIN post_tag pt ON pt.post_id = p.id
        JOIN tags t ON t.id = pt.tag_id
        WHERE p.id = :post_id
        ORDER BY t.name',
        ['post_id' => $id]
    )->fetchAll();

    page_start((string) $post['title'], '/beitraege');
    ?>
    <article class="post-detail">
        <p class="detail-number">Beitrag <?= (int) $post['id'] ?></p>
        <h1><?= e($post['title']) ?></h1>
        <p class="post-body"><?= nl2br(e($post['body'])) ?></p>

        <section class="tag-section" aria-labelledby="tag-heading">
            <h2 id="tag-heading">Schlagwörter</h2>
            <?php if ($tags === []): ?>
                <p class="muted-text">Noch keine Schlagwörter zugeordnet.</p>
            <?php else: ?>
                <ul class="tag-list">
                    <?php foreach ($tags as $tag): ?>
                        <li>
                            <span><?= e($tag['name']) ?></span>
                            <small><?= e($tag['slug']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <aside class="author-box">
            <h2>Autor</h2>
            <a class="text-link" href="/autoren/<?= (int) $post['author_id'] ?>">
                <?= e($post['author_name']) ?>
            </a>
            <p><?= e($post['company']) ?> · <?= e($post['city']) ?></p>
            <p><a href="mailto:<?= e($post['email']) ?>"><?= e($post['email']) ?></a></p>
        </aside>
    </article>
    <p><a class="text-link" href="/beitraege">Zurück zu den Beiträgen</a></p>
    <?php
    page_end();
}

function show_author(PDO $pdo, array $parameters): void
{
    $id = filter_var($parameters['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        not_found('Die Autoren-ID ist ungültig.');
        return;
    }

    $author = execute_statement(
        $pdo,
        'SELECT id, name, email, city, company FROM authors WHERE id = :id',
        ['id' => $id]
    )->fetch();

    if ($author === false) {
        not_found('Dieser Autor existiert nicht.');
        return;
    }

    $posts = execute_statement(
        $pdo,
        'SELECT id, title FROM posts WHERE author_id = :author_id ORDER BY id',
        ['author_id' => $id]
    )->fetchAll();

    page_start((string) $author['name']);
    ?>
    <section class="author-detail">
        <p class="detail-number">Autor <?= (int) $author['id'] ?></p>
        <h1><?= e($author['name']) ?></h1>
        <p><?= e($author['company']) ?> · <?= e($author['city']) ?></p>
        <p><a href="mailto:<?= e($author['email']) ?>"><?= e($author['email']) ?></a></p>

        <h2><?= count($posts) ?> Beiträge</h2>
        <ul class="simple-list">
            <?php foreach ($posts as $post): ?>
                <li>
                    <a class="text-link" href="/beitraege/<?= (int) $post['id'] ?>">
                        <?= e($post['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
    page_end();
}
