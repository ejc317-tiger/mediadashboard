<?php

declare(strict_types=1);

function getDatabaseConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO SQLite driver is not installed.');
    }

    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
        throw new RuntimeException('Unable to create data directory for SQLite file.');
    }

    $dbPath = $dataDir . '/pricing.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
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

    $count = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $pdo->exec("INSERT INTO services (id,name) VALUES
        (1,'Netflix'),(2,'Disney+'),(3,'Spotify')");

    $pdo->exec("INSERT INTO snapshot_prices
      (service_id,geography,region,plan_name,billing_period,currency,price_local,price_usd_monthly,effective_date)
      VALUES
      (1,'United States','North America','Standard with Ads','month','USD',7.99,7.99,'2026-03-01'),
      (1,'United States','North America','Standard','month','USD',16.49,16.49,'2026-03-01'),
      (1,'United Kingdom','Europe','Standard','month','GBP',10.99,14.14,'2026-03-01'),
      (1,'Brazil','Latin America','Standard','month','BRL',39.90,7.98,'2026-03-01'),
      (1,'India','Asia-Pacific','Standard','month','INR',499,5.99,'2026-03-01'),
      (2,'United States','North America','Standard','month','USD',9.99,9.99,'2026-03-01'),
      (2,'United States','North America','Premium','month','USD',15.99,15.99,'2026-03-01'),
      (2,'Germany','Europe','Premium','month','EUR',13.99,15.26,'2026-03-01'),
      (2,'Mexico','Latin America','Premium','month','MXN',299,17.59,'2026-03-01'),
      (3,'United States','North America','Individual','month','USD',11.99,11.99,'2026-03-01'),
      (3,'Japan','Asia-Pacific','Individual','month','JPY',1080,7.10,'2026-03-01'),
      (3,'South Africa','Middle East & Africa','Individual','month','ZAR',69.99,3.74,'2026-03-01')");
}

function fetchServices(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM services ORDER BY name')->fetchAll();
}

function fetchSnapshot(PDO $pdo, int $serviceId): array
{
    $stmt = $pdo->prepare('SELECT geography, region, plan_name, billing_period, currency, price_local, price_usd_monthly, effective_date
                           FROM snapshot_prices WHERE service_id = :service_id
                           ORDER BY region, geography, price_usd_monthly');
    $stmt->execute(['service_id' => $serviceId]);
    return $stmt->fetchAll();
}

function summarize(array $rows): array
{
    if (!$rows) {
        return ['geographies' => 0, 'plans' => 0, 'min' => null, 'max' => null, 'spread' => null];
    }
    $usd = array_column($rows, 'price_usd_monthly');
    return [
        'geographies' => count(array_unique(array_column($rows, 'geography'))),
        'plans' => count($rows),
        'min' => min($usd),
        'max' => max($usd),
        'spread' => max($usd) - min($usd),
    ];
}
