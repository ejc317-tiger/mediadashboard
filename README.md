# Northstar media dashboard

## Local setup

1. Create MySQL tables and seed the company records: `mysql -u root -p < schema.sql`.
2. Copy `config.php.example` to `config.php` and set the MySQL credentials, or set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
3. Start PHP: `php -S 127.0.0.1:4173`.
4. Open `http://127.0.0.1:4173`.

All dashboard records are loaded through `api.php`; the browser bundle contains no embedded company dataset. The API uses PDO prepared statements for filters and a strict allowlist for table, column, and sort identifiers.
