<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gemini.php';

$txtPath = UPLOAD_DIR . 'doc_20261004_131403_2ac43d8156649c39.pdf.txt';
$text = file_get_contents($txtPath);

echo "Loaded text: " . strlen($text) . " bytes\n";

$q = "What is this document about?";
$ctx = extract_relevant_document_context($text, $q, 28000);
echo "Extracted context length: " . strlen($ctx) . " bytes\n";
echo "Context empty? " . (empty(trim($ctx)) ? "YES" : "NO") . "\n";

$history = [];
$recentHistory = array_slice($history, -6);
$prompt = "You are a dedicated AI study assistant for the document: \"UNIT5-OST.pdf\" (Subject: Computer Science).

DOCUMENT EXCERPTS:
\"\"\"
{$ctx}
\"\"\"

CURRENT STUDENT QUESTION:
{$q}

ASSISTANT ANSWER:";

echo "Prompt length: " . strlen($prompt) . " bytes\n";
echo "Prompt empty? " . (empty(trim($prompt)) ? "YES" : "NO") . "\n";

$encoded = json_encode([
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
]);

echo "json_encode result: " . ($encoded !== false ? "SUCCESS (" . strlen($encoded) . " bytes)" : "FAILED: " . json_last_error_msg()) . "\n";
