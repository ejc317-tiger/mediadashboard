<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/secret-config.php';
requireLogin(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function checkOpenAiKey(string $key): array
{
    if ($key === '') return ['configured' => false, 'working' => false, 'message' => 'No API key is configured.'];
    $handle = curl_init('https://api.openai.com/v1/models');
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key], CURLOPT_TIMEOUT => 15]);
    $raw = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);
    if ($raw === false) return ['configured' => true, 'working' => false, 'message' => 'OpenAI could not be reached: ' . ($curlError ?: 'network error')];
    if ($status >= 200 && $status < 300) return ['configured' => true, 'working' => true, 'message' => 'The saved API key was accepted by OpenAI.'];
    $payload = json_decode($raw, true);
    $detail = trim((string) ($payload['error']['message'] ?? "OpenAI returned HTTP $status."));
    return ['configured' => true, 'working' => false, 'message' => $detail];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Method not allowed.');
    }
    verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (($input['action'] ?? '') === 'status') {
        $siteConfig = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
        echo json_encode(checkOpenAiKey(openAiApiKey(is_array($siteConfig) ? $siteConfig : [])), JSON_THROW_ON_ERROR);
        exit;
    }
    saveOpenAiApiKey((string) ($input['api_key'] ?? ''), (int) $_SESSION['user_id']);
    echo json_encode(['saved' => true, 'message' => 'API key encrypted and saved to the database. Future research requests will use it.'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 400);
    echo json_encode(['saved' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
