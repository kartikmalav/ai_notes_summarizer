<?php
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
echo "envPath: $envPath\n";
echo "exists: " . (file_exists($envPath) ? "true" : "false") . "\n";
echo "is_readable: " . (is_readable($envPath) ? "true" : "false") . "\n";
$envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
echo "count lines: " . count($envLines) . "\n";
foreach ($envLines as $idx => $line) {
    echo "Line $idx: [" . addcslashes($line, "\r\n\t\\\"'") . "]\n";
    if (strpos($line, '=') !== false) {
        list($envKey, $envVal) = explode('=', $line, 2);
        echo "  key: [" . trim($envKey) . "] val len: " . strlen(trim($envVal)) . "\n";
    }
}

