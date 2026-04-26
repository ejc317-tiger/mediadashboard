# Media Pricing Snapshot (Simple PHP)

This dashboard is designed to work on shared hosting (cPanel/Plesk/etc.) where
you may not have SSH access.

## How to use without SSH (including subfolders)

1. Upload these files to your target folder (for example `public_html/tools/media-pricing/`).
2. Open the file directly using its full URL, for example:
   - `https://yourdomain.com/tools/media-pricing/index.php`
3. The app will try SQLite first.
4. If SQLite is unavailable, it automatically falls back to built-in sample data so the page still loads.

## Files

- `index.php`: dashboard page
- `lib/db.php`: data loading (SQLite + automatic fallback)
- `styles.css`: basic styling

## Notes

- No CLI commands are required to view the dashboard.
- If your host supports SQLite, data persists in `data/pricing.sqlite`.

## If you still see **403 Forbidden** in a subfolder

1. Open `.../index.php` directly (not only the folder URL).
2. Verify the subfolder permissions (`755`) and file permissions (`644`).
3. Confirm there is no parent-domain rule blocking that subfolder path.
4. Ask hosting support to allow PHP execution in that subfolder.
