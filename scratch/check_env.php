<?php
$sources = [
    'getenv(GROQ_API_KEY)' => getenv('GROQ_API_KEY'),
    '$_SERVER[GROQ_API_KEY]' => $_SERVER['GROQ_API_KEY'] ?? null,
    '$_ENV[GROQ_API_KEY]' => $_ENV['GROQ_API_KEY'] ?? null,
];

foreach ($sources as $name => $val) {
    echo $name . ': ' . ($val !== false && $val !== null ? 'FOUND (length ' . strlen($val) . ')' : 'NOT SET') . "\n";
}

// Check if any .env or config file exists in the directory or parent directories
$envFiles = [
    'C:\\xampp\\htdocs\\ai_notes_summarizer\\.env',
    'C:\\xampp\\htdocs\\ai_notes_summarizer\\config\\.env',
    'C:\\xampp\\apache\\conf\\extra\\httpd-xampp.conf',
    'C:\\Users\\Kartik\\.env'
];

foreach ($envFiles as $ef) {
    if (file_exists($ef)) {
        $c = file_get_contents($ef);
        $hasGroq = stripos($c, 'GROQ') !== false;
        $hasGemini = stripos($c, 'GEMINI') !== false;
        echo $ef . ': EXISTS (has GROQ: ' . ($hasGroq ? 'YES' : 'NO') . ', has GEMINI: ' . ($hasGemini ? 'YES' : 'NO') . ")\n";
    } else {
        echo $ef . ": NOT FOUND\n";
    }
}
