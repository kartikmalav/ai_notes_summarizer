<?php
/**
 * Extracted Text Viewer Page
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$userId = $_SESSION['user_id'];
$pdfId = (int) ($_GET['id'] ?? 0);

if ($pdfId <= 0) {
    set_flash_message("Invalid document request.", "danger");
    header('Location: ' . url('my-pdfs.php'));
    exit();
}

$pdf = null;
$extractedText = '';

try {
    $pdo = getDBConnection();
    // Strict IDOR Check
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        set_flash_message("Document not found or access denied.", "danger");
        header('Location: ' . url('my-pdfs.php'));
        exit();
    }

    $textPath = UPLOAD_DIR . $pdf['stored_filename'] . '.txt';
    if (file_exists($textPath)) {
        $extractedText = file_get_contents($textPath);
    }

} catch (PDOException $e) {
    error_log("View Text DB Error: " . $e->getMessage());
    set_flash_message("Database error while loading document.", "danger");
    header('Location: ' . url('my-pdfs.php'));
    exit();
}

$ext = strtolower($pdf['file_type'] ?? pathinfo($pdf['original_filename'], PATHINFO_EXTENSION));
$icon = get_format_icon($ext);

$pageTitle = 'Extracted Document Text Preview';
$pageSubTitle = 'Normalized text extracted from ' . htmlspecialchars($pdf['original_filename']);

include __DIR__ . '/includes/header.php';
?>

<div class="page-card" style="max-width: 900px; margin: 0 auto;">
    <div class="section-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
        <div>
            <h2 class="page-title"><?= $icon ?> Extracted Text: <?= htmlspecialchars($pdf['original_filename']) ?></h2>
            <p class="page-subtitle">
                Format: <span class="tag-item"><?= strtoupper($ext) ?></span> | 
                Subject: <strong><?= htmlspecialchars($pdf['subject'] ?: 'General') ?></strong> | 
                Items: <strong><?= $pdf['page_count'] ?></strong> | 
                Size: <strong><?= round($pdf['file_size'] / (1024 * 1024), 2) ?> MB</strong>
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= url('view-pdf.php?id=' . $pdf['id']) ?>" target="_blank" class="btn btn-outline btn-sm">
                👁️ View Original File
            </a>
            <a href="<?= url('my-pdfs.php') ?>" class="btn btn-secondary btn-sm">
                &larr; Back to Library
            </a>
        </div>
    </div>

    <?php if (empty($extractedText)): ?>
        <div class="alert alert-danger">
            <div class="alert-icon">⚠</div>
            <div class="alert-body">No printable text was extracted from this PDF. It may be a scanned image document or password protected.</div>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <span class="tag-item" style="background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-200);">
                Total Characters: <?= number_format(strlen($extractedText)) ?>
            </span>
            <button type="button" class="btn btn-sm btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('textPreviewBox').innerText); alert('Text copied to clipboard!');">
                📋 Copy Text
            </button>
        </div>
        <div id="textPreviewBox" style="background: var(--bg-card-subtle); color: var(--text-main); border: 1px solid var(--border-color); font-family: monospace; font-size: 0.88rem; padding: 20px; border-radius: var(--radius-md); max-height: 500px; overflow-y: auto; white-space: pre-wrap; line-height: 1.6;">
            <?= htmlspecialchars($extractedText) ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
