<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gemini.php';

$txtPath = UPLOAD_DIR . 'doc_20261004_131403_2ac43d8156649c39.pdf.txt';
$fullText = file_get_contents($txtPath);

$maxChars = 28000;
$introLength = min(3500, (int)($maxChars * 0.15));
$bodyText = substr($fullText, $introLength);

$chunkSize = 1800;
$overlap = 200;
$step = $chunkSize - $overlap;
$bodyLen = strlen($bodyText);

for ($offset = 0; $offset < $bodyLen; $offset += $step) {
    $chunk = substr($bodyText, $offset, $chunkSize);
    if (!mb_check_encoding($chunk, 'UTF-8')) {
        echo "Chunk at offset $offset is INVALID UTF-8! Length: " . strlen($chunk) . "\n";
        // Show boundary bytes
        echo "End bytes: " . bin2hex(substr($chunk, -5)) . "\n";
        echo "Start bytes: " . bin2hex(substr($chunk, 0, 5)) . "\n";
    }
}
