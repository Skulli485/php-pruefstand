# Diamant 8.2 – POST, Validierung und Redirect

## Request-Ablauf

```text
GET /beitraege/neu
  → Formular anzeigen

POST /beitraege/neu
  → $_POST lesen und trimmen
  → serverseitig validieren
  → bei Fehlern Formular mit HTTP 422 erneut anzeigen
  → bei Erfolg Beitrag und Schlagwörter speichern
  → lastInsertId() lesen
  → HTTP 303 auf /beitraege/{neue-id}

GET /beitraege/{neue-id}
  → neue Detailseite anzeigen
```

## Serverseitige Validierung

- `author_id` muss eine positive ID eines vorhandenen Autors sein.
- `title` ist Pflicht und muss 5 bis 150 Zeichen lang sein.
- `body` ist Pflicht und muss 20 bis 5000 Zeichen lang sein.
- `tag_ids[]` muss mindestens eine vorhandene Schlagwort-ID enthalten.
- Arrays oder andere ungeeignete Werte anstelle erwarteter Texte werden ohne PHP-Warning abgelehnt.

Bei Fehlern bleiben gültige Werte erhalten. Titel, Inhalt und alle anderen externen Werte werden beim erneuten Anzeigen mit `e()` escaped.

## Speichern

Der INSERT in `posts` und alle INSERTs in `post_tag` laufen gemeinsam in einer Transaktion. Schlägt eine Beziehung fehl, wird auch der neue Beitrag zurückgerollt.

Alle Werte werden an Prepared Statements gebunden. `lastInsertId()` liefert die wirklich neu erzeugte lokale Beitrags-ID.

## POST → Redirect → GET

Nach dem Speichern antwortet der Server mit HTTP 303 und einem `Location`-Header. Der Browser lädt danach die Detailseite per GET. Ein Refresh wiederholt deshalb nur das GET und legt keinen zweiten Beitrag an.
