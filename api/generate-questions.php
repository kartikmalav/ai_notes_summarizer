<?php
/**
 * API Endpoint: Generate AI Important Questions via Gemini API
 * AI-Based PDF Notes Summarizer and Question Generator
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gemini.php';

// Enforce login check
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in first.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST request.']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get PDF ID from JSON payload or POST body
$input = json_decode(file_get_contents('php://input'), true);
$pdfId = (int) ($input['pdf_id'] ?? $_POST['pdf_id'] ?? 0);

if ($pdfId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid PDF document ID.']);
    exit();
}

try {
    $pdo = getDBConnection();

    // 1. IDOR Check: Ensure user owns this PDF
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. You do not own this document.']);
        exit();
    }

    // 2. Read extracted document text from storage (with fallback extraction)
    require_once __DIR__ . '/../includes/document-extractor.php';
    $textFilePath = UPLOAD_DIR . $pdf['stored_filename'] . '.txt';
    $sourceFilePath = UPLOAD_DIR . $pdf['stored_filename'];

    if (!file_exists($textFilePath) && file_exists($sourceFilePath)) {
        $extracted = extract_document_text($sourceFilePath, $pdf['file_type'] ?: 'pdf', $textFilePath);
        if ($extracted['success'] && !empty($extracted['text'])) {
            @file_put_contents($textFilePath, $extracted['text']);
        }
    }
    
    if (!file_exists($textFilePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Extracted text file was not found for this document.']);
        exit();
    }

    $pdfText = file_get_contents($textFilePath);

    if (empty(trim($pdfText)) || strlen(trim($pdfText)) < 20) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'No printable text found in this PDF document to generate questions.'
        ]);
        exit();
    }

    // 3. Send extracted text to Gemini API through PHP backend
    $result = generate_pdf_important_questions($pdfText, $pdf['subject'] ?: 'Computer Science');

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit();
    }

    $questionsContent = $result['data'];

    // 4. Save or update generated questions in database
    $checkStmt = $pdo->prepare("
        SELECT id FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type IN ('important_questions', 'questions') 
        LIMIT 1
    ");
    $checkStmt->execute([$pdfId, $userId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $updateStmt = $pdo->prepare("
            UPDATE generated_content 
            SET content_type = 'important_questions', content_data = ?, created_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$questionsContent, $existing['id']]);
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO generated_content (pdf_id, user_id, content_type, content_data, created_at)
            VALUES (?, ?, 'important_questions', ?, NOW())
        ");
        $insertStmt->execute([$pdfId, $userId, $questionsContent]);
    }

    // Mark status as completed
    $statusStmt = $pdo->prepare("UPDATE pdf_documents SET status = 'completed' WHERE id = ?");
    $statusStmt->execute([$pdfId]);

    echo json_encode([
        'success' => true,
        'message' => 'Important Questions generated successfully!',
        'redirect' => url('pdf-results.php?id=' . $pdfId . '&tab=questions')
    ]);
    exit();

} catch (PDOException $e) {
    error_log("Generate Questions DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while saving generated questions: ' . $e->getMessage()]);
    exit();
}
