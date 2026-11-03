<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gemini.php';

$txtPath = UPLOAD_DIR . 'doc_20261004_131403_2ac43d8156649c39.pdf.txt';
$text = file_get_contents($txtPath);

// Check if entire text is valid UTF-8
echo "Full text mb_check_encoding: " . (mb_check_encoding($text, 'UTF-8') ? 'VALID' : 'INVALID') . "\n";

$introLength = min(3500, (int)(28000 * 0.15));
$introText = substr($text, 0, $introLength);
echo "introText mb_check_encoding: " . (mb_check_encoding($introText, 'UTF-8') ? 'VALID' : 'INVALID') . "\n";

$bodyText = substr($text, $introLength);
echo "bodyText mb_check_encoding: " . (mb_check_encoding($bodyText, 'UTF-8') ? 'VALID' : 'INVALID') . "\n";

// Let's check mb_substr vs substr
$mbIntro = mb_substr($text, 0, $introLength, 'UTF-8');
echo "mb_substr intro mb_check_encoding: " . (mb_check_encoding($mbIntro, 'UTF-8') ? 'VALID' : 'INVALID') . "\n";
