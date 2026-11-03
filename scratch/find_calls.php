<?php
$files = array_merge(
    glob(__DIR__ . '/../includes/*.php'),
    glob(__DIR__ . '/../api/*.php'),
    glob(__DIR__ . '/../*.php')
);

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'call_gemini_api') !== false) {
        echo "Found in " . basename(dirname($file)) . "/" . basename($file) . ":\n";
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (strpos($line, 'call_gemini_api') !== false) {
                echo "  Line " . ($i + 1) . ": " . trim($line) . "\n";
            }
        }
    }
}
