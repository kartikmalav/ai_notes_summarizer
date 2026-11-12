<?php
$src = new PDO("mysql:host=127.0.0.1;port=3307;dbname=ai_notes_summarizer;charset=utf8mb4", "root", "kartik8007", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$dst = new PDO("mysql:host=127.0.0.1;port=3306;dbname=ai_notes_summarizer;charset=utf8mb4", "root", "kartik8007", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// 1. Align schema on 3306
$cols = $dst->query("SHOW COLUMNS FROM pdf_documents")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('file_type', $cols)) {
    $dst->exec("ALTER TABLE pdf_documents ADD COLUMN file_type varchar(10) NOT NULL DEFAULT 'pdf' AFTER subject");
    echo "Added file_type to 3306.pdf_documents\n";
}
if (!in_array('mime_type', $cols)) {
    $dst->exec("ALTER TABLE pdf_documents ADD COLUMN mime_type varchar(100) NOT NULL DEFAULT 'application/pdf' AFTER file_type");
    echo "Added mime_type to 3306.pdf_documents\n";
}
$dst->exec("ALTER TABLE generated_content MODIFY COLUMN content_type enum('summary','short_notes','questions','important_questions','exam_prep','mcqs','qna') NOT NULL");
echo "Updated content_type ENUM on 3306.generated_content\n";

// 2. Copy records without overwriting
$dst->exec("SET FOREIGN_KEY_CHECKS = 0");

$users = $src->query("SELECT * FROM users ORDER BY id")->fetchAll();
$insUser = $dst->prepare("INSERT IGNORE INTO users (id, full_name, email, password_hash, course, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($users as $u) {
    $insUser->execute([$u['id'], $u['full_name'], $u['email'], $u['password_hash'], $u['course'], $u['created_at'], $u['updated_at']]);
}


$docs = $src->query("SELECT * FROM pdf_documents ORDER BY id")->fetchAll();
$insDoc = $dst->prepare("INSERT IGNORE INTO pdf_documents (id, user_id, original_filename, stored_filename, file_path, file_size, page_count, subject, file_type, mime_type, status, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($docs as $d) {
    $insDoc->execute([$d['id'], $d['user_id'], $d['original_filename'], $d['stored_filename'], $d['file_path'], $d['file_size'], $d['page_count'], $d['subject'], $d['file_type'], $d['mime_type'], $d['status'], $d['uploaded_at']]);
}

$contents = $src->query("SELECT * FROM generated_content ORDER BY id")->fetchAll();
$insContent = $dst->prepare("INSERT IGNORE INTO generated_content (id, pdf_id, user_id, content_type, content_data, created_at) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($contents as $c) {
    $insContent->execute([$c['id'], $c['pdf_id'], $c['user_id'], $c['content_type'], $c['content_data'], $c['created_at']]);
}

$dst->exec("SET FOREIGN_KEY_CHECKS = 1");

foreach (['users', 'pdf_documents', 'generated_content'] as $t) {
    $maxId = (int)$dst->query("SELECT COALESCE(MAX(id), 0) FROM `$t`")->fetchColumn();
    $nextId = $maxId + 1;
    $dst->exec("ALTER TABLE `$t` AUTO_INCREMENT = $nextId");
}

echo "Port 3306 counts after migration:\n";
foreach (['users', 'pdf_documents', 'generated_content'] as $t) {
    echo "  $t = " . $dst->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . "\n";
}
