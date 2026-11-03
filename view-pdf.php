<?php
/**
 * Secure PDF File Viewer
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Enforce authentication
require_login();

$pdfId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($pdfId <= 0) {
    die("Invalid PDF request.");
}

try {
    $pdo = getDBConnection();
    // Strict IDOR Check: Ensure PDF belongs to logged-in user
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        http_response_code(403);
        die("
            <div style='font-family: sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; background: #fff0f0; border-radius: 8px; color: #900;'>
                <h3>⚠️ Access Denied</h3>
                <p>You do not have permission to view this document or it does not exist.</p>
            </div>
        ");
    }

    $fullPath = UPLOAD_DIR . $pdf['stored_filename'];

    if (!file_exists($fullPath)) {
        http_response_code(404);
        die("Requested PDF file was not found on the server.");
    }

    // Stream document safely to browser
    $mimeType = !empty($pdf['mime_type']) ? $pdf['mime_type'] : 'application/octet-stream';
    if (($pdf['file_type'] ?? '') === 'pdf') {
        $mimeType = 'application/pdf';
    } elseif (($pdf['file_type'] ?? '') === 'txt') {
        $mimeType = 'text/plain; charset=utf-8';
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . rawurlencode($pdf['original_filename']) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    readfile($fullPath);
    exit();

} catch (PDOException $e) {
    error_log("View PDF Error: " . $e->getMessage());
    die("Database error while loading document.");
}
