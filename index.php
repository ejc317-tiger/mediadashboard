<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

$pdo = getDatabaseConnection();

$services = fetchServices($pdo);
$serviceId = isset($_GET['service']) ? (int) $_GET['service'] : ($services[0]['id'] ?? 0);

$activeService = null;
foreach ($services as $service) {
    if ((int) $service['id'] === $serviceId) {
        $activeService = $service;
        break;
    }
}

if ($activeService === null && !empty($services)) {
    $activeService = $services[0];
    $serviceId = (int) $activeService['id'];
}

$snapshotRows = $serviceId > 0 ? fetchLatestSnapshotByService($pdo, $serviceId) : [];
$summary = buildSummary($snapshotRows);
$rowsByGeography = groupByGeography($snapshotRows);

function formatMoney(float $amount, string $currency): string
{
    if (class_exists(NumberFormatter::class)) {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);
        $formatted = $formatter->formatCurrency($amount, $currency);
        if ($formatted !== false) {
            return $formatted;
        }
    }

    return sprintf('%s %.2f', $currency, $amount);
}

function formatPeriod(string $billingPeriod): string
{
    return match ($billingPeriod) {
        'month' => 'Monthly',
        'year' => 'Yearly',
        default => ucfirst($billingPeriod),
    };
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Global Media Pricing Snapshot</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="site-header">
    <h1>Global Media Subscription Snapshot</h1>
    <p>Latest available pricing by major geography, with all published plan options.</p>
</header>

<main class="layout">
    <section class="panel">
        <form method="get" class="service-form">
            <label for="service">Media service</label>
            <select id="service" name="service" onchange="this.form.submit()">
                <?php foreach ($services as $service): ?>
                    <option value="<?= (int) $service['id'] ?>" <?= (int) $service['id'] === $serviceId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($service['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit">View snapshot</button></noscript>
        </form>
    </section>

    <section class="panel summary-panel">
        <h2><?= htmlspecialchars($activeService['name'] ?? 'No service selected') ?> — Latest Snapshot Summary</h2>
        <div class="stat-grid">
            <article class="stat-card">
                <p class="label">Geographies covered</p>
                <p class="value"><?= (int) ($summary['geographies'] ?? 0) ?></p>
            </article>
            <article class="stat-card">
                <p class="label">Plan variants shown</p>
                <p class="value"><?= (int) ($summary['plans'] ?? 0) ?></p>
            </article>
            <article class="stat-card">
                <p class="label">Lowest USD/month equivalent</p>
                <p class="value"><?= isset($summary['minUsd']) ? '$' . number_format($summary['minUsd'], 2) : '—' ?></p>
            </article>
            <article class="stat-card">
                <p class="label">Highest USD/month equivalent</p>
                <p class="value"><?= isset($summary['maxUsd']) ? '$' . number_format($summary['maxUsd'], 2) : '—' ?></p>
            </article>
            <article class="stat-card">
                <p class="label">Cross-market spread</p>
                <p class="value"><?= isset($summary['spreadUsd']) ? '$' . number_format($summary['spreadUsd'], 2) : '—' ?></p>
            </article>
        </div>
    </section>

    <section class="panel">
        <h2>Latest pricing by geography</h2>
        <?php if (empty($rowsByGeography)): ?>
            <p>No pricing rows available for this service.</p>
        <?php else: ?>
            <div class="geo-grid">
                <?php foreach ($rowsByGeography as $geography => $rows): ?>
                    <article class="geo-card">
                        <header>
                            <h3><?= htmlspecialchars($geography) ?></h3>
                            <p><?= htmlspecialchars($rows[0]['region']) ?></p>
                        </header>

                        <table>
                            <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Billing</th>
                                <th>Local price</th>
                                <th>USD/month eq.</th>
                                <th>As of</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['plan_name']) ?></strong>
                                        <?php if (!empty($row['plan_tier'])): ?>
                                            <div class="muted"><?= htmlspecialchars($row['plan_tier']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatPeriod($row['billing_period']) ?></td>
                                    <td><?= formatMoney((float) $row['price_local'], $row['currency']) ?></td>
                                    <td>$<?= number_format((float) $row['price_usd_monthly'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['effective_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
