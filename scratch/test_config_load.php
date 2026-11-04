<?php
require_once __DIR__ . '/../config/config.php';
echo "GROQ_API_KEY constant: " . (defined('GROQ_API_KEY') ? (GROQ_API_KEY !== '' ? 'SET (len ' . strlen(GROQ_API_KEY) . ')' : 'EMPTY STRING') : 'NOT DEFINED') . PHP_EOL;
echo "GROQ_MODEL constant: " . (defined('GROQ_MODEL') ? GROQ_MODEL : 'NOT DEFINED') . PHP_EOL;
echo "getenv(GROQ_API_KEY): " . var_export(getenv('GROQ_API_KEY'), true) . PHP_EOL;

