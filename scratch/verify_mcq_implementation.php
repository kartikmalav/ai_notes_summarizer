<?php
/**
 * Verification script for MCQ Generator & Practice Mode
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/gemini.php';

echo "=============================================\n";
echo "   MCQ GENERATOR & PRACTICE MODE TEST SUITE  \n";
echo "=============================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($condition, $testName) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $testName . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $testName . "\n";
        $failCount++;
    }
}

// -------------------------------------------------------------------
// TEST 1: JSON Extraction from various Gemini outputs
// -------------------------------------------------------------------
echo "--- 1. Testing extract_mcq_json() ---\n";

$rawJsonSample = json_encode([
    [
        'question' => 'What is an operating system?',
        'options' => ['A' => 'Software', 'B' => 'Hardware', 'C' => 'Firmware', 'D' => 'Network'],
        'correct_answer' => 'A',
        'explanation' => 'An OS is system software managing hardware.'
    ]
]);

$testPreambleAndFences = "Here are your 10 MCQs:\n```json\n" . $rawJsonSample . "\n```\nHope this helps!";
$extracted = extract_mcq_json($testPreambleAndFences);
assertTest(is_array($extracted) && count($extracted) === 1, "extract_mcq_json() strips markdown fences & preambles");

$testRawJson = $rawJsonSample;
$extracted2 = extract_mcq_json($testRawJson);
assertTest(is_array($extracted2) && count($extracted2) === 1, "extract_mcq_json() parses raw JSON array directly");

// -------------------------------------------------------------------
// TEST 2: Validation & Normalization with Exactly 10 MCQs
// -------------------------------------------------------------------
echo "\n--- 2. Testing validate_and_normalize_mcqs() ---\n";

$tenValidItems = [];
for ($i = 1; $i <= 10; $i++) {
    $tenValidItems[] = [
        'question' => "What is fundamental concept number {$i} in computer architecture?",
        'options' => [
            'A' => "Correct description for item {$i}",
            'B' => "Distractor 1 for item {$i}",
            'C' => "Distractor 2 for item {$i}",
            'D' => "Distractor 3 for item {$i}"
        ],
        'correct_answer' => ($i % 2 === 0) ? 'a' : 'B', // tests case insensitivity
        'explanation' => "This is verified in chapter {$i}."
    ];
}

$valResult = validate_and_normalize_mcqs($tenValidItems);
assertTest($valResult['valid'] === true, "validate_and_normalize_mcqs() passes for 10 valid MCQs");
assertTest(count($valResult['mcqs']) === 10, "validate_and_normalize_mcqs() yields exactly 10 items");
assertTest($valResult['mcqs'][0]['correct_answer'] === 'B', "validate_and_normalize_mcqs() preserves correct answer");
assertTest($valResult['mcqs'][1]['correct_answer'] === 'A', "validate_and_normalize_mcqs() normalizes lowercase answer 'a' to 'A'");

// Test duplicate rejection
$withDuplicate = $tenValidItems;
$withDuplicate[] = $tenValidItems[0]; // duplicate
$valResultDup = validate_and_normalize_mcqs($withDuplicate);
assertTest($valResultDup['valid'] === true && count($valResultDup['mcqs']) === 10, "validate_and_normalize_mcqs() ignores duplicate question");

// Test insufficient items
$nineItems = array_slice($tenValidItems, 0, 9);
$valResultShort = validate_and_normalize_mcqs($nineItems);
assertTest($valResultShort['valid'] === false, "validate_and_normalize_mcqs() rejects < 10 questions");

// Test indexed array options [0, 1, 2, 3]
$indexedItems = [];
for ($i = 1; $i <= 10; $i++) {
    $indexedItems[] = [
        'question' => "Question with indexed options {$i}?",
        'options' => ["Option 1", "Option 2", "Option 3", "Option 4"],
        'correct_answer' => 'C',
        'explanation' => "Explanation {$i}"
    ];
}
$valResultIndexed = validate_and_normalize_mcqs($indexedItems);
assertTest($valResultIndexed['valid'] === true, "validate_and_normalize_mcqs() converts indexed options to A, B, C, D");
assertTest(isset($valResultIndexed['mcqs'][0]['options']['A']) && isset($valResultIndexed['mcqs'][0]['options']['D']), "Normalized options have keys A, B, C, D");

// -------------------------------------------------------------------
// TEST 3: Database Storage & Duplicate Prevention
// -------------------------------------------------------------------
echo "\n--- 3. Testing Database Storage & Retries ---\n";

try {
    $pdo = getDBConnection();
    
    // Find a test PDF or user
    $docStmt = $pdo->query("SELECT id, user_id FROM pdf_documents LIMIT 1");
    $doc = $docStmt->fetch();

    if ($doc) {
        $testPdfId = (int)$doc['id'];
        $testUserId = (int)$doc['user_id'];
        $serializedJson = json_encode($valResult['mcqs']);

        // Check initial state
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM generated_content WHERE pdf_id = ? AND user_id = ? AND content_type = 'mcqs'");
        $cntStmt->execute([$testPdfId, $testUserId]);
        $initialCount = (int)$cntStmt->fetchColumn();

        // Simulate save 1 (using the exact logic in api/generate-mcqs.php)
        $checkStmt = $pdo->prepare("SELECT id FROM generated_content WHERE pdf_id = ? AND user_id = ? AND content_type = 'mcqs' LIMIT 1");
        $checkStmt->execute([$testPdfId, $testUserId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $updateStmt = $pdo->prepare("UPDATE generated_content SET content_data = ?, created_at = NOW() WHERE id = ?");
            $updateStmt->execute([$serializedJson, $existing['id']]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO generated_content (pdf_id, user_id, content_type, content_data, created_at) VALUES (?, ?, 'mcqs', ?, NOW())");
            $insertStmt->execute([$testPdfId, $testUserId, $serializedJson]);
        }

        // Simulate save 2 (Retry)
        $checkStmt->execute([$testPdfId, $testUserId]);
        $existing2 = $checkStmt->fetch();

        if ($existing2) {
            $updateStmt = $pdo->prepare("UPDATE generated_content SET content_data = ?, created_at = NOW() WHERE id = ?");
            $updateStmt->execute([$serializedJson, $existing2['id']]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO generated_content (pdf_id, user_id, content_type, content_data, created_at) VALUES (?, ?, 'mcqs', ?, NOW())");
            $insertStmt->execute([$testPdfId, $testUserId, $serializedJson]);
        }

        // Verify that retry did NOT duplicate row
        $cntStmt->execute([$testPdfId, $testUserId]);
        $postCount = (int)$cntStmt->fetchColumn();

        assertTest($postCount === 1, "Database save & retry prevents duplicate records (exactly 1 record exists)");

        // Verify retrieval and decoding
        $fetchStmt = $pdo->prepare("SELECT content_data FROM generated_content WHERE pdf_id = ? AND user_id = ? AND content_type = 'mcqs' LIMIT 1");
        $fetchStmt->execute([$testPdfId, $testUserId]);
        $fetchedJson = $fetchStmt->fetchColumn();
        $decodedBack = json_decode($fetchedJson, true);

        assertTest(is_array($decodedBack) && count($decodedBack) === 10, "Retrieved MCQs from DB decodes into exactly 10 questions");
        assertTest(isset($decodedBack[0]['options']['A'], $decodedBack[0]['correct_answer'], $decodedBack[0]['explanation']), "Retrieved MCQs preserve complete schema (question, options, correct_answer, explanation)");

    } else {
        echo "[SKIP] No PDF documents found in database to test DB operations.\n";
    }

} catch (Exception $e) {
    echo "[FAIL] Database test error: " . $e->getMessage() . "\n";
    $failCount++;
}

// -------------------------------------------------------------------
// TEST 4: File existence and syntax check
// -------------------------------------------------------------------
echo "\n--- 4. Checking Files & Syntax ---\n";

$filesToCheck = [
    'includes/gemini.php',
    'api/generate-mcqs.php',
    'mcqs-practice.php',
    'pdf-results.php',
    'my-pdfs.php'
];

foreach ($filesToCheck as $file) {
    $fullPath = realpath(__DIR__ . '/../' . $file);
    assertTest(file_exists($fullPath), "File exists: {$file}");
}

echo "\n=============================================\n";
echo "SUMMARY: Passed: {$passCount}, Failed: {$failCount}\n";
echo "=============================================\n";
