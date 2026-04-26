# Media Pricing Snapshot (Simple PHP)

This dashboard is designed to work on shared hosting (cPanel/Plesk/etc.) where
you may not have SSH access.

## How to use without SSH

1. Upload these files to your web root (`public_html` or similar).
2. Open `index.php` in your browser.
3. The app will try SQLite first.
4. If SQLite is unavailable, it automatically falls back to built-in sample data so the page still loads.

## Files

- `index.php`: dashboard page
- `lib/db.php`: data loading (SQLite + automatic fallback)
- `styles.css`: basic styling

## Notes

- No CLI commands are required to view the dashboard.
- If your host supports SQLite, data persists in `data/pricing.sqlite`.

## If you see **403 Forbidden**

1. Make sure files are uploaded inside your actual web root (for example `public_html/`), not above it.
2. Keep `.htaccess`, `index.php`, and `index.html` in that same web root.
3. Confirm folder/file permissions are readable by the web server (`755` folders, `644` files is typical).
4. If your host disables `.htaccess`, ask support to set `DirectoryIndex index.php index.html`.
