# Serienprüfstand

Der Serienprüfstand ist ein vollständiges PHP-Lernprojekt ohne Framework. Die Anwendung sucht Serien bei TVmaze, übernimmt ausgewählte Treffer samt Episoden in SQLite, wertet Beziehungen mit SQL-JOINs aus und erlaubt zusätzlich das sichere Anlegen eigener Serien.

Umgesetzt sind alle Aufgabenstufen von Bronze über Silber und Gold bis Diamant.

## Technologien

- PHP 8.5 mit cURL und PDO-SQLite
- eigener Router und Front Controller
- HTML5 und responsives CSS ohne Framework
- Prepared Statements, Transaktionen und Foreign Keys
- GET, POST und POST → Redirect → GET
- 1:n- und n:m-Beziehungen
- [TVmaze](https://www.tvmaze.com/api) als externe JSON-Datenquelle

## Voraussetzungen

- PHP 8.5.x
- PHP-Erweiterungen `curl`, `pdo` und `pdo_sqlite`
- Internetzugriff für den Datenimport
- Git zum Klonen des Repositories

```powershell
php -v
php -m
```

## Installation und Start

```powershell
git clone https://github.com/Skulli485/php-pruefstand.git
cd php-pruefstand
php scripts/setup.php
php -S localhost:8000 -t public public/index.php
```

Danach ist die Anwendung unter <http://localhost:8000> erreichbar.

`php scripts/setup.php` legt die Tabellen an, importiert zwölf englisch- oder deutschsprachige Serien und lädt deren Episoden. Zusätzlich ergänzt das Setup die deutsche Beispielserie „Nordlicht“ mit einem lokalen Poster und vier Demo-Episoden. Der Befehl ist wiederholbar: Eindeutige IDs und Upserts verhindern doppelte Datensätze. Weitere lokal angelegte Serien bleiben erhalten.

## Aufgabenstufen

### Bronze – vorzeigbar und sicher

- eigener Front Controller in `public/index.php`
- Router mit HTTP-Methoden und dynamischen `{id}`-Segmenten
- vollständiger [Prepared-Statement-Audit](docs/SQL-AUDIT.md)
- HTML-Escaping über `e()`
- verständliche 404-, 405-, 422- und 500-Zustände

### Silber – zweite Quelle und 1:n

Serien stammen aus `/shows?page=0`. Die Episoden jeder importierten Serie kommen zusätzlich aus `/shows/{id}/episodes`.

```text
shows
  id (Primary Key)
   │
   └── 1:n ── episodes
                show_id (Foreign Key → shows.id)
```

Eine Serie kann viele Episoden besitzen. Jede Episode gehört genau zu einer Serie, deshalb liegt `show_id` in `episodes` auf der n-Seite. `/episoden` zeigt die gemeinsame JOIN-Auswertung.

### Gold – echte n:m-Beziehung

TVmaze liefert für eine Serie mehrere Genres. Dasselbe Genre kann zu mehreren Serien gehören.

```text
shows
  id (Primary Key)
   │
   └── show_genre
         show_id  (Foreign Key → shows.id)
         genre_id (Foreign Key → genres.id)
         Primary Key (show_id, genre_id)
                    │
                    └── genres
                          id (Primary Key)
```

Die Zwischentabelle `show_genre` speichert jedes Serien-Genre-Paar höchstens einmal. Die Detailroute `/serien/{id}` lädt alle Genres über einen JOIN.

### Diamant – Eingabe

- API-Suchformular unter `/serien/neu?api_q=...`
- bewusste Auswahl aus bis zu acht TVmaze-Treffern
- automatischer Import über `POST /serien/importieren`
- zusätzlich ein manuelles Formular unter `/serien/neu`
- POST-Verarbeitung auf derselben Route
- serverseitige Prüfung aller Werte und Genre-IDs
- erhaltene und escaped Eingaben bei Fehlern
- Prepared INSERTs und gemeinsame Transaktion
- eindeutige externe IDs und Upserts gegen Duplikate
- HTTP 303 auf `/serien/{id}`

## Externe Datenquelle

Die öffentliche API stammt von [TVmaze](https://www.tvmaze.com/api):

| Endpoint | Gespeicherte Daten | Lokale Tabellen |
| --- | --- | --- |
| `/shows?page=0` | Titel, Sprache, Status, Premiere, Beschreibung, Poster, Genres | `shows`, `genres`, `show_genre` |
| `/search/shows?q={titel}` | bis zu acht Suchtreffer zur Auswahl | noch keine Speicherung |
| `/shows/{id}` | vollständige Daten der ausgewählten Serie | `shows`, `genres`, `show_genre` |
| `/shows/{id}/episodes` | Titel, Staffel, Nummer, Datum, Laufzeit, Beschreibung | `episodes` |

Nach der Auswahl sendet das Formular nur die TVmaze-ID. PHP lädt die Serie und ihre Episoden daraufhin selbst von den exakten Endpoints; Daten aus versteckten Formularfeldern werden nicht vertraut. Die HTML-Fragmente in API-Beschreibungen werden beim Import entfernt. Gespeichert werden nur Klartext und geprüfte HTTPS-URLs. TVmaze wird im Footer und auf externen Detailseiten als Quelle verlinkt.

„Nordlicht“ ist ein lokaler, reproduzierbarer Beispieldatensatz. Das textfreie Poster wurde eigens für die fiktive Serie erzeugt und liegt unter `public/images/nordlicht-poster.png`; die vier lokalen Episoden demonstrieren dieselbe 1:n-Beziehung wie die importierten TVmaze-Daten.

## Datenbankstruktur

Die aktive Datenbank entsteht unter `data/serienpruefstand.sqlite` und wird nicht in Git eingecheckt. `PRAGMA foreign_keys = ON` ist für jede Verbindung aktiv.

### `shows`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `external_id` | `UNIQUE`, für lokale Serien `NULL` |
| `name`, `language`, `status`, `summary` | `NOT NULL` |
| `premiered` | optionales Datum |
| `image_url`, `source_url` | geprüfte externe URLs oder leer |
| `created_at` | Standardwert `CURRENT_TIMESTAMP` |

### `episodes`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `external_id` | `NOT NULL`, `UNIQUE` |
| `show_id` | `NOT NULL`, Foreign Key auf `shows.id` |
| `name` | `NOT NULL` |
| `season`, `number`, `airdate`, `runtime` | Episodenmetadaten |
| `summary` | Klartextbeschreibung |

### `genres`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `name` | `NOT NULL`, `UNIQUE` |
| `slug` | `NOT NULL`, `UNIQUE` |

### `show_genre`

| Spalte | Regel |
| --- | --- |
| `show_id` | Foreign Key auf `shows.id` |
| `genre_id` | Foreign Key auf `genres.id` |
| beide zusammen | zusammengesetzter Primary Key |

## Routen

| Methode | Route | Aufgabe | Status |
| --- | --- | --- | --- |
| GET | `/` | Übersicht und Datenbestand | 200 |
| GET | `/serien` | Serienliste und Suche über `?q=` | 200 |
| GET | `/serien/neu` | API-Suche über `?api_q=` und manuelles Formular | 200, 422 oder 502 |
| POST | `/serien/neu` | validieren, speichern und umleiten | 303 oder 422 |
| POST | `/serien/importieren` | ausgewählte TVmaze-Serie samt Episoden importieren | 303, 422 oder 502 |
| GET | `/serien/{id}` | Serie, Genres und Episoden | 200 oder 404 |
| GET | `/episoden` | 1:n-Auswertung Serien ↔ Episoden | 200 |

Eine bekannte Route mit einer nicht unterstützten Methode antwortet mit HTTP 405 und einem `Allow`-Header.

## Serverseitige Validierung

PHP prüft:

- API-Suchbegriff mit 2 bis 100 Zeichen
- ausgewählte TVmaze-ID als positive Ganzzahl
- Titel mit 2 bis 150 Zeichen
- erlaubte Sprache und erlaubten Status
- optionales Premierendatum im Format `YYYY-MM-DD`
- Beschreibung mit 20 bis 5000 Zeichen
- mindestens ein vorhandenes Genre
- falsche Typen wie Arrays statt erwarteter Texte

HTML-Attribute helfen im Browser, ersetzen aber nicht die serverseitige Prüfung.

## Sicherheit

- SQL läuft ausschließlich über `prepare()` und `execute()`.
- GET-, POST-, API- und Routenwerte werden nie in SQL-Strings eingesetzt.
- API-Import und Formularspeicherung verwenden Transaktionen.
- Eine ausgewählte externe ID wird serverseitig erneut bei TVmaze aufgelöst.
- Die UNIQUE-Regel auf `shows.external_id` und Upserts verhindern doppelte Serien und Episoden.
- Foreign Keys, UNIQUE-Regeln und zusammengesetzte Primärschlüssel schützen Beziehungen.
- Alle dynamischen HTML-Ausgaben werden mit `e()` escaped oder kontrolliert als Integer ausgegeben.
- API-Beschreibungen werden vor dem Speichern von HTML befreit.
- Technische Exceptions werden protokolliert und nicht im Browser ausgegeben.
- `.env`, SQLite-Dateien, IDE-Dateien und Logs sind von Git ausgeschlossen.

## Projektstruktur

```text
php-pruefstand/
├── data/                   # lokale SQLite-Dateien, von Git ignoriert
├── docs/                   # Aufgaben- und Sicherheitsdokumentation
├── public/
│   ├── index.php           # Front Controller und Routentabelle
│   └── styles.css          # responsives Serienlayout
├── scripts/
│   └── setup.php           # Schema und TVmaze-Import
├── src/
│   ├── api.php             # cURL-basierter API-Abruf
│   ├── database.php        # PDO-Verbindung und Schema
│   ├── helpers.php         # Escaping und gemeinsames Layout
│   ├── import.php          # Serien-, Episoden- und Genre-Import
│   ├── router.php          # Methoden- und Pfadabgleich
│   └── routes.php          # Abfragen, Validierung und HTML-Ausgabe
├── Abschlussbericht.md
├── README.md
└── WARMUP.md
```

## Prüfungen

Geprüft werden unter anderem PHP-Syntax, TVmaze-Suche, Einzelimport von „Dark“ mit 26 Episoden, wiederholbarer Import ohne Duplikat, 200-/303-/404-/405-/422-/502-Antworten, Detailansicht, ungültige IDs, Formulargrenzen, falsche Datentypen, XSS-artige und SQL-artige Eingaben sowie die Redirects auf dynamische IDs.

## Screenshot

Ein Browser-Screenshot wird ergänzt, sobald ein Browser-Backend verfügbar ist. Während der automatischen Prüfung war keine In-App-Browser-Verbindung vorhanden; deshalb enthält die Dokumentation kein erfundenes Bild.
