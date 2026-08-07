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
