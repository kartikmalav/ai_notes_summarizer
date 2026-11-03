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

// Case 1: Empty JSON object {}
$res1 = testPost($endpoint, "{}");
echo "Case 1 (Empty JSON {}):\nHTTP " . $res1['code'] . "\n" . $res1['response'] . "\n\n";

// Case 2: Empty string ""
$res2 = testPost($endpoint, "");
echo "Case 2 (Empty string):\nHTTP " . $res2['code'] . "\n" . $res2['response'] . "\n\n";

// Case 3: Empty text in parts: {"contents":[{"parts":[{"text":""}]}]}
$res3 = testPost($endpoint, json_encode(["contents" => [["parts" => [["text" => ""]]]]]));
echo "Case 3 (text is empty):\nHTTP " . $res3['code'] . "\n" . $res3['response'] . "\n\n";
