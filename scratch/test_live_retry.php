<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/gemini.php';

$txtFile = UPLOAD_DIR . 'doc_20261004_131403_2ac43d8156649c39.pdf.txt';
$docText = file_get_contents($txtFile);

echo "Attempting live call with retry...\n";
for ($attempt = 1; $attempt <= 3; $attempt++) {
    echo "Attempt $attempt...\n";
    $liveResult = answer_document_question(
        $docText,
        "UNIT5-OST.pdf",
        "Computer Science",
        "What is the main topic covered in this document? Answer in 1 short sentence."
    );
    if ($liveResult['success']) {
        echo "SUCCESS on attempt $attempt!\n";
        echo "Answer: " . $liveResult['answer'] . "\n";
        break;
    } else {
        echo "Failed with: " . $liveResult['error'] . "\n";
        if ($attempt < 3) {
            echo "Sleeping 2 seconds before retry...\n";
            sleep(2);
        }
    }
}
