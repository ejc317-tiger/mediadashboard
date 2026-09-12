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
    $connection = database();
    $missing = missingRequiredTables($connection);
    if ($missing !== []) throw new RuntimeException('Schema repair did not restore: ' . implode(', ', $missing));
    echo json_encode([
        'healthy' => true,
        'message' => 'Database connected and all required tables are available.',
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode([
        'healthy' => false,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
