<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/import.php';
require_once __DIR__ . '/movie_api.php';

function movie_language_label(string $language): string
{
    return match (strtolower($language)) {
        'de' => 'Deutsch',
        'en' => 'Englisch',
        'fr' => 'Französisch',
        'es' => 'Spanisch',
        'it' => 'Italienisch',
        'ja' => 'Japanisch',
        'ko' => 'Koreanisch',
        'other' => 'Andere',
        default => $language !== '' ? strtoupper($language) : 'Unbekannt',
    };
}

function movie_status_label(string $status): string
{
    return match ($status) {
        'Released' => 'Veröffentlicht',
        'Post Production' => 'Postproduktion',
        'In Production' => 'In Produktion',
        'Planned' => 'Geplant',
        'Canceled' => 'Abgebrochen',
        'Rumored' => 'Gerücht',
        'Local' => 'Eigener Eintrag',
        default => $status !== '' ? $status : 'Unbekannt',
    };
}

function movie_offer_label(string $offerType): string
{
    return match ($offerType) {
        'flatrate' => 'Im Abo',
        'free' => 'Kostenlos',
        'ads' => 'Kostenlos mit Werbung',
        'rent' => 'Leihen',
        'buy' => 'Kaufen',
        default => 'Ansehen',
    };
}

function movie_year(?string $releaseDate): string
{
    return is_string($releaseDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $releaseDate) === 1
        ? substr($releaseDate, 0, 4)
        : 'Jahr unbekannt';
}

function attach_local_movie_ids(PDO $pdo, array $movies): array
{
    $existingMovies = execute_statement(
        $pdo,
        'SELECT id, external_id FROM movies WHERE external_id IS NOT NULL'
    )->fetchAll();
    $localIds = [];

    foreach ($existingMovies as $movie) {
        $localIds[(int) $movie['external_id']] = (int) $movie['id'];
    }

    foreach ($movies as &$movie) {
        $movie['local_id'] = $localIds[(int) $movie['external_id']] ?? null;
    }
    unset($movie);

    return $movies;
}

function render_tmdb_movie_results(array $movies): void
{
    ?>
    <ol class="api-result-list">
        <?php foreach ($movies as $movie): ?>
            <li class="api-result-card">
                <div class="api-result-poster">
                    <?php if ($movie['poster_url'] !== ''): ?>
                        <img
                            src="<?= e($movie['poster_url']) ?>"
                            alt="Poster zu <?= e($movie['title']) ?>"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <span class="poster-placeholder movie-placeholder" aria-hidden="true">FILM</span>
                    <?php endif; ?>
                </div>
                <div class="api-result-content">
                    <div>
                        <p class="api-result-meta">
                            <span><?= e(movie_language_label((string) $movie['original_language'])) ?></span>
                            <span><?= e(movie_year($movie['release_date'])) ?></span>
                        </p>
                        <h3><?= e($movie['title']) ?></h3>
                        <?php if ($movie['original_title'] !== '' && $movie['original_title'] !== $movie['title']): ?>
                            <p class="muted-text">Originaltitel: <?= e($movie['original_title']) ?></p>
                        <?php endif; ?>
                        <p class="genre-line">
                            <?= e($movie['genres'] !== [] ? implode(', ', $movie['genres']) : 'Ohne Genreangabe') ?>
                        </p>
                        <p><?= e(excerpt((string) $movie['overview'], 230)) ?></p>
                    </div>
                    <div class="api-result-action">
                        <?php if ($movie['local_id'] !== null): ?>
                            <a class="button" href="/filme/<?= (int) $movie['local_id'] ?>">
                                Bereits gespeichert
                            </a>
                        <?php else: ?>
                            <form method="post" action="/filme/importieren">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="external_id" value="<?= (int) $movie['external_id'] ?>">
                                <button type="submit">Film speichern</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php
}

function show_movies(PDO $pdo): void
{
    $search = request_text($_GET, 'q');
    $flashMessage = take_flash_message();
    $searchPattern = '%' . $search . '%';
    $genreAggregation = uses_postgres($pdo)
        ? "STRING_AGG(DISTINCT g.name, ', ')"
        : 'GROUP_CONCAT(DISTINCT g.name)';
    $movies = execute_statement(
        $pdo,
        'SELECT m.id, m.title, m.original_language, m.release_date, m.overview,
            m.poster_url, m.runtime,
            COUNT(DISTINCT mp.provider_id) AS provider_count,
            ' . $genreAggregation . ' AS genres
        FROM movies m
        LEFT JOIN movie_genre mg ON mg.movie_id = m.id
        LEFT JOIN genres g ON g.id = mg.genre_id
        LEFT JOIN movie_provider mp ON mp.movie_id = m.id
        WHERE LOWER(m.title) LIKE LOWER(:title) OR LOWER(m.overview) LIKE LOWER(:overview)
        GROUP BY m.id, m.title, m.original_language, m.release_date,
            m.overview, m.poster_url, m.runtime
        ORDER BY m.title
        LIMIT 50',
        ['title' => $searchPattern, 'overview' => $searchPattern]
    )->fetchAll();

    $hasExactLocalMatch = false;

    foreach ($movies as $movie) {
        if (mb_strtolower((string) $movie['title']) === mb_strtolower($search)) {
            $hasExactLocalMatch = true;
            break;
        }
    }

    $onlineMovies = [];
    $onlineSearchError = '';
    $shouldSearchOnline = $search !== '' && !$hasExactLocalMatch;

    if ($shouldSearchOnline) {
        $searchLength = mb_strlen($search);

        if ($searchLength < 2) {
            $onlineSearchError = 'Für die Online-Suche werden mindestens 2 Zeichen benötigt.';
        } elseif ($searchLength > 100) {
            $onlineSearchError = 'Für die Online-Suche sind höchstens 100 Zeichen erlaubt.';
        } elseif (!tmdb_is_configured()) {
            $onlineSearchError = 'Die TMDB-Suche ist vorbereitet, aber es sind noch keine TMDB-Zugangsdaten eingerichtet.';
        } else {
            try {
                $onlineMovies = attach_local_movie_ids($pdo, search_tmdb_movies($search));
            } catch (TmdbConfigurationException | RuntimeException $error) {
                $onlineSearchError = 'TMDB ist gerade nicht erreichbar. Deine lokale Filmsuche funktioniert weiterhin.';
            }
        }
    }

    page_start('Filme', '/filme');
    ?>
    <section class="list-heading">
        <p class="eyebrow">Deine Filmsammlung</p>
        <h1>Filme im Prüfstand</h1>
        <form class="search-form" method="get" action="/filme">
            <label for="movie-q">Nach Titel oder Beschreibung suchen</label>
            <div>
                <input
                    id="movie-q"
                    name="q"
                    type="search"
                    value="<?= e($search) ?>"
                    placeholder="Zum Beispiel Dune oder Batman"
                >
                <button type="submit">Suchen</button>
            </div>
        </form>
        <p class="result-count"><?= count($movies) ?> lokale Treffer</p>
    </section>

    <?php if ($flashMessage !== ''): ?>
        <section class="success-message" role="status"><?= e($flashMessage) ?></section>
    <?php endif; ?>

    <?php if ($movies === []): ?>
        <section class="empty-state">
            <h2>Noch kein Film gespeichert</h2>
            <p>Suche online nach einem Film oder lege einen eigenen Eintrag an.</p>
            <a class="text-link" href="/filme/neu">Film hinzufügen</a>
        </section>
    <?php else: ?>
        <ol class="series-grid movie-grid">
            <?php foreach ($movies as $movie): ?>
                <li class="series-card movie-card">
                    <a class="poster-link" href="/filme/<?= (int) $movie['id'] ?>" aria-label="<?= e($movie['title']) ?> ansehen">
                        <?php if ($movie['poster_url'] !== ''): ?>
                            <img src="<?= e($movie['poster_url']) ?>" alt="Poster zu <?= e($movie['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="poster-placeholder movie-placeholder" aria-hidden="true">FILM</span>
                        <?php endif; ?>
                    </a>
                    <div class="series-card-body">
                        <div class="series-meta">
                            <span><?= e(movie_year($movie['release_date'])) ?></span>
                            <span><?= $movie['runtime'] !== null ? (int) $movie['runtime'] . ' Min.' : 'Laufzeit offen' ?></span>
                        </div>
                        <h2><a href="/filme/<?= (int) $movie['id'] ?>"><?= e($movie['title']) ?></a></h2>
                        <p class="genre-line"><?= e($movie['genres'] ?: 'Ohne Genre') ?></p>
                        <p><?= e(excerpt((string) $movie['overview'], 170)) ?></p>
                        <a class="text-link" href="/filme/<?= (int) $movie['id'] ?>">Details und Anbieter</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($shouldSearchOnline): ?>
        <section class="online-results" aria-labelledby="movie-online-heading">
            <div class="api-results-heading">
                <p class="eyebrow">Online-Suche · TMDB</p>
                <h2 id="movie-online-heading">Weitere Filme online gefunden</h2>
                <p>Wähle einen Treffer aus, um Poster, Laufzeit, Genres und deutsche Anbieterinformationen zu speichern.</p>
            </div>

            <?php if ($onlineSearchError !== ''): ?>
                <div class="error-summary" role="status">
                    <h3>Online-Suche nicht möglich</h3>
                    <p><?= e($onlineSearchError) ?></p>
                </div>
            <?php elseif ($onlineMovies === []): ?>
                <div class="empty-state api-empty-state">
                    <h3>Auch online kein Treffer</h3>
                    <p>Prüfe die Schreibweise oder lege den Film manuell an.</p>
                </div>
            <?php else: ?>
                <p class="result-count"><?= count($onlineMovies) ?> Online-Treffer</p>
                <?php render_tmdb_movie_results($onlineMovies); ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php
    page_end();
}

function show_new_movie_form(
    PDO $pdo,
    array $values = [],
    array $errors = [],
    string $apiError = ''
): void {
    $values = array_replace([
        'title' => '',
        'original_language' => 'de',
        'release_date' => '',
        'runtime' => '',
        'overview' => '',
        'genre_ids' => [],
    ], $values);
    $genres = execute_statement($pdo, 'SELECT id, name FROM genres ORDER BY name')->fetchAll();
    $selectedGenreIds = array_map('intval', (array) $values['genre_ids']);
    $apiQuery = request_text($_GET, 'api_q');
    $apiMovies = [];

    if ($apiQuery !== '' && $apiError === '') {
        if (mb_strlen($apiQuery) < 2 || mb_strlen($apiQuery) > 100) {
            $apiError = 'Der Suchbegriff muss zwischen 2 und 100 Zeichen lang sein.';
        } elseif (!tmdb_is_configured()) {
            $apiError = 'Für die automatische Filmsuche müssen zuerst TMDB-Zugangsdaten eingerichtet werden.';
        } else {
            try {
                $apiMovies = attach_local_movie_ids($pdo, search_tmdb_movies($apiQuery));
            } catch (TmdbConfigurationException | RuntimeException $error) {
                $apiError = 'TMDB konnte nicht geladen werden. Bitte versuche es später erneut.';
            }
        }
    }

    page_start('Neuer Film', '/filme/neu');
    ?>
    <section class="form-heading">
        <p class="eyebrow">TMDB oder eigener Datensatz</p>
        <h1>Film hinzufügen</h1>
        <p>Suche zuerst online. Nur wenn der Film dort fehlt, kannst du ihn darunter manuell anlegen.</p>
    </section>

    <section class="api-search-panel" aria-labelledby="movie-api-search-heading">
        <div>
            <p class="eyebrow">Empfohlen</p>
            <h2 id="movie-api-search-heading">Filmdaten automatisch übernehmen</h2>
            <p>TMDB liefert Poster, Beschreibung, Sprache, Veröffentlichung, Laufzeit, Genres und Anbieterinformationen.</p>
        </div>
        <form class="api-search-form" method="get" action="/filme/neu">
            <label for="api-movie-q">Filmtitel</label>
            <div>
                <input
                    id="api-movie-q"
                    name="api_q"
                    type="search"
                    value="<?= e($apiQuery) ?>"
                    placeholder="Zum Beispiel Der Herr der Ringe"
                    minlength="2"
                    maxlength="100"
                    required
                >
                <button type="submit">Online suchen</button>
            </div>
        </form>
    </section>

    <?php if ($apiError !== ''): ?>
        <section class="error-summary" role="alert">
            <h2>API-Suche nicht erfolgreich</h2>
            <p><?= e($apiError) ?></p>
        </section>
    <?php elseif ($apiQuery !== '' && $apiMovies === []): ?>
        <section class="empty-state api-empty-state">
            <h2>Keinen passenden Film gefunden</h2>
            <p>Prüfe die Schreibweise oder versuche einen kürzeren Titel.</p>
        </section>
    <?php elseif ($apiMovies !== []): ?>
        <section class="api-results" aria-labelledby="movie-api-results-heading">
            <div class="api-results-heading">
                <p class="eyebrow"><?= count($apiMovies) ?> Treffer</p>
                <h2 id="movie-api-results-heading">Welchen Film meinst du?</h2>
                <p>Wähle den passenden Treffer aus, bevor du ihn mit Filmdaten und Anbieterinformationen speicherst.</p>
            </div>
            <?php render_tmdb_movie_results($apiMovies); ?>
        </section>
    <?php endif; ?>

    <div class="section-divider" aria-hidden="true"><span>oder manuell</span></div>

    <section class="manual-form-heading" aria-labelledby="movie-manual-form-heading">
        <p class="eyebrow">Eigener Datensatz · POST</p>
        <h2 id="movie-manual-form-heading">Eigenen Film ohne API anlegen</h2>
        <p>Für eigene oder nicht bei TMDB geführte Filme bleiben alle Felder frei editierbar.</p>
    </section>

    <?php if ($errors !== []): ?>
        <section class="error-summary" role="alert">
            <h2>Bitte prüfe deine Eingaben</h2>
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </section>
    <?php endif; ?>

    <div class="form-layout">
        <form class="entry-form" method="post" action="/filme/neu">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="form-field">
                <label for="movie-title">Titel</label>
                <input id="movie-title" name="title" type="text" required minlength="2" maxlength="150" value="<?= e($values['title']) ?>">
            </div>

            <div class="form-field">
                <label for="movie-language">Originalsprache</label>
                <select id="movie-language" name="original_language" required>
                    <?php foreach (['de' => 'Deutsch', 'en' => 'Englisch', 'other' => 'Andere'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $values['original_language'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="movie-release">Veröffentlichung</label>
                <input id="movie-release" name="release_date" type="date" value="<?= e($values['release_date']) ?>">
            </div>

            <div class="form-field">
                <label for="movie-runtime">Laufzeit in Minuten</label>
                <input id="movie-runtime" name="runtime" type="number" min="1" max="1000" value="<?= e($values['runtime']) ?>">
            </div>

            <div class="form-field">
                <label for="movie-overview">Beschreibung</label>
                <textarea id="movie-overview" name="overview" rows="9" required minlength="20" maxlength="5000"><?= e($values['overview']) ?></textarea>
            </div>

            <fieldset class="form-field">
                <legend>Genres</legend>
                <div class="checkbox-list">
                    <?php foreach ($genres as $genre): ?>
                        <label>
                            <input type="checkbox" name="genre_ids[]" value="<?= (int) $genre['id'] ?>" <?= in_array((int) $genre['id'], $selectedGenreIds, true) ? 'checked' : '' ?>>
                            <span><?= e($genre['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit">Film speichern</button>
                <a class="text-link" href="/filme">Abbrechen</a>
            </div>
        </form>

        <aside class="form-guidance">
            <h2>Eingabe prüfen</h2>
            <dl>
                <div>
                    <dt>Pflichtfelder</dt>
                    <dd>Titel, Originalsprache, Beschreibung und mindestens ein Genre.</dd>
                </div>
                <div>
                    <dt>Optional</dt>
                    <dd>Veröffentlichungsdatum und Laufzeit können leer bleiben.</dd>
                </div>
                <div>
                    <dt>Empfehlung</dt>
                    <dd>Nutze möglichst die TMDB-Suche, damit Poster und aktuelle Anbieterinformationen übernommen werden.</dd>
                </div>
            </dl>
        </aside>
    </div>
    <?php
    page_end();
}

function import_tmdb_movie(PDO $pdo, int $externalId): array
{
    $movie = tmdb_movie_details($externalId);
    $movieStatement = $pdo->prepare(
        'INSERT INTO movies (
            external_id, title, original_title, original_language, status,
            release_date, overview, poster_url, source_url, provider_link, runtime
        ) VALUES (
            :external_id, :title, :original_title, :original_language, :status,
            :release_date, :overview, :poster_url, :source_url, :provider_link, :runtime
        ) ON CONFLICT(external_id) DO UPDATE SET
            title = excluded.title,
            original_title = excluded.original_title,
            original_language = excluded.original_language,
            status = excluded.status,
            release_date = excluded.release_date,
            overview = excluded.overview,
            poster_url = excluded.poster_url,
            source_url = excluded.source_url,
            provider_link = excluded.provider_link,
            runtime = excluded.runtime'
    );
    $genreStatement = $pdo->prepare(
        'INSERT INTO genres (name, slug)
        VALUES (:name, :slug)
        ON CONFLICT DO NOTHING'
    );
    $genreIdStatement = $pdo->prepare(
        'SELECT id FROM genres
        WHERE name = :name OR slug = :slug_lookup
        ORDER BY CASE WHEN slug = :slug_sort THEN 0 ELSE 1 END
        LIMIT 1'
    );
    $relationStatement = $pdo->prepare(
        'INSERT INTO movie_genre (movie_id, genre_id)
        VALUES (:movie_id, :genre_id)
        ON CONFLICT(movie_id, genre_id) DO NOTHING'
    );
    $providerStatement = $pdo->prepare(
        'INSERT INTO movie_provider (
            movie_id, provider_id, provider_name, provider_logo_url,
            offer_type, display_priority, country_code
        ) VALUES (
            :movie_id, :provider_id, :provider_name, :provider_logo_url,
            :offer_type, :display_priority, :country_code
        ) ON CONFLICT(movie_id, provider_id, offer_type) DO UPDATE SET
            provider_name = excluded.provider_name,
            provider_logo_url = excluded.provider_logo_url,
            display_priority = excluded.display_priority,
            country_code = excluded.country_code'
    );

    $pdo->beginTransaction();

    try {
        $movieStatement->execute([
            'external_id' => $movie['external_id'],
            'title' => $movie['title'],
            'original_title' => $movie['original_title'],
            'original_language' => $movie['original_language'],
            'status' => $movie['status'],
            'release_date' => $movie['release_date'],
            'overview' => $movie['overview'],
            'poster_url' => $movie['poster_url'],
            'source_url' => $movie['source_url'],
            'provider_link' => $movie['provider_link'],
            'runtime' => $movie['runtime'],
        ]);
        $movieId = (int) execute_statement(
            $pdo,
            'SELECT id FROM movies WHERE external_id = :external_id',
            ['external_id' => $externalId]
        )->fetchColumn();

        if ($movieId < 1) {
            throw new RuntimeException('Der importierte Film konnte lokal nicht gefunden werden.');
        }

        execute_statement($pdo, 'DELETE FROM movie_genre WHERE movie_id = :movie_id', ['movie_id' => $movieId]);
        execute_statement($pdo, 'DELETE FROM movie_provider WHERE movie_id = :movie_id', ['movie_id' => $movieId]);

        foreach ($movie['genres'] as $genreName) {
            $genreSlug = genre_slug($genreName);
            $genreParameters = ['name' => $genreName, 'slug' => $genreSlug];
            $genreStatement->execute($genreParameters);
            $genreIdStatement->execute([
                'name' => $genreName,
                'slug_lookup' => $genreSlug,
                'slug_sort' => $genreSlug,
            ]);
            $genreId = (int) $genreIdStatement->fetchColumn();

            if ($genreId > 0) {
                $relationStatement->execute(['movie_id' => $movieId, 'genre_id' => $genreId]);
            }
        }

        foreach ($movie['providers'] as $provider) {
            $providerStatement->execute(['movie_id' => $movieId] + $provider);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    return ['movie_id' => $movieId, 'providers' => count($movie['providers'])];
}

function handle_tmdb_movie_import(PDO $pdo): void
{
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        forbidden('Das Sicherheitstoken ist ungültig oder abgelaufen. Bitte öffne die Filmsuche erneut.');
        return;
    }

    $externalId = filter_var($_POST['external_id'] ?? null, FILTER_VALIDATE_INT);

    if ($externalId === false || $externalId < 1) {
        http_response_code(422);
        show_new_movie_form($pdo, apiError: 'Die ausgewählte TMDB-ID ist ungültig.');
        return;
    }

    try {
        $result = import_tmdb_movie($pdo, $externalId);
    } catch (TmdbConfigurationException | RuntimeException $error) {
        http_response_code(502);
        show_new_movie_form($pdo, apiError: 'Der Film konnte nicht von TMDB geladen werden. Bitte prüfe den API-Token.');
        return;
    }

    rotate_csrf_token();
    header('Location: /filme/' . (int) $result['movie_id'] . '?imported=1', true, 303);
    exit;
}

function handle_new_movie(PDO $pdo): void
{
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        forbidden('Das Sicherheitstoken ist ungültig oder abgelaufen. Bitte öffne das Formular erneut.');
        return;
    }

    $title = request_text($_POST, 'title');
    $language = request_text($_POST, 'original_language');
    $releaseDate = request_text($_POST, 'release_date');
    $runtimeText = request_text($_POST, 'runtime');
    $overview = request_text($_POST, 'overview');
    $rawGenreIds = $_POST['genre_ids'] ?? [];
    $genreIds = [];
    $invalidGenre = !is_array($rawGenreIds);

    if (is_array($rawGenreIds)) {
        foreach ($rawGenreIds as $rawGenreId) {
            if (!is_string($rawGenreId) || !ctype_digit($rawGenreId) || (int) $rawGenreId < 1) {
                $invalidGenre = true;
                continue;
            }

            $genreIds[] = (int) $rawGenreId;
        }
    }

    $genreIds = array_values(array_unique($genreIds));
    $errors = [];

    if (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
        $errors['title'] = 'Der Titel muss zwischen 2 und 150 Zeichen lang sein.';
    }
    if (!in_array($language, ['de', 'en', 'other'], true)) {
        $errors['original_language'] = 'Bitte wähle eine gültige Sprache aus.';
    }
    if ($releaseDate !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $releaseDate);
        if ($date === false || $date->format('Y-m-d') !== $releaseDate) {
            $errors['release_date'] = 'Das Veröffentlichungsdatum ist ungültig.';
        }
    }
    $runtime = null;
    if ($runtimeText !== '') {
        $runtime = filter_var($runtimeText, FILTER_VALIDATE_INT);
        if ($runtime === false || $runtime < 1 || $runtime > 1000) {
            $errors['runtime'] = 'Die Laufzeit muss zwischen 1 und 1000 Minuten liegen.';
        }
    }
    if (mb_strlen($overview) < 20 || mb_strlen($overview) > 5000) {
        $errors['overview'] = 'Die Beschreibung muss zwischen 20 und 5000 Zeichen lang sein.';
    }

    $availableGenreIds = array_map(
        static fn(array $genre): int => (int) $genre['id'],
        execute_statement($pdo, 'SELECT id FROM genres')->fetchAll()
    );
    if ($genreIds === []) {
        $errors['genre_ids'] = 'Bitte wähle mindestens ein Genre aus.';
    } elseif ($invalidGenre || array_diff($genreIds, $availableGenreIds) !== []) {
        $errors['genre_ids'] = 'Mindestens ein ausgewähltes Genre ist ungültig.';
    }

    $values = [
        'title' => $title,
        'original_language' => $language,
        'release_date' => $releaseDate,
        'runtime' => $runtimeText,
        'overview' => $overview,
        'genre_ids' => $genreIds,
    ];

    if ($errors !== []) {
        http_response_code(422);
        show_new_movie_form($pdo, $values, $errors);
        return;
    }

    $pdo->beginTransaction();

    try {
        $movieId = insert_and_return_id(
            $pdo,
            'INSERT INTO movies (
                title, original_title, original_language, status,
                release_date, overview, runtime
            ) VALUES (
                :title, :original_title, :original_language, :status,
                :release_date, :overview, :runtime
            )',
            [
                'title' => $title,
                'original_title' => $title,
                'original_language' => $language,
                'status' => 'Local',
                'release_date' => $releaseDate !== '' ? $releaseDate : null,
                'overview' => $overview,
                'runtime' => $runtime,
            ]
        );
        $relationStatement = $pdo->prepare(
            'INSERT INTO movie_genre (movie_id, genre_id) VALUES (:movie_id, :genre_id)'
        );

        foreach ($genreIds as $genreId) {
            $relationStatement->execute(['movie_id' => $movieId, 'genre_id' => $genreId]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    rotate_csrf_token();
    header('Location: /filme/' . $movieId, true, 303);
    exit;
}

function handle_delete_movie(PDO $pdo, array $parameters): void
{
    $id = filter_var($parameters['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        not_found('Die Film-ID ist ungültig.');
        return;
    }
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        forbidden('Das Sicherheitstoken ist ungültig oder abgelaufen. Bitte öffne die Filmseite erneut.');
        return;
    }

    $movie = execute_statement($pdo, 'SELECT id, title FROM movies WHERE id = :id', ['id' => $id])->fetch();

    if ($movie === false) {
        not_found('Dieser Film existiert nicht oder wurde bereits gelöscht.');
        return;
    }

    execute_statement($pdo, 'DELETE FROM movies WHERE id = :id', ['id' => $id]);
    rotate_csrf_token();
    set_flash_message('Der Film „' . (string) $movie['title'] . '“ wurde gelöscht.');
    header('Location: /filme', true, 303);
    exit;
}

function show_movie_detail(PDO $pdo, array $parameters): void
{
    $id = filter_var($parameters['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        not_found('Die Film-ID ist ungültig.');
        return;
    }

    $movie = execute_statement(
        $pdo,
        'SELECT id, external_id, title, original_title, original_language, status,
            release_date, overview, poster_url, source_url, provider_link, runtime
        FROM movies WHERE id = :id',
        ['id' => $id]
    )->fetch();

    if ($movie === false) {
        not_found('Dieser Film existiert nicht.');
        return;
    }

    $genres = execute_statement(
        $pdo,
        'SELECT g.id, g.name, g.slug
        FROM movie_genre mg
        JOIN genres g ON g.id = mg.genre_id
        WHERE mg.movie_id = :movie_id
        ORDER BY g.name',
        ['movie_id' => $id]
    )->fetchAll();
    $providers = execute_statement(
        $pdo,
        'SELECT provider_name, provider_logo_url, offer_type, display_priority, country_code
        FROM movie_provider
        WHERE movie_id = :movie_id
        ORDER BY
            CASE offer_type
                WHEN \'flatrate\' THEN 1
                WHEN \'free\' THEN 2
                WHEN \'ads\' THEN 3
                WHEN \'rent\' THEN 4
                WHEN \'buy\' THEN 5
                ELSE 6
            END,
            display_priority, provider_name',
        ['movie_id' => $id]
    )->fetchAll();

    page_start((string) $movie['title'], '/filme');
    ?>
    <?php if (request_text($_GET, 'imported') === '1'): ?>
        <section class="success-message" role="status">
            <strong>Import abgeschlossen.</strong>
            Poster, Filmdaten, Genres und <?= count($providers) ?> Anbieteroptionen wurden übernommen.
        </section>
    <?php endif; ?>

    <article class="series-detail movie-detail">
        <div class="detail-poster">
            <?php if ($movie['poster_url'] !== ''): ?>
                <img src="<?= e($movie['poster_url']) ?>" alt="Poster zu <?= e($movie['title']) ?>">
            <?php else: ?>
                <span class="poster-placeholder movie-placeholder" aria-hidden="true">FILM</span>
            <?php endif; ?>
        </div>

        <div class="detail-content">
            <p class="detail-number">Film <?= (int) $movie['id'] ?></p>
            <h1><?= e($movie['title']) ?></h1>
            <?php if ($movie['original_title'] !== '' && $movie['original_title'] !== $movie['title']): ?>
                <p class="original-title">Originaltitel: <?= e($movie['original_title']) ?></p>
            <?php endif; ?>
            <dl class="fact-list">
                <div><dt>Sprache</dt><dd><?= e(movie_language_label((string) $movie['original_language'])) ?></dd></div>
                <div><dt>Status</dt><dd><?= e(movie_status_label((string) $movie['status'])) ?></dd></div>
                <div><dt>Start</dt><dd><?= e($movie['release_date'] ?: 'Nicht angegeben') ?></dd></div>
                <div><dt>Laufzeit</dt><dd><?= $movie['runtime'] !== null ? (int) $movie['runtime'] . ' Min.' : 'Nicht angegeben' ?></dd></div>
            </dl>

            <p class="series-summary"><?= nl2br(e($movie['overview'])) ?></p>

            <section class="tag-section" aria-labelledby="movie-genre-heading">
                <h2 id="movie-genre-heading">Genres</h2>
                <?php if ($genres === []): ?>
                    <p class="muted-text">Noch keine Genres zugeordnet.</p>
                <?php else: ?>
                    <ul class="tag-list">
                        <?php foreach ($genres as $genre): ?>
                            <li><span><?= e($genre['name']) ?></span><small><?= e($genre['slug']) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </article>

    <section class="watch-section movie-watch-section" aria-labelledby="movie-watch-heading">
        <div class="watch-heading">
            <p class="eyebrow">Deutschland · JustWatch über TMDB</p>
            <h2 id="movie-watch-heading">Wo kann ich den Film ansehen?</h2>
        </div>

        <?php if ($providers === []): ?>
            <p class="muted-text">Für Deutschland sind aktuell keine Anbieterinformationen gespeichert.</p>
        <?php else: ?>
            <ul class="provider-grid">
                <?php foreach ($providers as $provider): ?>
                    <li class="provider-card">
                        <?php if ($provider['provider_logo_url'] !== ''): ?>
                            <img src="<?= e($provider['provider_logo_url']) ?>" alt="Logo von <?= e($provider['provider_name']) ?>" loading="lazy">
                        <?php endif; ?>
                        <div>
                            <span><?= e(movie_offer_label((string) $provider['offer_type'])) ?></span>
                            <strong><?= e($provider['provider_name']) ?></strong>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="watch-note">
            Anbieterinformationen werden von JustWatch über TMDB bereitgestellt und können sich jederzeit ändern.
            Der Link führt zur aktuellen Übersicht für Deutschland.
        </p>
        <div class="watch-actions">
            <?php if ($movie['provider_link'] !== ''): ?>
                <a class="button" href="<?= e($movie['provider_link']) ?>" target="_blank" rel="noopener noreferrer">Aktuelle Anbieter prüfen</a>
            <?php endif; ?>
            <?php if ($movie['source_url'] !== ''): ?>
                <a class="text-link" href="<?= e($movie['source_url']) ?>" target="_blank" rel="noopener noreferrer">Filmdaten bei TMDB</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="danger-zone" aria-labelledby="movie-delete-heading">
        <div>
            <p class="eyebrow">Gefahrenbereich</p>
            <h2 id="movie-delete-heading">Film löschen</h2>
            <p id="movie-delete-description">Dabei werden der Film sowie alle Genre- und Anbieterzuordnungen dauerhaft entfernt.</p>
        </div>
        <form method="post" action="/filme/<?= (int) $movie['id'] ?>/loeschen" onsubmit="return confirm('Film wirklich löschen?')">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="danger-button" type="submit" aria-describedby="movie-delete-description">Film endgültig löschen</button>
        </form>
    </section>

    <p><a class="text-link" href="/filme">Zurück zu allen Filmen</a></p>
    <?php
    page_end();
}
