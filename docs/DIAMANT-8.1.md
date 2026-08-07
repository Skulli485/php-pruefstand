# Diamant 8.1 – Eingabeformular

Die GET-Route `/serien/neu` zeigt zuerst eine TVmaze-Suche und darunter weiterhin das Formular für eine neue lokale Serie.

## Automatische Suche

- `api_q`: Serientitel mit 2 bis 100 Zeichen
- GET-Anfrage an `/search/shows?q={titel}`
- höchstens acht Treffer mit Poster, Sprache, Status, Premiere, Genres und Kurzbeschreibung
- bereits importierte Treffer führen direkt zur lokalen Detailseite
- ein neuer Treffer kann gezielt über `POST /serien/importieren` übernommen werden

Die Auswahl ist absichtlich ein eigener Schritt, weil TVmaze mehrere gleichnamige oder ähnlich benannte Serien liefern kann.

## Felder

- `name`: Titel mit 2 bis 150 Zeichen
- `language`: erlaubte Sprache auswählen
- `status`: erlaubten Serienstatus auswählen
- `premiered`: optionales Datum
- `summary`: Beschreibung mit 20 bis 5000 Zeichen
- `genre_ids[]`: mindestens ein vorhandenes Genre

Die Felder passen direkt zu `shows`, `genres` und `show_genre`. Genres werden per vorbereitetem SELECT geladen. Dynamische Werte werden escaped oder als geprüfte Integer ausgegeben.

Die dynamische `{id}`-Route akzeptiert nur Ziffern. Dadurch bleiben `/serien/neu` und `/serien/importieren` eindeutig. HTML-Regeln unterstützen die Eingabe; die verbindliche Prüfung erfolgt erneut auf dem Server.
