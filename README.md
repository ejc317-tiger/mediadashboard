# Northstar media dashboard

## Local setup

1. The application uses the bundled MySQL database name, username, and password. Set `DB_HOST` and `DB_PORT` only when MySQL is not running at `127.0.0.1:3306`.
2. Start PHP: `php -S 127.0.0.1:4173`.
3. Open `http://127.0.0.1:4173`.

On the first request, PHP connects to the existing `rive4320_ibd` database, automatically imports `schema.sql` and creates the empty tables. The schema file remains deployed for recovery and each revision is applied exactly once using its SHA-256 hash in `schema_migrations`. A filesystem lock prevents concurrent requests from running a migration twice. The MySQL user needs table-creation permission inside that database, but does not need server-wide database-creation permission.

If the server returns HTTP 403, confirm the upload directory is web-accessible and that Apache honors the included `.htaccess`. The dashboard must be opened through `index.php`, not by browsing `schema.sql` or another protected support file.

All dashboard records are loaded through `api.php`; the browser bundle contains no embedded company dataset. The API uses PDO prepared statements for filters and a strict allowlist for table, column, and sort identifiers.

Companies live in one canonical `companies` table so every relationship points to the same record. `company_pe_ownership` links PE firms to owned companies and `company_vc_investments` links VC firms to financing rounds. **All companies** searches both kinds of fund investor; the PE and VC modules show cross-linked portfolios; **AI companies** is a live filtered view with round date, size, valuation, and investors. The **SPACs** module reads the `spac_vehicles` table and shows each vehicle's sponsor, IPO date, original size, completion deadline, and dynamically calculated time remaining.

## Authentication and AI updates

The first visit to `login.php` creates the initial administrator with a securely hashed password. Subsequent visitors must authenticate. Set `OPENAI_API_KEY` on the PHP server to enable **Research and update**; optionally set `OPENAI_MODEL`. The AI endpoint only accepts sourced records with valid public URLs, writes through allowlisted table/column mappings in a transaction, and marks every result `Review` for analyst verification.

No company, PE firm, VC firm, investor, data-center, capacity, or activity record is embedded in the browser or seeded by the installer.

## Research data sources

AI updates always use web search and prioritize regulatory filings plus company and investor disclosures. Licensed databases are included when their server credentials are configured: `PITCHBOOK_API_URL` / `PITCHBOOK_API_KEY`, `CRUNCHBASE_API_URL` / `CRUNCHBASE_API_KEY`, and `FINANCE_DATA_API_URL` / `FINANCE_DATA_API_KEY` for another licensed finance-data gateway. Connector URLs are configurable because products, entitlements, and endpoints vary by provider contract. The application does not scrape paywalled services or bypass provider licensing. Each run records which configured providers returned data.

## Database connection checks

The connection defaults to `localhost` (the usual shared-hosting MySQL socket) and safely falls back to `127.0.0.1`; set `DB_HOST` and `DB_PORT` only if the hosting provider supplies different values. The login screen now displays an actionable database/schema error instead of a blank server error. `schema.sql` must remain beside `db.php`; it is protected from HTTP downloads by `.htaccess` and tracked through `schema_migrations` rather than deleted.
