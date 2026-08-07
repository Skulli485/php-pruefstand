# Abschlussbericht – Serienprüfstand

## 1. Repository

<https://github.com/Skulli485/php-pruefstand>

## 2. Lokaler Projektpfad

```text
C:\Users\Koch\Documents\Morphos\Module_4\Woche_1\php-pruefstand
```

## 3. Startbefehl

```powershell
cd C:\Users\Koch\Documents\Morphos\Module_4\Woche_1\php-pruefstand
php scripts/setup.php
php -S localhost:8000 -t public public/index.php
```

Danach ist die Anwendung unter <http://localhost:8000> erreichbar.

## 4. Thema und API-Entscheidung

Der ursprüngliche Stand verwendete Blindtexte von JSONPlaceholder. Diese Texte waren inhaltlich schwer verständlich und passten nicht gut zu einer vorzeigbaren Lernanwendung. Deshalb wurde der Prüfstand auf Serien umgestellt.

Die Daten stammen jetzt aus der öffentlichen [TVmaze-API](https://www.tvmaze.com/api). Importiert werden ausschließlich Serien, deren Sprache von TVmaze als Englisch oder Deutsch angegeben ist. Beschreibungen werden von HTML befreit und als Klartext gespeichert.

## 5. Routen

| Methode | Route | Funktion |
| --- | --- | --- |
| GET | `/` | Übersicht und Datenbestand |
| GET | `/serien` | Serienliste und Suche mit `?q=` |
| GET | `/serien/{id}` | Serie mit Genres und Episoden |
| GET | `/episoden` | 1:n-Auswertung Serien ↔ Episoden |
| GET | `/serien/neu` | Eingabeformular |
| POST | `/serien/neu` | validieren, speichern und redirecten |

## 6. Datenbanktabellen

Die aktive SQLite-Datenbank `data/serienpruefstand.sqlite` enthält vier Tabellen:

- `shows`
- `episodes`
- `genres`
- `show_genre`

Externe IDs werden getrennt von lokalen Primärschlüsseln gespeichert. Dadurch können lokal angelegte Serien eine normale lokale ID erhalten, während ihre `external_id` leer bleibt.

## 7. 1:n-Beziehung

```text
shows.id ── 1:n ── episodes.show_id
```

Eine Serie kann viele Episoden besitzen. Jede Episode gehört genau zu einer Serie. Der Fremdschlüssel `show_id` liegt deshalb in `episodes`, also auf der n-Seite.

Die Route `/episoden` verwendet einen `LEFT JOIN`. Dadurch erscheinen auch lokal angelegte Serien, die noch keine Episode besitzen.

## 8. n:m-Beziehung

```text
shows.id
   │
   └── show_genre (show_id, genre_id)
                           │
                           └── genres.id
```

Eine Serie kann mehrere Genres besitzen. Dasselbe Genre kann vielen Serien zugeordnet sein. Deshalb ist die Beziehung n:m und benötigt die Zwischentabelle `show_genre`.

## 9. Zwischentabelle

`show_genre` enthält:

- `show_id` als Foreign Key auf `shows.id`
- `genre_id` als Foreign Key auf `genres.id`
- den zusammengesetzten Primärschlüssel `(show_id, genre_id)`

Der zusammengesetzte Primärschlüssel verhindert doppelte Genre-Zuordnungen.

## 10. Verwendete API-Endpunkte

| Endpoint | Verwendung |
| --- | --- |
| `/shows?page=0` | Serien, Poster, Sprache, Status, Beschreibung und Genres |
| `/shows/{id}/episodes` | Episoden der zuvor importierten Serien |

Der Abruf erfolgt mit PHP-cURL, einem eindeutigen User-Agent und kontrollierter Behandlung von HTTP- und JSON-Fehlern. TVmaze wird im Footer und über Links auf den Detailseiten als Quelle genannt.

## 11. Bronze

Bronze enthält:

- eine vollständige README
- einen eigenen Front Controller
- einen eigenen Router mit HTTP-Methoden und `{id}`-Segmenten
- HTML-Escaping über `e()`
- einen vollständigen Prepared-Statement-Audit
- verständliche 404-, 405-, 422- und 500-Zustände

## 12. Silber

Silber ergänzt:

- den zweiten Endpoint `/shows/{id}/episodes`
- die Tabelle `episodes`
- die echte 1:n-Beziehung zwischen Serien und Episoden
- die Route `/episoden`
- eine JOIN-Auswertung mit Episoden- und Staffelanzahl je Serie

## 13. Gold

Gold ergänzt:

- die Tabelle `genres`
- die Zwischentabelle `show_genre`
- zwei Foreign Keys
- einen zusammengesetzten Primärschlüssel
- die sichtbare n:m-Beziehung auf `/serien/{id}`

## 14. Diamant

Diamant ergänzt:

- das Formular unter `/serien/neu`
- GET und POST auf derselben Route
- serverseitige Validierung
- verständliche Fehlermeldungen und erhaltene Eingaben
- eine Transaktion für Serie und Genre-Beziehungen
- `lastInsertId()` für die neue Serien-ID
- einen HTTP-303-Redirect auf die neue Detailseite

## 15. Serverseitige Validierung

Der Server prüft:

- Titel als Pflichtfeld mit 2 bis 150 Zeichen
- Sprache gegen eine feste Liste erlaubter Werte
- Status gegen eine feste Liste erlaubter Werte
- optionales Premierendatum auf ein echtes Datum im Format `YYYY-MM-DD`
- Beschreibung als Pflichtfeld mit 20 bis 5000 Zeichen
- mindestens ein ausgewähltes Genre
- Existenz aller Genre-IDs
- falsche Datentypen wie Arrays statt erwarteter Texte

Fehlerhafte Eingaben führen zu HTTP 422. Das Formular wird erneut angezeigt, gültige Eingaben bleiben erhalten und alle dynamischen Werte werden escaped.

## 16. POST → Redirect → GET

```text
POST /serien/neu
→ Eingaben validieren
→ INSERT in shows
→ INSERTs in show_genre
→ lastInsertId()
→ HTTP 303 mit Location: /serien/{id}
→ GET /serien/{id}
```

Ein Refresh wiederholt nur die GET-Anfrage. Der Datensatz wird dadurch nicht doppelt gespeichert.

## 17. Sicherheitsmaßnahmen

- Alle SQL-Anweisungen verwenden `prepare()` und `execute()`.
- Variable Werte werden nicht an SQL-Strings geklebt.
- Routen-IDs werden als positive Integer validiert und danach als Parameter gebunden.
- API-Import und Formularspeicherung laufen in Transaktionen.
- `PRAGMA foreign_keys = ON` aktiviert die Foreign-Key-Prüfung.
- UNIQUE-Regeln verhindern doppelte externe Datensätze.
- Der zusammengesetzte Primärschlüssel verhindert doppelte n:m-Paare.
- API-Beschreibungen werden vor dem Speichern von HTML befreit.
- API-, Datenbank- und Benutzerdaten werden mit `e()` escaped.
- XSS-artige Eingaben werden als Text angezeigt.
- SQL-artige Eingaben bleiben gewöhnliche Datenwerte.
- Technische Exception-Details werden nicht im Browser ausgegeben.
- `.env`, SQLite-Dateien, Logs und IDE-Dateien werden nicht committed.

## 18. Durchgeführte Tests

Erfolgreich getestet wurden:

- PHP-Syntax aller acht PHP-Dateien
- TVmaze-Live-Import von 12 Serien und 1.081 Episoden
- wiederholbarer Setup-Lauf ohne Duplikate oder Deprecation-Warnings
- 36 importierte Serien-Genre-Beziehungen
- `PRAGMA foreign_key_check` ohne Fehler
- UNIQUE-Regel für externe Serien-IDs
- Foreign Key `episodes.show_id`
- zusammengesetzter Primärschlüssel in `show_genre`
- HTTP 200 für `/`, `/serien`, `/episoden`, `/serien/1` und `/serien/neu`
- HTTP 404 für eine ungültige Serien-ID
- HTTP 405 mit `Allow: GET` bei einer falschen Methode
- HTTP 422 für leere, falsche und manipulierte Formulardaten
- Escaping von `<script>alert(1)</script>`
- SQL-artige Eingabe `' OR '1'='1`
- Array statt erwartetem Titel ohne PHP-Warning
- gültiger POST mit HTTP 303 auf die dynamische ID `/serien/13`
- Detailseite der lokal angelegten deutschen Beispielserie „Nordlicht“
- Refresh der Detailseite ohne zweiten INSERT
- Serverprotokoll ohne Warnings, Fatals oder ungefangene Exceptions

Nach dem Formular-Test enthielt die lokale Testdatenbank 13 Serien, 1.081 Episoden, 13 Genres und 37 Genre-Beziehungen. Ein frischer Setup-Lauf beginnt mit zwölf API-Serien; lokale Einträge werden nicht durch das Setup gelöscht.

## 19. Bekannte offene Punkte

- Ein echter Screenshot fehlt, weil die installierte In-App-Browser-Verbindung in der Testumgebung mit „No browser is available“ antwortete.
- Die TVmaze-Beschreibungen sind überwiegend Englisch. Sie sind verständlicher als die vorherigen Blindtexte, werden aber nicht automatisch ins Deutsche übersetzt.
- `WARMUP.md` bleibt absichtlich unbeantwortet, damit die Lernfragen selbst bearbeitet werden können.

## 20. Git-Status nach Abschluss und Push

```text
On branch main
Your branch is up to date with 'origin/main'.

nothing to commit, working tree clean
```

## 21. Commit-Verlauf vor dem Berichtscommit

```text
78402df Update documentation for series project
43a14c8 Adapt routes and interface for series
d555aa1 Switch data import to TVmaze
e91f2a9 Update project documentation
c222324 Add POST processing and validation
296ebdd Add data entry form
0f6de82 Display many-to-many relationship
2e9bec6 Add many-to-many database relationship
98bb352 Add one-to-many analysis
f251118 Add second external data source
0afd112 Verify prepared statements
aa909f9 Add project README
888f2a7 Initial project setup
```

Der Commit dieses Abschlussberichts kommt anschließend als eigener Dokumentationsschritt hinzu.

## Was sollte ich meinem Dozenten erklären können?

### Request → Router → Response

Der Browser sendet Methode und URL an `public/index.php`. Der Front Controller liest Pfad und HTTP-Methode. Der Router vergleicht beides mit der Routentabelle, erkennt dynamische IDs und ruft die passende Funktion auf. Diese Funktion lädt oder speichert Daten und erzeugt die HTTP-Antwort.

### Prepared Statements

SQL-Struktur und Werte werden getrennt an SQLite übergeben. Ein Eingabewert kann dadurch den SQL-Satz nicht verändern.

### Escaping

`e()` wandelt HTML-Sonderzeichen vor der Ausgabe um. Ein Text wie `<script>` wird angezeigt und nicht als JavaScript ausgeführt.

### Beziehungen

Bei 1:n liegt der Fremdschlüssel auf der Seite mit den vielen Datensätzen. Bei n:m wird eine Zwischentabelle benötigt, weil beide Seiten mehrere Gegenstücke besitzen können.
