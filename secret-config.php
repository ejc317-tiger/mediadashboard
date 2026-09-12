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
