# Global Media Subscription Pricing Dashboard (PHP + SQLite)

This project now uses **PHP with a SQLite database** (instead of JSON files)
for compatibility and easier expansion.

## What this version does

- Stores services, geographies, plans, and prices in relational tables.
- Lets you pick a media service from a dropdown.
- Shows a **latest snapshot** of pricing for that service in each major geography.
- Displays detailed plan variants (tier + billing period + local price + USD/month equivalent + effective date).
- Includes summary stats to make cross-market differences obvious.

## Project structure

- `index.php` - dashboard page
- `lib/db.php` - database connection and query helpers
- `sql/schema.sql` - schema definition
- `sql/seed.sql` - starter data
- `data/pricing.sqlite` - auto-created on first run
- `styles.css` - UI styles

## Run locally

```bash
php -S localhost:8000
```

Then open <http://localhost:8000/index.php>.

> On first request, the app creates `data/pricing.sqlite` and loads
> `sql/schema.sql` + `sql/seed.sql` automatically.

## Data model overview

- `services`: list of media platforms
- `geographies`: country + major region
- `plans`: service plan variants and billing period
- `prices`: historical prices by plan/geography/effective date with USD-monthly normalization

To extend coverage, add more rows in `sql/seed.sql` (or import your production data
into the same schema).
