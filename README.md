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
