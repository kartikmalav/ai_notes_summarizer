<?php
/**
 * Standalone Test Script for Multi-Format Extractor Engine
 */

require_once __DIR__ . '/../includes/document-extractor.php';

$testDir = __DIR__ . '/test_docs/';
if (!is_dir($testDir)) {
    mkdir($testDir, 0777, true);
}

echo "====================================================\n";
echo "       MULTI-FORMAT EXTRACTOR VERIFICATION TEST     \n";
echo "====================================================\n\n";

$results = [];

// 1. Test TXT
$txtFile = $testDir . 'sample.txt';
file_put_contents($txtFile, "Title: Operating Systems Revision Notes\nSection 1: Process Management\nA process is an instance of a program in execution.\nStates: New, Ready, Running, Waiting, Terminated.");
$resTxt = extract_document_text($txtFile, 'txt');
$results['TXT'] = $resTxt;

// 2. Test CSV
$csvFile = $testDir . 'sample.csv';
$csvData = "Module,Topic,Importance\nModule 1,Process Scheduling,High\nModule 2,Memory Management,High\nModule 3,File Systems,Medium";
file_put_contents($csvFile, $csvData);
$resCsv = extract_document_text($csvFile, 'csv');
$results['CSV'] = $resCsv;

// 3. Test DOCX (Create minimal zip containing word/document.xml)
$docxFile = $testDir . 'sample.docx';
$zip = new ZipArchive();
if ($zip->open($docxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
         . '<w:body>'
         . '<w:p><w:r><w:t>Unit 1: Data Structures and Algorithms</w:t></w:r></w:p>'
         . '<w:p><w:r><w:t>Array is a linear data structure holding homogeneous elements.</w:t></w:r></w:p>'
         . '<w:p><w:r><w:t>Stack follows Last-In First-Out (LIFO) principle.</w:t></w:r></w:p>'
         . '</w:body></w:document>';
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();
}
$resDocx = extract_document_text($docxFile, 'docx');
$results['DOCX'] = $resDocx;

// 4. Test XLSX (Create minimal zip with sharedStrings and worksheet)
$xlsxFile = $testDir . 'sample.xlsx';
$zipXlsx = new ZipArchive();
if ($zipXlsx->open($xlsxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $sharedStrXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                  . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3">'
                  . '<si><t>DBMS Unit</t></si>'
                  . '<si><t>Relational Model</t></si>'
                  . '<si><t>Normalization 1NF 2NF 3NF BCNF</t></si>'
                  . '</sst>';
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
              . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
              . '<sheetData>'
              . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
              . '<row r="2"><c r="A2" t="s"><v>2</v></c></row>'
              . '</sheetData></worksheet>';
    $zipXlsx->addFromString('xl/sharedStrings.xml', $sharedStrXml);
    $zipXlsx->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zipXlsx->close();
}
$resXlsx = extract_document_text($xlsxFile, 'xlsx');
$results['XLSX'] = $resXlsx;

// 5. Test PPTX (Create minimal zip with ppt/slides/slide1.xml)
$pptxFile = $testDir . 'sample.pptx';
$zipPptx = new ZipArchive();
if ($zipPptx->open($pptxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $slideXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
              . '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
              . '<p:cSld><p:spTree>'
              . '<p:sp><p:txBody><a:p><a:r><a:t>Computer Networks Presentation</a:t></a:r></a:p>'
              . '<a:p><a:r><a:t>OSI Reference Model: 7 Layers</a:t></a:r></a:p></p:txBody></p:sp>'
              . '</p:spTree></p:cSld></p:sld>';
    $zipPptx->addFromString('ppt/slides/slide1.xml', $slideXml);
    $zipPptx->close();
}
$resPptx = extract_document_text($pptxFile, 'pptx');
$results['PPTX'] = $resPptx;

// 6. Test PDF (via companion text file loader)
$pdfFile = $testDir . 'sample.pdf';
file_put_contents($pdfFile, '%PDF-1.4 mock content');
file_put_contents($pdfFile . '.txt', "--- Page 1 ---\nComputer Architecture Notes.\nCPU, ALUs, Registers, Control Unit.");
$resPdf = extract_document_text($pdfFile, 'pdf');
$results['PDF'] = $resPdf;

// Report Results
$allPassed = true;
foreach ($results as $format => $res) {
    if ($res['success'] && !empty($res['text'])) {
        echo "[PASS] Format: {$format}\n";
        echo "       Items Extracted: {$res['item_count']}\n";
        echo "       Text Length: " . strlen($res['text']) . " chars\n";
        echo "       Snippet: " . str_replace("\n", " ", substr($res['text'], 0, 80)) . "...\n\n";
    } else {
        echo "[FAIL] Format: {$format} - Error: " . ($res['error'] ?? 'Unknown error') . "\n\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "====================================================\n";
    echo "       ALL 6 FORMAT EXTRACTORS TESTED & PASSED!     \n";
    echo "====================================================\n";
} else {
    echo "FAILED: Some format extractors did not pass.\n";
}
