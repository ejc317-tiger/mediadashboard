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
    ], is_array($fileConfig) ? $fileConfig : []);
    $config['database'] = 'rive4320_ibd';
    $config['username'] = 'rive4320_ibd';
    $config['password'] = 'rive4320_IBD$$';

    installSchemaIfPresent($config);

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}

/** Install the bundled schema once and remove it after a successful import. */
function installSchemaIfPresent(array $config): void
{
    $schemaPath = __DIR__ . '/schema.sql';
    if (!is_file($schemaPath)) {
        return;
    }

    $lock = fopen(sys_get_temp_dir() . '/northstar-schema-install.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to lock the database installer.');
    }

    try {
        clearstatcache(true, $schemaPath);
        if (!is_file($schemaPath)) {
            return;
        }
        $schema = file_get_contents($schemaPath);
        if ($schema === false || trim($schema) === '') {
            throw new RuntimeException('The bundled database schema is unreadable.');
        }

        // Shared-hosting users normally own an existing database but do not
        // have server-wide CREATE DATABASE permission. Install inside it.
        $installerDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
        $installer = new PDO($installerDsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $installer->exec($schema);

        if (!unlink($schemaPath)) {
            throw new RuntimeException('Schema installed, but schema.sql could not be deleted.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
