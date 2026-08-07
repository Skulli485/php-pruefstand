# Gold 7.1 – n:m-Datenbankbeziehung

## Warum Beiträge ↔ Schlagwörter n:m ist

Ein Beitrag kann gleichzeitig zum Beispiel `PHP`, `Routing` und `Sicherheit` behandeln. Umgekehrt gehört das Schlagwort `PHP` zu vielen verschiedenen Beiträgen. Beide Seiten können also mehrere Gegenstücke besitzen: Die Beziehung ist n:m.

Die importierte Beispiel-API liefert keine Schlagwörter. Deshalb ergänzt der Prüfstand vier kleine lokale Redaktions-Schlagwörter und verknüpft sie nachvollziehbar mit fünf Beispielbeiträgen. Die fachliche Beziehung bleibt auch für später lokal angelegte Beiträge dieselbe.

## Tabellen

```text
posts
  id (Primary Key)
   │
   └── post_tag
         post_id (Foreign Key → posts.id)
         tag_id  (Foreign Key → tags.id)
         Primary Key (post_id, tag_id)
                   │
                   └── tags
                         id (Primary Key)
                         name (UNIQUE)
                         slug (UNIQUE)
```

Die Zwischentabelle enthält nur Beziehungspaare. Ihr zusammengesetzter Primärschlüssel verhindert, dass derselbe Beitrag zweimal mit demselben Schlagwort verbunden wird.

`PRAGMA foreign_keys = ON` wird direkt nach jeder PDO-Verbindung ausgeführt. Dadurch lehnt SQLite Beziehungen zu nicht vorhandenen Beiträgen oder Schlagwörtern ab.

## Beispieldaten

Die Schlagwörter sind `PHP`, `Datenbanken`, `Sicherheit` und `Routing`. Die ersten fünf externen Beispielbeiträge erhalten jeweils zwei oder drei Schlagwörter. Alle INSERT-Anweisungen sind vorbereitet; wiederholtes Setup erzeugt keine doppelten Paare.
