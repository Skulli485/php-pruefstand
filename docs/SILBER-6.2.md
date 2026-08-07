# Silber 6.2 – 1:n-Auswertung

## Beziehung

`shows` ist die 1-Seite. `episodes` ist die n-Seite, weil zu einer Serie viele Episoden gehören können. Der Fremdschlüssel liegt deshalb in `episodes`.

```text
shows
  id (Primary Key)
   │
   └── 1:n ── episodes
                show_id (Foreign Key → shows.id)
```

## Route `/episoden`

```sql
SELECT s.id, s.name, s.status,
    COUNT(e.id) AS episode_count,
    COUNT(DISTINCT e.season) AS season_count,
    MIN(e.airdate) AS first_airdate,
    MAX(e.airdate) AS last_airdate
FROM shows s
LEFT JOIN episodes e ON e.show_id = s.id
GROUP BY s.id, s.name, s.status
ORDER BY episode_count DESC, s.name ASC
```

Der `LEFT JOIN` zeigt auch lokal angelegte Serien ohne Episode. `GROUP BY` bildet pro Serie eine Gruppe; `COUNT(e.id)` zählt deren Episoden und ergibt ohne Treffer 0.
