<?php
/**
 * API Endpoint: Ask Your Document (Document Q&A Assistant)
 * AI-Based PDF Notes Summarizer and Question Generator
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gemini.php';
require_once __DIR__ . '/../includes/markdown.php';

// Enforce login check
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in first.']);
    exit();
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse input payload (handles JSON body and standard POST/GET)
$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
} else {
    $input = $_GET;
}

$pdfId = (int) ($input['pdf_id'] ?? 0);
$action = trim((string) ($input['action'] ?? ($method === 'GET' ? 'history' : 'ask')));

if ($pdfId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid document ID.']);
    exit();
}

try {
    $pdo = getDBConnection();

    // 1. Strict IDOR Check: Ensure user owns this document
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. You do not own this document.']);
        exit();
    }

    // 2. Fetch existing conversation history from generated_content
    $qnaStmt = $pdo->prepare("
        SELECT id, content_data FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type = 'qna' 
        LIMIT 1
    ");
    $qnaStmt->execute([$pdfId, $userId]);
    $existingRow = $qnaStmt->fetch();

    $messages = [];
    if ($existingRow && !empty($existingRow['content_data'])) {
        $decoded = json_decode($existingRow['content_data'], true);
        if (is_array($decoded)) {
            $messages = $decoded;
        }
    }

    // -------------------------------------------------------------
    // ACTION: Clear conversation
    // -------------------------------------------------------------
    if ($action === 'clear') {
        if ($existingRow) {
            $delStmt = $pdo->prepare("DELETE FROM generated_content WHERE id = ?");
            $delStmt->execute([$existingRow['id']]);
        }
        echo json_encode([
            'success' => true,
            'message' => 'Conversation cleared successfully.',
            'messages' => []
        ]);
        exit();
    }

    // Helper to enrich messages with pre-rendered content_html
    $enrichMessages = function($msgs) {
        $out = [];
        foreach ($msgs as $m) {
            if ($m['role'] === 'assistant') {
                $m['content_html'] = render_markdown($m['content']);
            }
            $out[] = $m;
        }
        return $out;
    };

    // -------------------------------------------------------------
    // ACTION: Fetch history only
    // -------------------------------------------------------------
    if ($action === 'history') {
        echo json_encode([
            'success' => true,
            'messages' => $enrichMessages($messages)
        ]);
        exit();
    }

    // -------------------------------------------------------------
    // ACTION: Ask question (Default)
    // -------------------------------------------------------------
    $question = trim((string) ($input['question'] ?? ''));

    if (empty($question) || strlen($question) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please enter a valid question.']);
        exit();
    }

    if (strlen($question) > 1000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Question is too long (maximum 1000 characters).']);
        exit();
    }

    // Prevent duplicate submissions:
    // If the last message was from the user with the identical question within the last 15 seconds, return the existing answer
    if (!empty($messages)) {
        $lastIdx = count($messages) - 1;
        $lastMsg = $messages[$lastIdx];
        $prevMsg = ($lastIdx >= 1) ? $messages[$lastIdx - 1] : null;

        if ($prevMsg && $prevMsg['role'] === 'user' && $prevMsg['content'] === $question && $lastMsg['role'] === 'assistant') {
            $timeDiff = time() - strtotime($lastMsg['timestamp'] ?? 'now');
            if ($timeDiff >= 0 && $timeDiff < 15) {
                echo json_encode([
                    'success' => true,
                    'answer' => $lastMsg['content'],
                    'answer_html' => render_markdown($lastMsg['content']),
                    'messages' => $enrichMessages($messages),
                    'cached' => true
                ]);
                exit();
            }
        }
    }

    // Read companion extracted document text
    $textFilePath = UPLOAD_DIR . $pdf['stored_filename'] . '.txt';

    if (!file_exists($textFilePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Extracted text file was not found for this document.']);
        exit();
    }

    $docText = file_get_contents($textFilePath);

    if (empty(trim($docText)) || strlen(trim($docText)) < 20) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No readable text found in this document to answer questions.']);
        exit();
    }

    // Send question to Gemini with document excerpts and recent history
    $result = answer_document_question(
        $docText,
        $pdf['original_filename'],
        $pdf['subject'] ?: 'General',
        $question,
        $messages
    );

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit();
    }

    $answer = $result['answer'];
    $timestamp = date('Y-m-d H:i:s');

    // Append user & assistant messages
    $messages[] = [
        'id' => 'u_' . bin2hex(random_bytes(6)),
        'role' => 'user',
        'content' => $question,
        'timestamp' => $timestamp
    ];

    $messages[] = [
        'id' => 'a_' . bin2hex(random_bytes(6)),
        'role' => 'assistant',
        'content' => $answer,
        'timestamp' => $timestamp
    ];

    $jsonPayload = json_encode($messages, JSON_UNESCAPED_UNICODE);

    // Save or update in generated_content
    if ($existingRow) {
        $upStmt = $pdo->prepare("
            UPDATE generated_content 
            SET content_data = ?, created_at = NOW() 
            WHERE id = ?
        ");
        $upStmt->execute([$jsonPayload, $existingRow['id']]);
    } else {
        $insStmt = $pdo->prepare("
            INSERT INTO generated_content (pdf_id, user_id, content_type, content_data, created_at)
            VALUES (?, ?, 'qna', ?, NOW())
        ");
        $insStmt->execute([$pdfId, $userId, $jsonPayload]);
    }

    echo json_encode([
        'success' => true,
        'answer' => $answer,
        'answer_html' => render_markdown($answer),
        'messages' => $enrichMessages($messages)
    ]);
    exit();

} catch (PDOException $e) {
    error_log("Ask Document DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while processing your question: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    error_log("Ask Document General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred. Please try again.']);
    exit();
}
