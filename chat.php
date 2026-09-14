<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/secret-config.php';
requireLogin(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Method not allowed.');
    verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
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
    $siteConfig = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
    $payload = ['model'=>$model,'instructions'=>'You are the Northstar investment-intelligence assistant. Give thorough, clear answers. Do not claim to update the database; database changes happen only through the separate research confirmation workflow.','input'=>$conversation,'max_output_tokens'=>8000];
    if (!empty($input['web_search'])) $payload['tools'] = [['type'=>'web_search']];
    $handle = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.openAiApiKey(is_array($siteConfig)?$siteConfig:[]),'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_TIMEOUT=>120]);
    $raw=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
    if($raw===false)throw new RuntimeException('Chat connection failed: '.($error?:'network error'));
    $response=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if($status>=400)throw new RuntimeException($response['error']['message']??"OpenAI returned HTTP $status.");
    $text=(string)($response['output_text']??'');
    if($text==='')foreach($response['output']??[] as $output)foreach($output['content']??[] as $content)if(isset($content['text']))$text.=(string)$content['text'];
    if($text==='')throw new RuntimeException('The chat model returned no text.');
    echo json_encode(['message'=>$text],JSON_THROW_ON_ERROR);
} catch(Throwable $exception){http_response_code(400);echo json_encode(['error'=>$exception->getMessage()]);}
