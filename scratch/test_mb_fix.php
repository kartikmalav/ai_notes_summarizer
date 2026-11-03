<?php
require_once __DIR__ . '/../config/config.php';

$txtPath = UPLOAD_DIR . 'doc_20261004_131403_2ac43d8156649c39.pdf.txt';
$fullText = file_get_contents($txtPath);

function testExtractMb($fullText, $query, $maxChars = 28000) {
    if (!mb_check_encoding($fullText, 'UTF-8')) {
        $fullText = mb_convert_encoding($fullText, 'UTF-8', 'UTF-8');
    }
    $fullText = trim($fullText);
    $textLength = mb_strlen($fullText, 'UTF-8');

    if ($textLength <= $maxChars) {
        return $fullText;
    }

    $introLength = min(3500, (int)($maxChars * 0.15));
    $introText = mb_substr($fullText, 0, $introLength, 'UTF-8');
    $remainingBudget = $maxChars - $introLength;

    $bodyText = mb_substr($fullText, $introLength, null, 'UTF-8');

    $stopWords = ['what', 'when', 'where', 'which', 'who', 'how', 'this', 'that', 'the', 'is', 'are'];
    preg_match_all('/\b[a-zA-Z0-9]{3,}\b/u', mb_strtolower($query, 'UTF-8'), $matches);
    $keywords = array_values(array_filter($matches[0] ?? [], fn($t) => !in_array($t, $stopWords)));

    $chunkSize = 1800;
    $overlap = 200;
    $step = $chunkSize - $overlap;
    $chunks = [];
    $bodyLen = mb_strlen($bodyText, 'UTF-8');

    for ($offset = 0; $offset < $bodyLen; $offset += $step) {
        $chunk = mb_substr($bodyText, $offset, $chunkSize, 'UTF-8');
        if (mb_strlen(trim($chunk), 'UTF-8') < 50) continue;

        $score = 0;
        $chunkLower = mb_strtolower($chunk, 'UTF-8');
        foreach ($keywords as $kw) {
            $cnt = mb_substr_count($chunkLower, $kw, 'UTF-8');
            if ($cnt > 0) $score += $cnt;
        }

        $chunks[] = [
            'offset' => $offset,
            'text' => $chunk,
            'score' => $score
        ];
    }

    usort($chunks, fn($a, $b) => $b['score'] <=> $a['score']);

    $selected = [];
    $accum = 0;
    foreach ($chunks as $c) {
        $len = mb_strlen($c['text'], 'UTF-8');
        if ($accum + $len <= $remainingBudget) {
            $selected[] = $c;
            $accum += $len;
        }
        if ($accum >= $remainingBudget) break;
    }

    usort($selected, fn($a, $b) => $a['offset'] <=> $b['offset']);

    $combined = $introText . "\n\n[... Additional relevant document sections ...]\n\n";
    foreach ($selected as $s) {
        $combined .= "\n" . trim($s['text']) . "\n";
    }

    return $combined;
}

$q = "What is this document about?";
$ctx = testExtractMb($fullText, $q, 28000);

echo "Extracted context mb_check_encoding: " . (mb_check_encoding($ctx, 'UTF-8') ? 'VALID' : 'INVALID') . "\n";
echo "Context length: " . mb_strlen($ctx, 'UTF-8') . " chars\n";

$reqBody = [
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => $ctx]
            ]
        ]
    ]
];

$encoded = json_encode(
    $reqBody,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);

echo "json_encode result: " . ($encoded !== false ? "SUCCESS (" . strlen($encoded) . " bytes)" : "FAILED: " . json_last_error_msg()) . "\n";
