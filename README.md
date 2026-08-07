# Redaktionsprüfstand

Der Redaktionsprüfstand ist ein vollständiges PHP-Lernprojekt ohne Framework. Die Anwendung importiert Autoren, Beiträge und Kommentare aus JSONPlaceholder, speichert sie lokal in SQLite, wertet Beziehungen mit SQL-JOINs aus und erlaubt das sichere Anlegen eigener Beiträge.

Umgesetzt sind alle Aufgabenstufen von Bronze über Silber und Gold bis einschließlich Diamant.

## Technologien

- PHP 8.5
- PDO mit SQLite
- eigener Router und Front Controller
- HTML5
- eigenes responsives CSS ohne Framework
- Prepared Statements
- GET und POST
- Transaktionen und Foreign Keys
- 1:n- und n:m-Beziehungen
- JSONPlaceholder als externe Datenquelle

## Voraussetzungen

- PHP 8.5.x
- PDO-Treiber `sqlite`
- Internetzugriff für den Datenimport
- Git zum Klonen des Repositories

PHP und die verfügbaren PDO-Treiber lassen sich so prüfen:

```powershell
php -v
php -r "echo implode(', ', PDO::getAvailableDrivers()), PHP_EOL;"
```

## Installation und Start

```powershell
git clone https://github.com/Skulli485/php-pruefstand.git
cd php-pruefstand
php scripts/setup.php
php -S localhost:8000 -t public public/index.php
```

Danach ist die Anwendung unter <http://localhost:8000> erreichbar.

`php scripts/setup.php` legt das Schema an, importiert die API-Daten und ergänzt die lokalen Beispiel-Schlagwörter. Der Befehl kann wiederholt werden: `UNIQUE`-Regeln und Upserts verhindern doppelte externe Datensätze, der zusammengesetzte Primärschlüssel verhindert doppelte Schlagwort-Beziehungen.

## Aufgabenstufen

### Bronze – vorzeigbar und sicher

- Projekt-README mit Startanleitung, Routen und Datenbankbeschreibung
- eigener Front Controller unter `public/index.php`
- methodenfähiger Router mit dynamischen `{id}`-Segmenten
- vollständiger [Prepared-Statement-Audit](docs/SQL-AUDIT.md)
- HTML-Escaping über die Hilfsfunktion `e()`
- verständliche 404-, 405-, 422- und 500-Seiten beziehungsweise Fehlerzustände

### Silber – zweite Quelle und 1:n

- `/comments` als zweiter JSONPlaceholder-Endpoint
- eigene Tabelle `comments` mit eindeutiger externer ID
- Fremdschlüssel `comments.post_id`
- Route `/resonanz` mit JOIN-Auswertung über Beiträge und Kommentare

Die 1:n-Beziehung lautet:

```text
posts
  id (Primary Key)
   │
   └── 1:n ── comments
                post_id (Foreign Key → posts.id)
```

Ein Beitrag kann viele Kommentare besitzen. Jeder Kommentar gehört genau zu einem Beitrag, deshalb liegt der Fremdschlüssel auf der n-Seite in `comments`.

### Gold – echte n:m-Beziehung

- lokale Schlagwörter in `tags`
- Zwischentabelle `post_tag`
- zwei Foreign Keys
- zusammengesetzter Primärschlüssel `(post_id, tag_id)`
- Anzeige aller Schlagwörter auf `/beitraege/{id}` über einen JOIN

Die n:m-Beziehung lautet:

```text
posts
  id (Primary Key)
   │
   └── post_tag
         post_id (Foreign Key → posts.id)
         tag_id  (Foreign Key → tags.id)
         Primary Key (post_id, tag_id)
                   │
                   └── tags
                         id (Primary Key)
```

Ein Beitrag kann mehrere Schlagwörter haben, und ein Schlagwort kann vielen Beiträgen zugeordnet sein. `post_tag` speichert jedes gültige Paar genau einmal.

### Diamant – Eingabe

- GET-Formular unter `/beitraege/neu`
- POST-Verarbeitung auf derselben Route
- serverseitige Prüfung von Autor, Titel, Inhalt und Schlagwörtern
- verständliche Fehler mit erhaltenen und escaped Eingaben
- Prepared INSERTs für Beitrag und Schlagwort-Paare
- gemeinsame Transaktion
- neue ID über `lastInsertId()`
- POST → Redirect → GET mit HTTP 303

## Externe Datenquelle

Die Daten kommen von [JSONPlaceholder](https://jsonplaceholder.typicode.com/):

| Endpoint | Gespeicherte Daten | Lokale Tabelle |
| --- | --- | --- |
| `/users` | Name, E-Mail, Stadt, Firma | `authors` |
| `/posts` | Autorenzuordnung, Titel, Inhalt | `posts` |
| `/comments` | Beitragszuordnung, Betreff, E-Mail, Inhalt | `comments` |

Beim Import werden nur benötigte Felder gespeichert. HTTP-Fehler, nicht erfolgreiche Statuscodes und ungültiges JSON werden kontrolliert behandelt. Jeder Import läuft in einer Transaktion.

## Datenbankstruktur

Die lokale Datei entsteht unter `data/pruefstand.sqlite` und wird nicht in Git eingecheckt. `PRAGMA foreign_keys = ON` wird für jede PDO-Verbindung aktiviert.

### `authors`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `external_id` | `UNIQUE` |
| `name` | `NOT NULL` |
| `email` | `NOT NULL` |
| `city` | `NOT NULL` |
| `company` | `NOT NULL` |

### `posts`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `external_id` | `UNIQUE`, bei lokalen Beiträgen `NULL` |
| `author_id` | `NOT NULL`, Foreign Key auf `authors.id` |
| `title` | `NOT NULL` |
| `body` | `NOT NULL` |
| `created_at` | `NOT NULL`, Standardwert `CURRENT_TIMESTAMP` |

### `comments`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `external_id` | `NOT NULL`, `UNIQUE` |
| `post_id` | `NOT NULL`, Foreign Key auf `posts.id` |
| `name` | `NOT NULL` |
| `email` | `NOT NULL` |
| `body` | `NOT NULL` |

### `tags`

| Spalte | Regel |
| --- | --- |
| `id` | `INTEGER PRIMARY KEY` |
| `name` | `NOT NULL`, `UNIQUE` |
| `slug` | `NOT NULL`, `UNIQUE` |

### `post_tag`

| Spalte | Regel |
| --- | --- |
| `post_id` | `NOT NULL`, Foreign Key auf `posts.id` |
| `tag_id` | `NOT NULL`, Foreign Key auf `tags.id` |
| beide zusammen | zusammengesetzter Primary Key |

## Routen

| Methode | Route | Aufgabe | Status |
| --- | --- | --- | --- |
| GET | `/` | Übersicht und Datenbestand | 200 |
| GET | `/beitraege` | Beitragsliste und Suche über `?q=` | 200 |
| GET | `/beitraege/neu` | Eingabeformular | 200 |
| POST | `/beitraege/neu` | validieren, speichern und umleiten | 303 oder 422 |
| GET | `/beitraege/{id}` | Beitrag, Autor und Schlagwörter | 200 oder 404 |
| GET | `/autoren/{id}` | Autor mit seinen Beiträgen | 200 oder 404 |
| GET | `/resonanz` | 1:n-Auswertung Beiträge ↔ Kommentare | 200 |

Eine bekannte Route mit einer nicht unterstützten Methode antwortet mit HTTP 405 und einem passenden `Allow`-Header.

## Serverseitige Validierung

Beim Anlegen eines Beitrags prüft PHP:

- Autor ist ausgewählt und existiert.
- Titel ist 5 bis 150 Zeichen lang.
- Inhalt ist 20 bis 5000 Zeichen lang.
- mindestens ein Schlagwort ist ausgewählt.
- alle Schlagwort-IDs existieren.
- erwartete Texte und IDs wurden nicht als ungeeignete Arrays übertragen.

HTML-Attribute wie `required`, `minlength` und `maxlength` helfen im Browser. Entscheidend bleibt die erneute Prüfung auf dem Server, weil eine HTTP-Anfrage ohne das Formular gesendet werden kann.

## POST → Redirect → GET

Nach einem gültigen POST werden Beitrag und Schlagwort-Paare gespeichert. `lastInsertId()` liefert die neue Beitrags-ID. Der Server antwortet dann mit HTTP 303 und `Location: /beitraege/{id}`.

Der Browser ruft anschließend die Detailseite per GET ab. Ein Refresh wiederholt deshalb das GET und legt keinen zweiten Beitrag an.

## Sicherheit

- Jede SQL-Anweisung wird mit `prepare()` und `execute()` ausgeführt.
- Variable Werte werden nie an SQL-Strings geklebt.
- Die Suche bindet den vollständigen LIKE-Wert als Parameter.
- IDs werden typisiert, auf Existenz geprüft und gebunden.
- API-Import und Formularspeicherung nutzen Transaktionen.
- Foreign Keys und UNIQUE-Regeln schützen die Datenbank zusätzlich.
- API-, Datenbank- und Benutzerdaten werden vor HTML-Ausgabe mit `e()` escaped.
- XSS-artige Eingaben werden als Text angezeigt, nicht ausgeführt.
- SQL-artige Eingaben bleiben gewöhnliche Datenwerte.
- Technische Exception-Details werden protokolliert und nicht im Browser ausgegeben.
- `.env`, SQLite-Datei, IDE-Dateien und Logs sind von Git ausgeschlossen.

## Projektstruktur

```text
php-pruefstand/
├── data/                   # lokale SQLite-Datei (von Git ignoriert)
├── docs/                   # Lern- und Aufgabendokumentation
├── public/
│   ├── index.php           # Front Controller und Routentabelle
│   └── styles.css          # eigene Farbwelt und responsives Layout
├── scripts/
│   └── setup.php           # Schema, API-Import und Beispieldaten
├── src/
│   ├── api.php             # robuster API-Abruf
│   ├── database.php        # PDO-Verbindung und Schema
│   ├── helpers.php         # Escaping, Layout und kleine Hilfsfunktionen
│   ├── import.php          # API-Import und Schlagwort-Beziehungen
│   ├── router.php          # Methoden- und Pfadabgleich
│   └── routes.php          # Abfragen, Validierung und HTML-Ausgabe
├── .gitignore
├── README.md
└── WARMUP.md
```

## Prüfungen

Im Projektverlauf wurden unter anderem getestet:

- PHP-Syntax aller Dateien
- wiederholbarer Import ohne Duplikate
- 200-, 303-, 404-, 405- und 422-Antworten
- ungültige und fehlende IDs
- leeres Formular, Pflichtfelder, Mindest- und Maximallängen
- Array-Werte statt erwarteter Texte
- Foreign-Key- und UNIQUE-Verletzungen
- XSS-artige Eingaben
- SQL-artige Eingaben
- Redirect auf die dynamisch erzeugte ID
- Refresh nach Redirect ohne zweiten INSERT

## Screenshot

**Platzhalter:** Ein echter Browser-Screenshot wird später unter `docs/screenshot.png` ergänzt. Während der automatischen Prüfung stand kein Browser-Backend zur Verfügung; deshalb enthält die README bewusst kein erfundenes Bild.

Zum manuellen Ergänzen die Anwendung starten, im Browser öffnen, einen Screenshot als `docs/screenshot.png` speichern und anschließend diese Zeile hier einsetzen:

```markdown
![Beitragsliste im Redaktionsprüfstand](docs/screenshot.png)
```
