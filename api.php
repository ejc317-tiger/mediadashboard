<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$resources = [
    'companies' => ['table' => 'companies', 'columns' => ['name','category','ownership','status','metric','as_of_date','description','source_name','source_url','confidence']],
    'datacenters' => ['table' => 'data_centers', 'columns' => ['name','category','location','owner','builder','power','tenant','financing','status','as_of_date','description','source_name','source_url','confidence']],
    'spacs' => ['table' => 'spacs', 'columns' => ['name','category','sponsor','ipo_size','deadline','remaining','status','as_of_date','description','source_name','source_url','confidence']],
];

try {
    $resource = $_GET['resource'] ?? '';
    if (!isset($resources[$resource])) {
        http_response_code(400);
        throw new InvalidArgumentException('Unknown resource.');
    }
    $definition = $resources[$resource];
    $search = trim((string) ($_GET['search'] ?? ''));
    $category = trim((string) ($_GET['category'] ?? ''));
    $sort = (string) ($_GET['sort'] ?? 'name');
    $direction = strtolower((string) ($_GET['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    if (!in_array($sort, $definition['columns'], true)) {
        $sort = 'name';
    }

    $where = [];
    $parameters = [];
    if ($search !== '') {
        $searchable = array_filter($definition['columns'], fn(string $column): bool => !in_array($column, ['as_of_date','deadline'], true));
        $where[] = '(' . implode(' OR ', array_map(fn(string $column): string => "`$column` LIKE :search", $searchable)) . ')';
        $parameters['search'] = '%' . $search . '%';
    }
    if ($category !== '' && $category !== 'all') {
        $where[] = '`category` = :category';
        $parameters['category'] = $category;
    }

    $columns = implode(',', array_map(fn(string $column): string => "`$column`", $definition['columns']));
    $sql = "SELECT `id`,$columns FROM `{$definition['table']}`";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= " ORDER BY `$sort` $direction LIMIT 500";
    $statement = database()->prepare($sql);
    $statement->execute($parameters);
    $records = $statement->fetchAll();

    $categoryStatement = database()->query("SELECT DISTINCT `category` FROM `{$definition['table']}` WHERE `category` IS NOT NULL ORDER BY `category`");
    $categories = array_column($categoryStatement->fetchAll(), 'category');
    $countStatement = database()->query("SELECT COUNT(*) total, SUM(confidence = 'Verified') verified, MAX(updated_at) last_sync FROM `{$definition['table']}`");
    $stats = $countStatement->fetch();

    echo json_encode(['records' => $records, 'categories' => $categories, 'stats' => $stats], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['error' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The data service is temporarily unavailable.'], JSON_THROW_ON_ERROR);
}
