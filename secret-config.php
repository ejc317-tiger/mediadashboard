<?php
declare(strict_types=1);

function settingsEncryptionKey(): string
{
    $environment = trim((string) (getenv('APP_ENCRYPTION_KEY') ?: ''));
    if ($environment !== '') return hash('sha256', $environment, true);
    $path = __DIR__ . '/.app-encryption-key';
    if (!is_file($path)) {
        $generated = random_bytes(32);
        if (file_put_contents($path, base64_encode($generated) . "\n", LOCK_EX) === false) throw new RuntimeException('Unable to create the settings encryption key.');
        @chmod($path, 0600);
        return $generated;
    }
    $decoded = base64_decode(trim((string) file_get_contents($path)), true);
    if ($decoded === false || strlen($decoded) !== 32) throw new RuntimeException('The settings encryption key is invalid.');
    return $decoded;
}

function encryptSetting(string $value): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($value, 'aes-256-gcm', settingsEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) throw new RuntimeException('Unable to encrypt the API key.');
    return base64_encode($iv . $tag . $ciphertext);
}

function decryptSetting(string $payload): string
{
    $decoded = base64_decode($payload, true);
    if ($decoded === false || strlen($decoded) < 29) throw new RuntimeException('The saved API key is invalid.');
    $value = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', settingsEncryptionKey(), OPENSSL_RAW_DATA, substr($decoded, 0, 12), substr($decoded, 12, 16));
    if ($value === false) throw new RuntimeException('The saved API key could not be decrypted.');
    return $value;
}

function openAiApiKey(array $siteConfig = []): string
{
    $statement = database()->prepare('SELECT setting_value FROM app_settings WHERE setting_key=?');
    $statement->execute(['openai_api_key']);
    $saved = $statement->fetchColumn();
    if (is_string($saved) && $saved !== '') return decryptSetting($saved);

    // Backward compatibility for installations that used the previous file-backed setting.
    $legacyPath = __DIR__ . '/.openai-api-key';
    if (is_file($legacyPath) && is_readable($legacyPath)) {
        $legacyKey = trim((string) file_get_contents($legacyPath));
        if ($legacyKey !== '') return $legacyKey;
    }
    $environmentKey = trim((string) (getenv('OPENAI_API_KEY') ?: ''));
    if ($environmentKey !== '') return $environmentKey;
    $keyFile = trim((string) (getenv('OPENAI_API_KEY_FILE') ?: ($siteConfig['openai_api_key_file'] ?? '')));
    if ($keyFile !== '') {
        if (!is_file($keyFile) || !is_readable($keyFile)) throw new RuntimeException('OPENAI_API_KEY_FILE is not a readable file.');
        $fileKey = trim((string) file_get_contents($keyFile));
        if ($fileKey === '') throw new RuntimeException('OPENAI_API_KEY_FILE is empty.');
        return $fileKey;
    }
    return trim((string) ($siteConfig['openai_api_key'] ?? ''));
}

function saveOpenAiApiKey(string $key, int $userId): void
{
    $key = trim($key);
    if (!preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $key)) throw new InvalidArgumentException('Enter a valid OpenAI API key beginning with sk-.');
    database()->prepare('INSERT INTO app_settings(setting_key,setting_value,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute(['openai_api_key', encryptSetting($key), $userId]);
    $legacyPath = __DIR__ . '/.openai-api-key';
    if (is_file($legacyPath)) @unlink($legacyPath);
}
