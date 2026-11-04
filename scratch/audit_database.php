<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    echo "=== DATABASE CONNECTION SUCCESSFUL ===\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Port: " . DB_PORT . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n\n";

    // Tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "=== TABLES FOUND (" . count($tables) . ") ===\n";
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo " - Table: $t (Rows: $count)\n";
    }
    echo "\n";

    // Structure of each table
    foreach ($tables as $t) {
        echo "=== STRUCTURE: $t ===\n";
        $cols = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo "   " . str_pad($c['Field'], 25) . str_pad($c['Type'], 20) . str_pad($c['Null'], 6) . str_pad($c['Key'], 6) . ($c['Default'] ?? 'NULL') . "\n";
        }
        
        // Foreign keys
        $fks = $pdo->query("
            SELECT COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$t' AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        if ($fks) {
            echo "   Foreign Keys:\n";
            foreach ($fks as $fk) {
                echo "    * {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']}) [Constraint: {$fk['CONSTRAINT_NAME']}]\n";
            }
        }
        echo "\n";
    }

    // Inspect user table without revealing passwords
    echo "=== USERS SAMPLE (Masked) ===\n";
    $users = $pdo->query("SELECT id, name, email, created_at FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $maskedEmail = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $u['email']);
        echo "User ID: {$u['id']}, Name: {$u['name']}, Email: {$maskedEmail}, Created: {$u['created_at']}\n";
    }
    echo "\n";

    // Inspect documents/pdfs table
    echo "=== PDFS / DOCUMENTS SAMPLE ===\n";
    $pdfTable = in_array('documents', $tables) ? 'documents' : (in_array('pdfs', $tables) ? 'pdfs' : null);
    if ($pdfTable) {
        $pdfs = $pdo->query("SELECT id, user_id, filename, file_path, subject, file_size, created_at FROM `$pdfTable` LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pdfs as $p) {
            echo "ID: {$p['id']}, UserID: {$p['user_id']}, File: {$p['filename']}, Subject: {$p['subject']}, Size: {$p['file_size']}, Created: {$p['created_at']}\n";
        }
    }

} catch (Exception $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . "\n";
}

