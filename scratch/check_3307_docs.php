<?php
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=ai_notes_summarizer", "root", "kartik8007");
foreach ($pdo->query("SELECT id, user_id, original_filename, uploaded_at FROM pdf_documents") as $r) {
    echo "{$r['id']} | user={$r['user_id']} | {$r['original_filename']} | {$r['uploaded_at']}\n";
}



