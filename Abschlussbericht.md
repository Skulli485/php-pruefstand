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

Die Daten stammen jetzt aus der öffentlichen [TVmaze-API](https://www.tvmaze.com/api). Das automatische Setup wählt englisch- oder deutschsprachige Serien aus. Die normale Suche prüft zuerst den lokalen Katalog und zeigt bei fehlendem exaktem Titel zusätzlich passende TVmaze-Treffer an. Von dort kann jede gewünschte Serie bewusst importiert werden. Beschreibungen werden von HTML befreit und als Klartext gespeichert.

## 5. Routen

| Methode | Route | Funktion |
| --- | --- | --- |
| GET | `/` | Übersicht und Datenbestand |
| GET | `/serien` | lokale Suche mit Online-Fallback über `?q=` |
| GET | `/serien/{id}` | Serie mit Genres und Episoden |
| GET | `/episoden` | 1:n-Auswertung Serien ↔ Episoden |
| GET | `/serien/neu` | TVmaze-Suche und manuelles Eingabeformular |
| POST | `/serien/neu` | validieren, speichern und redirecten |
| POST | `/serien/importieren` | gewählten TVmaze-Treffer samt Episoden importieren |
| POST | `/serien/{id}/loeschen` | Serie, Episoden und Genre-Zuordnungen löschen |

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
| `/search/shows?q={titel}` | passende Serien suchen und zur Auswahl anzeigen |
| `/shows/{id}` | ausgewählte Serie mit Poster und Genres exakt laden |
| `/shows/{id}/episodes` | Episoden der zuvor importierten Serien |

Der Abruf erfolgt mit PHP-cURL, einem eindeutigen User-Agent und kontrollierter Behandlung von HTTP- und JSON-Fehlern. TVmaze wird im Footer und über Links auf den Detailseiten als Quelle genannt.

Zusätzlich werden `network`, `webChannel` und `officialSite` aus dem Seriendatensatz gespeichert. Network beziehungsweise Web Channel beschreiben den von TVmaze geführten aktuellen/letzten Ausstrahlungskanal, nicht automatisch alle derzeit buchbaren Streamingdienste. Die Detailseite zeigt deshalb einen transparenten Hinweis und verlinkt zur länderabhängigen Anbieterprüfung auf TVmaze.

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

- die Suche nach Serientiteln unter `/serien/neu`
- den Online-Fallback in der normalen Suche `/serien?q=...`
- eine Trefferliste mit Poster, Metadaten und eindeutiger Auswahl
- den automatischen Import von Serie, Genres und Episoden
- einen „Wo ansehen?“-Bereich mit Sender-/Webkanal und geprüften externen Links
- das weiterhin verfügbare manuelle Formular
- einen klar abgegrenzten Löschbereich auf jeder Detailseite
- CSRF-Schutz und Bestätigungsdialog für die Löschaktion
- GET und POST auf derselben Route
- serverseitige Validierung
- verständliche Fehlermeldungen und erhaltene Eingaben
- eine Transaktion für Serie und Genre-Beziehungen
- `lastInsertId()` für die neue Serien-ID
- einen HTTP-303-Redirect auf die neue Detailseite

## 15. Serverseitige Validierung

Der Server prüft:

- API-Suchbegriffe auf 2 bis 100 Zeichen
- die ausgewählte TVmaze-ID als positive Ganzzahl
- das Import-Token gegen die aktive PHP-Sitzung
- die Lösch-ID als positive Ganzzahl
- das Lösch-Token gegen die aktive PHP-Sitzung
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

Der automatische Import folgt ebenfalls POST → Redirect → GET:

```text
GET /serien/neu?api_q=Dark
→ TVmaze-Treffer anzeigen
→ Benutzer wählt die genaue Serie
→ POST /serien/importieren mit der TVmaze-ID
→ PHP lädt /shows/{id} und /shows/{id}/episodes
→ Upserts in shows, genres, show_genre und episodes
→ Sender-/Webkanal und offizielle Serienseite speichern
→ HTTP 303 auf /serien/{lokale-id}?imported=1
```

Ein erneuter Import derselben TVmaze-ID aktualisiert denselben lokalen Datensatz.

Auch das Löschen folgt POST → Redirect → GET:

```text
GET /serien/{id}
→ CSRF-Token und Löschformular anzeigen
→ Benutzer bestätigt die Warnung
→ POST /serien/{id}/loeschen
→ ID und CSRF-Token validieren
→ vorbereitetes DELETE in einer Transaktion
→ Episoden und Genre-Zuordnungen per ON DELETE CASCADE entfernen
→ HTTP 303 auf /serien
→ einmalige Erfolgsmeldung anzeigen
```

## 17. Sicherheitsmaßnahmen

- Alle SQL-Anweisungen verwenden `prepare()` und `execute()`.
- Variable Werte werden nicht an SQL-Strings geklebt.
- Routen-IDs werden als positive Integer validiert und danach als Parameter gebunden.
- API-Import und Formularspeicherung laufen in Transaktionen.
- Der Browser sendet beim API-Import nur die externe ID; PHP lädt die vertrauenswürdigen Felder erneut direkt von TVmaze.
- Der Online-Import verlangt zusätzlich ein gültiges, sitzungsgebundenes CSRF-Token.
- Die destruktive Route akzeptiert nur POST und verlangt ein kryptografisch zufälliges, sitzungsgebundenes CSRF-Token.
- Das Token wird mit `hash_equals()` geprüft und nach erfolgreichem Löschen erneuert.
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
- reproduzierbare Ergänzung von „Nordlicht“ mit lokalem Poster und 4 Episoden
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
- TVmaze-Suche nach „Dark“ mit acht unterscheidbaren Treffern
- Hauptsuche zeigt TVmaze-Treffer, wenn „Dark“ nicht als exakter lokaler Titel vorhanden ist
- exakter lokaler Treffer „Arrow“ überspringt die Online-Suche
- Einzelimport von „Dark“ mit Poster, Genres und 26 Episoden
- Speicherung von Netflix als TVmaze-Webkanal und der offiziellen Netflix-URL
- „Wo ansehen?“-Bereich mit Anbieterhinweis, TVmaze-Link und sicherem `rel="noopener noreferrer"`
- fehlendes Import-CSRF-Token führt zu HTTP 403
- kurzer Suchbegriff und vollständig unbekannter Online-Suchbegriff erhalten verständliche Zustände
- HTTP 303 auf `/serien/{lokale-id}?imported=1`
- zweiter Import von „Dark“ auf dieselbe lokale ID und weiterhin 26 Episoden
- Kennzeichnung bereits importierter Suchtreffer als „Bereits vorhanden“
- HTTP 422 für leere, nullwertige und als Array gesendete TVmaze-IDs
- HTTP 502 mit verständlicher Meldung für eine nicht vorhandene TVmaze-ID
- HTML-Escaping eines `<script>`-Suchbegriffs
- HTTP 405 mit `Allow: POST` bei GET auf `/serien/importieren`
- sichtbarer Gefahrenbereich mit Bestätigungsdialog auf der Detailseite
- HTTP 403 für fehlende, falsche und als Array gesendete CSRF-Tokens
- bestehende Serie bleibt nach einem abgewiesenen Löschversuch erhalten
- HTTP 405 mit `Allow: POST` bei GET auf die Löschroute
- HTTP 303 auf `/serien` nach erfolgreichem Löschen
- einmalige, escaped Erfolgsmeldung nach dem Redirect
- Cascade-Löschung einer temporären Serie, ihrer Episode und ihrer Genre-Zuordnung
- `PRAGMA foreign_key_check` auch nach dem Löschen ohne Fehler
- vollständige Posteranzeige mit `object-fit: contain` in der Serienübersicht
- getrennte, begrenzte Poster- und Textspalten auf der Detailseite
- Refresh der Detailseite ohne zweiten INSERT
- Serverprotokoll ohne Warnings, Fatals oder ungefangene Exceptions

Ein frischer Setup-Lauf enthält 13 Serien, 1.085 Episoden, 13 Genres und 37 Genre-Beziehungen. Darin enthalten sind zwölf TVmaze-Serien und die lokale Beispielserie „Nordlicht“ mit vier Episoden. Über die neue Suche importierte Serien kommen hinzu und bleiben bei weiteren Setup-Läufen erhalten.

## 19. Bekannte offene Punkte

- Ein automatisierter Nachher-Screenshot fehlt, weil die installierte In-App-Browser-Verbindung in der Testumgebung keine verfügbaren Browser meldete. Die vom Benutzer bereitgestellten Vorher-Screenshots dienten als Fehlerreferenz.
- Die TVmaze-Beschreibungen sind überwiegend Englisch. Sie sind verständlicher als die vorherigen Blindtexte, werden aber nicht automatisch ins Deutsche übersetzt.
- Die öffentliche TVmaze-API liefert keine vollständige aktuelle Streaming-Verfügbarkeit pro Land. Angezeigt werden deshalb der TVmaze-Sender/Webkanal und ein Link zur aktuellen, länderabhängigen Anbieterprüfung auf der TVmaze-Seite.
- `WARMUP.md` bleibt absichtlich unbeantwortet, damit die Lernfragen selbst bearbeitet werden können.

## 20. Git-Prüfung vor der Abgabe

Der Arbeitsbaum wird nach jedem logischen Commit mit `git status --short` und vor der Abgabe zusätzlich gegen `origin/main` geprüft. Die lokale SQLite-Datenbank bleibt dabei durch `.gitignore` außerhalb der Versionsverwaltung.

## 21. Commit-Verlauf vor diesem Dokumentationsupdate

```text
0d41b77 Add online search fallback and watch links
d0145b5 Document protected series deletion
249708d Add protected series deletion
4d54e4d Answer PHP warmup questions
3f176a2 Add TVmaze series search and import
b6bb512 Polish poster layouts and Nordlicht demo
387e6d4 Add final series report
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

## Was sollte ich meinem Dozenten erklären können?

### Request → Router → Response

Der Browser sendet Methode und URL an `public/index.php`. Der Front Controller liest Pfad und HTTP-Methode. Der Router vergleicht beides mit der Routentabelle, erkennt dynamische IDs und ruft die passende Funktion auf. Diese Funktion lädt oder speichert Daten und erzeugt die HTTP-Antwort.

### Prepared Statements

SQL-Struktur und Werte werden getrennt an SQLite übergeben. Ein Eingabewert kann dadurch den SQL-Satz nicht verändern.

### Escaping

`e()` wandelt HTML-Sonderzeichen vor der Ausgabe um. Ein Text wie `<script>` wird angezeigt und nicht als JavaScript ausgeführt.

### Beziehungen

Bei 1:n liegt der Fremdschlüssel auf der Seite mit den vielen Datensätzen. Bei n:m wird eine Zwischentabelle benötigt, weil beide Seiten mehrere Gegenstücke besitzen können.
