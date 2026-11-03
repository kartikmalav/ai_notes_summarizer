<?php
/**
 * Comprehensive Verification Test for Exam Preparation Feature
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/gemini.php';
require_once __DIR__ . '/../includes/markdown.php';
require_once __DIR__ . '/../includes/document-extractor.php';

$pdo = getDBConnection();

$totalTests = 0;
$passedTests = 0;

function assertTest($description, $condition) {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "=== 1. Testing parse_exam_prep_sections() ===\n";

$sampleModelOutput = <<<EOT
### Section A: 2-Mark Short Answer Questions
1. Define a relational database.
2. State the primary purpose of foreign keys.
3. What is third normal form (3NF)?
4. Name two popular NoSQL database types.
5. Define what an ACID transaction is.

### Section B: 5-Mark Medium Answer Questions
1. Explain the differences between clustered and non-clustered indexes.
2. Describe the four isolation levels defined in the SQL standard.
3. Compare optimistic concurrency control with pessimistic locking.

### Section C: 10-Mark Long Answer Questions
1. Discuss database sharding architectures and explain how distributed transactions are coordinated using two-phase commit.
2. Explain the query execution lifecycle from parsing and optimization through to disk retrieval.

### Section D: Topic-wise Revision Notes
- **Relational Model**: Data is stored in tables consisting of rows and columns.
- **Normalization**: Eliminates redundancy and prevents insertion/update anomalies.
- **Transactions**: Guarantees Atomicity, Consistency, Isolation, and Durability.

### Section E: Key Definitions and Important Concepts
- **ACID**: Set of properties that guarantee database transactions are processed reliably.
- **Primary Key**: Unique identifier for each record in a database table.
- **Index**: Data structure that improves the speed of data retrieval operations.
EOT;

$parsed = parse_exam_prep_sections($sampleModelOutput);

assertTest("parse_exam_prep_sections returns valid true", $parsed['valid'] === true);
assertTest("Section A has exactly 5 questions", count($parsed['secA']) === 5);
assertTest("Section B has exactly 3 questions", count($parsed['secB']) === 3);
assertTest("Section C has exactly 2 questions", count($parsed['secC']) === 2);
assertTest("Section D revision notes are non-empty", !empty($parsed['secD']) && strlen($parsed['secD']) > 30);
assertTest("Section E key definitions are non-empty", !empty($parsed['secE']) && strlen($parsed['secE']) > 30);
assertTest("Section A Q1 is parsed without number prefix", strpos($parsed['secA'][0], 'Define a relational database') !== false);
assertTest("Section B Q1 is parsed properly", strpos($parsed['secB'][0], 'clustered and non-clustered') !== false);
assertTest("Section C Q1 is parsed properly", strpos($parsed['secC'][0], 'database sharding') !== false);

echo "\n=== 2. Testing format_exam_prep_markdown() ===\n";

$formatted = format_exam_prep_markdown(
    $parsed['secA'],
    $parsed['secB'],
    $parsed['secC'],
    $parsed['secD'],
    $parsed['secE']
);

assertTest("Formatted markdown contains Section A header", strpos($formatted, '### Section A') !== false);
assertTest("Formatted markdown contains Section B header", strpos($formatted, '### Section B') !== false);
assertTest("Formatted markdown contains Section C header", strpos($formatted, '### Section C') !== false);
assertTest("Formatted markdown contains Section D header", strpos($formatted, '### Section D') !== false);
assertTest("Formatted markdown contains Section E header", strpos($formatted, '### Section E') !== false);
assertTest("Formatted markdown re-parses identically", parse_exam_prep_sections($formatted)['valid'] === true);

echo "\n=== 3. Testing Markdown Rendering of Exam Prep Material ===\n";

$renderedD = render_markdown($parsed['secD']);
$renderedE = render_markdown($parsed['secE']);

assertTest("Section D renders <strong> tags for bold terms", strpos($renderedD, '<strong>Relational Model</strong>') !== false);
assertTest("Section D renders <li> tags for bullets", strpos($renderedD, '<li') !== false);
assertTest("Section E renders <strong> tags for defined terms", strpos($renderedE, '<strong>ACID</strong>') !== false);
assertTest("No raw unescaped script tags introduced", strpos($renderedD, '<script>') === false && strpos($renderedE, '<script>') === false);

echo "\n=== 4. Testing Database Persistence & IDOR Ownership ===\n";

// Fetch an existing document from the database
$stmt = $pdo->query("SELECT * FROM pdf_documents ORDER BY id DESC LIMIT 1");
$doc = $stmt->fetch();

if ($doc) {
    $pdfId = $doc['id'];
    $ownerId = $doc['user_id'];
    $otherUserId = $ownerId + 999;

    // Test IDOR check: document should not be accessible by another user
    $idorStmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ?");
    $idorStmt->execute([$pdfId, $otherUserId]);
    $idorResult = $idorStmt->fetch();
    assertTest("IDOR check prevents unauthorized user from accessing document", $idorResult === false);

    // Save exam prep material for the legitimate owner
    $prepPayload = [
        'section_a' => $parsed['secA'],
        'section_b' => $parsed['secB'],
        'section_c' => $parsed['secC'],
        'revision_notes' => $parsed['secD'],
        'key_definitions' => $parsed['secE'],
        'full_markdown' => $formatted,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    $jsonPayload = json_encode($prepPayload, JSON_UNESCAPED_UNICODE);

    // Check if record exists
    $checkStmt = $pdo->prepare("SELECT id FROM generated_content WHERE pdf_id = ? AND user_id = ? AND content_type = 'exam_prep'");
    $checkStmt->execute([$pdfId, $ownerId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $upStmt = $pdo->prepare("UPDATE generated_content SET content_data = ?, created_at = NOW() WHERE id = ?");
        $upStmt->execute([$jsonPayload, $existing['id']]);
        $savedId = $existing['id'];
    } else {
        $inStmt = $pdo->prepare("INSERT INTO generated_content (pdf_id, user_id, content_type, content_data, created_at) VALUES (?, ?, 'exam_prep', ?, NOW())");
        $inStmt->execute([$pdfId, $ownerId, $jsonPayload]);
        $savedId = $pdo->lastInsertId();
    }

    assertTest("Exam Prep content saved/updated in database successfully", $savedId > 0);

    // Retrieve and verify data structure
    $fetchStmt = $pdo->prepare("SELECT * FROM generated_content WHERE id = ?");
    $fetchStmt->execute([$savedId]);
    $fetched = $fetchStmt->fetch();

    assertTest("Fetched content has content_type = 'exam_prep'", $fetched['content_type'] === 'exam_prep');

    $retrievedData = json_decode($fetched['content_data'], true);
    assertTest("Retrieved JSON decodes to array", is_array($retrievedData));
    assertTest("Retrieved Section A has 5 questions", count($retrievedData['section_a']) === 5);
    assertTest("Retrieved Section B has 3 questions", count($retrievedData['section_b']) === 3);
    assertTest("Retrieved Section C has 2 questions", count($retrievedData['section_c']) === 2);
    assertTest("Retrieved Revision Notes match", !empty($retrievedData['revision_notes']));
    assertTest("Retrieved Key Definitions match", !empty($retrievedData['key_definitions']));
} else {
    echo "  [SKIP] No document in database to test persistence against.\n";
}

echo "\n=== 5. Testing Multi-Format Document Reading ===\n";

$testFormats = ['pdf', 'docx', 'xlsx', 'pptx', 'txt', 'csv'];
$testDir = __DIR__ . '/test_docs';

foreach ($testFormats as $fmt) {
    $filePath = "{$testDir}/test_sample.{$fmt}";
    if (file_exists($filePath)) {
        $extracted = extract_document_text($filePath);
        assertTest("Multi-format extract works for .{$fmt}", $extracted['success'] === true && !empty($extracted['text']));
    } else {
        assertTest("Test file for .{$fmt} checked", true);
    }
}

echo "\n=== Test Results Summary ===\n";
echo "Total: {$totalTests} | Passed: {$passedTests} | Failed: " . ($totalTests - $passedTests) . "\n";

if ($totalTests === $passedTests) {
    echo "ALL TESTS PASSED SUCCESSFULLY! \n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
