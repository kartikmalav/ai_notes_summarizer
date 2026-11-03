<?php
/**
 * Comprehensive Functional Verification Test Suite
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gemini.php';
require_once __DIR__ . '/../includes/document-extractor.php';

$testsPassed = 0;
$testsFailed = 0;

function assert_test($description, $condition) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $testsFailed++;
    }
}

echo "=======================================================\n";
echo "1. DATABASE CONNECTION & SCHEMA VERIFICATION\n";
echo "=======================================================\n";
try {
    $pdo = getDBConnection();
    assert_test("Database PDO connection established", $pdo instanceof PDO);

    // Verify active port
    $portStmt = $pdo->query("SHOW VARIABLES LIKE 'port'");
    $portRow = $portStmt->fetch();
    $activePort = $portRow['Value'] ?? 'unknown';
    echo "  (Active MySQL server port: {$activePort})\n";
    assert_test("Connected to active MySQL instance", !empty($activePort));

    // Verify tables exist
    $tables = ['users', 'pdf_documents', 'generated_content'];
    foreach ($tables as $t) {
        $check = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetch();
        assert_test("Table '{$t}' exists in database", !empty($check));
    }

    // Verify record counts
    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $docCount = (int) $pdo->query("SELECT COUNT(*) FROM pdf_documents")->fetchColumn();
    $genCount = (int) $pdo->query("SELECT COUNT(*) FROM generated_content")->fetchColumn();
    echo "  (Found: {$userCount} users, {$docCount} documents, {$genCount} generated content items)\n";
    assert_test("Existing user records preserved", $userCount >= 3);
    assert_test("Existing document records preserved", $docCount >= 3);

} catch (Exception $e) {
    assert_test("Database connection failed: " . $e->getMessage(), false);
}

echo "\n=======================================================\n";
echo "2. GEMINI AI CONFIGURATION & MODEL VERIFICATION\n";
echo "=======================================================\n";
$supportedModels = get_supported_gemini_models();
assert_test("Supported Gemini models defined", count($supportedModels) >= 4);

$activeModel = get_active_gemini_model();
echo "  (Active Gemini model: '{$activeModel}')\n";
assert_test("Active Gemini model is supported by Google API", in_array($activeModel, $supportedModels, true));
assert_test("Model is NOT the invalid 'gemini-3.6-flash'", $activeModel !== 'gemini-3.6-flash');

// Test call_gemini_api without API key gives clean error (no fake output)
$aiTest = call_gemini_api("Test prompt");
echo "  (Call result with current env: " . ($aiTest['success'] ? 'SUCCESS' : 'HONEST ERROR: ' . substr($aiTest['error'] ?? '', 0, 70)) . "...)\n";
if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
    assert_test("Honest error returned when API key unconfigured (no fake AI)", !$aiTest['success'] && strpos($aiTest['error'], 'not configured') !== false);
} else {
    assert_test("API call executed with configured key", isset($aiTest['success']));
}

echo "\n=======================================================\n";
echo "3. DOCUMENT TEXT EXTRACTION VERIFICATION\n";
echo "=======================================================\n";
$uploads = scandir('C:\\xampp\\htdocs\\ai_notes_summarizer\\uploads');
$pdfFiles = array_filter($uploads, function($f) { return substr($f, -4) === '.pdf'; });
$firstPdf = !empty($pdfFiles) ? 'C:\\xampp\\htdocs\\ai_notes_summarizer\\uploads\\' . reset($pdfFiles) : null;

if ($firstPdf) {
    $textRes = extract_document_text($firstPdf, 'pdf');
    assert_test("PDF text extracted via extractor", $textRes['success'] && strlen($textRes['text']) > 100);
    echo "  (Extracted " . strlen($textRes['text']) . " characters from " . basename($firstPdf) . ")\n";
} else {
    echo "  (No sample PDF found in uploads)\n";
}

// Test corrupted file handling
$corruptFile = 'C:\\xampp\\htdocs\\ai_notes_summarizer\\scratch\\corrupt.pdf';
file_put_contents($corruptFile, "NOT A REAL PDF FILE 12345");
$corruptRes = extract_pdf_content($corruptFile);
assert_test("Corrupted PDF rejected with clear error", !$corruptRes['success'] && strpos($corruptRes['error'], 'Invalid file format') !== false);
@unlink($corruptFile);

// Test encrypted PDF detection
$encFile = 'C:\\xampp\\htdocs\\ai_notes_summarizer\\scratch\\encrypted.pdf';
file_put_contents($encFile, "%PDF-1.4\n1 0 obj\n<< /Encrypt 2 0 R >>\nendobj\n");
$encRes = extract_pdf_content($encFile);
assert_test("Encrypted PDF detected with clear error", !$encRes['success'] && strpos($encRes['error'], 'Password-protected') !== false);
@unlink($encFile);

echo "\n=======================================================\n";
echo "4. SECURITY & API ACCESS CONTROL VERIFICATION\n";
echo "=======================================================\n";
// Test unauthenticated API requests via Apache HTTP
$apiEndpoints = [
    'api/generate-summary.php',
    'api/generate-questions.php',
    'api/generate-exam-prep.php',
    'api/generate-mcqs.php',
    'api/ask-document.php'
];

foreach ($apiEndpoints as $ep) {
    $ch = curl_init("http://127.0.0.1/ai_notes_summarizer/" . $ep);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['pdf_id' => 4]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    assert_test("Unauthorized POST to {$ep} returns HTTP 401", $httpCode === 401);
}

// Test CSRF token generation and validation
$_SESSION = [];
$t1 = get_csrf_token();
assert_test("CSRF token generated (64 hex chars)", strlen($t1) === 64);
assert_test("CSRF token validates correctly with identical token", validate_csrf_token($t1));
assert_test("CSRF token rejects invalid/spoofed token", !validate_csrf_token("invalid_token_123456"));

echo "\n=======================================================\n";
echo "5. HTTP SERVER ENDPOINT ACCESSIBILITY (APACHE)\n";
echo "=======================================================\n";
$pages = [
    'login.php',
    'signup.php',
    'db-test.php'
];

foreach ($pages as $p) {
    $ch = curl_init("http://127.0.0.1/ai_notes_summarizer/" . $p);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    assert_test("Apache serves http://127.0.0.1/ai_notes_summarizer/{$p} (HTTP 200)", $code === 200);
}

echo "\n=======================================================\n";
echo "TEST RESULTS SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "=======================================================\n";
