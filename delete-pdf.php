<?php
/**
 * PDF Delete Action Handler
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Enforce authentication
require_login();

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdfId = (int) ($_POST['pdf_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrfToken)) {
        set_flash_message("Invalid security session token. Action aborted.", "danger");
        header('Location: ' . url('my-pdfs.php'));
        exit();
    }

    if ($pdfId > 0) {
        try {
            $pdo = getDBConnection();

            // IDOR Protection: Strictly check if PDF belongs to logged in user
            $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$pdfId, $userId]);
            $pdf = $stmt->fetch();

            if ($pdf) {
                // Remove stored physical files
                $filePath = UPLOAD_DIR . $pdf['stored_filename'];
                $textPath = UPLOAD_DIR . $pdf['stored_filename'] . '.txt';

                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                if (file_exists($textPath)) {
                    @unlink($textPath);
                }

                // Delete associated generated content records
                $delContent = $pdo->prepare("DELETE FROM generated_content WHERE pdf_id = ? AND user_id = ?");
                $delContent->execute([$pdfId, $userId]);

                // Delete PDF document record
                $delPdf = $pdo->prepare("DELETE FROM pdf_documents WHERE id = ? AND user_id = ?");
                $delPdf->execute([$pdfId, $userId]);

                set_flash_message("PDF document '" . htmlspecialchars($pdf['original_filename']) . "' deleted successfully.", "success");
            } else {
                set_flash_message("Unauthorized action or document not found.", "danger");
            }
        } catch (PDOException $e) {
            error_log("Delete PDF DB Error: " . $e->getMessage());
            set_flash_message("Database error while attempting to delete document.", "danger");
        }
    }
}

header('Location: ' . url('my-pdfs.php'));
exit();
