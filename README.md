# Northstar media dashboard

## Local setup

1. The application uses the bundled MySQL database name, username, and password. Set `DB_HOST` and `DB_PORT` only when MySQL is not running at `127.0.0.1:3306`.
2. Start PHP: `php -S 127.0.0.1:4173`.
3. Open `http://127.0.0.1:4173`.

On the first request, PHP connects to the existing `rive4320_ibd` database, automatically imports `schema.sql`, creates the tables, seeds the initial company records, and deletes `schema.sql` only after the import succeeds. A filesystem lock prevents concurrent first requests from running the installer twice. If installation fails, the schema remains available for the next request after the configuration is corrected. The MySQL user needs table-creation permission inside that database, but does not need server-wide database-creation permission.

If the server returns HTTP 403, confirm the upload directory is web-accessible and that Apache honors the included `.htaccess`. The dashboard must be opened through `index.php`, not by browsing `schema.sql` or another protected support file.

All dashboard records are loaded through `api.php`; the browser bundle contains no embedded company dataset. The API uses PDO prepared statements for filters and a strict allowlist for table, column, and sort identifiers.

AI company-to-investor relationships are normalized through `ai_company_investors`. The **VC portfolios** module searches `vc_firms` and returns linked AI companies, while company searches also match linked investor names. Deploying an updated `schema.sql` causes the first subsequent request to apply the new idempotent tables and seeds and remove the file again.
