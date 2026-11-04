<?php
/**
 * API Endpoint: Generate AI Multiple Choice Questions (MCQs) via Gemini API
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
    echo json_encode(['success' => false, 'error' => 'Invalid document ID.']);
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

    // 2. Read extracted document text from storage
    $textFilePath = UPLOAD_DIR . $pdf['stored_filename'] . '.txt';
    
    if (!file_exists($textFilePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Extracted text file was not found for this document.']);
        exit();
    }

    $docText = file_get_contents($textFilePath);

    if (empty(trim($docText)) || strlen(trim($docText)) < 20) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'No printable text found in this document to generate MCQs.'
        ]);
        exit();
    }

    // 3. Send extracted text to Gemini API through PHP backend
    $result = generate_document_mcqs($docText, $pdf['subject'] ?: 'General');

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit();
    }

    $mcqsJson = $result['data'];

    // 4. Save or update generated MCQs in database (prevent duplicates on retry)
    $checkStmt = $pdo->prepare("
        SELECT id FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type = 'mcqs' 
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
        $updateStmt->execute([$mcqsJson, $existing['id']]);
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO generated_content (pdf_id, user_id, content_type, content_data, created_at)
            VALUES (?, ?, 'mcqs', ?, NOW())
        ");
        $insertStmt->execute([$pdfId, $userId, $mcqsJson]);
    }

    // Mark status as completed
    $statusStmt = $pdo->prepare("UPDATE pdf_documents SET status = 'completed' WHERE id = ?");
    $statusStmt->execute([$pdfId]);

    echo json_encode([
        'success' => true,
        'message' => 'MCQs generated successfully!',
        'mcqs' => $result['mcqs'],
        'redirect' => url('mcqs-practice.php?id=' . $pdfId)
    ]);
    exit();

} catch (PDOException $e) {
    error_log("Generate MCQs DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while saving generated MCQs: ' . $e->getMessage()]);
    exit();
}
