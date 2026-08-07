# Diamant 8.1 – Eingabeformular

Die GET-Route `/serien/neu` zeigt ein Formular für eine neue lokale Serie.

## Felder

- `name`: Titel mit 2 bis 150 Zeichen
- `language`: erlaubte Sprache auswählen
- `status`: erlaubten Serienstatus auswählen
- `premiered`: optionales Datum
- `summary`: Beschreibung mit 20 bis 5000 Zeichen
- `genre_ids[]`: mindestens ein vorhandenes Genre

Die Felder passen direkt zu `shows`, `genres` und `show_genre`. Genres werden per vorbereitetem SELECT geladen. Dynamische Werte werden escaped oder als geprüfte Integer ausgegeben.

Die feste Route steht vor `/serien/{id}`, damit `neu` nicht als dynamische ID behandelt wird. HTML-Regeln unterstützen die Eingabe; die verbindliche Prüfung erfolgt erneut auf dem Server.
