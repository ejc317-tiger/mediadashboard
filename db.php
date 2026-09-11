<?php
declare(strict_types=1);

function database(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $fileConfig = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $config = array_merge([
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'northstar',
        'username' => getenv('DB_USER') ?: 'northstar',
        'password' => getenv('DB_PASSWORD') ?: '',
    ], is_array($fileConfig) ? $fileConfig : []);

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}
