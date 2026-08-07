# Silber 6.1 – Zweite Datenquelle

## Auswahl

Die Serien werden über den TVmaze-Endpoint `/shows?page=0` geladen. Als zweite Datenquelle dient für jede ausgewählte Serie `/shows/{id}/episodes`. Die Sprache der importierten Serien wird auf Englisch oder Deutsch begrenzt.

Gespeichert werden pro Episode:

- `id` als externe ID
- die lokale Serien-ID als `show_id`
- Titel, Staffel und Episodennummer
- Ausstrahlungsdatum und Laufzeit
- eine von HTML befreite Beschreibung

## Tabelle `episodes`

| Spalte | Bedeutung |
| --- | --- |
| `id` | lokaler `INTEGER PRIMARY KEY` |
| `external_id` | API-ID, `NOT NULL` und `UNIQUE` |
| `show_id` | Foreign Key auf `shows.id` |
| `name` | Episodentitel, `NOT NULL` |
| `season`, `number` | Position innerhalb der Serie |
| `airdate`, `runtime` | optionale Metadaten |
| `summary` | bereinigte Beschreibung |

Die externe Serien-ID wird beim Import auf die lokale `shows.id` abgebildet. Unvollständige Zeilen werden übersprungen. HTTP-Fehler und ungültiges JSON führen zu einer kontrollierten Exception. Alle Datenbankwerte werden an Prepared Statements gebunden.
