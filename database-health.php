<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
requireLogin(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Use the Check and repair button to run this operation.');
    }
    verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $connection = database(false);
    $missingBeforeRepair = missingRequiredTables($connection);
    $missingColumnsBeforeRepair = missingRequiredColumns($connection);
    $legacyCharacterSetsBeforeRepair = tablesNeedingUtf8mb4($connection);
    installSchema($connection);
    $missingAfterRepair = missingRequiredTables($connection);
    $missingColumnsAfterRepair = missingRequiredColumns($connection);
    $legacyCharacterSetsAfterRepair = tablesNeedingUtf8mb4($connection);
    if ($missingAfterRepair !== [] || $missingColumnsAfterRepair !== [] || $legacyCharacterSetsAfterRepair !== []) throw new RuntimeException('Schema repair did not restore: ' . implode(', ', array_merge($missingAfterRepair, $missingColumnsAfterRepair, $legacyCharacterSetsAfterRepair)));
    $repaired = array_values(array_diff($missingBeforeRepair, $missingAfterRepair));
    $repairedColumns = array_values(array_diff($missingColumnsBeforeRepair, $missingColumnsAfterRepair));
    $repairedCharacterSets = array_map(static fn(string $table): string => "$table character set", array_values(array_diff($legacyCharacterSetsBeforeRepair, $legacyCharacterSetsAfterRepair)));
    $repairs = array_merge($repaired, $repairedColumns, $repairedCharacterSets);
    echo json_encode([
        'healthy' => true,
        'repaired' => $repaired,
        'message' => $repairs === []
            ? 'Database connected and all required tables are available.'
            : 'Database connected and repaired: ' . implode(', ', $repairs) . '.',
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode([
        'healthy' => false,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
