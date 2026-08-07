# Diamant 8.1 – Eingabeformular

Die GET-Route `/beitraege/neu` zeigt ein Formular für einen neuen lokalen Beitrag.

## Felder

- `author_id`: vorhandenen Autor auswählen
- `title`: Pflichtfeld mit 5 bis 150 Zeichen
- `body`: Pflichtfeld mit 20 bis 5000 Zeichen
- `tag_ids[]`: ein oder mehrere vorhandene Schlagwörter auswählen

Die Felder passen direkt zu den Tabellen `posts`, `authors`, `tags` und `post_tag`. Es gibt keine zusätzlichen Eingaben ohne fachlichen Zweck.

Autoren und Schlagwörter werden mit vorbereiteten SELECT-Anweisungen aus SQLite geladen. Jeder Wert aus der Datenbank wird vor der HTML-Ausgabe escaped oder als Integer ausgegeben.

Die HTML-Attribute `required`, `minlength` und `maxlength` helfen beim Ausfüllen. Sie ersetzen nicht die serverseitige Prüfung, die in Diamant 8.2 folgt.

Die Route steht in der Routentabelle vor `/beitraege/{id}`. Dadurch wird das feste Segment `neu` nicht irrtümlich als dynamische Beitrags-ID behandelt.
