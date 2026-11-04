<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/groq.php';

// Ensure GROQ_API_KEY is loaded if CLI missed it
if (empty(GROQ_API_KEY)) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $l) {
        if (strpos(trim($l), 'GROQ_API_KEY=') === 0) {
            putenv('GROQ_API_KEY=' . trim(substr(trim($l), strlen('GROQ_API_KEY='))));
            $_ENV['GROQ_API_KEY'] = trim(substr(trim($l), strlen('GROQ_API_KEY=')));
            $_SERVER['GROQ_API_KEY'] = trim(substr(trim($l), strlen('GROQ_API_KEY=')));
        }
    }
}

$sampleText = "Open Source Software (OSS) is computer software that is released under a license in which the copyright holder grants users the rights to use, study, change, and distribute the software and its source code to anyone and for any purpose. Examples include Linux OS, Apache HTTP Server, MySQL, and Python. Open source licensing models include GNU General Public License (GPL), MIT License, and Apache License 2.0. Key advantages of OSS are transparency, cost effectiveness, community support, security through peer review, and freedom from vendor lock-in. Key challenges include lack of formal support, potential compatibility issues, and copyright compliance.";

echo "=== 1. TESTING groq_generate_short_notes ===\n";
$res1 = groq_generate_short_notes($sampleText, "Open Source Technologies");
echo "Success: " . ($res1['success'] ? 'YES' : 'NO') . "\n";
if (!$res1['success']) echo "Error: " . $res1['error'] . "\n";
else echo "Data preview: " . substr(strip_tags($res1['data']), 0, 150) . "...\n";
echo "\n";

echo "=== 2. TESTING groq_generate_important_questions ===\n";
$res2 = groq_generate_important_questions($sampleText, "Open Source Technologies");
echo "Success: " . ($res2['success'] ? 'YES' : 'NO') . "\n";
if (!$res2['success']) echo "Error: " . $res2['error'] . "\n";
else echo "Data preview: " . substr(strip_tags($res2['data']), 0, 150) . "...\n";
echo "\n";

echo "=== 3. TESTING groq_generate_mcqs ===\n";
$res3 = groq_generate_mcqs($sampleText, "Open Source Technologies");
echo "Success: " . ($res3['success'] ? 'YES' : 'NO') . "\n";
if (!$res3['success']) echo "Error: " . $res3['error'] . "\n";
else {
    $mcqJson = json_decode($res3['data'], true);
    echo "MCQ parsed JSON count: " . (is_array($mcqJson) ? count($mcqJson) : 'FAILED_TO_PARSE') . "\n";
}
echo "\n";

echo "=== 4. TESTING groq_generate_exam_prep ===\n";
$res4 = groq_generate_exam_prep($sampleText, "Open Source Technologies");
echo "Success: " . ($res4['success'] ? 'YES' : 'NO') . "\n";
if (!$res4['success']) echo "Error: " . $res4['error'] . "\n";
else echo "Exam Prep length: " . strlen($res4['data']) . " chars\n";
echo "\n";

echo "=== 5. TESTING groq_answer_document_question ===\n";
$res5 = groq_answer_document_question($sampleText, "UNIT5-OST.pdf", "Open Source Technologies", "What are the main advantages of open source software?");
echo "Success: " . ($res5['success'] ? 'YES' : 'NO') . "\n";
if (!$res5['success']) echo "Error: " . $res5['error'] . "\n";
else echo "Answer preview: " . substr(strip_tags($res5['answer']), 0, 150) . "...\n";
echo "\n";
