<?php
require_once __DIR__ . '/../config/config.php';

$apiKey = trim(GEMINI_API_KEY);
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . rawurlencode($apiKey);

function testPost($endpoint, $payload) {
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'response' => $response];
}

// Test A: contents is empty array []
$resA = testPost($endpoint, json_encode(["contents" => []]));
echo "Test A (contents is []):\nHTTP " . $resA['code'] . "\n" . $resA['response'] . "\n\n";

// Test B: json_encode failed on non-UTF-8 string:
$nonUtf8 = "Hello \xB1\x31\xFF world";
$encoded = json_encode(["contents" => [["parts" => [["text" => $nonUtf8]]]]]);
echo "Test B (json_encode with bad UTF8 returns):\n" . var_export($encoded, true) . "\n";
echo "json_last_error_msg(): " . json_last_error_msg() . "\n\n";

// Test C: json_encode with JSON_INVALID_UTF8_SUBSTITUTE:
$encodedSub = json_encode(
    ["contents" => [["parts" => [["text" => $nonUtf8]]]]],
    JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
);
echo "Test C (json_encode with JSON_INVALID_UTF8_SUBSTITUTE returns):\n" . var_export($encodedSub, true) . "\n\n";
