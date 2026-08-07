# Gold 7.2 – n:m-Beziehung anzeigen

Die Detailroute `/beitraege/{id}` zeigt einen Beitrag zusammen mit allen verknüpften Schlagwörtern.

## Beteiligte Tabellen

- `posts`: Beiträge
- `tags`: Schlagwörter
- `post_tag`: Zwischentabelle mit `post_id` und `tag_id`

Die beiden Foreign Keys zeigen auf `posts.id` und `tags.id`. Der zusammengesetzte Primärschlüssel verhindert doppelte Paare.

## JOIN

```sql
SELECT t.id, t.name, t.slug
FROM posts p
JOIN post_tag pt ON pt.post_id = p.id
JOIN tags t ON t.id = pt.tag_id
WHERE p.id = :post_id
ORDER BY t.name
```

Die Abfrage startet beim gesuchten Beitrag. Der erste JOIN findet alle Paare der Zwischentabelle für diesen Beitrag. Der zweite JOIN ersetzt jede gefundene `tag_id` durch den vollständigen Schlagwort-Datensatz.

Ohne `post_tag` müsste entweder ein Beitrag mehrere Schlagwort-IDs in einer Textspalte speichern oder jedes Schlagwort mehrfach kopiert werden. Beides wäre schwer zuverlässig abzufragen und würde die Datenbankregeln umgehen.

`:post_id` wird als vorbereiteter Parameter gebunden. Ein Beitrag ohne Verknüpfung liefert ein leeres Array und die Seite zeigt einen verständlichen Leerzustand.
