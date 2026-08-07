# Gold 7.2 – n:m-Beziehung anzeigen

Die Detailroute `/serien/{id}` zeigt eine Serie zusammen mit allen verknüpften Genres.

```sql
SELECT g.id, g.name, g.slug
FROM show_genre sg
JOIN genres g ON g.id = sg.genre_id
WHERE sg.show_id = :show_id
ORDER BY g.name
```

Die Abfrage findet in `show_genre` alle Paare der gesuchten Serie. Der JOIN ersetzt jede `genre_id` durch den vollständigen Genre-Datensatz. `:show_id` wird nach Integer-Validierung als Prepared-Statement-Parameter gebunden. Ohne Zuordnung zeigt die Seite einen verständlichen Leerzustand.
