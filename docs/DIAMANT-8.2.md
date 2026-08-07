# Diamant 8.2 – POST, Validierung und Redirect

## Request-Ablauf

```text
GET /serien/neu
  → Formular anzeigen

POST /serien/neu
  → Werte lesen und serverseitig validieren
  → bei Fehlern Formular mit HTTP 422 erneut anzeigen
  → bei Erfolg Serie und Genre-Paare speichern
  → lastInsertId() lesen
  → HTTP 303 auf /serien/{neue-id}

GET /serien/{neue-id}
  → neue Detailseite anzeigen
```

## Automatischer API-Import

```text
GET /serien/neu?api_q={titel}
  → TVmaze-Treffer anzeigen

POST /serien/importieren
  → externe ID als positive Ganzzahl validieren
  → Serie und Episoden serverseitig erneut von TVmaze laden
  → Serie, Genres, Beziehungen und Episoden in einer Transaktion upserten
  → HTTP 303 auf /serien/{lokale-id}?imported=1
```

Nur die externe ID kommt aus dem Formular. Poster, Texte, Metadaten, Genres und Episoden werden nicht aus versteckten Feldern übernommen, sondern direkt von den exakten TVmaze-Endpunkten geladen. UNIQUE-Regeln und Upserts verhindern bei einem zweiten Import Duplikate.

## Geschütztes Löschen

```text
GET /serien/{id}
  → sitzungsgebundenes CSRF-Token im Löschformular ausgeben
  → Benutzer bestätigt den Bestätigungsdialog

POST /serien/{id}/loeschen
  → positive ID und CSRF-Token validieren
  → Serie mit vorbereitetem DELETE in einer Transaktion löschen
  → Episoden und Genre-Zuordnungen per ON DELETE CASCADE entfernen
  → Token erneuern und Erfolgsmeldung in der Sitzung speichern
  → HTTP 303 auf /serien
```

Die destruktive Aktion ist nicht über GET erreichbar. Ein fehlendes, falsches oder als Array gesendetes Token führt zu HTTP 403, ohne den Datensatz zu verändern.

## Validierung

- Titel: 2 bis 150 Zeichen
- Sprache und Status: nur vorgegebene Werte
- Premiere: leer oder echtes Datum im Format `YYYY-MM-DD`
- Beschreibung: 20 bis 5000 Zeichen
- Genres: mindestens eine vorhandene ID
- Arrays statt erwarteter Texte: ohne PHP-Warning abweisen

Bei Fehlern bleiben gültige Werte erhalten und werden mit `e()` escaped.

## Speichern und Redirect

Der INSERT in `shows` und alle INSERTs in `show_genre` laufen gemeinsam in einer Transaktion. `lastInsertId()` liefert die neue lokale Serien-ID. Anschließend antwortet der Server mit HTTP 303 und einem `Location`-Header. Ein Refresh wiederholt dadurch nur das GET und legt keinen zweiten Datensatz an.
