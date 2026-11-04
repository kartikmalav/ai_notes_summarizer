<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    echo "=== USERS SAMPLE (Masked) ===\n";
    $users = $pdo->query("SELECT id, full_name, email, course, created_at FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $maskedEmail = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $u['email']);
        echo "User ID: {$u['id']}, Name: {$u['full_name']}, Email: {$maskedEmail}, Course: {$u['course']}, Created: {$u['created_at']}\n";
    }
    echo "\n";

    echo "=== PDF_DOCUMENTS SAMPLE ===\n";
    $pdfs = $pdo->query("SELECT id, user_id, original_filename, stored_filename, file_size, subject, file_type, uploaded_at FROM pdf_documents LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pdfs as $p) {
        echo "ID: {$p['id']}, UserID: {$p['user_id']}, Original: {$p['original_filename']}, Stored: {$p['stored_filename']}, Subject: {$p['subject']}, Type: {$p['file_type']}, Size: {$p['file_size']}, Uploaded: {$p['uploaded_at']}\n";
    }
    echo "\n";

    echo "=== GENERATED_CONTENT BREAKDOWN ===\n";
    $breakdown = $pdo->query("SELECT content_type, COUNT(*) as cnt FROM generated_content GROUP BY content_type")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($breakdown as $b) {
        echo "Content Type: {$b['content_type']}, Count: {$b['cnt']}\n";
    }

} catch (Exception $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . "\n";
}

