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

function callResearchService(string $prompt, string $key, array $licensed, string $depth = 'deep', int $maximumRecords = 15, string $model = 'gpt-5-mini'): array
{
    $depthInstructions = [
        'standard' => 'Run a focused search and verify each material fact with the strongest available source.',
        'deep' => 'Run an in-depth investigation. Search broadly, cross-check material facts across multiple independent sources, inspect company and investor disclosures, and identify all clearly supported financing and ownership relationships.',
        'exhaustive' => 'Run an exhaustive investigation. Search iteratively across regulatory filings, company announcements, investor portfolio pages, and reputable reporting. Reconcile conflicts, prefer primary sources, and capture every clearly supported entity and relationship up to the record limit.',
    ];
    $depth = array_key_exists($depth, $depthInstructions) ? $depth : 'deep';
    $maximumRecords = max(1, min(50, $maximumRecords));
    if (!isChatModelId($model)) $model = 'gpt-5-mini';
    $licensedContext = $licensed ? "\nLicensed database results supplied by the server:\n" . json_encode($licensed, JSON_THROW_ON_ERROR) : '';
    $instructions = $depthInstructions[$depth] . ' Use web search and supplied licensed finance-database results. Prioritize regulatory filings, company and investor disclosures, and licensed sources. Never invent undisclosed values. Do not ask the user follow-up questions; make the best supported determination from the request and clearly note any limitations. Write a thorough, readable research report with source URLs. Do not format the response as JSON; a separate pass will extract database records.';
    $payload = [
        'model' => $model,
        'instructions' => $instructions,
        'input' => $prompt . $licensedContext,
        'tools' => [['type' => 'web_search']],
        // Keep the report bounded; breadth comes from tool calls and record extraction is chunked separately.
        'max_output_tokens' => $depth === 'standard' ? 4000 : 8000,
        'max_tool_calls' => $depth === 'exhaustive' ? 20 : ($depth === 'deep' ? 12 : 6),
        'background' => true,
        'store' => true,
    ];
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('OpenAI background-job submission failed before a job ID was returned: ' . ($curlError ?: 'unknown network error') . '. Confirm the server allows outbound HTTPS to api.openai.com.');
    $response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($status >= 400) {
        $detail = trim((string) ($response['error']['message'] ?? 'The API returned HTTP ' . $status . '.'));
        throw new RuntimeException('AI service rejected the request: ' . $detail);
    }
    if (in_array($response['status'] ?? '', ['queued','in_progress'], true) && !empty($response['id'])) return ['background_id'=>(string)$response['id']];
    return finishResearchResponse($response, $key, $model, $maximumRecords, $depth);
}

function finishResearchResponse(array $response, string $key, string $model, int $maximumRecords, string $depth): array
{
    if (($response['status'] ?? '') === 'failed') throw new RuntimeException('AI research failed: ' . ($response['error']['message'] ?? 'unknown service error'));
    $text = (string) ($response['output_text'] ?? '');
    if ($text === '') foreach ($response['output'] ?? [] as $output) foreach ($output['content'] ?? [] as $content) if (($content['type'] ?? '') === 'output_text' || isset($content['text'])) $text .= (string) ($content['text'] ?? '');
    if ($text === '') {
        $reason = (string) ($response['incomplete_details']['reason'] ?? 'No output text was returned.');
        throw new RuntimeException('AI service returned no research results: ' . $reason);
    }
    try {
        $result = extractResearchRecords($text, $key, $model, $maximumRecords, $depth);
    } catch (Throwable $exception) {
        return ['records'=>[], 'report'=>$text, 'extraction_error'=>$exception->getMessage()];
    }
    if (!isset($result['records']) || !is_array($result['records'])) throw new RuntimeException('AI service returned an invalid records response.');
    $result['report'] = $text;
    return $result;
}

function extractResearchRecords(string $report, string $key, string $model, int $maximumRecords, string $depth): array
{
    $chunks = splitResearchReport($report);
    $records = [];
    $errors = [];
    $perChunkLimit = max(5, (int) ceil($maximumRecords / count($chunks)) + 2);
    foreach ($chunks as $chunk) {
        try {
            $result = extractResearchChunk($chunk, $key, $model, $perChunkLimit, $depth);
            foreach ($result['records'] ?? [] as $record) {
                $identity = strtolower((string) ($record['type'] ?? '') . '|' . (string) ($record['name'] ?? '') . '|' . (string) (($record['data']['round_name'] ?? $record['data']['company_name'] ?? '')));
                if ($identity !== '||') $records[$identity] = $record;
                if (count($records) >= $maximumRecords) break 2;
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
    if ($records === []) throw new RuntimeException($errors[0] ?? 'No database records could be extracted from the report.');
    return ['records'=>array_values($records)];
}

function splitResearchReport(string $report, int $maximumCharacters = 40000): array
{
    if (strlen($report) <= $maximumCharacters) return [$report];
    $paragraphs = preg_split('/\n{2,}/', $report) ?: [$report];
    $chunks = []; $current = '';
    foreach ($paragraphs as $paragraph) {
        if ($current !== '' && strlen($current) + strlen($paragraph) + 2 > $maximumCharacters) { $chunks[] = $current; $current = ''; }
        if (strlen($paragraph) > $maximumCharacters) {
            if ($current !== '') { $chunks[] = $current; $current = ''; }
            while ($paragraph !== '') {
                $cut = min($maximumCharacters, strlen($paragraph));
                while ($cut > 0 && $cut < strlen($paragraph) && (ord($paragraph[$cut]) & 0xC0) === 0x80) $cut--;
                if ($cut === 0) $cut = min($maximumCharacters, strlen($paragraph));
                $chunks[] = substr($paragraph, 0, $cut);
                $paragraph = substr($paragraph, $cut);
            }
        } else $current .= ($current === '' ? '' : "\n\n") . $paragraph;
    }
    if ($current !== '') $chunks[] = $current;
    return $chunks ?: [$report];
}

function extractResearchChunk(string $report, string $key, string $model, int $maximumRecords, string $depth): array
{
    $instructions = "Extract up to $maximumRecords database records from the supplied report. Return one JSON object with a records array. Every record has type (company, ai_company, data_center, pe_firm, vc_firm, spac, vc_investment, pe_ownership), name, category, and data. Company data should include what it does, last_round_date, last_round_size, last_round_valuation, valuation_currency, and investors when disclosed. VC and PE firm data should include aum, aum_currency, key_contacts, strategy, headquarters, and active portfolios when disclosed. Data-center records should include geographic region in category, location, latitude, longitude, owner, builder, power_mw, tenant, financing, status, description, and as_of_date when disclosed. Only include facts supported by a public source_url in the report. Include entity records before relationship records. Use null for undisclosed values.";
    $payload = ['model'=>$model,'instructions'=>$instructions,'input'=>"Return JSON records extracted from this research report:\n\n".$report,'text'=>['format'=>['type'=>'json_object']],'max_output_tokens'=>$depth === 'exhaustive' ? 16000 : 12000];
    $handle = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($handle, [CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR),CURLOPT_TIMEOUT=>120]);
    $raw = curl_exec($handle); $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
    if ($raw === false) throw new RuntimeException('AI record extraction failed: ' . ($error ?: 'network error'));
    $response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($status >= 400) throw new RuntimeException('AI record extraction was rejected: ' . ($response['error']['message'] ?? "HTTP $status"));
    $text = (string) ($response['output_text'] ?? '');
    if ($text === '') foreach ($response['output'] ?? [] as $output) foreach ($output['content'] ?? [] as $content) if (isset($content['text'])) $text .= (string) $content['text'];
    if ($text === '') throw new RuntimeException('The report was created, but the extraction pass returned no database records.');
    try {
        return decodeResearchResult($text);
    } catch (Throwable) {
        return repairExtractedJson($text, $key, $model);
    }
}

/** Ask the chat model to close or repair truncated JSON rather than discarding a long report. */
function repairExtractedJson(string $text, string $key, string $model): array
{
    $payload = [
        'model'=>$model,
        'instructions'=>'Repair the supplied partial or malformed JSON. Return one valid JSON object with a records array. Preserve every complete record, discard only incomplete trailing records, and do not add facts or commentary.',
        'input'=>"Repair this content and return valid JSON only:\n\n".$text,
        'text'=>['format'=>['type'=>'json_object']],
        'max_output_tokens'=>24000,
    ];
    $handle = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($handle, [CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR),CURLOPT_TIMEOUT=>120]);
    $raw = curl_exec($handle); $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
    if ($raw === false) throw new RuntimeException('The research report is available, but record repair could not connect: ' . ($error ?: 'network error'));
    $response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($status >= 400) throw new RuntimeException('The research report is available, but record repair was rejected: ' . ($response['error']['message'] ?? "HTTP $status"));
    $repaired = (string) ($response['output_text'] ?? '');
    if ($repaired === '') foreach ($response['output'] ?? [] as $output) foreach ($output['content'] ?? [] as $content) if (isset($content['text'])) $repaired .= (string) $content['text'];
    if ($repaired === '') throw new RuntimeException('The research report is available, but the model could not repair the database preview.');
    try {
        return decodeResearchResult($repaired);
    } catch (Throwable $exception) {
        throw new RuntimeException('The research report is available, but structured records could not be completed. Try a smaller record limit.', 0, $exception);
    }
}

/** Decode JSON requested through instructions because web search cannot be combined with JSON mode. */
function decodeResearchResult(string $text): array
{
    $candidate = trim($text);
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $candidate, $match)) $candidate = trim($match[1]);
    try {
        $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (JsonException $exception) {
        $start = strpos($candidate, '{');
        $end = strrpos($candidate, '}');
        if ($start === false || $end === false || $end <= $start) throw new RuntimeException('Structured records were incomplete.', 0, $exception);
        try {
            $decoded = json_decode(substr($candidate, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException $nestedException) {
            throw new RuntimeException('Structured records were incomplete.', 0, $nestedException);
        }
    }
    if (!is_array($decoded)) throw new RuntimeException('Structured records were incomplete.');
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
        'pe_firm'=>['private_equity_firms',['strategy','headquarters','aum','aum_currency','key_contacts','description','source_name','source_url','as_of_date']],
        'vc_firm'=>['vc_firms',['category','headquarters','aum','aum_currency','key_contacts','description','source_name','source_url','as_of_date']],
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
        if (!is_array($pending)) {
            $statement=database()->prepare("SELECT prompt,result_json,providers_json FROM research_jobs WHERE id=? AND user_id=? AND status='Awaiting confirmation'");$statement->execute([$token,$_SESSION['user_id']]);$stored=$statement->fetch();
            if($stored)$pending=['prompt'=>$stored['prompt'],'result'=>json_decode($stored['result_json'],true,512,JSON_THROW_ON_ERROR),'providers'=>json_decode($stored['providers_json']?:'[]',true,512,JSON_THROW_ON_ERROR)];
        }
        if (!is_array($pending)) throw new RuntimeException('This research preview is unavailable or was already processed.');
        unset($_SESSION['pending_research'][$token]);
        $written=writeResearchResults(database(), $pending['result'], $pending['prompt'], $pending['providers']);
        database()->prepare("UPDATE research_jobs SET status='Confirmed',completed_at=NOW() WHERE id=? AND user_id=?")->execute([$token,$_SESSION['user_id']]);
        jsonResponse($written);
    }
    if ($action === 'cancel') {
        unset($_SESSION['pending_research'][(string) ($input['token'] ?? '')]);
        unset($_SESSION['pending_research_jobs'][(string) ($input['token'] ?? '')]);
        database()->prepare("UPDATE research_jobs SET status='Cancelled',completed_at=NOW() WHERE id=? AND user_id=?")->execute([(string)($input['token']??''),$_SESSION['user_id']]);
        jsonResponse(['cancelled' => true]);
    }
    if($action==='list'){
        $statement=database()->prepare("SELECT id,status,LEFT(prompt,180) prompt,created_at,updated_at FROM research_jobs WHERE user_id=? AND status IN ('Queued','In progress','Awaiting confirmation') ORDER BY updated_at DESC LIMIT 20");$statement->execute([$_SESSION['user_id']]);
        jsonResponse(['jobs'=>$statement->fetchAll()]);
    }
    if ($action === 'poll') {
        $token = (string) ($input['token'] ?? '');
        $statement=database()->prepare('SELECT * FROM research_jobs WHERE id=? AND user_id=?');$statement->execute([$token,$_SESSION['user_id']]);$job=$statement->fetch();
        if (!is_array($job) && is_array($_SESSION['pending_research_jobs'][$token] ?? null)) {
            $legacy=$_SESSION['pending_research_jobs'][$token];
            $providers=$legacy['providers']??[];
            database()->prepare('INSERT IGNORE INTO research_jobs(id,user_id,response_id,prompt,model,depth,maximum_records,providers_json) VALUES(?,?,?,?,?,?,?,?)')->execute([$token,$_SESSION['user_id'],$legacy['response_id'],$legacy['prompt'],$legacy['model'],$legacy['depth'],$legacy['maximum_records'],json_encode($providers,JSON_THROW_ON_ERROR)]);
            $statement->execute([$token,$_SESSION['user_id']]);$job=$statement->fetch();
        }
        if (!is_array($job)) jsonResponse(['error'=>'The saved research job could not be found. It can be restarted automatically from the original request.','code'=>'job_not_found'],404);
        if($job['status']==='Awaiting confirmation'){jsonResponse(['requires_confirmation'=>true,'token'=>$token,'records'=>previewRecords(json_decode($job['result_json'],true,512,JSON_THROW_ON_ERROR)['records']??[]),'report'=>$job['report']]);}
        if($job['status']==='Failed')throw new RuntimeException($job['error_message']?:'The research job failed.');
        $siteConfig = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
        $key = openAiApiKey(is_array($siteConfig) ? $siteConfig : []);
        $handle = curl_init('https://api.openai.com/v1/responses/' . rawurlencode($job['response_id']));
        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key],CURLOPT_TIMEOUT=>20]);
        $raw=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
        if($raw===false)throw new RuntimeException('Could not check research progress: '.($error?:'network error'));
        $response=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if($status>=400)throw new RuntimeException($response['error']['message']??"OpenAI returned HTTP $status.");
        if(in_array($response['status']??'', ['queued','in_progress'], true)){database()->prepare('UPDATE research_jobs SET status=? WHERE id=?')->execute([$response['status']==='queued'?'Queued':'In progress',$token]);jsonResponse(['processing'=>true,'token'=>$token,'status'=>$response['status']]);}
        unset($_SESSION['pending_research_jobs'][$token]);
        try{$result=finishResearchResponse($response,$key,$job['model'],$job['maximum_records'],$job['depth']);}catch(Throwable $exception){database()->prepare("UPDATE research_jobs SET status='Failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([substr($exception->getMessage(),0,1000),$token]);throw $exception;}
        $preview=previewRecords($result['records']);
        if($preview===[]){database()->prepare("UPDATE research_jobs SET status='Failed',report=?,error_message=?,completed_at=NOW() WHERE id=?")->execute([$result['report'],$result['extraction_error']??'No sourced database records were found.',$token]);jsonResponse(['requires_confirmation'=>false,'records'=>[],'report'=>$result['report'],'warning'=>$result['extraction_error']??'No sourced database records were found.']);}
        database()->prepare("UPDATE research_jobs SET status='Awaiting confirmation',report=?,result_json=?,completed_at=NOW() WHERE id=?")->execute([$result['report'],json_encode($result,JSON_THROW_ON_ERROR),$token]);
        $_SESSION['pending_research'][$token]=['prompt'=>$job['prompt'],'result'=>$result,'providers'=>json_decode($job['providers_json']?:'[]',true,512,JSON_THROW_ON_ERROR),'created_at'=>time()];
        jsonResponse(['requires_confirmation'=>true,'token'=>$token,'records'=>$preview,'report'=>$result['report']]);
    }
    $prompt = trim((string) ($input['prompt'] ?? ''));
    if (strlen($prompt) < 10) throw new RuntimeException('Please provide a more specific research request.');
    $siteConfig = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $key = openAiApiKey(is_array($siteConfig) ? $siteConfig : []);
    if (!$key) throw new RuntimeException('OPENAI_API_KEY is not configured. Open Settings in the top-right and save an API key.');
    $depth = (string) ($input['depth'] ?? 'deep');
    $maximumRecords = (int) ($input['maximum_records'] ?? 15);
    $model = (string) ($input['model'] ?? (getenv('OPENAI_MODEL') ?: 'gpt-5-mini'));
    $licensed = queryFinanceSources($prompt);
    $result = callResearchService($prompt, $key, $licensed, $depth, $maximumRecords, $model);
    if(isset($result['background_id'])){
        $token=bin2hex(random_bytes(24));
        $providers=array_values(array_unique(array_column($licensed,'provider')));$model=isChatModelId($model)?$model:'gpt-5-mini';
        database()->prepare('INSERT INTO research_jobs(id,user_id,response_id,prompt,model,depth,maximum_records,providers_json) VALUES(?,?,?,?,?,?,?,?)')->execute([$token,$_SESSION['user_id'],$result['background_id'],$prompt,$model,$depth,$maximumRecords,json_encode($providers,JSON_THROW_ON_ERROR)]);
        jsonResponse(['processing'=>true,'token'=>$token,'status'=>'queued']);
    }
    $preview = previewRecords($result['records']);
    if ($preview === []) jsonResponse(['requires_confirmation'=>false,'records'=>[],'report'=>$result['report'],'warning'=>$result['extraction_error'] ?? 'No sourced database records were found.']);
    $token = bin2hex(random_bytes(24));
    $_SESSION['pending_research'] = $_SESSION['pending_research'] ?? [];
    $_SESSION['pending_research'][$token] = ['prompt'=>$prompt,'result'=>$result,'providers'=>array_values(array_unique(array_column($licensed,'provider'))),'created_at'=>time()];
    foreach ($_SESSION['pending_research'] as $storedToken => $pending) if (($pending['created_at'] ?? 0) < time() - 1800) unset($_SESSION['pending_research'][$storedToken]);
    jsonResponse(['requires_confirmation'=>true,'token'=>$token,'records'=>$preview,'report'=>$result['report']]);
} catch (Throwable $exception) {
    jsonResponse(['error' => $exception->getMessage()], 400);
}
