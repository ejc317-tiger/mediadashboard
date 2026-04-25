PRAGMA foreign_keys = ON;

CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE geographies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    region TEXT NOT NULL
);

CREATE TABLE plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    tier TEXT,
    billing_period TEXT NOT NULL CHECK (billing_period IN ('month', 'year')),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

CREATE TABLE prices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id INTEGER NOT NULL,
    geography_id INTEGER NOT NULL,
    currency TEXT NOT NULL,
    price_local REAL NOT NULL,
    price_usd_monthly REAL NOT NULL,
    effective_date TEXT NOT NULL,
    FOREIGN KEY (plan_id) REFERENCES plans(id),
    FOREIGN KEY (geography_id) REFERENCES geographies(id),
    UNIQUE(plan_id, geography_id, effective_date)
);

CREATE INDEX idx_prices_lookup ON prices(plan_id, geography_id, effective_date);
