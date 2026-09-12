<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/secret-config.php';
requireLogin(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Method not allowed.');
    }
    verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    saveOpenAiApiKey((string) ($input['api_key'] ?? ''));
    echo json_encode(['saved' => true, 'message' => 'API key saved securely. Future research requests will use it.'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 400);
    echo json_encode(['saved' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
