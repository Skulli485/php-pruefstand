# SQL-Audit – Bronze 5.2

Stand: 7. August 2026

## Ergebnis

Alle SQL-Anweisungen laufen über Prepared Statements. Es gibt im Projekt keine Aufrufe von `PDO::query()` oder `PDO::exec()` und keine SQL-Anweisung, in die ein Wert aus API, URL, GET oder POST per String-Verkettung eingesetzt wird.

`execute_statement()` in `src/database.php` ist die gemeinsame Abkürzung für:

```php
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
```

Die Importfunktion bereitet ihre beiden INSERT-Anweisungen einmal vor und führt sie anschließend mit jeweils neuen Parameterwerten mehrfach aus. Das ist sicher und spart unnötiges erneutes Vorbereiten in der Schleife.

## Geprüfte SQL-Anweisungen

| Datei | Anweisung | Variable Werte | Prüfung |
| --- | --- | --- | --- |
| `src/database.php` | `PRAGMA foreign_keys = ON` | keine | über `execute_statement()` vorbereitet |
| `src/database.php` | `CREATE TABLE authors` | keine | über `execute_statement()` vorbereitet |
| `src/database.php` | `CREATE TABLE posts` | keine | über `execute_statement()` vorbereitet |
| `src/import.php` | `INSERT INTO authors ... ON CONFLICT` | API-ID, Name, E-Mail, Stadt, Firma | einmal vorbereitet, Werte bei `execute()` gebunden |
| `src/import.php` | `INSERT INTO posts ... ON CONFLICT` | API-ID, Autor-ID, Titel, Inhalt | einmal vorbereitet, Werte bei `execute()` gebunden |
| `src/import.php` | `SELECT id, external_id FROM authors` | keine | mit `prepare()` und `execute()` ausgeführt |
| `src/routes.php` | `SELECT COUNT(*) FROM authors` | keine | über `execute_statement()` vorbereitet |
| `src/routes.php` | `SELECT COUNT(*) FROM posts` | keine | über `execute_statement()` vorbereitet |
| `src/routes.php` | Beitragsliste mit `JOIN` und `LIKE` | GET-Suchbegriff | zwei benannte Platzhalter `:title` und `:body` |
| `src/routes.php` | Beitragsdetail mit `JOIN` | dynamische Routen-ID | Platzhalter `:id` nach Integer-Validierung |
| `src/routes.php` | Autorendetail | dynamische Routen-ID | Platzhalter `:id` nach Integer-Validierung |
| `src/routes.php` | Beiträge eines Autors | dynamische Routen-ID | Platzhalter `:author_id` |

## Bereits sichere Stellen

Alle oben genannten Stellen waren bereits sicher. Besonders wichtig sind:

- Die Suche bindet auch die Prozentzeichen als Teil des Wertes und klebt den GET-Text nicht an SQL.
- Dynamische IDs werden zuerst als positive Integer validiert und anschließend trotzdem als Parameter gebunden.
- API-Daten werden nie direkt in einen SQL-String geschrieben.
- Die feste `LIMIT 50` ist keine Benutzereingabe und bleibt Teil der vorbereiteten SQL-Struktur.

## Geänderte Stellen

Es musste keine SQL-Anweisung geändert werden. Der sichere Aufbau war bereits Teil des Initial-Commits. Neu hinzugekommen ist dieses Audit als nachvollziehbare Dokumentation der vollständigen Kontrolle.

## Negativtests

- Suchtext `' OR '1'='1` wird als gewöhnlicher Suchwert behandelt und verändert die WHERE-Bedingung nicht.
- Eine Routen-ID wie `1 OR 1=1` besteht die Integer-Validierung nicht und führt zu HTTP 404.
- Die Zahl der Beiträge bleibt nach den Angriffstests unverändert.

## Abschluss-Nachtrag nach Silber, Gold und Diamant

Nach dem Bronze-Audit kamen weitere SQL-Anweisungen hinzu. Auch sie wurden einzeln geprüft:

- Schema und Import für `comments`
- 1:n-Auswertung mit `LEFT JOIN` auf `/resonanz`
- Schema und Beispieldaten für `tags` und `post_tag`
- n:m-JOIN auf der Beitragsdetailseite
- SELECTs für Autoren und Schlagwörter im Formular
- Existenzprüfung für Autor- und Schlagwort-IDs
- INSERT in `posts` und `post_tag` beim POST

Alle neuen Anweisungen laufen ebenfalls über `prepare()` und `execute()`. Der abschließende Quelltext-Scan findet keine direkten Aufrufe von `PDO::query()` oder `PDO::exec()` und keine Verkettung von GET-, POST-, Routen- oder API-Werten in SQL.
