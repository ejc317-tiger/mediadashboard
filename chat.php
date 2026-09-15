<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/secret-config.php';
requireLogin(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function chatJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function chatResponseText(array $response): string
{
    $text = (string) ($response['output_text'] ?? '');
    if ($text === '') foreach ($response['output'] ?? [] as $output) foreach ($output['content'] ?? [] as $content) if (isset($content['text'])) $text .= (string) $content['text'];
    if ($text === '') throw new RuntimeException('The chat model returned no text.');
    return $text;
}

function openAiKeyForChat(): string
{
    $siteConfig = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
    $key = openAiApiKey(is_array($siteConfig) ? $siteConfig : []);
    if ($key === '') throw new RuntimeException('No OpenAI API key is configured.');
    return $key;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') chatJson(['error'=>'Method not allowed.'], 405);
    verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (($input['action'] ?? '') === 'poll') {
        $token = (string) ($input['token'] ?? '');
        $responseId = $_SESSION['pending_chat_jobs'][$token] ?? '';
        if ($responseId === '') chatJson(['error'=>'This chat response is no longer available.','code'=>'chat_job_not_found'],404);
        $handle = curl_init('https://api.openai.com/v1/responses/' . rawurlencode($responseId));
        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.openAiKeyForChat(),'Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20]);
        $raw=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
        if($raw===false)throw new RuntimeException('Could not check chat progress: '.($error?:'network error'));
        $response=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if($status>=400)throw new RuntimeException($response['error']['message']??"OpenAI returned HTTP $status.");
        if(in_array($response['status']??'', ['queued','in_progress'], true))chatJson(['processing'=>true,'token'=>$token,'status'=>$response['status']]);
        unset($_SESSION['pending_chat_jobs'][$token]);
        chatJson(['message'=>chatResponseText($response)]);
    }
    $model = (string) ($input['model'] ?? 'gpt-5-mini');
    if (!isChatModelId($model)) throw new InvalidArgumentException('Select a supported chat model.');
    $messages = array_slice(is_array($input['messages'] ?? null) ? $input['messages'] : [], -20);
    $conversation = [];
    foreach ($messages as $message) {
        $role = ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $content = trim((string) ($message['content'] ?? ''));
        if ($content !== '') $conversation[] = ['role'=>$role,'content'=>function_exists('mb_substr') ? mb_substr($content,0,12000) : substr($content,0,12000)];
    }
    if ($conversation === []) throw new InvalidArgumentException('Enter a message.');
    $payload = ['model'=>$model,'instructions'=>'You are the Northstar investment-intelligence assistant. Give thorough, clear answers. Do not claim to update the database; database changes happen only through the separate research confirmation workflow.','input'=>$conversation,'max_output_tokens'=>8000,'background'=>true,'store'=>true];
    if (!empty($input['web_search'])) $payload['tools'] = [['type'=>'web_search']];
    $handle = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.openAiKeyForChat(),'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25]);
    $raw=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
    if($raw===false)throw new RuntimeException('OpenAI chat-job submission failed: '.($error?:'network error'));
    $response=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if($status>=400)throw new RuntimeException($response['error']['message']??"OpenAI returned HTTP $status.");
    if(in_array($response['status']??'', ['queued','in_progress'], true)&&!empty($response['id'])){$token=bin2hex(random_bytes(24));$_SESSION['pending_chat_jobs'][$token]=(string)$response['id'];chatJson(['processing'=>true,'token'=>$token,'status'=>$response['status']]);}
    chatJson(['message'=>chatResponseText($response)]);
} catch(Throwable $exception){chatJson(['error'=>$exception->getMessage()],400);}
