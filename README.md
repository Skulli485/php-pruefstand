# Film- & Serienprüfstand

Der Film- & Serienprüfstand ist ein vollständiges PHP-Lernprojekt ohne Framework. Die Anwendung verwaltet Serien und Filme in getrennten relationalen Tabellen derselben Datenbank. Serien werden über TVmaze samt Episoden importiert; Filme können manuell gespeichert oder über TMDB samt Poster, Laufzeit, Genres und deutschen Anbieterinformationen übernommen werden. Lokal speichert die App in SQLite, auf Vercel persistent in Postgres.

Umgesetzt sind alle Aufgabenstufen von Bronze über Silber und Gold bis Diamant.

## Technologien

- PHP 8.5 mit cURL und PDO
- SQLite für die lokale Entwicklung, Postgres für Vercel
- eigener Router und Front Controller
- HTML5 und responsives CSS ohne Framework
- Prepared Statements, Transaktionen und Foreign Keys
- Instanzunabhängiger Double-Submit-Cookie-CSRF-Schutz für POST-Aktionen
- GET, POST und POST → Redirect → GET
- 1:n- und n:m-Beziehungen
- [TVmaze](https://www.tvmaze.com/api) als externe JSON-Datenquelle
- [TMDB](https://www.themoviedb.org) für Filmdaten und Poster
- JustWatch-Anbieterinformationen über den TMDB-Watch-Provider-Endpunkt

## Voraussetzungen

- PHP 8.5.x
- PHP-Erweiterungen `curl`, `pdo` und `pdo_sqlite`
- für eine lokale Postgres-Verbindung zusätzlich `pdo_pgsql`
- Internetzugriff für den Datenimport
- Git zum Klonen des Repositories
- optional `TMDB_API_TOKEN`, `TMDB_API_READ_TOKEN` oder `TMDB_API_KEY` für die automatische Filmsuche

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

Ohne TMDB-Zugangsdaten bleibt der gesamte Serienbereich sowie das manuelle Anlegen,
Anzeigen und Löschen von Filmen funktionsfähig. Für die lokale Online-Filmsuche wird
die Vorlage einmal nach `.env` kopiert und dort ein Read-Token oder v3-API-Schlüssel
eingetragen. Die lokale Datei wird von Git ignoriert:

```powershell
Copy-Item .env.example .env
php -S localhost:8000 -t public public/index.php
```

Bereits gesetzte System- oder Vercel-Umgebungsvariablen haben Vorrang vor der lokalen `.env`.

`php scripts/setup.php` legt die Tabellen an, importiert zwölf englisch- oder deutschsprachige Serien und lädt deren Episoden. Zusätzlich ergänzt das Setup die deutsche Beispielserie „Nordlicht“ mit einem lokalen Poster und vier Demo-Episoden. Der Befehl ist wiederholbar: Eindeutige IDs und Upserts verhindern doppelte Datensätze. Weitere lokal angelegte Serien bleiben erhalten.

## Veröffentlichung auf Vercel

Die Vercel-Version verwendet den empfohlenen PHP-Community-Runtime und eine über
`DATABASE_URL` verbundene Postgres-Datenbank. Eine SQLite-Datei wäre in einer
Vercel Function nicht dauerhaft beschreibbar und wird deshalb dort bewusst nicht
als Fallback verwendet.

1. Das GitHub-Repository in Vercel als neues Projekt importieren oder im verknüpften
   Projekt `vercel git connect` ausführen.
2. Im Vercel Marketplace eine Postgres-Integration wie Neon mit dem Projekt verbinden.
   Die Integration muss `DATABASE_URL` für Production und Preview setzen.
3. `TMDB_API_TOKEN`, `TMDB_API_READ_TOKEN` oder `TMDB_API_KEY` in den
   Vercel-Umgebungsvariablen für Production und Preview hinterlegen.
4. Beim ersten Request prüft die Anwendung das Schema und legt fehlende Tabellen
   automatisch und idempotent an. Beispieldaten können optional auf einem Rechner
   mit aktivem `pdo_pgsql` importiert werden, ohne Zugangsdaten in eine Datei zu schreiben:

```powershell
vercel env run --environment=production -- php scripts/setup.php
```

5. Danach mit `vercel --prod` veröffentlichen. Bei einer aktiven Git-Verknüpfung
   löst anschließend jeder Push auf `main` automatisch ein Production-Deployment aus;
   andere Branches erhalten Preview-Deployments.

Zugangsdaten gehören ausschließlich in Vercel beziehungsweise eine lokale, ignorierte
`.env`-Datei und niemals in Git. `.env.example` dokumentiert nur den Variablennamen.

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

### Diamant – Eingabe und Löschen

- API-Suchformular unter `/serien/neu?api_q=...`
- bewusste Auswahl aus bis zu acht TVmaze-Treffern
- automatischer Online-Fallback direkt in der normalen Serien-Suche
- automatischer Import über `POST /serien/importieren`
- zusätzlich ein manuelles Formular unter `/serien/neu`
- POST-Verarbeitung auf derselben Route
- serverseitige Prüfung aller Werte und Genre-IDs
- erhaltene und escaped Eingaben bei Fehlern
- Prepared INSERTs und gemeinsame Transaktion
- eindeutige externe IDs und Upserts gegen Duplikate
- HTTP 303 auf `/serien/{id}`
- Sender-/Webkanal und Anbieterlinks auf der Detailseite
- geschütztes Löschen per POST mit Bestätigungsdialog
- automatische Cascade-Löschung von Episoden und Genre-Zuordnungen

### Erweiterung – persönliche Filmsammlung

- eigener Bereich unter `/filme` mit lokaler Suche
- automatischer TMDB-Fallback, wenn kein exakter lokaler Filmtitel existiert
- bewusste Auswahl aus bis zu acht Online-Treffern
- Import von deutschem Titel, Originaltitel, Sprache, Startdatum, Laufzeit,
  Beschreibung, Poster und Genres
- deutsche Streaming-, Leih- und Kaufoptionen von JustWatch über TMDB
- manuelles Filmformular als Alternative zur API
- Detailansicht mit nach Angebotsart gruppierbaren Provider-Daten
- CSRF-geschütztes Hinzufügen und Löschen
- Cascade-Löschung aller Genre- und Anbieterzuordnungen

## Externe Datenquelle

Die öffentliche API stammt von [TVmaze](https://www.tvmaze.com/api):

| Endpoint | Gespeicherte Daten | Lokale Tabellen |
| --- | --- | --- |
| `/shows?page=0` | Titel, Sprache, Status, Premiere, Beschreibung, Poster, Genres | `shows`, `genres`, `show_genre` |
| `/search/shows?q={titel}` | bis zu acht Suchtreffer zur Auswahl | noch keine Speicherung |
| `/shows/{id}` | vollständige Daten der ausgewählten Serie | `shows`, `genres`, `show_genre` |
| `/shows/{id}/episodes` | Titel, Staffel, Nummer, Datum, Laufzeit, Beschreibung | `episodes` |

Nach der Auswahl sendet das Formular nur die TVmaze-ID und ein cookiegebundenes CSRF-Token. PHP lädt die Serie und ihre Episoden daraufhin selbst von den exakten Endpoints; Daten aus versteckten Formularfeldern werden nicht vertraut. Die HTML-Fragmente in API-Beschreibungen werden beim Import entfernt. Gespeichert werden nur Klartext und geprüfte HTTPS-URLs. TVmaze wird im Footer und auf externen Detailseiten als Quelle verlinkt.

TVmaze liefert in der öffentlichen API den aktuellen beziehungsweise zuletzt geführten Sender oder Web-/Streaming-Kanal, aber keine vollständige länderspezifische Liste aller heutigen Streaming-Angebote. Die Detailseite kennzeichnet diese Einschränkung und verlinkt deshalb zusätzlich auf die TVmaze-Serienseite. Dort können – abhängig von Land und Serie – aktuelle „Watch now“-Angebote angezeigt werden. Siehe [TVmaze-Erklärung zu Network und Web Channel](https://www.tvmaze.com/faq/13/shows).

„Nordlicht“ ist ein lokaler, reproduzierbarer Beispieldatensatz. Das textfreie Poster wurde eigens für die fiktive Serie erzeugt und liegt unter `public/images/nordlicht-poster.png`; die vier lokalen Episoden demonstrieren dieselbe 1:n-Beziehung wie die importierten TVmaze-Daten.

### Filmdatenquelle

Die Filmsuche verwendet die [TMDB Search API](https://developer.themoviedb.org/reference/search-movie)
mit `de-DE` und Region `DE`. Nach der Auswahl lädt PHP den Film erneut über
`/movie/{id}` und fragt `/movie/{id}/watch/providers` ab. Es werden ausschließlich
die deutschen Anbieter gespeichert. Die Anbieterinformationen stammen von JustWatch;
die Detailseite kennzeichnet dies und verlinkt auf die von TMDB gelieferte aktuelle
Anbieterübersicht.

TMDB verlangt für nicht kommerzielle API-Nutzung eine Quellenangabe. Deshalb enthält
der Footer das unveränderte, freigegebene TMDB-Logo sowie den Hinweis, dass die App
nicht von TMDB unterstützt oder zertifiziert wird. Der Token wird nur serverseitig
im `Authorization: Bearer`-Header verwendet und nie an den Browser ausgegeben.

## Datenbankstruktur

Ohne `DATABASE_URL` entsteht die lokale Datenbank unter `data/serienpruefstand.sqlite`
und wird nicht in Git eingecheckt. `PRAGMA foreign_keys = ON` ist für jede
SQLite-Verbindung aktiv. Wenn `DATABASE_URL` gesetzt ist, verbindet sich dieselbe
PDO-Schicht stattdessen per TLS mit Postgres. Das Schema, die Constraints und die
Cascade-Regeln sind für beide Datenbanken gleichwertig definiert.

### `shows`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `external_id` | `UNIQUE`, für lokale Serien `NULL` |
| `name`, `language`, `status`, `summary` | `NOT NULL` |
| `premiered` | optionales Datum |
| `image_url`, `source_url` | geprüfte externe URLs oder leer |
| `official_site_url` | geprüfte HTTPS-URL der offiziellen Serienseite oder leer |
| `distribution_name`, `distribution_type`, `distribution_country` | Sender-/Webkanal-Hinweis von TVmaze |
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

### `movies`

| Spalte | Regel |
| --- | --- |
| `id` | Primary Key beziehungsweise Postgres Identity |
| `external_id` | eindeutige TMDB-ID, für manuelle Filme `NULL` |
| `title`, `overview` | `NOT NULL` |
| `original_title`, `original_language`, `status` | Filmmetadaten |
| `release_date`, `runtime` | optionales Datum und optionale Laufzeit |
| `poster_url`, `source_url`, `provider_link` | geprüfte HTTPS-URLs oder leer |

### `movie_genre`

Die Zwischentabelle bildet die n:m-Beziehung zwischen `movies` und den bereits
gemeinsam genutzten `genres` ab. `(movie_id, genre_id)` ist der zusammengesetzte
Primary Key.

### `movie_provider`

Die Tabelle speichert deutsche Anbieter pro Film und Angebotsart (`flatrate`,
`free`, `ads`, `rent`, `buy`). `(movie_id, provider_id, offer_type)` verhindert
doppelte Einträge; `ON DELETE CASCADE` entfernt sie zusammen mit dem Film.

## Routen

| Methode | Route | Aufgabe | Status |
| --- | --- | --- | --- |
| GET | `/` | Übersicht und Datenbestand | 200 |
| GET | `/serien` | lokale Suche und bei fehlendem exaktem Titel TVmaze-Fallback über `?q=` | 200 |
| GET | `/serien/neu` | API-Suche über `?api_q=` und manuelles Formular | 200, 422 oder 502 |
| POST | `/serien/neu` | validieren, speichern und umleiten | 303 oder 422 |
| POST | `/serien/importieren` | ausgewählte TVmaze-Serie samt Episoden importieren | 303, 422 oder 502 |
| POST | `/serien/{id}/loeschen` | Serie samt abhängigen Datensätzen löschen | 303, 403 oder 404 |
| GET | `/serien/{id}` | Serie, Genres und Episoden | 200 oder 404 |
| GET | `/episoden` | 1:n-Auswertung Serien ↔ Episoden | 200 |
| GET | `/filme` | lokale Filmsuche und optionaler TMDB-Fallback über `?q=` | 200 |
| GET | `/filme/neu` | TMDB-Suche über `?api_q=` und manuelles Filmformular | 200 oder 502 |
| POST | `/filme/neu` | manuellen Film validieren, speichern und umleiten | 303 oder 422 |
| POST | `/filme/importieren` | ausgewählten TMDB-Film samt Genres und Anbietern importieren | 303, 422 oder 502 |
| GET | `/filme/{id}` | Film, Genres und deutsche Anbieter | 200 oder 404 |
| POST | `/filme/{id}/loeschen` | Film samt Zuordnungen löschen | 303, 403 oder 404 |

Eine bekannte Route mit einer nicht unterstützten Methode antwortet mit HTTP 405 und einem `Allow`-Header.

## Serverseitige Validierung

PHP prüft:

- API-Suchbegriff mit 2 bis 100 Zeichen
- ausgewählte TVmaze-ID als positive Ganzzahl
- Import-CSRF-Token gegen das HttpOnly-CSRF-Cookie
- Formular-CSRF-Token gegen das HttpOnly-CSRF-Cookie
- Lösch-ID als positive Ganzzahl und CSRF-Token gegen das HttpOnly-CSRF-Cookie
- TMDB-ID als positive Ganzzahl und Filmsuchbegriff mit 2 bis 100 Zeichen
- Filmtitel, Originalsprache, Veröffentlichungsdatum und optionale Laufzeit
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
- Auch das Hinzufügen aus Online-Suchergebnissen ist CSRF-geschützt.
- Die UNIQUE-Regel auf `shows.external_id` und Upserts verhindern doppelte Serien und Episoden.
- Löschen ist ausschließlich per POST und mit einem per `hash_equals()` geprüften CSRF-Token möglich.
- `ON DELETE CASCADE` entfernt Episoden und Genre-Zuordnungen zusammen mit der Serie.
- Foreign Keys, UNIQUE-Regeln und zusammengesetzte Primärschlüssel schützen Beziehungen.
- Alle dynamischen HTML-Ausgaben werden mit `e()` escaped oder kontrolliert als Integer ausgegeben.
- API-Beschreibungen werden vor dem Speichern von HTML befreit.
- Technische Exceptions werden protokolliert und nicht im Browser ausgegeben.
- `.env`, SQLite-Dateien, IDE-Dateien und Logs sind von Git ausgeschlossen.
- Der TMDB-Token bleibt ausschließlich in der Serverumgebung und wird nie in URLs eingebaut.
- Erwachsene TMDB-Suchergebnisse werden nicht angefordert und zusätzlich herausgefiltert.

## Projektstruktur

```text
php-pruefstand/
├── api/
│   └── index.php           # Vercel-PHP-Entrypoint
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
│   ├── environment.php     # lädt lokale, ignorierte Umgebungsvariablen
│   ├── helpers.php         # Escaping und gemeinsames Layout
│   ├── import.php          # Serien-, Episoden- und Genre-Import
│   ├── movie_api.php       # TMDB-Suche, Filmdetails und Anbieter
│   ├── movie_routes.php    # Filmseiten, Import, Formular und Löschen
│   ├── router.php          # Methoden- und Pfadabgleich
│   └── routes.php          # Abfragen, Validierung und HTML-Ausgabe
├── .env.example            # Vorlage für Datenbank- und TMDB-Konfiguration
├── vercel.json             # Runtime- und Routing-Konfiguration
├── Abschlussbericht.md
├── README.md
└── WARMUP.md
```

## Prüfungen

Geprüft werden unter anderem PHP-Syntax, lokaler Exakttreffer ohne API-Fallback,
Online-Treffer bei fehlendem lokalen Titel, CSRF-geschützte Einzelimporte,
persistierte Anbieter, wiederholbare Imports ohne Duplikat, geschütztes Löschen mit
Cascade-Regeln, manuelles Anlegen und Löschen eines temporären Films,
200-/303-/403-/404-/405-/422-/502-Antworten, ungültige IDs und Tokens,
Formulargrenzen sowie XSS-artige und SQL-artige Eingaben.

## Screenshot

Ein Browser-Screenshot wird ergänzt, sobald ein Browser-Backend verfügbar ist. Während der automatischen Prüfung war keine In-App-Browser-Verbindung vorhanden; deshalb enthält die Dokumentation kein erfundenes Bild.
