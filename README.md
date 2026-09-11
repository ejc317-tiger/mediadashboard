# Northstar media dashboard

## Local setup

1. The application uses the bundled MySQL database name, username, and password. Set `DB_HOST` and `DB_PORT` only when MySQL is not running at `127.0.0.1:3306`.
2. Start PHP: `php -S 127.0.0.1:4173`.
3. Open `http://127.0.0.1:4173`.

On the first request, PHP automatically imports `schema.sql`, creates the database and tables, seeds the initial company records, and deletes `schema.sql` only after the import succeeds. A filesystem lock prevents concurrent first requests from running the installer twice. If installation fails, the schema remains available for the next request after the configuration is corrected.

All dashboard records are loaded through `api.php`; the browser bundle contains no embedded company dataset. The API uses PDO prepared statements for filters and a strict allowlist for table, column, and sort identifiers.
