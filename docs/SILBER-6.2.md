# Silber 6.2 – 1:n-Auswertung

## Beziehung

`posts` ist die 1-Seite: Ein Beitrag existiert genau einmal. `comments` ist die n-Seite: Zu einem Beitrag können beliebig viele Kommentare gehören.

Der Fremdschlüssel `comments.post_id` liegt in der n-Tabelle. Jede Kommentarzeile kann damit auf genau einen Beitrag zeigen, während dieselbe Beitrags-ID in vielen Kommentarzeilen vorkommen darf.

```text
posts
  id (Primary Key)
   │
   └── 1:n ── comments
                post_id (Foreign Key → posts.id)
```

## Route `/resonanz`

Die Route wertet Beiträge, Autoren und Kommentare in einer Abfrage aus:

```sql
SELECT p.id, p.title, a.name AS author_name,
    COUNT(c.id) AS comment_count
FROM posts p
JOIN authors a ON a.id = p.author_id
LEFT JOIN comments c ON c.post_id = p.id
GROUP BY p.id, p.title, a.name
ORDER BY comment_count DESC, p.id ASC
```

`LEFT JOIN` startet bei `posts` und verbindet alle passenden Kommentare über `c.post_id = p.id`. Ein Beitrag erscheint dadurch auch dann, wenn er noch keinen Kommentar besitzt; die Zählung ergibt für ihn 0. Ein gewöhnlicher `JOIN` würde einen solchen Beitrag vollständig aus der Auswertung entfernen.

`GROUP BY` bildet pro Beitrag eine Gruppe. `COUNT(c.id)` zählt nur vorhandene Kommentar-IDs innerhalb dieser Gruppe.
