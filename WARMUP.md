# Warmup – Antworten

## 1. Was macht der Router mit `/produkte/7`?

Unser Projekt verwendet zwar `/serien/7`, der Ablauf für eine Route wie `/produkte/{id}` wäre aber derselbe:

1. Der Browser sendet zum Beispiel `GET /produkte/7`.
2. `public/index.php` liest die URL aus `$_SERVER['REQUEST_URI']` und trennt mit `parse_url()` den Pfad ab.
3. Zusätzlich wird die HTTP-Methode aus `$_SERVER['REQUEST_METHOD']` gelesen.
4. `dispatch()` vergleicht Methode und Pfad mit allen Einträgen der Routentabelle.
5. `route_parameters()` zerlegt `/produkte/{id}` und `/produkte/7` in einzelne Segmente.
6. Das feste Segment `produkte` muss genau übereinstimmen. Für `{id}` wird `7` als Parameter übernommen. In unserem Router müssen IDs nur aus Ziffern bestehen.
7. Der Router ruft den zur Route gehörenden Handler mit `['id' => '7']` auf.
8. Der Handler validiert die ID, lädt den Datensatz mit einem Prepared Statement und erzeugt die HTML-Antwort.

Existiert der Pfad nicht, antwortet der Router mit HTTP 404. Existiert der Pfad, aber nicht für die verwendete Methode, antwortet er mit HTTP 405 und einem `Allow`-Header.

## 2. Warum verhindert ein Prepared Statement SQL-Injection?

Bei einem Prepared Statement werden SQL-Struktur und Werte getrennt an die Datenbank übergeben:

```php
$statement = $pdo->prepare('SELECT * FROM shows WHERE id = :id');
$statement->execute(['id' => $id]);
```

SQLite kennt den SQL-Befehl bereits, bevor der Wert für `:id` eingesetzt wird. Der Wert wird deshalb ausschließlich als Datenwert behandelt. Selbst eine Eingabe wie `' OR 1=1 --` kann den SQL-Befehl nicht verändern.

Bei einem zusammengeklebten String landen Eingabe und SQL dagegen im selben Text:

```php
$sql = "SELECT * FROM shows WHERE name = '$name'";
```

Enthält `$name` Anführungszeichen und zusätzlichen SQL-Code, kann sich dadurch die Bedeutung der Abfrage ändern. Deshalb verwendet unser Projekt für variable Werte immer `prepare()` und `execute()`.

## 3. Wo liegt die n:m-Beziehung?

Die n:m-Beziehung besteht zwischen `shows` und `genres`:

- Eine Serie kann mehrere Genres haben.
- Ein Genre kann zu mehreren Serien gehören.

Eine einzelne Fremdschlüsselspalte reicht dafür nicht aus. Deshalb gibt es die Zwischentabelle `show_genre` mit den Spalten `show_id` und `genre_id`.

```text
shows.id ── show_genre.show_id
genres.id ─ show_genre.genre_id
```

Der zusammengesetzte Primärschlüssel `(show_id, genre_id)` verhindert, dass dasselbe Serien-Genre-Paar doppelt gespeichert wird. Beide Spalten sind außerdem Foreign Keys und schützen damit die Beziehungen zwischen den Datensätzen.

## 4. Was liefert `array_first()` bei einem leeren Array?

Seit PHP 8.5 gibt `array_first([])` den Wert `null` zurück. Bei einem nicht leeren Array liefert die Funktion den ersten Wert – unabhängig davon, welchen Schlüssel dieser Wert besitzt.

```php
$first = array_first([]); // null
```

Das ist sicherer und lesbarer als `$arr[0]`: Ein Array muss keinen Schlüssel `0` besitzen, und bei einem leeren Array würde der direkte Zugriff eine Warnung wegen eines nicht vorhandenen Schlüssels auslösen. Falls `null` auch ein echter erster Wert sein kann, muss man mit `array_key_first()` oder einer zusätzlichen Leerprüfung unterscheiden.

Quelle: [PHP-Handbuch zu `array_first()`](https://www.php.net/manual/de/function.array-first.php)

## 5. Welchen Projektteil würde ich einem Interviewer zuerst zeigen?

Ich würde zuerst `import_selected_show()` in `src/import.php` zeigen. Die Funktion verbindet mehrere wichtige Fähigkeiten in einem nachvollziehbaren Ablauf:

- Abruf einer externen JSON-API
- Prüfung und Bereinigung fremder Daten
- Prepared Statements
- Upserts gegen doppelte Datensätze
- eine Transaktion mit Rollback
- Speicherung einer 1:n-Beziehung zu Episoden
- Speicherung einer n:m-Beziehung zu Genres

Danach würde ich die Detailroute zeigen, weil dort sichtbar wird, wie die gespeicherten Beziehungen mit JOINs wieder ausgelesen und sicher escaped im Browser dargestellt werden. Zusammen zeigen diese beiden Stellen den kompletten Weg von der API über PHP und SQLite bis zur fertigen Benutzeroberfläche.
