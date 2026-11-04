<?php
$envPath = __DIR__ . '/../.env';
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$apiKey = '';
foreach ($lines as $line) {
    if (strpos(trim($line), 'GROQ_API_KEY=') === 0) {
        $apiKey = trim(substr(trim($line), strlen('GROQ_API_KEY=')));
    }
}

echo "Key length: " . strlen($apiKey) . "\n";

// Test Groq models endpoint
$ch = curl_init('https://api.groq.com/openai/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$data = json_decode($response, true);
if (isset($data['data'])) {
    echo "Available Groq models:\n";
    foreach ($data['data'] as $m) {
        echo " - " . $m['id'] . "\n";
    }
} else {
    echo "Response: $response\n";
}

