<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

$error = null;
$services = [];
$rows = [];
$summary = ['geographies' => 0, 'plans' => 0, 'min' => null, 'max' => null, 'spread' => null];
$serviceId = 0;

try {
    $pdo = getDatabaseConnection();
    $services = fetchServices($pdo);
    $serviceId = isset($_GET['service']) ? (int) $_GET['service'] : (int)($services[0]['id'] ?? 0);
    $rows = $serviceId > 0 ? fetchSnapshot($pdo, $serviceId) : [];
    $summary = summarize($rows);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function money(float $amount, string $currency): string
{
    return sprintf('%s %.2f', $currency, $amount);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Media Pricing Snapshot</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="site-header">
  <h1>Media Pricing Snapshot</h1>
  <p>Simple latest-price view by service and geography.</p>
</div>

<main class="layout">
  <?php if ($error !== null): ?>
    <section class="panel"><p><strong>Unable to load dashboard:</strong> <?= htmlspecialchars($error) ?></p></section>
  <?php else: ?>
    <section class="panel">
      <form method="get" class="service-form">
        <label for="service">Service</label>
        <select id="service" name="service" onchange="this.form.submit()">
          <?php foreach ($services as $service): ?>
            <option value="<?= (int)$service['id'] ?>" <?= (int)$service['id'] === $serviceId ? 'selected' : '' ?>>
              <?= htmlspecialchars($service['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </section>

    <section class="panel">
      <h2>Snapshot summary</h2>
      <p>Geographies: <strong><?= $summary['geographies'] ?></strong> · Plans: <strong><?= $summary['plans'] ?></strong></p>
      <p>USD/month range: <strong><?= $summary['min'] === null ? '—' : '$' . number_format((float)$summary['min'],2) ?></strong>
        to <strong><?= $summary['max'] === null ? '—' : '$' . number_format((float)$summary['max'],2) ?></strong>
        (spread <strong><?= $summary['spread'] === null ? '—' : '$' . number_format((float)$summary['spread'],2) ?></strong>)</p>
    </section>

    <section class="panel">
      <h2>Latest pricing detail</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Geography</th>
              <th>Region</th>
              <th>Plan</th>
              <th>Billing</th>
              <th>Local Price</th>
              <th>USD/month</th>
              <th>As of</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['geography']) ?></td>
                <td><?= htmlspecialchars($row['region']) ?></td>
                <td><?= htmlspecialchars($row['plan_name']) ?></td>
                <td><?= htmlspecialchars($row['billing_period']) ?></td>
                <td><?= money((float)$row['price_local'], $row['currency']) ?></td>
                <td>$<?= number_format((float)$row['price_usd_monthly'], 2) ?></td>
                <td><?= htmlspecialchars($row['effective_date']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
