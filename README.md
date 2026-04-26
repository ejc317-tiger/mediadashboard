# Media Pricing Snapshot (Simple PHP + SQLite)

A minimal PHP dashboard that shows a latest pricing snapshot for media services
across major geographies.

## What changed

- Simplified loading path: one DB connection helper and one page.
- Auto-creates SQLite schema and starter rows directly in PHP on first run.
- Clear on-page error message if SQLite support is missing.

## Run

```bash
php -S localhost:8000
```

Open <http://localhost:8000/index.php>.

## Files

- `index.php`: page rendering and request handling
- `lib/db.php`: SQLite setup, seed, and query functions
- `styles.css`: basic styling
