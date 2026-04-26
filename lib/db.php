<?php

declare(strict_types=1);

/**
 * Returns a unified pricing source.
 * mode: sqlite | fallback
 */
function loadPricingSource(): array
{
    try {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            return fallbackSource('SQLite driver not installed; using built-in sample data.');
        }

        $pdo = getDatabaseConnection();
        $services = fetchServices($pdo);
        $rows = fetchAllSnapshotRows($pdo);

        return [
            'mode' => 'sqlite',
            'notice' => null,
            'services' => $services,
            'rows' => $rows,
        ];
    } catch (Throwable $e) {
        return fallbackSource('Database unavailable; using built-in sample data.');
    }
}

function fallbackSource(string $notice): array
{
    $rows = [
        ['service_id' => 1, 'service_name' => 'Netflix', 'geography' => 'United States', 'region' => 'North America', 'plan_name' => 'Standard with Ads', 'billing_period' => 'month', 'currency' => 'USD', 'price_local' => 7.99, 'price_usd_monthly' => 7.99, 'effective_date' => '2026-03-01'],
        ['service_id' => 1, 'service_name' => 'Netflix', 'geography' => 'United Kingdom', 'region' => 'Europe', 'plan_name' => 'Standard', 'billing_period' => 'month', 'currency' => 'GBP', 'price_local' => 10.99, 'price_usd_monthly' => 14.14, 'effective_date' => '2026-03-01'],
        ['service_id' => 1, 'service_name' => 'Netflix', 'geography' => 'India', 'region' => 'Asia-Pacific', 'plan_name' => 'Standard', 'billing_period' => 'month', 'currency' => 'INR', 'price_local' => 499.00, 'price_usd_monthly' => 5.99, 'effective_date' => '2026-03-01'],
        ['service_id' => 2, 'service_name' => 'Disney+', 'geography' => 'United States', 'region' => 'North America', 'plan_name' => 'Premium', 'billing_period' => 'month', 'currency' => 'USD', 'price_local' => 15.99, 'price_usd_monthly' => 15.99, 'effective_date' => '2026-03-01'],
        ['service_id' => 2, 'service_name' => 'Disney+', 'geography' => 'Germany', 'region' => 'Europe', 'plan_name' => 'Premium', 'billing_period' => 'month', 'currency' => 'EUR', 'price_local' => 13.99, 'price_usd_monthly' => 15.26, 'effective_date' => '2026-03-01'],
        ['service_id' => 3, 'service_name' => 'Spotify', 'geography' => 'United States', 'region' => 'North America', 'plan_name' => 'Individual', 'billing_period' => 'month', 'currency' => 'USD', 'price_local' => 11.99, 'price_usd_monthly' => 11.99, 'effective_date' => '2026-03-01'],
        ['service_id' => 3, 'service_name' => 'Spotify', 'geography' => 'Japan', 'region' => 'Asia-Pacific', 'plan_name' => 'Individual', 'billing_period' => 'month', 'currency' => 'JPY', 'price_local' => 1080.00, 'price_usd_monthly' => 7.10, 'effective_date' => '2026-03-01'],
    ];

    $services = [
        ['id' => 1, 'name' => 'Netflix'],
        ['id' => 2, 'name' => 'Disney+'],
        ['id' => 3, 'name' => 'Spotify'],
    ];

    return [
        'mode' => 'fallback',
        'notice' => $notice,
        'services' => $services,
        'rows' => $rows,
    ];
}

function getDatabaseConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
        throw new RuntimeException('Unable to create data directory for SQLite file.');
    }

    $pdo = new PDO('sqlite:' . $dataDir . '/pricing.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    ensureSchemaAndSeed($pdo);

    return $pdo;
}

function ensureSchemaAndSeed(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS services (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE);
         CREATE TABLE IF NOT EXISTS snapshot_prices (
            id INTEGER PRIMARY KEY,
            service_id INTEGER NOT NULL,
            geography TEXT NOT NULL,
            region TEXT NOT NULL,
            plan_name TEXT NOT NULL,
            billing_period TEXT NOT NULL,
            currency TEXT NOT NULL,
            price_local REAL NOT NULL,
            price_usd_monthly REAL NOT NULL,
            effective_date TEXT NOT NULL
         );'
    );

    if ((int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() > 0) {
        return;
    }

    $pdo->exec("INSERT INTO services (id,name) VALUES (1,'Netflix'),(2,'Disney+'),(3,'Spotify')");
    $pdo->exec("INSERT INTO snapshot_prices
      (service_id,geography,region,plan_name,billing_period,currency,price_local,price_usd_monthly,effective_date)
      VALUES
      (1,'United States','North America','Standard with Ads','month','USD',7.99,7.99,'2026-03-01'),
      (1,'United Kingdom','Europe','Standard','month','GBP',10.99,14.14,'2026-03-01'),
      (1,'India','Asia-Pacific','Standard','month','INR',499,5.99,'2026-03-01'),
      (2,'United States','North America','Premium','month','USD',15.99,15.99,'2026-03-01'),
      (2,'Germany','Europe','Premium','month','EUR',13.99,15.26,'2026-03-01'),
      (3,'United States','North America','Individual','month','USD',11.99,11.99,'2026-03-01'),
      (3,'Japan','Asia-Pacific','Individual','month','JPY',1080,7.10,'2026-03-01')");
}

function fetchServices(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM services ORDER BY name')->fetchAll();
}

function fetchAllSnapshotRows(PDO $pdo): array
{
    return $pdo->query('SELECT s.name AS service_name, p.service_id, p.geography, p.region, p.plan_name, p.billing_period,
                               p.currency, p.price_local, p.price_usd_monthly, p.effective_date
                        FROM snapshot_prices p JOIN services s ON s.id = p.service_id
                        ORDER BY s.name, p.region, p.geography, p.price_usd_monthly')->fetchAll();
}

function rowsForService(array $rows, int $serviceId): array
{
    return array_values(array_filter($rows, static fn(array $row): bool => (int) $row['service_id'] === $serviceId));
}

function summarize(array $rows): array
{
    if (!$rows) {
        return ['geographies' => 0, 'plans' => 0, 'min' => null, 'max' => null, 'spread' => null];
    }
    $usd = array_map(static fn(array $row): float => (float)$row['price_usd_monthly'], $rows);
    return [
        'geographies' => count(array_unique(array_column($rows, 'geography'))),
        'plans' => count($rows),
        'min' => min($usd),
        'max' => max($usd),
        'spread' => max($usd) - min($usd),
    ];
}
