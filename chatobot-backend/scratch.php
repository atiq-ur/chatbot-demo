<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client();
$response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
    'stream' => true,
    'headers' => [
        'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
        'Content-Type' => 'application/json'
    ],
    'json' => [
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => 'Say hello']],
        'stream' => true
    ]
]);

$body = $response->getBody();
while (!$body->eof()) {
    echo $body->read(1024);
}
