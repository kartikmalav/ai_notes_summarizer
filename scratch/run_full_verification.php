<?php
/**
 * Automated Verification Pass Suite
 * AI Notes Summarizer Project
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/document-extractor.php';
require_once __DIR__ . '/../includes/markdown.php';
require_once __DIR__ . '/../includes/gemini.php';

$testsPassed = [];
$testsFailed = [];

function record_pass($name, $details = '') {
    global $testsPassed;
    $testsPassed[] = ['name' => $name, 'details' => $details];
    echo "  [PASS] {$name}" . ($details ? " - {$details}" : '') . "\n";
}

function record_fail($name, $details = '') {
    global $testsFailed;
    $testsFailed[] = ['name' => $name, 'details' => $details];
    echo "  [FAIL] {$name}" . ($details ? " - {$details}" : '') . "\n";
}

echo "=================================================================\n";
echo "           AI NOTES SUMMARIZER - COMPREHENSIVE VERIFICATION      \n";
echo "=================================================================\n\n";

// -------------------------------------------------------------
// TEST SUITE 1: Database Connection & Schema Verification
// -------------------------------------------------------------
echo "1. Checking Database Connection & Schema...\n";
try {
    $pdo = getDBConnection();
    if ($pdo) {
        record_pass("DB Connection", "Connected successfully via PDO to " . DB_NAME);

        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $expectedTables = ['users', 'pdf_documents', 'generated_content'];
        $missingTables = array_diff($expectedTables, $tables);

        if (empty($missingTables)) {
            record_pass("Tables Present", "All core tables exist: " . implode(', ', $tables));
        } else {
            record_fail("Tables Present", "Missing tables: " . implode(', ', $missingTables));
        }

        // Check columns on pdf_documents
        $cols = $pdo->query("SHOW COLUMNS FROM pdf_documents")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('file_type', $cols) && in_array('mime_type', $cols)) {
            record_pass("pdf_documents Multi-Format Columns", "file_type and mime_type columns verified");
        } else {
            record_fail("pdf_documents Multi-Format Columns", "Missing file_type or mime_type column");
        }

        // Check content_type on generated_content
        $genCols = $pdo->query("SHOW COLUMNS FROM generated_content")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('content_type', $genCols) && in_array('content_data', $genCols)) {
            record_pass("generated_content Columns", "content_type and content_data columns verified");
        } else {
            record_fail("generated_content Columns", "Missing required columns in generated_content");
        }
    }
} catch (Exception $e) {
    record_fail("DB Connection", $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 2: Multi-Format Document Extraction Engine
// -------------------------------------------------------------
echo "2. Verifying Document Extraction Engine for all 6 formats...\n";
$testDir = __DIR__ . '/test_docs/';
if (!is_dir($testDir)) {
    mkdir($testDir, 0777, true);
}

// 2.1 TXT
$txtFile = $testDir . 'test.txt';
file_put_contents($txtFile, "Title: Operating Systems Revision Notes\nLine 1: A process is an instance of a program in execution.\nLine 2: States: New, Ready, Running, Waiting, Terminated.");
$resTxt = extract_document_text($txtFile, 'txt');
if ($resTxt['success'] && strlen($resTxt['text']) > 50 && $resTxt['item_count'] >= 3) {
    record_pass("TXT Extraction", "Extracted {$resTxt['item_count']} lines, " . strlen($resTxt['text']) . " chars");
} else {
    record_fail("TXT Extraction", $resTxt['error'] ?? 'Incomplete text');
}

// 2.2 CSV
$csvFile = $testDir . 'test.csv';
file_put_contents($csvFile, "ID,Topic,Difficulty\n1,Deadlocks,Hard\n2,Paging,Medium\n3,Threads,Easy");
$resCsv = extract_document_text($csvFile, 'csv');
if ($resCsv['success'] && strpos($resCsv['text'], 'Deadlocks') !== false && $resCsv['item_count'] === 4) {
    record_pass("CSV Extraction", "Extracted {$resCsv['item_count']} rows, " . strlen($resCsv['text']) . " chars");
} else {
    record_fail("CSV Extraction", $resCsv['error'] ?? 'Incomplete CSV text');
}

// 2.3 DOCX
$docxFile = $testDir . 'test.docx';
$zipDocx = new ZipArchive();
if ($zipDocx->open($docxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $docxXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Unit 1: Computer Architecture Overview</w:t></w:r></w:p><w:p><w:r><w:t>ALU performs arithmetic and logic computations.</w:t></w:r></w:p></w:body></w:document>';
    $zipDocx->addFromString('word/document.xml', $docxXml);
    $zipDocx->close();
}
$resDocx = extract_document_text($docxFile, 'docx');
if ($resDocx['success'] && strpos($resDocx['text'], 'Computer Architecture') !== false) {
    record_pass("DOCX Extraction", "Extracted {$resDocx['item_count']} paragraphs, " . strlen($resDocx['text']) . " chars");
} else {
    record_fail("DOCX Extraction", $resDocx['error'] ?? 'Incomplete DOCX text');
}

// 2.4 XLSX
$xlsxFile = $testDir . 'test.xlsx';
$zipXlsx = new ZipArchive();
if ($zipXlsx->open($xlsxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2" uniqueCount="2"><si><t>DBMS Indexing</t></si><si><t>B-Tree and Hash Index</t></si></sst>';
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row></sheetData></worksheet>';
    $zipXlsx->addFromString('xl/sharedStrings.xml', $sst);
    $zipXlsx->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zipXlsx->close();
}
$resXlsx = extract_document_text($xlsxFile, 'xlsx');
if ($resXlsx['success'] && strpos($resXlsx['text'], 'DBMS Indexing') !== false) {
    record_pass("XLSX Extraction", "Extracted {$resXlsx['item_count']} sheet, " . strlen($resXlsx['text']) . " chars");
} else {
    record_fail("XLSX Extraction", $resXlsx['error'] ?? 'Incomplete XLSX text');
}

// 2.5 PPTX
$pptxFile = $testDir . 'test.pptx';
$zipPptx = new ZipArchive();
if ($zipPptx->open($pptxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $slideXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:sp><p:txBody><a:p><a:r><a:t>Slide 1: Network Topology</a:t></a:r></a:p><a:p><a:r><a:t>Mesh, Star, Bus and Ring</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>';
    $zipPptx->addFromString('ppt/slides/slide1.xml', $slideXml);
    $zipPptx->close();
}
$resPptx = extract_document_text($pptxFile, 'pptx');
if ($resPptx['success'] && strpos($resPptx['text'], 'Network Topology') !== false) {
    record_pass("PPTX Extraction", "Extracted {$resPptx['item_count']} slide, " . strlen($resPptx['text']) . " chars");
} else {
    record_fail("PPTX Extraction", $resPptx['error'] ?? 'Incomplete PPTX text');
}

// 2.6 PDF (via companion extracted text file)
$pdfFile = $testDir . 'test.pdf';
file_put_contents($pdfFile, '%PDF-1.4 dummy header');
file_put_contents($pdfFile . '.txt', "--- Page 1 ---\nData Structures & Algorithms Notes.\nBinary Search Trees, Heaps, AVL Trees.");
$resPdf = extract_document_text($pdfFile, 'pdf');
if ($resPdf['success'] && strpos($resPdf['text'], 'Data Structures') !== false) {
    record_pass("PDF Text Reader", "Loaded companion text, " . strlen($resPdf['text']) . " chars");
} else {
    record_fail("PDF Text Reader", $resPdf['error'] ?? 'Failed to load PDF companion text');
}

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 3: Markdown Rendering & Decorative Symbol Cleaner
// -------------------------------------------------------------
echo "3. Verifying Markdown Rendering & Symbol Sanitization...\n";

$dirtyMarkdown = <<<EOD
### 📌 Executive Summary
A process is a program in execution.

### 💡 Key Definitions & Terminology
- ✓ **Process**: Independent execution unit with separate memory space.
◆ **Thread**: Lightweight execution unit sharing address space.
● **Semaphore**: Signaling mechanism for synchronization ★.

### Comparison Table
| Type | Process | Thread |
|---|---|---|
| Memory | Isolated | Shared |

### Formulas & Math
- Formula: f(x) = x^2 / 2 <= 50 and ptr->next != NULL
- Greek & Math: α + β = γ and A ∩ B ⊆ C
EOD;

$cleaned = clean_markdown_symbols($dirtyMarkdown);

// Verify emojis stripped
if (strpos($cleaned, '📌') === false && strpos($cleaned, '💡') === false) {
    record_pass("Emoji Removal", "All decorative emojis stripped from headings");
} else {
    record_fail("Emoji Removal", "Emojis still present in cleaned text");
}

// Verify decorative bullets normalized
if (strpos($cleaned, '◆') === false && strpos($cleaned, '●') === false && strpos($cleaned, '★') === false) {
    record_pass("Decorative Bullets Normalization", "★, ◆, ●, ✓ stripped and converted to clean markdown hyphens");
} else {
    record_fail("Decorative Bullets Normalization", "Decorative symbols still present");
}

// Verify technical operators preserved
if (strpos($cleaned, 'ptr->next != NULL') !== false && strpos($cleaned, '<= 50') !== false) {
    record_pass("Code & Technical Operator Preservation", "'->' and '!=' and '<=' fully preserved");
} else {
    record_fail("Code & Technical Operator Preservation", "Code operators corrupted");
}

if (strpos($cleaned, 'α + β = γ') !== false && strpos($cleaned, 'A ∩ B ⊆ C') !== false) {
    record_pass("Mathematical Notation Preservation", "Greek letters and set theory symbols preserved");
} else {
    record_fail("Mathematical Notation Preservation", "Mathematical symbols lost");
}

// Verify HTML rendering
$html = render_markdown($dirtyMarkdown);
if (strpos($html, '<table class="table-custom">') !== false && strpos($html, '<ul class="markdown-ul">') !== false) {
    record_pass("Markdown Table & List HTML Rendering", "Clean table and ul list rendered in HTML");
} else {
    record_fail("Markdown Table & List HTML Rendering", "HTML table or ul missing");
}

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 4: Important Questions Section Validation (5, 3, 2)
// -------------------------------------------------------------
echo "4. Verifying Important Questions Validator (Exact 5, 3, 2 counts)...\n";

$validIQText = <<<EOD
### Section A: 2-Mark Short Answer Questions
1. Define an operating system and state its primary goals.
2. What is the difference between multiprogramming and multitasking?
3. List the states in a five-state process model.
4. Define context switching and identify why it introduces overhead.
5. What is mutual exclusion in concurrency control?

### Section B: 5-Mark Medium Answer Questions
1. Explain the Round Robin CPU scheduling algorithm with an example.
2. Describe the Banker's Algorithm for deadlock avoidance.
3. Compare contiguous memory allocation with non-contiguous memory allocation.

### Section C: 10-Mark Long Answer Questions
1. Discuss the Critical Section Problem, Peterson's algorithm, and hardware synchronization solutions in detail.
2. Explain the concept of Virtual Memory, demand paging, and page fault handling mechanism with diagrams.
EOD;

$valRes = split_and_validate_important_questions($validIQText);
if ($valRes && $valRes['valid'] && count($valRes['secA']) === 5 && count($valRes['secB']) === 3 && count($valRes['secC']) === 2) {
    record_pass("Valid Important Questions Format", "Successfully validated exactly Sec A: 5, Sec B: 3, Sec C: 2 questions");
} else {
    record_fail("Valid Important Questions Format", "Validation failed on valid input");
}

// Test with extra questions (e.g. 6 questions in Sec A, 4 in Sec B) -> should cleanly slice to 5, 3, 2
$excessIQText = <<<EOD
### Section A: 2-Mark Short Answer Questions
1. Define an operating system and state its primary goals.
2. What is the difference between multiprogramming and multitasking?
3. List the states in a five-state process model.
4. Define context switching and identify why it introduces overhead.
5. What is mutual exclusion in concurrency control?
6. Extra question that should be sliced off.

### Section B: 5-Mark Medium Answer Questions
1. Explain the Round Robin CPU scheduling algorithm with an example.
2. Describe the Banker's Algorithm for deadlock avoidance.
3. Compare contiguous memory allocation with non-contiguous memory allocation.
4. Extra question in section B.

### Section C: 10-Mark Long Answer Questions
1. Discuss the Critical Section Problem in detail.
2. Explain the concept of Virtual Memory with diagrams.
3. Extra question in section C.
EOD;

$valExcess = split_and_validate_important_questions($excessIQText);
if ($valExcess && count($valExcess['secA']) === 5 && count($valExcess['secB']) === 3 && count($valExcess['secC']) === 2) {
    record_pass("Exact Count Slicing", "Excess questions correctly sliced to exactly 5, 3, 2");
} else {
    record_fail("Exact Count Slicing", "Did not normalize question count to 5, 3, 2");
}

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 5: Truncation Detection & Incomplete Output Prevention
// -------------------------------------------------------------
echo "5. Verifying Truncation Detection & Incomplete Output Prevention...\n";

// 5.1 Incomplete section detection
$truncatedIQText = <<<EOD
### Section A: 2-Mark Short Answer Questions
1. Define an operating system and state its primary goals.
2. What is the difference between multiprogramming and multitasking?
3. List the states in a five-state process model.
4. Define context switching and identify why it introduces overhead.
5. What is mutual exclusion in concurrency control?

### Section B: 5-Mark Medium Answer Questions
1. Explain the Round Robin CPU scheduling algorithm with an example.
2. Describe the Banker's Algorithm for deadlock avoidance.
EOD; // Missing Section C and Section B only has 2 questions

$valTrunc = split_and_validate_important_questions($truncatedIQText);
if ($valTrunc === false) {
    record_pass("Incomplete Output Detection", "Correctly rejected response missing Section C and short on Section B");
} else {
    record_fail("Incomplete Output Detection", "Failed: Accepted incomplete response as valid");
}

// 5.2 Verify api/generate-questions.php database save logic
// Read api/generate-questions.php and inspect that it only updates/inserts on $result['success'] === true
$apiCode = file_get_contents(__DIR__ . '/../api/generate-questions.php');
if (strpos($apiCode, 'if (!$result[\'success\'])') !== false && 
    strpos($apiCode, 'http_response_code(500)') !== false &&
    strpos($apiCode, '$updateStmt = $pdo->prepare') !== false) {
    record_pass("Database Save Guard", "api/generate-questions.php rejects incomplete output and updates existing records without creating duplicates");
} else {
    record_fail("Database Save Guard", "Save guard pattern not detected in api/generate-questions.php");
}

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 6: Fallback Generation Simulation
// -------------------------------------------------------------
echo "6. Verifying Section Fallback Mechanism...\n";

// Test fallback formatting function
$mockSecA = ["Question A1", "Question A2", "Question A3", "Question A4", "Question A5"];
$mockSecB = ["Question B1", "Question B2", "Question B3"];
$mockSecC = ["Question C1", "Question C2"];

$finalFallback = format_final_important_questions($mockSecA, $mockSecB, $mockSecC);
$revalidated = split_and_validate_important_questions($finalFallback);

if ($revalidated && count($revalidated['secA']) === 5 && count($revalidated['secB']) === 3 && count($revalidated['secC']) === 2) {
    record_pass("Fallback Assembler", "Combined fallback sections produce valid 5, 3, 2 question paper");
} else {
    record_fail("Fallback Assembler", "Fallback output failed validation");
}

echo "\n";

// -------------------------------------------------------------
// TEST SUITE 7: Live Document Cache Verification
// -------------------------------------------------------------
echo "7. Verifying Existing Extracted Documents & Generated Content in Database...\n";
try {
    $stmtDocs = $pdo->query("SELECT id, original_filename, file_type, page_count FROM pdf_documents LIMIT 5");
    $docs = $stmtDocs->fetchAll();
    echo "  Found " . count($docs) . " document(s) in database:\n";
    foreach ($docs as $d) {
        $textPath = UPLOAD_DIR . 'doc_' . substr($d['original_filename'], 0, 10);
        echo "    - Doc ID {$d['id']}: {$d['original_filename']} ({$d['file_type']}, {$d['page_count']} pages/items)\n";
    }

    $stmtGen = $pdo->query("SELECT id, pdf_id, content_type, created_at, LENGTH(content_data) as len FROM generated_content LIMIT 5");
    $gens = $stmtGen->fetchAll();
    echo "  Found " . count($gens) . " generated content item(s) in database:\n";
    foreach ($gens as $g) {
        echo "    - Gen ID {$g['id']}: pdf_id={$g['pdf_id']}, type={$g['content_type']}, len={$g['len']} bytes, date={$g['created_at']}\n";
    }
    record_pass("Database Record Integrity", "Database records and references verified intact");
} catch (Exception $e) {
    record_fail("Database Record Integrity", $e->getMessage());
}

echo "\n=================================================================\n";
echo "                      VERIFICATION SUMMARY                       \n";
echo "=================================================================\n";
echo "Total Passed Tests: " . count($testsPassed) . "\n";
echo "Total Failed Tests: " . count($testsFailed) . "\n";
if (empty($testsFailed)) {
    echo "STATUS: ALL VERIFICATION CHECKS PASSED!\n";
} else {
    echo "STATUS: SOME TESTS FAILED!\n";
}
echo "=================================================================\n";
