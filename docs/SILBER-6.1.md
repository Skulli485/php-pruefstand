# Silber 6.1 – Zweite Datenquelle

## Auswahl

Als zweite Quelle dient der JSONPlaceholder-Endpoint `/comments`. Autoren und Beiträge stammen bereits aus `/users` und `/posts`. Kommentare ergänzen das Redaktionsthema sinnvoll, weil sie Rückmeldungen zu genau diesen Beiträgen darstellen.

Gespeichert werden nur die benötigten Felder:

- `id` als externe ID zum Erkennen bereits importierter Kommentare
- `postId` zur Zuordnung zum Beitrag
- `name` als Betreff des Kommentars
- `email` als Absenderadresse
- `body` als Kommentartext

## Tabelle `comments`

| Spalte | Bedeutung |
| --- | --- |
| `id` | lokaler `INTEGER PRIMARY KEY` |
| `external_id` | API-ID, `NOT NULL` und `UNIQUE` |
| `post_id` | Fremdschlüssel mit `REFERENCES posts(id)` |
| `name` | Betreff, `NOT NULL` |
| `email` | Absenderadresse, `NOT NULL` |
| `body` | Kommentartext, `NOT NULL` |

Der lokale Primärschlüssel gehört der eigenen Datenbank. `external_id` bleibt separat, damit API-ID und lokale Datenbank-ID nicht miteinander verwechselt werden. Die UNIQUE-Regel sorgt dafür, dass derselbe API-Kommentar nicht zweimal gespeichert wird. Beim wiederholten Import aktualisiert `ON CONFLICT(external_id)` den bestehenden Datensatz.

`post_id` liegt in `comments`, weil jeder Kommentar genau zu einem Beitrag gehört. Der Wert verweist auf die lokale Beitrags-ID und nicht direkt auf die externe API-ID.

## Fehlerbehandlung

- HTTP-Statuscodes außerhalb von 200–299 führen zu einer verständlichen `RuntimeException`.
- Ungültiges JSON wird abgelehnt.
- Unvollständige Kommentarzeilen oder Kommentare ohne bekannten Beitrag werden übersprungen.
- Der Import läuft in einer Transaktion und wird bei einem Fehler vollständig zurückgerollt.
- Alle INSERT- und SELECT-Anweisungen werden vorbereitet; API-Werte werden als Parameter gebunden.
