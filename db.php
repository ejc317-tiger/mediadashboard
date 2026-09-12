<?php
declare(strict_types=1);

function database(bool $install = true): PDO
{
    static $connection;
    static $schemaInstalled = false;
    if ($connection instanceof PDO) {
        if ($install && !$schemaInstalled) {
            installSchema($connection);
            $schemaInstalled = true;
        }
        return $connection;
    }

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

    if ($install) {
        installSchema($connection);
        $schemaInstalled = true;
    }
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
        if ($check->fetchColumn() && requiredTablesExist($connection) && missingRequiredColumns($connection) === []) return;
        $connection->exec($schema);
        repairRequiredColumns($connection);
        $connection->prepare('INSERT INTO schema_migrations(schema_hash) VALUES(?) ON DUPLICATE KEY UPDATE applied_at=CURRENT_TIMESTAMP')->execute([$hash]);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Minimum columns used by runtime queries, with safe definitions for upgrading older installs. */
function requiredColumnDefinitions(): array
{
    return [
        'users'=>['email'=>'VARCHAR(255) NULL','password_hash'=>'VARCHAR(255) NULL','display_name'=>'VARCHAR(120) NULL'],
        'ai_update_runs'=>['user_id'=>'BIGINT UNSIGNED NULL','prompt'=>'TEXT NULL','status'=>"VARCHAR(20) NOT NULL DEFAULT 'Running'",'records_written'=>'INT UNSIGNED NOT NULL DEFAULT 0','error_message'=>'VARCHAR(500) NULL','completed_at'=>'TIMESTAMP NULL'],
        'ai_run_sources'=>['run_id'=>'BIGINT UNSIGNED NULL','provider'=>'VARCHAR(100) NULL'],
        'data_sources'=>['name'=>'VARCHAR(100) NULL','source_type'=>'VARCHAR(40) NULL','enabled'=>'BOOLEAN NOT NULL DEFAULT TRUE','last_success_at'=>'TIMESTAMP NULL'],
        'companies'=>['name'=>'VARCHAR(180) NULL','sector'=>"VARCHAR(120) NOT NULL DEFAULT 'Uncategorized'",'is_ai'=>'BOOLEAN NOT NULL DEFAULT FALSE','headquarters'=>'VARCHAR(180) NULL','founded_year'=>'SMALLINT UNSIGNED NULL','description'=>'TEXT NULL','last_round_date'=>'DATE NULL','last_round_size'=>'DECIMAL(18,2) NULL','last_round_valuation'=>'DECIMAL(18,2) NULL','valuation_currency'=>'CHAR(3) NULL','source_name'=>'VARCHAR(180) NULL','source_url'=>'VARCHAR(2048) NULL','as_of_date'=>'DATE NULL','confidence'=>"VARCHAR(20) NOT NULL DEFAULT 'Review'",'updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        'private_equity_firms'=>['name'=>'VARCHAR(180) NULL','strategy'=>"VARCHAR(120) NOT NULL DEFAULT 'Private Equity'",'headquarters'=>'VARCHAR(180) NULL','description'=>'TEXT NULL','source_name'=>'VARCHAR(180) NULL','source_url'=>'VARCHAR(2048) NULL','as_of_date'=>'DATE NULL','confidence'=>"VARCHAR(20) NOT NULL DEFAULT 'Review'",'updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        'vc_firms'=>['name'=>'VARCHAR(180) NULL','category'=>"VARCHAR(100) NOT NULL DEFAULT 'Venture Capital'",'headquarters'=>'VARCHAR(180) NULL','description'=>'TEXT NULL','source_name'=>'VARCHAR(180) NULL','source_url'=>'VARCHAR(2048) NULL','as_of_date'=>'DATE NULL','confidence'=>"VARCHAR(20) NOT NULL DEFAULT 'Review'",'updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        'company_pe_ownership'=>['company_id'=>'BIGINT UNSIGNED NULL','pe_firm_id'=>'BIGINT UNSIGNED NULL','acquired_date'=>'DATE NULL','exited_date'=>'DATE NULL','ownership_notes'=>'VARCHAR(255) NULL','source_url'=>'VARCHAR(2048) NULL'],
        'company_vc_investments'=>['company_id'=>'BIGINT UNSIGNED NULL','vc_firm_id'=>'BIGINT UNSIGNED NULL','round_name'=>"VARCHAR(100) NOT NULL DEFAULT 'Undisclosed'",'announced_date'=>'DATE NULL','amount'=>'DECIMAL(18,2) NULL','currency'=>'CHAR(3) NULL','is_lead'=>'BOOLEAN DEFAULT FALSE','source_url'=>'VARCHAR(2048) NULL'],
        'spac_vehicles'=>['name'=>'VARCHAR(180) NULL','sponsor'=>'VARCHAR(180) NULL','raised_date'=>'DATE NULL','ipo_size'=>'DECIMAL(18,2) NULL','currency'=>"CHAR(3) NOT NULL DEFAULT 'USD'",'deadline'=>'DATE NULL','status'=>"VARCHAR(50) NOT NULL DEFAULT 'Active'",'description'=>'TEXT NULL','source_name'=>'VARCHAR(180) NULL','source_url'=>'VARCHAR(2048) NULL','as_of_date'=>'DATE NULL','confidence'=>"VARCHAR(20) NOT NULL DEFAULT 'Review'",'updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        'data_centers'=>['name'=>'VARCHAR(180) NULL','category'=>"VARCHAR(100) NOT NULL DEFAULT 'Uncategorized'",'location'=>"VARCHAR(180) NOT NULL DEFAULT 'Unknown'",'latitude'=>'DECIMAL(9,6) NULL','longitude'=>'DECIMAL(9,6) NULL','owner'=>'VARCHAR(180) NULL','builder'=>'VARCHAR(180) NULL','power_mw'=>'DECIMAL(10,2) NULL','tenant'=>'VARCHAR(180) NULL','financing'=>'VARCHAR(255) NULL','status'=>'VARCHAR(50) NULL','as_of_date'=>'DATE NULL','description'=>'TEXT NULL','source_name'=>'VARCHAR(180) NULL','source_url'=>'VARCHAR(2048) NULL','confidence'=>"VARCHAR(20) NOT NULL DEFAULT 'Review'",'updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
    ];
}

function missingRequiredColumns(PDO $connection): array
{
    $missing = [];
    foreach (requiredColumnDefinitions() as $table => $columns) {
        $statement = $connection->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
        $statement->execute([$table]);
        $existing = $statement->fetchAll(PDO::FETCH_COLUMN);
        foreach (array_keys($columns) as $column) if (!in_array($column, $existing, true)) $missing[] = "$table.$column";
    }
    return $missing;
}

function repairRequiredColumns(PDO $connection): void
{
    $missing = array_flip(missingRequiredColumns($connection));
    foreach (requiredColumnDefinitions() as $table => $columns) {
        foreach ($columns as $column => $definition) {
            if (isset($missing["$table.$column"])) $connection->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}

/** Confirm that every table used by the dashboard is present in this database. */
function requiredTablesExist(PDO $connection): bool
{
    return missingRequiredTables($connection) === [];
}

/** Return the application tables that are absent from the selected database. */
function missingRequiredTables(PDO $connection): array
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
    $statement = $connection->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ($placeholders)");
    $statement->execute($requiredTables);
    $existingTables = $statement->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_diff($requiredTables, $existingTables));
}
