<?php
$files = [
    'api/generate-summary.php',
    'api/generate-questions.php',
    'api/generate-exam-prep.php',
    'api/generate-mcqs.php',
    'api/ask-document.php',
    'delete-pdf.php',
    'my-pdfs.php',
    'includes/auth.php',
    'includes/gemini.php',
    'includes/document-extractor.php',
    'config/config.php',
    'config/database.php',
    'login.php',
    'signup.php',
    'logout.php',
    'dashboard.php',
    'upload-pdf.php',
    'pdf-results.php',
    'exam-preparation.php',
    'mcqs-practice.php',
    'ask-from-pdf.php',
    'view-pdf.php',
    'view-text.php'
];

$allPassed = true;
foreach ($files as $f) {
    $full = 'C:\\xampp\\htdocs\\ai_notes_summarizer\\' . $f;
    $output = [];
    $returnVar = 0;
    exec("\"C:\\xampp\\php\\php.exe\" -l \"{$full}\" 2>&1", $output, $returnVar);
    if ($returnVar !== 0) {
        echo "FAILED: $f -> " . implode("\n", $output) . "\n";
        $allPassed = false;
    } else {
        echo "OK: $f\n";
    }
}

if ($allPassed) {
    echo "\nALL FILES PASSED SYNTAX CHECK!\n";
}
