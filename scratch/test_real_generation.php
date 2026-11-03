<?php
/**
 * Test script for generate_pdf_important_questions using real uploaded text
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gemini.php';

$txtFile = __DIR__ . '/../uploads/doc_20261004_131403_2ac43d8156649c39.pdf.txt';
if (!file_exists($txtFile)) {
    die("Test file not found: {$txtFile}\n");
}

$text = file_get_contents($txtFile);
echo "Loaded text length: " . strlen($text) . " characters\n";
echo "Testing generate_pdf_important_questions...\n";

$result = generate_pdf_important_questions($text, 'Operating Systems');

echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
if ($result['success']) {
    echo "\n=== GENERATED CONTENT ===\n";
    echo $result['data'] . "\n\n";

    // Validate that all three sections are present and count lines
    $hasA = strpos($result['data'], 'Section A') !== false;
    $hasB = strpos($result['data'], 'Section B') !== false;
    $hasC = strpos($result['data'], 'Section C') !== false;

    echo "Contains Section A: " . ($hasA ? 'YES' : 'NO') . "\n";
    echo "Contains Section B: " . ($hasB ? 'YES' : 'NO') . "\n";
    echo "Contains Section C: " . ($hasC ? 'YES' : 'NO') . "\n";
} else {
    echo "Error: " . $result['error'] . "\n";
}
