# Gold 7.1 – n:m-Datenbankbeziehung

## Warum Serien ↔ Genres n:m ist

Eine Serie kann zugleich Drama, Thriller und Science-Fiction sein. Umgekehrt gehört Drama zu vielen Serien. Beide Seiten besitzen mehrere mögliche Gegenstücke: Die Beziehung ist n:m.

```text
shows
  id (Primary Key)
   │
   └── show_genre
         show_id  (Foreign Key → shows.id)
         genre_id (Foreign Key → genres.id)
         Primary Key (show_id, genre_id)
                    │
                    └── genres
                          id (Primary Key)
                          name (UNIQUE)
                          slug (UNIQUE)
```

`show_genre` enthält nur Beziehungspaare. Der zusammengesetzte Primärschlüssel verhindert doppelte Zuordnungen. `PRAGMA foreign_keys = ON` lehnt Verweise auf nicht vorhandene Serien oder Genres ab.

Die Genres stammen direkt aus den TVmaze-Seriendaten. Beim wiederholten Import werden die Beziehungen einer externen Serie kontrolliert neu aufgebaut, ohne doppelte Paare zu erzeugen.
