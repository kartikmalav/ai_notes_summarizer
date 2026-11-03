<?php
/**
 * Database Connection Test Page
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDBConnection();
    if ($pdo instanceof PDO) {
        echo "Database connection successful";
    } else {
        echo "Database connection failed";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error: " . $e->getMessage();
}
