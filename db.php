<?php
declare(strict_types=1);

function database(): PDO
{
    static $connection;
    if ($connection instanceof PDO) return $connection;

    $fileConfig = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $config = array_merge([
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
    ], is_array($fileConfig) ? $fileConfig : []);
    $config['database'] = 'rive4320_ibd';
    $config['username'] = 'rive4320_ibd';
    $config['password'] = 'rive4320_IBD$$';

    $hosts = array_values(array_unique([$config['host'], 'localhost', '127.0.0.1']));
    $lastError = null;
    foreach ($hosts as $host) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $config['port'], $config['database']);
            $connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            break;
        } catch (PDOException $exception) {
            $lastError = $exception;
        }
    }
    if (!$connection instanceof PDO) {
        throw new RuntimeException('Unable to connect to MySQL database rive4320_ibd. Confirm the database exists and DB_HOST/DB_PORT are correct.', 0, $lastError);
    }

    installSchema($connection);
    return $connection;
}

/** Apply each schema revision and restore any missing application tables. */
function installSchema(PDO $connection): void
{
    $schemaPath = __DIR__ . '/schema.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('schema.sql is missing from the application directory. Re-upload it from the release.');
    }
    $schema = file_get_contents($schemaPath);
    if ($schema === false || trim($schema) === '') throw new RuntimeException('schema.sql is empty or unreadable.');
    $hash = hash('sha256', $schema);

    $lock = fopen(sys_get_temp_dir() . '/northstar-schema-install.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('Unable to lock the database installer.');
    try {
        $connection->exec('CREATE TABLE IF NOT EXISTS schema_migrations (schema_hash CHAR(64) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
        $check = $connection->prepare('SELECT 1 FROM schema_migrations WHERE schema_hash = ?');
        $check->execute([$hash]);
        if ($check->fetchColumn() && requiredTablesExist($connection)) return;
        $connection->exec($schema);
        $connection->prepare('INSERT INTO schema_migrations(schema_hash) VALUES(?) ON DUPLICATE KEY UPDATE applied_at=CURRENT_TIMESTAMP')->execute([$hash]);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Confirm that every table used by the dashboard is present in this database. */
function requiredTablesExist(PDO $connection): bool
{
    $requiredTables = [
        'users',
        'ai_update_runs',
        'ai_run_sources',
        'data_sources',
        'companies',
        'private_equity_firms',
        'vc_firms',
        'company_pe_ownership',
        'company_vc_investments',
        'spac_vehicles',
        'data_centers',
    ];
    $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $statement = $connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ($placeholders)");
    $statement->execute($requiredTables);
    return (int) $statement->fetchColumn() === count($requiredTables);
}
