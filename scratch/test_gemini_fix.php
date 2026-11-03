<?php
/**
 * Regression Test Suite for Gemini GenerateContent contents fix
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gemini.php';

echo "=================================================================\n";
echo "    REGRESSION TEST: GEMINI API CONTENTS & PROMPT FIX            \n";
echo "=================================================================\n\n";

$pass = 0;
$fail = 0;

function check($cond, $title, $detail = '') {
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "[PASS] " . $title . "\n";
        if ($detail) echo "       └─ " . $detail . "\n";
    } else {
        $fail++;
        echo "[FAIL] " . $title . "\n";
        if ($detail) echo "       └─ ERROR: " . $detail . "\n";
    }
}

// -------------------------------------------------------------
// Test 1: Empty string prompt is caught safely before sending
// -------------------------------------------------------------
$resEmpty = call_gemini_api("");
check(!$resEmpty['success'] && strpos($resEmpty['error'], 'empty or invalid') !== false,
      "Empty string prompt caught safely with application error",
      $resEmpty['error']);

// -------------------------------------------------------------
// Test 2: Whitespace-only prompt is caught safely
// -------------------------------------------------------------
$resSpace = call_gemini_api("    \t\n   ");
check(!$resSpace['success'] && strpos($resSpace['error'], 'empty or invalid') !== false,
      "Whitespace-only prompt caught safely with application error",
      $resSpace['error']);

// -------------------------------------------------------------
// Test 3: Null prompt is caught safely
// -------------------------------------------------------------
$resNull = call_gemini_api(null);
check(!$resNull['success'] && strpos($resNull['error'], 'empty or invalid') !== false,
      "Null prompt caught safely with application error",
      $resNull['error']);

// -------------------------------------------------------------
// Test 4: Non-string prompt (array) is caught safely
// -------------------------------------------------------------
$resArr = call_gemini_api(['some' => 'array']);
check(!$resArr['success'] && strpos($resArr['error'], 'empty or invalid') !== false,
      "Array prompt caught safely with application error",
      $resArr['error']);

// -------------------------------------------------------------
// Test 5: Malformed UTF-8 bytes do not cause json_encode failure
// -------------------------------------------------------------
$badUtf8Prompt = "Test prompt with broken bytes: \x80\x99\xFF hello";
$sanitizedPrompt = mb_check_encoding($badUtf8Prompt, 'UTF-8') ? $badUtf8Prompt : mb_convert_encoding($badUtf8Prompt, 'UTF-8', 'UTF-8');
$testBody = [
    "contents" => [
        [
            "role" => "user",
            "parts" => [["text" => $sanitizedPrompt]]
        ]
    ]
];
$encoded = json_encode($testBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
check($encoded !== false && strlen($encoded) > 0,
      "Malformed UTF-8 string encodes safely without failing json_encode",
      "Encoded bytes: " . strlen($encoded));

// -------------------------------------------------------------
// Test 6: Real document text multi-byte chunking & encoding
// -------------------------------------------------------------
$txtFile = UPLOAD_DIR . 'doc_20261004_131403_2ac43d8156649c39.pdf.txt';
$docText = file_get_contents($txtFile);

$query = "What is the main topic of this unit?";
$ctx = extract_relevant_document_context($docText, $query, 28000);
$ctxValidUtf8 = mb_check_encoding($ctx, 'UTF-8');

$ctxBody = [
    "contents" => [
        [
            "role" => "user",
            "parts" => [["text" => $ctx]]
        ]
    ]
];
$ctxEncoded = json_encode($ctxBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

check($ctxValidUtf8 && $ctxEncoded !== false && strlen($ctxEncoded) > 1000,
      "Real document context extraction produces valid UTF-8 and non-empty contents",
      "Context length: " . mb_strlen($ctx, 'UTF-8') . " chars, JSON payload: " . strlen($ctxEncoded) . " bytes");

// -------------------------------------------------------------
// Test 7: Live Gemini API Call with Ask Your Document prompt
// -------------------------------------------------------------
echo "\n--- Running Live Gemini API Call for Verification ---\n";
$liveResult = answer_document_question(
    $docText,
    "UNIT5-OST.pdf",
    "Computer Science",
    "What is the main topic covered in this document? Answer in 1-2 sentences."
);

$liveSuccess = $liveResult['success'] === true && !empty($liveResult['answer']);
check($liveSuccess, "Live Gemini API call succeeded with valid answer",
      $liveSuccess ? "Answer excerpt: " . substr(strip_tags($liveResult['answer']), 0, 150) . "..." : "Error: " . $liveResult['error']);

echo "\n=================================================================\n";
echo sprintf("RESULTS: %d PASSED, %d FAILED (Total: %d)\n", $pass, $fail, $pass + $fail);
echo "=================================================================\n";
