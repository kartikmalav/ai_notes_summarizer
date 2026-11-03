<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM pdf_documents");
$docs = $stmt->fetchAll();

echo "Checking documents in pdf_documents:\n";
foreach ($docs as $d) {
    echo "ID: " . $d['id'] . " | Name: " . $d['original_filename'] . " | Stored: " . $d['stored_filename'] . "\n";
    $txtPath = UPLOAD_DIR . $d['stored_filename'] . '.txt';
    if (file_exists($txtPath)) {
        $text = file_get_contents($txtPath);
        $len = strlen($text);
        $isUtf8 = mb_check_encoding($text, 'UTF-8');
        echo "  - Text file exists: YES ($len bytes)\n";
        echo "  - Valid UTF-8: " . ($isUtf8 ? 'YES' : 'NO') . "\n";
        
        $reqBody = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $text]
                    ]
                ]
            ]
        ];
        $encoded = json_encode($reqBody);
        echo "  - json_encode result: " . ($encoded !== false ? "SUCCESS (" . strlen($encoded) . " bytes)" : "FAILED (json_last_error_msg: " . json_last_error_msg() . ")") . "\n";
    } else {
        echo "  - Text file exists: NO\n";
    }
}
