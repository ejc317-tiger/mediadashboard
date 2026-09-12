<?php
declare(strict_types=1);

/**
 * Query licensed finance-data connectors configured for this deployment.
 * Endpoint URLs are deliberately configurable because PitchBook, Crunchbase,
 * and other providers expose different products under different contracts.
 */
function queryFinanceSources(string $query): array
{
    $providers = [
        ['name' => 'Crunchbase', 'url' => getenv('CRUNCHBASE_API_URL'), 'key' => getenv('CRUNCHBASE_API_KEY'), 'header' => 'X-cb-user-key: '],
        ['name' => 'PitchBook', 'url' => getenv('PITCHBOOK_API_URL'), 'key' => getenv('PITCHBOOK_API_KEY'), 'header' => 'Authorization: Bearer '],
        ['name' => 'Finance database', 'url' => getenv('FINANCE_DATA_API_URL'), 'key' => getenv('FINANCE_DATA_API_KEY'), 'header' => 'Authorization: Bearer '],
    ];
    $results = [];
    foreach ($providers as $provider) {
        if (!$provider['url'] || !$provider['key']) continue;
        $curl = curl_init($provider['url']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', $provider['header'] . $provider['key']],
            CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'limit' => 50], JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if (is_string($body) && $status >= 200 && $status < 300) {
            $results[] = ['provider' => $provider['name'], 'payload' => mb_substr($body, 0, 40000)];
        }
    }
    return $results;
}
