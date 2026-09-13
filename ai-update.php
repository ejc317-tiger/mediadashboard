<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/finance-sources.php';
require __DIR__ . '/secret-config.php';
requireLogin(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function callResearchService(string $prompt, string $key, array $licensed): array
{
    $licensedContext = $licensed ? "\nLicensed database results supplied by the server:\n" . json_encode($licensed, JSON_THROW_ON_ERROR) : '';
    $instructions = 'Use web search and supplied licensed finance-database results. Prioritize regulatory filings, company and investor disclosures, and licensed sources. Cross-check material facts. Never invent undisclosed values. Return only one valid JSON object with a records array, without Markdown fences or commentary. Every record has type (company, ai_company, data_center, pe_firm, vc_firm, spac, vc_investment, pe_ownership), name, category, and data. Every record must contain a valid public source_url in data and an as_of_date where applicable. Investment data includes company_name, vc_firm_name, round_name, announced_date, amount, currency, is_lead, and source_url. Ownership data includes company_name, pe_firm_name, acquired_date, exited_date, ownership_notes, and source_url. Use null for undisclosed facts.';
    $payload = [
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-5-mini',
        'instructions' => $instructions,
        'input' => $prompt . $licensedContext,
        'tools' => [['type' => 'web_search']],
    ];
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR), CURLOPT_TIMEOUT => 120]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('AI service connection failed: ' . ($curlError ?: 'unknown network error'));
    $response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($status >= 400) {
        $detail = trim((string) ($response['error']['message'] ?? 'The API returned HTTP ' . $status . '.'));
        throw new RuntimeException('AI service rejected the request: ' . $detail);
    }
    $text = (string) ($response['output_text'] ?? '');
    if ($text === '') foreach ($response['output'] ?? [] as $output) foreach ($output['content'] ?? [] as $content) if (($content['type'] ?? '') === 'output_text' || isset($content['text'])) $text .= (string) ($content['text'] ?? '');
    if ($text === '') throw new RuntimeException('AI service returned no research results.');
    $result = decodeResearchResult($text);
    if (!isset($result['records']) || !is_array($result['records'])) throw new RuntimeException('AI service returned an invalid records response.');
    return $result;
}

/** Decode JSON requested through instructions because web search cannot be combined with JSON mode. */
function decodeResearchResult(string $text): array
{
    $candidate = trim($text);
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $candidate, $match)) $candidate = trim($match[1]);
    try {
        $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $start = strpos($candidate, '{');
        $end = strrpos($candidate, '}');
        if ($start === false || $end === false || $end <= $start) throw new RuntimeException('AI service returned research in an unreadable format.', 0, $exception);
        try {
            $decoded = json_decode(substr($candidate, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $nestedException) {
            throw new RuntimeException('AI service returned research in an unreadable format.', 0, $nestedException);
        }
    }
    if (!is_array($decoded)) throw new RuntimeException('AI service returned research in an unreadable format.');
    return $decoded;
}

function previewRecords(array $records): array
{
    $preview = [];
    foreach ($records as $record) {
        $data = is_array($record['data'] ?? null) ? $record['data'] : [];
        $sourceUrl = (string) ($data['source_url'] ?? '');
        if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) continue;
        $preview[] = ['type' => (string) ($record['type'] ?? 'record'), 'name' => (string) ($record['name'] ?? $data['company_name'] ?? 'Unnamed record'), 'category' => (string) ($record['category'] ?? ''), 'source_url' => $sourceUrl];
    }
    return $preview;
}

function writeResearchResults(PDO $db, array $result, string $prompt, array $providers): array
{
    $maps = [
        'company'=>['companies',['sector','is_ai','headquarters','founded_year','description','last_round_date','last_round_size','last_round_valuation','valuation_currency','source_name','source_url','as_of_date']],
        'ai_company'=>['companies',['sector','headquarters','founded_year','description','last_round_date','last_round_size','last_round_valuation','valuation_currency','source_name','source_url','as_of_date']],
        'data_center'=>['data_centers',['category','location','latitude','longitude','owner','builder','power_mw','tenant','financing','status','description','source_name','source_url','as_of_date']],
        'pe_firm'=>['private_equity_firms',['strategy','headquarters','description','source_name','source_url','as_of_date']],
        'vc_firm'=>['vc_firms',['category','headquarters','description','source_name','source_url','as_of_date']],
        'spac'=>['spac_vehicles',['sponsor','raised_date','ipo_size','currency','deadline','status','description','source_name','source_url','as_of_date']],
    ];
    $run = $db->prepare('INSERT INTO ai_update_runs(user_id,prompt) VALUES(?,?)');
    $run->execute([$_SESSION['user_id'], $prompt]);
    $runId = (int) $db->lastInsertId();
    try {
        $sourceLog = $db->prepare('INSERT IGNORE INTO ai_run_sources(run_id,provider) VALUES(?,?)');
        foreach (array_unique(array_merge(['Web search'], $providers)) as $provider) $sourceLog->execute([$runId, $provider]);
        foreach ($providers as $provider) $db->prepare('UPDATE data_sources SET last_success_at=NOW() WHERE name=?')->execute([$provider]);
        $db->beginTransaction();
        $updated = 0;
        foreach ($result['records'] as $record) {
            $type = (string) ($record['type'] ?? '');
            if (!isset($maps[$type]) || !is_array($record['data'] ?? null)) continue;
            $data = $record['data'];
            if (in_array($type, ['company','ai_company'], true)) $data['sector'] ??= $record['category'] ?? 'Uncategorized';
            if ($type === 'pe_firm') $data['strategy'] ??= $record['category'] ?? 'Private Equity';
            if (in_array($type, ['vc_firm','data_center'], true)) $data['category'] ??= $record['category'] ?? 'Uncategorized';
            if (empty($data['source_url']) || !filter_var($data['source_url'], FILTER_VALIDATE_URL) || empty($record['name'])) continue;
            [$table, $allowed] = $maps[$type];
            $values = ['name' => $record['name']];
            if ($type === 'ai_company') $values['is_ai'] = 1;
            foreach ($allowed as $column) if (array_key_exists($column, $data)) $values[$column] = $data[$column];
            $values['confidence'] = 'Review';
            $columns = array_keys($values);
            $sql = "INSERT INTO `$table` (`" . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(',', array_map(fn($column) => "`$column`=VALUES(`$column`)", array_slice($columns, 1)));
            $db->prepare($sql)->execute(array_values($values));
            $updated++;
        }
        foreach ($result['records'] as $record) {
            $type = (string) ($record['type'] ?? '');
            $data = is_array($record['data'] ?? null) ? $record['data'] : [];
            if (!in_array($type, ['vc_investment','pe_ownership'], true) || empty($data['source_url']) || !filter_var($data['source_url'], FILTER_VALIDATE_URL)) continue;
            $company = $db->prepare('SELECT id FROM companies WHERE name=?');
            $company->execute([$data['company_name'] ?? $record['name'] ?? '']);
            $companyId = $company->fetchColumn();
            if (!$companyId) continue;
            if ($type === 'vc_investment') {
                $firm = $db->prepare('SELECT id FROM vc_firms WHERE name=?'); $firm->execute([$data['vc_firm_name'] ?? '']); $firmId = $firm->fetchColumn(); if (!$firmId) continue;
                $db->prepare('INSERT INTO company_vc_investments(company_id,vc_firm_id,round_name,announced_date,amount,currency,is_lead,source_url) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE announced_date=VALUES(announced_date),amount=VALUES(amount),currency=VALUES(currency),is_lead=VALUES(is_lead),source_url=VALUES(source_url)')->execute([$companyId,$firmId,$data['round_name'] ?? 'Undisclosed',$data['announced_date'] ?? null,$data['amount'] ?? null,$data['currency'] ?? null,!empty($data['is_lead']),$data['source_url']]);
            } else {
                $firm = $db->prepare('SELECT id FROM private_equity_firms WHERE name=?'); $firm->execute([$data['pe_firm_name'] ?? '']); $firmId = $firm->fetchColumn(); if (!$firmId) continue;
                $db->prepare('INSERT INTO company_pe_ownership(company_id,pe_firm_id,acquired_date,exited_date,ownership_notes,source_url) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE acquired_date=VALUES(acquired_date),exited_date=VALUES(exited_date),ownership_notes=VALUES(ownership_notes),source_url=VALUES(source_url)')->execute([$companyId,$firmId,$data['acquired_date'] ?? null,$data['exited_date'] ?? null,$data['ownership_notes'] ?? null,$data['source_url']]);
            }
            $updated++;
        }
        $db->commit();
        $db->prepare("UPDATE ai_update_runs SET status='Completed',records_written=?,completed_at=NOW() WHERE id=?")->execute([$updated,$runId]);
        return ['updated' => $updated, 'run_id' => $runId];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        $db->prepare("UPDATE ai_update_runs SET status='Failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([substr($exception->getMessage(),0,500),$runId]);
        throw $exception;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed.'], 405);
    verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $action = (string) ($input['action'] ?? 'preview');
    if ($action === 'confirm') {
        $token = (string) ($input['token'] ?? '');
        $pending = $_SESSION['pending_research'][$token] ?? null;
        if (!is_array($pending)) throw new RuntimeException('This research preview expired. Please run the research again.');
        unset($_SESSION['pending_research'][$token]);
        jsonResponse(writeResearchResults(database(), $pending['result'], $pending['prompt'], $pending['providers']));
    }
    if ($action === 'cancel') {
        unset($_SESSION['pending_research'][(string) ($input['token'] ?? '')]);
        jsonResponse(['cancelled' => true]);
    }
    $prompt = trim((string) ($input['prompt'] ?? ''));
    if (strlen($prompt) < 10) throw new RuntimeException('Please provide a more specific research request.');
    $siteConfig = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $key = openAiApiKey(is_array($siteConfig) ? $siteConfig : []);
    if (!$key) throw new RuntimeException('OPENAI_API_KEY is not configured. Open Settings in the top-right and save an API key.');
    $licensed = queryFinanceSources($prompt);
    $result = callResearchService($prompt, $key, $licensed);
    $preview = previewRecords($result['records']);
    if ($preview === []) throw new RuntimeException('Research completed, but no sourced records were returned. Nothing was added.');
    $token = bin2hex(random_bytes(24));
    $_SESSION['pending_research'] = $_SESSION['pending_research'] ?? [];
    $_SESSION['pending_research'][$token] = ['prompt'=>$prompt,'result'=>$result,'providers'=>array_values(array_unique(array_column($licensed,'provider'))),'created_at'=>time()];
    foreach ($_SESSION['pending_research'] as $storedToken => $pending) if (($pending['created_at'] ?? 0) < time() - 1800) unset($_SESSION['pending_research'][$storedToken]);
    jsonResponse(['requires_confirmation'=>true,'token'=>$token,'records'=>$preview]);
} catch (Throwable $exception) {
    jsonResponse(['error' => $exception->getMessage()], 400);
}
