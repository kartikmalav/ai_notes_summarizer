<?php
/**
 * API Endpoint: Generate AI Exam Preparation Material via Gemini API
 * AI-Based PDF Notes Summarizer and Question Generator
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gemini.php';
require_once __DIR__ . '/../includes/document-extractor.php';

// 1. Enforce login check
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

// Get document ID from JSON payload or POST body
$input = json_decode(file_get_contents('php://input'), true);
$pdfId = (int) ($input['pdf_id'] ?? $_POST['pdf_id'] ?? 0);

if ($pdfId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid document ID.']);
    exit();
}

try {
    $pdo = getDBConnection();

    // 2. IDOR Check: Ensure user owns this document
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. You do not own this document.']);
        exit();
    }

    // 3. Read extracted document text from storage (or re-extract if missing)
    $textFilePath = UPLOAD_DIR . $pdf['stored_filename'] . '.txt';
    $docText = '';

    if (file_exists($textFilePath)) {
        $docText = file_get_contents($textFilePath);
    } else {
        // Fallback: Attempt on-the-fly extraction from original uploaded file
        $originalFilePath = UPLOAD_DIR . $pdf['stored_filename'];
        if (file_exists($originalFilePath)) {
            $extracted = extract_document_text($originalFilePath);
            if ($extracted['success'] && !empty($extracted['text'])) {
                $docText = $extracted['text'];
                file_put_contents($textFilePath, $docText);
            }
        }
    }

    if (empty(trim($docText)) || strlen(trim($docText)) < 20) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No printable text found in this document to generate exam preparation material.'
        ]);
        exit();
    }

    // 4. Send extracted text to Gemini API through PHP backend
    $result = generate_exam_preparation_material($docText, $pdf['subject'] ?: 'General');

    if (!$result['success']) {
        http_response_code(500);
        $errMsg = $result['error'] ?? 'Failed to generate Exam Preparation Kit.';
        // Safety: Ensure API key is never exposed in error responses
        if (defined('GEMINI_API_KEY') && GEMINI_API_KEY) {
            $errMsg = str_replace(GEMINI_API_KEY, '[REDACTED_API_KEY]', $errMsg);
        }
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit();
    }

    $examPrepJson = $result['data'];

    // 5. Save or update generated Exam Prep in database (prevent duplicates on regeneration)
    $checkStmt = $pdo->prepare("
        SELECT id FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type = 'exam_prep' 
        LIMIT 1
    ");
    $checkStmt->execute([$pdfId, $userId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $updateStmt = $pdo->prepare("
            UPDATE generated_content 
            SET content_data = ?, created_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$examPrepJson, $existing['id']]);
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO generated_content (pdf_id, user_id, content_type, content_data, created_at)
            VALUES (?, ?, 'exam_prep', ?, NOW())
        ");
        $insertStmt->execute([$pdfId, $userId, $examPrepJson]);
    }

    // Mark document status as completed
    $statusStmt = $pdo->prepare("UPDATE pdf_documents SET status = 'completed' WHERE id = ?");
    $statusStmt->execute([$pdfId]);

    echo json_encode([
        'success' => true,
        'message' => 'Exam Preparation Kit generated successfully!',
        'exam_prep' => $result['exam_prep'],
        'redirect' => url('exam-preparation.php?id=' . $pdfId)
    ]);
    exit();

} catch (PDOException $e) {
    error_log("Generate Exam Prep DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while saving generated Exam Preparation material.']);
    exit();
}
