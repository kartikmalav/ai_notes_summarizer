<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/document-extractor.php';

$pdf1 = 'C:\\xampp\\htdocs\\ai_notes_summarizer\\uploads\\doc_20261004_131403_2ac43d8156649c39.pdf';
$pdf2 = 'C:\\xampp\\htdocs\\ai_notes_summarizer\\uploads\\doc_20261004_131953_2a49553ebd456ad6.pdf';
$pptx = 'C:\\xampp\\htdocs\\ai_notes_summarizer\\uploads\\doc_20261011_181148_08cb1c8734782430.pptx';

echo "Testing PDF 1 (with companion):\n";
$r1 = extract_document_text($pdf1, 'pdf');
echo "PDF 1 success: " . ($r1['success'] ? 'YES' : 'NO') . " | Items: " . $r1['item_count'] . " | Length: " . strlen($r1['text']) . "\n";

echo "\nTesting PDF 2 (fallback without companion by passing dummy path):\n";
$r2 = extract_pdf_content($pdf2, 'C:\\nonexistent.txt');
echo "PDF 2 fallback success: " . ($r2['success'] ? 'YES' : 'NO') . " | Error: " . ($r2['error'] ?? 'None') . " | Length: " . strlen($r2['text']) . "\n";

echo "\nTesting PPTX:\n";
$r3 = extract_document_text($pptx, 'pptx');
echo "PPTX success: " . ($r3['success'] ? 'YES' : 'NO') . " | Items: " . $r3['item_count'] . " | Length: " . strlen($r3['text']) . "\n";
