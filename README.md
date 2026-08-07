# Redaktionsprüfstand

Der Redaktionsprüfstand ist ein kleines PHP-Lernprojekt ohne Framework. Die Anwendung importiert Autoren und Beiträge aus einer externen API in eine lokale SQLite-Datenbank und stellt sie über einen eigenen Router als Übersicht, Suche und Detailseiten dar.

## Technologien

- PHP 8.5
- PDO mit SQLite
- eigener Router und Front Controller
- HTML5
- eigenes CSS ohne Framework
- Prepared Statements
- JSONPlaceholder als externe Datenquelle

## Voraussetzungen

- PHP 8.5.x
- aktivierte PDO-Treiber `sqlite` und `mysql` oder mindestens `sqlite`
- Internetzugriff für den einmaligen Datenimport

Die verfügbaren PDO-Treiber lassen sich so prüfen:

```powershell
php -r "echo implode(', ', PDO::getAvailableDrivers()), PHP_EOL;"
```

## Installation und Start

```powershell
git clone https://github.com/Skulli485/php-pruefstand.git
cd php-pruefstand
php scripts/setup.php
php -S localhost:8000 -t public public/index.php
```

Danach ist die Anwendung unter <http://localhost:8000> erreichbar. Der Setup-Befehl kann wiederholt ausgeführt werden: Die API-Datensätze werden anhand ihrer externen ID aktualisiert und nicht doppelt angelegt.

## Externe Datenquelle

Die Ausgangsdaten kommen von [JSONPlaceholder](https://jsonplaceholder.typicode.com/):

- `/users` liefert die Autoren.
- `/posts` liefert die Beiträge.

Beim Import werden nur die im Projekt benötigten Felder gespeichert. HTTP-Fehler und ungültiges JSON führen zu einer verständlichen Fehlermeldung im Terminal.

## Datenbank

Die lokale Datenbank liegt nach dem Setup unter `data/pruefstand.sqlite`. Sie wird nicht in Git eingecheckt.

### Tabelle `authors`

- `id`: lokaler Primärschlüssel
- `external_id`: eindeutige ID aus JSONPlaceholder
- `name`, `email`, `city`, `company`: Autorendaten

### Tabelle `posts`

- `id`: lokaler Primärschlüssel
- `external_id`: eindeutige ID aus JSONPlaceholder
- `author_id`: Fremdschlüssel auf `authors.id`
- `title`, `body`: Beitragsinhalt
- `created_at`: lokaler Erstellungszeitpunkt

Zwischen `authors` und `posts` besteht eine 1:n-Beziehung: Ein Autor kann viele Beiträge schreiben, jeder Beitrag verweist aber genau auf einen Autor. Der Fremdschlüssel liegt deshalb in `posts` auf der n-Seite.

## Wichtige Routen

| Methode | Route | Aufgabe |
| --- | --- | --- |
| GET | `/` | Übersicht und Datenbestand |
| GET | `/beitraege` | Beitragsliste und Suche über `?q=` |
| GET | `/beitraege/{id}` | Detailseite eines Beitrags |
| GET | `/autoren/{id}` | Autor mit seinen Beiträgen |

Unbekannte Seiten antworten mit HTTP 404. Bekannte Routen mit einer nicht erlaubten HTTP-Methode antworten mit HTTP 405.

## Projektstruktur

```text
php-pruefstand/
├── data/               # lokale SQLite-Datei (von Git ignoriert)
├── public/
│   ├── index.php       # Front Controller und Routentabelle
│   └── styles.css      # eigene Farbwelt und responsives Layout
├── scripts/
│   └── setup.php       # Schema anlegen und API-Daten importieren
├── src/
│   ├── api.php         # robuster API-Abruf
│   ├── database.php    # PDO-Verbindung und Basisschema
│   ├── helpers.php     # Escaping, Layout und kleine Hilfsfunktionen
│   ├── import.php      # vorbereitete INSERT-/UPDATE-Anweisungen
│   ├── router.php      # Methoden- und Pfadabgleich
│   └── routes.php      # Abfragen und HTML-Ausgabe der Routen
├── .gitignore
├── README.md
└── WARMUP.md
```

## Sicherheit

- Alle SQL-Anweisungen werden vorbereitet und Werte getrennt gebunden.
- Externe Werte und Suchtexte werden vor der HTML-Ausgabe mit `e()` escaped.
- IDs werden validiert, bevor sie an SQL-Abfragen übergeben werden.
- PDO wirft Exceptions; technische Details landen im Serverprotokoll statt im Browser.
- SQLite prüft Fremdschlüssel durch `PRAGMA foreign_keys = ON` bei jeder Verbindung.

## Screenshot

**Platzhalter:** Ein echter Browser-Screenshot wird später unter `docs/screenshot.png` ergänzt. Während der automatischen Prüfung stand kein Browser-Backend zur Verfügung; deshalb enthält die README bewusst kein erfundenes Bild.

Zum manuellen Ergänzen die Anwendung starten, im Browser öffnen, einen Screenshot als `docs/screenshot.png` speichern und anschließend diese Zeile hier einsetzen:

```markdown
![Beitragsliste im Redaktionsprüfstand](docs/screenshot.png)
```
