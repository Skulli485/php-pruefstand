<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

function language_label(string $language): string
{
    return match ($language) {
        'English' => 'Englisch',
        'German' => 'Deutsch',
        'Other' => 'Andere',
        default => $language,
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'Running' => 'Läuft',
        'Ended' => 'Beendet',
        'In Development' => 'In Entwicklung',
        'To Be Determined' => 'Noch offen',
        default => $status,
    };
}

function show_home(PDO $pdo): void
{
    $showCount = execute_statement($pdo, 'SELECT COUNT(*) FROM shows')->fetchColumn();
    $episodeCount = execute_statement($pdo, 'SELECT COUNT(*) FROM episodes')->fetchColumn();
    $genreCount = execute_statement($pdo, 'SELECT COUNT(*) FROM genres')->fetchColumn();

    page_start('Übersicht', '/');
    ?>
    <section class="intro">
        <p class="eyebrow">PHP · API · SQLite</p>
        <h1>Serien, Episoden und echte Beziehungen.</h1>
        <p>
            Der Serienprüfstand importiert verständliche TV-Daten von TVmaze,
            speichert sie lokal und wertet sie mit sicheren SQL-JOINs aus.
        </p>
        <div class="hero-actions">
            <a class="button" href="/serien">Serien entdecken</a>
            <a class="text-link" href="/serien/neu">Eigene Serie anlegen</a>
        </div>
    </section>

    <section class="summary" aria-label="Datenbestand">
        <div>
            <strong><?= (int) $showCount ?></strong>
            <span>Serien</span>
        </div>
        <div>
            <strong><?= (int) $episodeCount ?></strong>
            <span>Episoden</span>
        </div>
        <div>
            <strong><?= (int) $genreCount ?></strong>
            <span>Genres</span>
        </div>
    </section>
    <?php
    page_end();
}

function show_series(PDO $pdo): void
{
    $search = request_text($_GET, 'q');
    $searchPattern = '%' . $search . '%';
    $shows = execute_statement(
        $pdo,
        'SELECT s.id, s.name, s.language, s.status, s.premiered,
            s.summary, s.image_url,
            COUNT(DISTINCT e.id) AS episode_count,
            GROUP_CONCAT(DISTINCT g.name) AS genres
        FROM shows s
        LEFT JOIN episodes e ON e.show_id = s.id
        LEFT JOIN show_genre sg ON sg.show_id = s.id
        LEFT JOIN genres g ON g.id = sg.genre_id
        WHERE s.name LIKE :name OR s.summary LIKE :summary
        GROUP BY s.id, s.name, s.language, s.status, s.premiered, s.summary, s.image_url
        ORDER BY s.name
        LIMIT 50',
        [
            'name' => $searchPattern,
            'summary' => $searchPattern,
        ]
    )->fetchAll();

    page_start('Serien', '/serien');
    ?>
    <section class="list-heading">
        <p class="eyebrow">Lokaler TV-Katalog</p>
        <h1>Serien im Prüfstand</h1>
        <form class="search-form" method="get" action="/serien">
            <label for="q">Nach Titel oder Beschreibung suchen</label>
            <div>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="<?= e($search) ?>"
                    placeholder="Zum Beispiel Arrow oder Detective"
                >
                <button type="submit">Suchen</button>
            </div>
        </form>
        <p class="result-count"><?= count($shows) ?> Treffer</p>
    </section>

    <?php if ($shows === []): ?>
        <section class="empty-state">
            <h2>Keine Serie gefunden</h2>
            <p>Versuche einen anderen Suchbegriff oder lege selbst eine Serie an.</p>
            <a class="text-link" href="/serien/neu">Neue Serie anlegen</a>
        </section>
    <?php else: ?>
        <ol class="series-grid">
            <?php foreach ($shows as $show): ?>
                <li class="series-card">
                    <a class="poster-link" href="/serien/<?= (int) $show['id'] ?>" aria-label="<?= e($show['name']) ?> ansehen">
                        <?php if ($show['image_url'] !== ''): ?>
                            <img
                                src="<?= e($show['image_url']) ?>"
                                alt="Poster zu <?= e($show['name']) ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <span class="poster-placeholder" aria-hidden="true">TV</span>
                        <?php endif; ?>
                    </a>
                    <div class="series-card-body">
                        <div class="series-meta">
                            <span><?= e(language_label((string) $show['language'])) ?></span>
                            <span><?= (int) $show['episode_count'] ?> Episoden</span>
                        </div>
                        <h2>
                            <a href="/serien/<?= (int) $show['id'] ?>"><?= e($show['name']) ?></a>
                        </h2>
                        <p class="genre-line"><?= e($show['genres'] ?: 'Ohne Genre') ?></p>
                        <p><?= e(excerpt((string) $show['summary'], 170)) ?></p>
                        <a class="text-link" href="/serien/<?= (int) $show['id'] ?>">Details und Episoden</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php
    page_end();
}

function show_episode_analysis(PDO $pdo): void
{
    $shows = execute_statement(
        $pdo,
        'SELECT s.id, s.name, s.status,
            COUNT(e.id) AS episode_count,
            COUNT(DISTINCT e.season) AS season_count,
            MIN(e.airdate) AS first_airdate,
            MAX(e.airdate) AS last_airdate
        FROM shows s
        LEFT JOIN episodes e ON e.show_id = s.id
        GROUP BY s.id, s.name, s.status
        ORDER BY episode_count DESC, s.name ASC'
    )->fetchAll();

    $episodeCount = array_sum(array_map(
        static fn(array $show): int => (int) $show['episode_count'],
        $shows
    ));

    page_start('Episoden-Auswertung', '/episoden');
    ?>
    <section class="analysis-heading">
        <p class="eyebrow">Silber · 1:n</p>
        <h1>Serien und ihre Episoden</h1>
        <p>
            <?= count($shows) ?> Serien und <?= $episodeCount ?> Episoden werden
            mit einem LEFT JOIN gemeinsam ausgewertet.
        </p>
    </section>

    <div class="table-scroll">
        <table class="analysis-table">
            <thead>
                <tr>
                    <th scope="col">Serie</th>
                    <th scope="col">Status</th>
                    <th scope="col">Staffeln</th>
                    <th scope="col">Episoden</th>
                    <th scope="col">Zeitraum</th>
                    <th scope="col">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shows as $show): ?>
                    <tr>
                        <td><?= e($show['name']) ?></td>
                        <td><?= e(status_label((string) $show['status'])) ?></td>
                        <td><?= (int) $show['season_count'] ?></td>
                        <td class="number-cell"><?= (int) $show['episode_count'] ?></td>
                        <td>
                            <?= e($show['first_airdate'] ?: '—') ?>
                            <?php if ($show['last_airdate']): ?>– <?= e($show['last_airdate']) ?><?php endif; ?>
                        </td>
                        <td><a class="text-link" href="/serien/<?= (int) $show['id'] ?>">Ansehen</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    page_end();
}

function show_new_series_form(PDO $pdo, array $values = [], array $errors = []): void
{
    $values = array_replace([
        'name' => '',
        'language' => 'English',
        'status' => 'Running',
        'premiered' => '',
        'summary' => '',
        'genre_ids' => [],
    ], $values);
    $selectedGenreIds = is_array($values['genre_ids']) ? $values['genre_ids'] : [];
    $genres = execute_statement(
        $pdo,
        'SELECT id, name FROM genres ORDER BY name'
    )->fetchAll();
    $languageOptions = [
        'English' => 'Englisch',
        'German' => 'Deutsch',
        'Other' => 'Andere',
    ];
    $statusOptions = [
        'Running' => 'Läuft',
        'Ended' => 'Beendet',
        'In Development' => 'In Entwicklung',
        'To Be Determined' => 'Noch offen',
    ];

    page_start('Neue Serie', '/serien/neu');
    ?>
    <section class="form-heading">
        <p class="eyebrow">Diamant · POST</p>
        <h1>Eigene Serie anlegen</h1>
        <p>Die neue Serie wird lokal in SQLite gespeichert.</p>
    </section>

    <?php if ($errors !== []): ?>
        <section class="error-summary" role="alert" aria-labelledby="error-heading">
            <h2 id="error-heading">Bitte prüfe deine Eingaben</h2>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="form-layout">
        <form class="entry-form" method="post" action="/serien/neu">
            <div class="form-field">
                <label for="name">Titel</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="<?= e($values['name']) ?>"
                    required
                    minlength="2"
                    maxlength="150"
                    <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>
                >
                <?php if (isset($errors['name'])): ?>
                    <p class="field-error" id="name-error"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="language">Sprache</label>
                <select
                    id="language"
                    name="language"
                    required
                    <?= isset($errors['language']) ? 'aria-invalid="true" aria-describedby="language-error"' : '' ?>
                >
                    <?php foreach ($languageOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $values['language'] === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['language'])): ?>
                    <p class="field-error" id="language-error"><?= e($errors['language']) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="status">Status</label>
                <select
                    id="status"
                    name="status"
                    required
                    <?= isset($errors['status']) ? 'aria-invalid="true" aria-describedby="status-error"' : '' ?>
                >
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $values['status'] === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?>
                    <p class="field-error" id="status-error"><?= e($errors['status']) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="premiered">Premiere</label>
                <input
                    id="premiered"
                    name="premiered"
                    type="date"
                    value="<?= e($values['premiered']) ?>"
                    <?= isset($errors['premiered']) ? 'aria-invalid="true" aria-describedby="premiered-error"' : '' ?>
                >
                <?php if (isset($errors['premiered'])): ?>
                    <p class="field-error" id="premiered-error"><?= e($errors['premiered']) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="summary">Beschreibung</label>
                <textarea
                    id="summary"
                    name="summary"
                    rows="9"
                    required
                    minlength="20"
                    maxlength="5000"
                    <?= isset($errors['summary']) ? 'aria-invalid="true" aria-describedby="summary-error"' : '' ?>
                ><?= e($values['summary']) ?></textarea>
                <?php if (isset($errors['summary'])): ?>
                    <p class="field-error" id="summary-error"><?= e($errors['summary']) ?></p>
                <?php endif; ?>
            </div>

            <fieldset class="form-field" <?= isset($errors['genre_ids']) ? 'aria-describedby="genre_ids-error"' : '' ?>>
                <legend>Genres</legend>
                <div class="checkbox-list">
                    <?php foreach ($genres as $genre): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="genre_ids[]"
                                value="<?= (int) $genre['id'] ?>"
                                <?= in_array((int) $genre['id'], $selectedGenreIds, true) ? 'checked' : '' ?>
                            >
                            <span><?= e($genre['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['genre_ids'])): ?>
                    <p class="field-error" id="genre_ids-error"><?= e($errors['genre_ids']) ?></p>
                <?php endif; ?>
            </fieldset>

            <div class="form-actions">
                <button type="submit">Serie speichern</button>
                <a class="text-link" href="/serien">Abbrechen</a>
            </div>
        </form>

        <aside class="form-guidance">
            <h2>Eingabe prüfen</h2>
            <dl>
                <div>
                    <dt>Pflichtfelder</dt>
                    <dd>Titel, Sprache, Status, Beschreibung und mindestens ein Genre.</dd>
                </div>
                <div>
                    <dt>Titel</dt>
                    <dd>2 bis 150 Zeichen.</dd>
                </div>
                <div>
                    <dt>Beschreibung</dt>
                    <dd>20 bis 5000 Zeichen.</dd>
                </div>
                <div>
                    <dt>Premiere</dt>
                    <dd>Optional, aber im Format Jahr-Monat-Tag.</dd>
                </div>
            </dl>
        </aside>
    </div>
    <?php
    page_end();
}

function handle_new_series(PDO $pdo): void
{
    $name = request_text($_POST, 'name');
    $language = request_text($_POST, 'language');
    $status = request_text($_POST, 'status');
    $premiered = request_text($_POST, 'premiered');
    $summary = request_text($_POST, 'summary');
    $rawGenreIds = $_POST['genre_ids'] ?? [];
    $genreIds = [];
    $invalidGenreValue = !is_array($rawGenreIds);

    if (is_array($rawGenreIds)) {
        foreach ($rawGenreIds as $rawGenreId) {
            if (!is_string($rawGenreId) || !ctype_digit($rawGenreId) || (int) $rawGenreId < 1) {
                $invalidGenreValue = true;
                continue;
            }

            $genreIds[] = (int) $rawGenreId;
        }
    }

    $genreIds = array_values(array_unique($genreIds));
    $errors = [];
    $nameLength = mb_strlen($name);

    if ($name === '') {
        $errors['name'] = 'Der Titel ist ein Pflichtfeld.';
    } elseif ($nameLength < 2) {
        $errors['name'] = 'Der Titel muss mindestens 2 Zeichen lang sein.';
    } elseif ($nameLength > 150) {
        $errors['name'] = 'Der Titel darf höchstens 150 Zeichen lang sein.';
    }

    $allowedLanguages = ['English', 'German', 'Other'];
    if (!in_array($language, $allowedLanguages, true)) {
        $errors['language'] = 'Bitte wähle eine gültige Sprache aus.';
    }

    $allowedStatuses = ['Running', 'Ended', 'In Development', 'To Be Determined'];
    if (!in_array($status, $allowedStatuses, true)) {
        $errors['status'] = 'Bitte wähle einen gültigen Status aus.';
    }

    if ($premiered !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $premiered);
        if ($date === false || $date->format('Y-m-d') !== $premiered) {
            $errors['premiered'] = 'Das Premierendatum ist ungültig.';
        }
    }

    $summaryLength = mb_strlen($summary);
    if ($summary === '') {
        $errors['summary'] = 'Die Beschreibung ist ein Pflichtfeld.';
    } elseif ($summaryLength < 20) {
        $errors['summary'] = 'Die Beschreibung muss mindestens 20 Zeichen lang sein.';
    } elseif ($summaryLength > 5000) {
        $errors['summary'] = 'Die Beschreibung darf höchstens 5000 Zeichen lang sein.';
    }

    $availableGenreIds = array_map(
        static fn(array $genre): int => (int) $genre['id'],
        execute_statement($pdo, 'SELECT id FROM genres')->fetchAll()
    );

    if ($genreIds === []) {
        $errors['genre_ids'] = 'Bitte wähle mindestens ein Genre aus.';
    } elseif ($invalidGenreValue || array_diff($genreIds, $availableGenreIds) !== []) {
        $errors['genre_ids'] = 'Mindestens ein ausgewähltes Genre ist ungültig.';
    }

    $values = [
        'name' => $name,
        'language' => $language,
        'status' => $status,
        'premiered' => $premiered,
        'summary' => $summary,
        'genre_ids' => $genreIds,
    ];

    if ($errors !== []) {
        http_response_code(422);
        show_new_series_form($pdo, $values, $errors);
        return;
    }

    $showStatement = $pdo->prepare(
        'INSERT INTO shows (name, language, status, premiered, summary)
        VALUES (:name, :language, :status, :premiered, :summary)'
    );
    $genreStatement = $pdo->prepare(
        'INSERT INTO show_genre (show_id, genre_id)
        VALUES (:show_id, :genre_id)'
    );

    $pdo->beginTransaction();

    try {
        $showStatement->execute([
            'name' => $name,
            'language' => $language,
            'status' => $status,
            'premiered' => $premiered !== '' ? $premiered : null,
            'summary' => $summary,
        ]);
        $showId = (int) $pdo->lastInsertId();

        foreach ($genreIds as $genreId) {
            $genreStatement->execute([
                'show_id' => $showId,
                'genre_id' => $genreId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    header('Location: /serien/' . $showId, true, 303);
    exit;
}

function show_series_detail(PDO $pdo, array $parameters): void
{
    $id = filter_var($parameters['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        not_found('Die Serien-ID ist ungültig.');
        return;
    }

    $show = execute_statement(
        $pdo,
        'SELECT id, name, language, status, premiered, summary, image_url, source_url
        FROM shows
        WHERE id = :id',
        ['id' => $id]
    )->fetch();

    if ($show === false) {
        not_found('Diese Serie existiert nicht.');
        return;
    }

    $genres = execute_statement(
        $pdo,
        'SELECT g.id, g.name, g.slug
        FROM show_genre sg
        JOIN genres g ON g.id = sg.genre_id
        WHERE sg.show_id = :show_id
        ORDER BY g.name',
        ['show_id' => $id]
    )->fetchAll();
    $episodeCount = (int) execute_statement(
        $pdo,
        'SELECT COUNT(*) FROM episodes WHERE show_id = :show_id',
        ['show_id' => $id]
    )->fetchColumn();
    $episodes = execute_statement(
        $pdo,
        'SELECT name, season, number, airdate, runtime
        FROM episodes
        WHERE show_id = :show_id
        ORDER BY season IS NULL, season, number IS NULL, number, airdate
        LIMIT 60',
        ['show_id' => $id]
    )->fetchAll();

    page_start((string) $show['name'], '/serien');
    ?>
    <article class="series-detail">
        <div class="detail-poster">
            <?php if ($show['image_url'] !== ''): ?>
                <img src="<?= e($show['image_url']) ?>" alt="Poster zu <?= e($show['name']) ?>">
            <?php else: ?>
                <span class="poster-placeholder" aria-hidden="true">TV</span>
            <?php endif; ?>
        </div>

        <div class="detail-content">
            <p class="detail-number">Serie <?= (int) $show['id'] ?></p>
            <h1><?= e($show['name']) ?></h1>
            <dl class="fact-list">
                <div><dt>Sprache</dt><dd><?= e(language_label((string) $show['language'])) ?></dd></div>
                <div><dt>Status</dt><dd><?= e(status_label((string) $show['status'])) ?></dd></div>
                <div><dt>Premiere</dt><dd><?= e($show['premiered'] ?: 'Nicht angegeben') ?></dd></div>
                <div><dt>Episoden</dt><dd><?= $episodeCount ?></dd></div>
            </dl>

            <p class="series-summary"><?= nl2br(e($show['summary'])) ?></p>

            <section class="tag-section" aria-labelledby="genre-heading">
                <h2 id="genre-heading">Genres</h2>
                <?php if ($genres === []): ?>
                    <p class="muted-text">Noch keine Genres zugeordnet.</p>
                <?php else: ?>
                    <ul class="tag-list">
                        <?php foreach ($genres as $genre): ?>
                            <li>
                                <span><?= e($genre['name']) ?></span>
                                <small><?= e($genre['slug']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($show['source_url'] !== ''): ?>
                <p><a class="text-link" href="<?= e($show['source_url']) ?>">Datensatz bei TVmaze ansehen</a></p>
            <?php endif; ?>
        </div>
    </article>

    <section class="episode-section" aria-labelledby="episode-heading">
        <div class="section-heading-row">
            <div>
                <p class="eyebrow">1:n-Beziehung</p>
                <h2 id="episode-heading"><?= $episodeCount ?> Episoden</h2>
            </div>
            <a class="text-link" href="/episoden">Zur Gesamtauswertung</a>
        </div>

        <?php if ($episodes === []): ?>
            <p class="empty-state">Für eine lokal angelegte Serie sind noch keine Episoden importiert.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="episode-table">
                    <thead>
                        <tr>
                            <th scope="col">Nr.</th>
                            <th scope="col">Titel</th>
                            <th scope="col">Ausstrahlung</th>
                            <th scope="col">Laufzeit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($episodes as $episode): ?>
                            <tr>
                                <td class="episode-number">
                                    <?php if ($episode['season'] !== null && $episode['number'] !== null): ?>
                                        S<?= str_pad((string) $episode['season'], 2, '0', STR_PAD_LEFT) ?>E<?= str_pad((string) $episode['number'], 2, '0', STR_PAD_LEFT) ?>
                                    <?php else: ?>
                                        Spezial
                                    <?php endif; ?>
                                </td>
                                <td><?= e($episode['name']) ?></td>
                                <td><?= e($episode['airdate'] ?: '—') ?></td>
                                <td><?= $episode['runtime'] !== null ? (int) $episode['runtime'] . ' Min.' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($episodeCount > count($episodes)): ?>
                <p class="muted-text">Angezeigt werden die ersten <?= count($episodes) ?> Episoden.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <p><a class="text-link" href="/serien">Zurück zu allen Serien</a></p>
    <?php
    page_end();
}
