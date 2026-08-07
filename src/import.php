<?php

declare(strict_types=1);

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/database.php';

const IMPORT_SHOW_LIMIT = 12;

function api_text(mixed $value, string $fallback = ''): string
{
    if (!is_string($value)) {
        return $fallback;
    }

    $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim(is_string($text) ? $text : $fallback) ?: $fallback;
}

function genre_slug(string $name): string
{
    $slug = mb_strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
    $slug = trim(is_string($slug) ? $slug : '', '-');

    return $slug !== '' ? $slug : 'genre-' . substr(hash('sha256', $name), 0, 12);
}

function https_url(mixed $value): string
{
    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    return str_starts_with($value, 'https://') ? $value : '';
}

function valid_api_date(mixed $value): ?string
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }

    return $value;
}

function nullable_positive_integer(mixed $value): ?int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);

    return $number !== false && $number > 0 ? $number : null;
}

function import_shows(PDO $pdo): array
{
    $apiShows = api_get('/shows?page=0');
    $eligibleShows = array_values(array_filter(
        $apiShows,
        static function (mixed $show): bool {
            if (!is_array($show)) {
                return false;
            }

            $language = $show['language'] ?? null;

            return in_array($language, ['English', 'German'], true)
                && api_text($show['name'] ?? '') !== ''
                && api_text($show['summary'] ?? '') !== '';
        }
    ));
    $eligibleShows = array_slice($eligibleShows, 0, IMPORT_SHOW_LIMIT);

    $showStatement = $pdo->prepare(
        'INSERT INTO shows (
            external_id, name, language, status, premiered, summary, image_url, source_url
        ) VALUES (
            :external_id, :name, :language, :status, :premiered, :summary, :image_url, :source_url
        ) ON CONFLICT(external_id) DO UPDATE SET
            name = excluded.name,
            language = excluded.language,
            status = excluded.status,
            premiered = excluded.premiered,
            summary = excluded.summary,
            image_url = excluded.image_url,
            source_url = excluded.source_url'
    );
    $showIdStatement = $pdo->prepare(
        'SELECT id FROM shows WHERE external_id = :external_id'
    );
    $deleteGenresStatement = $pdo->prepare(
        'DELETE FROM show_genre WHERE show_id = :show_id'
    );
    $genreStatement = $pdo->prepare(
        'INSERT INTO genres (name, slug)
        VALUES (:name, :slug)
        ON CONFLICT(name) DO UPDATE SET slug = excluded.slug'
    );
    $genreIdStatement = $pdo->prepare(
        'SELECT id FROM genres WHERE name = :name'
    );
    $relationStatement = $pdo->prepare(
        'INSERT OR IGNORE INTO show_genre (show_id, genre_id)
        VALUES (:show_id, :genre_id)'
    );

    $importedShows = 0;
    $genreRelations = 0;
    $skippedRows = 0;
    $showIds = [];

    $pdo->beginTransaction();

    try {
        foreach ($eligibleShows as $show) {
            $externalId = filter_var($show['id'] ?? null, FILTER_VALIDATE_INT);
            $name = api_text($show['name'] ?? '');
            $language = api_text($show['language'] ?? '', 'Unbekannt');
            $status = api_text($show['status'] ?? '', 'Unbekannt');
            $summary = api_text($show['summary'] ?? '');

            if ($externalId === false || $externalId < 1 || $name === '' || $summary === '') {
                $skippedRows++;
                continue;
            }

            $showStatement->execute([
                'external_id' => $externalId,
                'name' => $name,
                'language' => $language,
                'status' => $status,
                'premiered' => valid_api_date($show['premiered'] ?? null),
                'summary' => $summary,
                'image_url' => https_url($show['image']['medium'] ?? null),
                'source_url' => https_url($show['url'] ?? null),
            ]);

            $showIdStatement->execute(['external_id' => $externalId]);
            $showId = (int) $showIdStatement->fetchColumn();

            if ($showId < 1) {
                $skippedRows++;
                continue;
            }

            $showIds[$externalId] = $showId;
            $deleteGenresStatement->execute(['show_id' => $showId]);

            foreach ($show['genres'] ?? [] as $genreName) {
                $genreName = api_text($genreName);

                if ($genreName === '') {
                    continue;
                }

                $genreStatement->execute([
                    'name' => $genreName,
                    'slug' => genre_slug($genreName),
                ]);
                $genreIdStatement->execute(['name' => $genreName]);
                $genreId = (int) $genreIdStatement->fetchColumn();

                if ($genreId < 1) {
                    continue;
                }

                $relationStatement->execute([
                    'show_id' => $showId,
                    'genre_id' => $genreId,
                ]);
                $genreRelations += $relationStatement->rowCount();
            }

            $importedShows++;
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    return [
        'shows' => $importedShows,
        'genre_relations' => $genreRelations,
        'skipped' => $skippedRows,
        'show_ids' => $showIds,
    ];
}

function import_episodes(PDO $pdo, array $showIds): array
{
    $episodeStatement = $pdo->prepare(
        'INSERT INTO episodes (
            external_id, show_id, name, season, number, airdate, runtime, summary
        ) VALUES (
            :external_id, :show_id, :name, :season, :number, :airdate, :runtime, :summary
        ) ON CONFLICT(external_id) DO UPDATE SET
            show_id = excluded.show_id,
            name = excluded.name,
            season = excluded.season,
            number = excluded.number,
            airdate = excluded.airdate,
            runtime = excluded.runtime,
            summary = excluded.summary'
    );

    $importedEpisodes = 0;
    $skippedEpisodes = 0;

    foreach ($showIds as $externalShowId => $localShowId) {
        $episodes = api_get('/shows/' . (int) $externalShowId . '/episodes');
        $pdo->beginTransaction();

        try {
            foreach ($episodes as $episode) {
                if (!is_array($episode)) {
                    $skippedEpisodes++;
                    continue;
                }

                $externalId = filter_var($episode['id'] ?? null, FILTER_VALIDATE_INT);
                $name = api_text($episode['name'] ?? '');

                if ($externalId === false || $externalId < 1 || $name === '') {
                    $skippedEpisodes++;
                    continue;
                }

                $episodeStatement->execute([
                    'external_id' => $externalId,
                    'show_id' => (int) $localShowId,
                    'name' => $name,
                    'season' => nullable_positive_integer($episode['season'] ?? null),
                    'number' => nullable_positive_integer($episode['number'] ?? null),
                    'airdate' => valid_api_date($episode['airdate'] ?? null),
                    'runtime' => nullable_positive_integer($episode['runtime'] ?? null),
                    'summary' => api_text($episode['summary'] ?? ''),
                ]);
                $importedEpisodes++;
            }

            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    return [
        'episodes' => $importedEpisodes,
        'skipped' => $skippedEpisodes,
    ];
}

function seed_nordlicht(PDO $pdo): array
{
    $show = execute_statement(
        $pdo,
        'SELECT id FROM shows
        WHERE external_id IS NULL AND name = :name
        ORDER BY id
        LIMIT 1',
        ['name' => 'Nordlicht']
    )->fetch();

    $pdo->beginTransaction();

    try {
        if ($show === false) {
            $showStatement = $pdo->prepare(
                'INSERT INTO shows (
                    name, language, status, premiered, summary, image_url
                ) VALUES (
                    :name, :language, :status, :premiered, :summary, :image_url
                )'
            );
            $showStatement->execute([
                'name' => 'Nordlicht',
                'language' => 'German',
                'status' => 'Running',
                'premiered' => '2026-08-07',
                'summary' => 'Eine deutschsprachige Mysteryserie über ein rätselhaftes Signal aus dem Norden.',
                'image_url' => '/images/nordlicht-poster.png',
            ]);
            $showId = (int) $pdo->lastInsertId();
        } else {
            $showId = (int) $show['id'];
            execute_statement(
                $pdo,
                'UPDATE shows SET image_url = :image_url WHERE id = :id',
                ['image_url' => '/images/nordlicht-poster.png', 'id' => $showId]
            );
        }

        execute_statement(
            $pdo,
            'INSERT INTO genres (name, slug)
            VALUES (:name, :slug)
            ON CONFLICT(name) DO UPDATE SET slug = excluded.slug',
            ['name' => 'Drama', 'slug' => 'drama']
        );
        $genreId = (int) execute_statement(
            $pdo,
            'SELECT id FROM genres WHERE name = :name',
            ['name' => 'Drama']
        )->fetchColumn();
        execute_statement(
            $pdo,
            'INSERT OR IGNORE INTO show_genre (show_id, genre_id)
            VALUES (:show_id, :genre_id)',
            ['show_id' => $showId, 'genre_id' => $genreId]
        );

        $episodeStatement = $pdo->prepare(
            'INSERT INTO episodes (
                external_id, show_id, name, season, number, airdate, runtime, summary
            ) VALUES (
                :external_id, :show_id, :name, :season, :number, :airdate, :runtime, :summary
            ) ON CONFLICT(external_id) DO UPDATE SET
                show_id = excluded.show_id,
                name = excluded.name,
                season = excluded.season,
                number = excluded.number,
                airdate = excluded.airdate,
                runtime = excluded.runtime,
                summary = excluded.summary'
        );
        $episodes = [
            [-1001, 'Das Signal', '2026-08-07', 48, 'Ein unbekanntes Funksignal führt die Ermittlerin Liv bis an die sturmumtoste Küste.'],
            [-1002, 'Weiße Nacht', '2026-08-14', 46, 'Während die Nacht nicht dunkel wird, taucht eine zweite Nachricht aus dem Rauschen auf.'],
            [-1003, 'Unter dem Eis', '2026-08-21', 51, 'Eine Spur im gefrorenen Fjord verbindet das Signal mit einem alten Forschungsprojekt.'],
            [-1004, 'Die Frequenz', '2026-08-28', 49, 'Liv muss entscheiden, ob die letzte Übertragung eine Warnung oder eine Einladung ist.'],
        ];

        foreach ($episodes as $index => [$externalId, $name, $airdate, $runtime, $summary]) {
            $episodeStatement->execute([
                'external_id' => $externalId,
                'show_id' => $showId,
                'name' => $name,
                'season' => 1,
                'number' => $index + 1,
                'airdate' => $airdate,
                'runtime' => $runtime,
                'summary' => $summary,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    return ['show_id' => $showId, 'episodes' => count($episodes)];
}
