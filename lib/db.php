<?php

declare(strict_types=1);

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../data/pricing.sqlite';
    $needsBootstrap = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($needsBootstrap) {
        bootstrapDatabase($pdo);
    }

    return $pdo;
}

function bootstrapDatabase(PDO $pdo): void
{
    $schema = file_get_contents(__DIR__ . '/../sql/schema.sql');
    $seed = file_get_contents(__DIR__ . '/../sql/seed.sql');

    if ($schema === false || $seed === false) {
        throw new RuntimeException('Unable to load SQL bootstrap files.');
    }

    $pdo->exec($schema);
    $pdo->exec($seed);
}

function fetchServices(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM services ORDER BY name');

    return $stmt->fetchAll();
}

function fetchLatestSnapshotByService(PDO $pdo, int $serviceId): array
{
    $sql = <<<SQL
WITH latest_prices AS (
    SELECT
        p.plan_id,
        p.geography_id,
        MAX(p.effective_date) AS latest_date
    FROM prices p
    GROUP BY p.plan_id, p.geography_id
)
SELECT
    g.name AS geography,
    g.region,
    pl.name AS plan_name,
    pl.tier AS plan_tier,
    pl.billing_period,
    pr.currency,
    pr.price_local,
    pr.price_usd_monthly,
    pr.effective_date
FROM plans pl
JOIN latest_prices lp ON lp.plan_id = pl.id
JOIN prices pr
  ON pr.plan_id = lp.plan_id
 AND pr.geography_id = lp.geography_id
 AND pr.effective_date = lp.latest_date
JOIN geographies g ON g.id = pr.geography_id
WHERE pl.service_id = :service_id
ORDER BY g.region, g.name, pr.price_usd_monthly ASC, pl.name
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['service_id' => $serviceId]);

    return $stmt->fetchAll();
}

function groupByGeography(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['geography']][] = $row;
    }

    return $grouped;
}

function buildSummary(array $rows): array
{
    if (empty($rows)) {
        return ['geographies' => 0, 'plans' => 0];
    }

    $usdValues = array_map(static fn (array $row): float => (float) $row['price_usd_monthly'], $rows);
    $geoNames = array_unique(array_map(static fn (array $row): string => $row['geography'], $rows));

    $min = min($usdValues);
    $max = max($usdValues);

    return [
        'geographies' => count($geoNames),
        'plans' => count($rows),
        'minUsd' => $min,
        'maxUsd' => $max,
        'spreadUsd' => $max - $min,
    ];
}
