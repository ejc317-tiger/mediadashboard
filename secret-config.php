<?php
declare(strict_types=1);

/**
 * Resolve the OpenAI API key without placing credentials in tracked source.
 *
 * Environment variables take precedence. A file-backed secret is useful for
 * container and hosting platforms that mount credentials into the filesystem.
 */
function openAiApiKey(array $siteConfig = []): string
{
    $settingsFile = __DIR__ . '/.openai-api-key';
    if (is_file($settingsFile) && is_readable($settingsFile)) {
        $settingsKey = trim((string) file_get_contents($settingsFile));
        if ($settingsKey !== '') return $settingsKey;
    }

    $environmentKey = trim((string) (getenv('OPENAI_API_KEY') ?: ''));
    if ($environmentKey !== '') return $environmentKey;

    $keyFile = trim((string) (getenv('OPENAI_API_KEY_FILE') ?: ($siteConfig['openai_api_key_file'] ?? '')));
    if ($keyFile !== '') {
        if (!is_file($keyFile) || !is_readable($keyFile)) {
            throw new RuntimeException('OPENAI_API_KEY_FILE is not a readable file.');
        }
        $fileKey = trim((string) file_get_contents($keyFile));
        if ($fileKey === '') throw new RuntimeException('OPENAI_API_KEY_FILE is empty.');
        return $fileKey;
    }

    return trim((string) ($siteConfig['openai_api_key'] ?? ''));
}

function saveOpenAiApiKey(string $key): void
{
    $key = trim($key);
    if (!preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $key)) {
        throw new InvalidArgumentException('Enter a valid OpenAI API key beginning with sk-.');
    }
    $path = __DIR__ . '/.openai-api-key';
    if (file_put_contents($path, $key . "\n", LOCK_EX) === false) {
        throw new RuntimeException('The API key could not be saved. Check directory write permissions.');
    }
    @chmod($path, 0600);
}
