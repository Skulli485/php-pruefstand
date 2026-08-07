# SQL-Audit – Serienprüfstand

Stand: 7. August 2026

## Ergebnis

Alle SQL-Anweisungen laufen über Prepared Statements. Das Projekt verwendet weder `PDO::query()` noch `PDO::exec()`. Kein Wert aus API, URL, GET oder POST wird per String-Verkettung in SQL eingesetzt.

`execute_statement()` in `src/database.php` ist die gemeinsame Abkürzung:

```php
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
```

## Geprüfte Bereiche

| Datei | SQL-Aufgabe | Variable Werte | Absicherung |
| --- | --- | --- | --- |
| `src/database.php` | PRAGMA, Tabellen und Indizes | keine | feste SQL-Struktur über `execute_statement()` |
| `src/import.php` | Upsert in `shows` | TVmaze-Felder | benannte Parameter |
| `src/import.php` | Upsert in `episodes` | TVmaze-Felder und lokale Serien-ID | benannte Parameter |
| `src/import.php` | Upsert in `genres` | Name und Slug | benannte Parameter |
| `src/import.php` | DELETE und INSERT in `show_genre` | lokale IDs | benannte Parameter |
| `src/routes.php` | Serienliste mit JOIN und LIKE | GET-Suchbegriff | `:name` und `:summary` |
| `src/routes.php` | Episoden-Auswertung | keine | feste JOIN-Abfrage |
| `src/routes.php` | Seriendetail und Genres | Routen-ID | Integer-Prüfung und Parameter |
| `src/routes.php` | Formular-Existenzprüfung | Genre-IDs | vorhandene IDs werden per SELECT geladen und verglichen |
| `src/routes.php` | INSERT in `shows` und `show_genre` | POST-Werte | vorbereitete INSERTs in Transaktion |

## Wichtige sichere Stellen

- Die Suche bindet die Prozentzeichen als Bestandteil des Parameterwerts.
- Dynamische IDs werden zuerst als positive Integer validiert und danach trotzdem gebunden.
- TVmaze-Daten werden bereinigt und nie direkt in SQL geschrieben.
- Die festen Werte `LIMIT 50` und `LIMIT 60` stammen nicht aus Benutzereingaben.
- Das Formular akzeptiert nur bekannte Sprach-, Status- und Genre-Werte.
- Alle Transaktionen werden bei Exceptions zurückgerollt.

## Negativtests

- Der Suchtext `' OR '1'='1` bleibt ein gewöhnlicher LIKE-Wert.
- Eine Routen-ID wie `1 OR 1=1` besteht die Integer-Prüfung nicht und führt zu HTTP 404.
- Nicht vorhandene Genre-IDs führen zu HTTP 422.
- XSS-artige Titel und Beschreibungen werden escaped angezeigt.
- Der Quelltext-Scan findet keine direkten Aufrufe von `PDO::query()` oder `PDO::exec()`.
