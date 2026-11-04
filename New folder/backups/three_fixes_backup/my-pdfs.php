<?php
/**
 * My Documents Library Page
 * AI-Based Study Assistant (PDF, DOCX, TXT, CSV, XLSX, PPTX)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Enforce authentication
require_login();

$userId = $_SESSION['user_id'];
$pdfs = [];
$searchQuery = trim($_GET['search'] ?? '');

try {
    $pdo = getDBConnection();
    // Fetch logged-in user's documents with summary, mcq, and exam_prep status
    $sql = "
        SELECT p.*, 
               (SELECT COUNT(*) FROM generated_content g WHERE g.pdf_id = p.id AND g.user_id = :u1 AND g.content_type IN ('summary', 'short_notes')) AS has_summary,
               (SELECT COUNT(*) FROM generated_content g WHERE g.pdf_id = p.id AND g.user_id = :u2 AND g.content_type = 'mcqs') AS has_mcqs,
               (SELECT COUNT(*) FROM generated_content g WHERE g.pdf_id = p.id AND g.user_id = :u3 AND g.content_type = 'exam_prep') AS has_exam_prep
        FROM pdf_documents p 
        WHERE p.user_id = :u4 
    ";
    $params = ['u1' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId];

    if ($searchQuery !== '') {
        $sql .= " AND (p.original_filename LIKE :s1 OR p.subject LIKE :s2) ";
        $params['s1'] = '%' . $searchQuery . '%';
        $params['s2'] = '%' . $searchQuery . '%';
    }

    $sql .= " ORDER BY p.uploaded_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pdfs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("My Documents DB Error: " . $e->getMessage());
    set_flash_message("Failed to load your document library from database.", "danger");
}

$pageTitle = 'My Document Library';
$pageSubTitle = 'Manage your uploaded study materials (PDF, Word, Text, CSV, Excel, PowerPoint), view extracted text, or generate AI notes';

include __DIR__ . '/includes/header.php';
?>

<div class="page-card">
    <div class="section-header" style="margin-bottom: 24px;">
        <div>
            <h2 class="page-title">📁 My Document Library</h2>
            <p class="page-subtitle">Showing <?= count($pdfs) ?> uploaded <?= count($pdfs) === 1 ? 'document' : 'documents' ?></p>
        </div>
        <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary">
            📤 Upload New Document
        </a>
    </div>

    <?php display_flash_message(); ?>

    <?php if (empty($pdfs)): ?>
        <div style="text-align: center; padding: 50px 20px; color: var(--text-muted); background: var(--slate-50); border-radius: var(--radius-lg); border: 2px dashed var(--slate-300);">
            <div style="font-size: 3.5rem; margin-bottom: 12px; color: var(--primary-500);">📚</div>
            <h3 style="color: var(--slate-800); margin-bottom: 6px;">Your document library is empty</h3>
            <p style="font-size: 0.9rem; max-width: 480px; margin: 0 auto 20px;">
                Upload your lecture notes, Word documents, text files, spreadsheets, or presentations to generate AI summaries!
            </p>
            <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary">Upload First Document</a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Format</th>
                        <th>Document Filename</th>
                        <th>Subject</th>
                        <th>Items / Pages</th>
                        <th>Size</th>
                        <th>Upload Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pdfs as $pdf): 
                        $ext = strtolower($pdf['file_type'] ?? pathinfo($pdf['original_filename'], PATHINFO_EXTENSION));
                        $icon = get_format_icon($ext);
                    ?>
                        <tr>
                            <td>
                                <span class="tag-item" style="font-weight: bold; background: var(--slate-100); color: var(--slate-800); border-color: var(--slate-300);">
                                    <?= $icon ?> <?= strtoupper($ext) ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: var(--slate-900);">
                                <?= htmlspecialchars($pdf['original_filename']) ?>
                            </td>
                            <td>
                                <span class="tag-item" style="background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-200);">
                                    <?= htmlspecialchars($pdf['subject'] ?: 'General') ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= (int) $pdf['page_count'] ?></strong> 
                                <?php 
                                    if ($ext === 'csv') echo 'rows';
                                    elseif ($ext === 'xlsx') echo 'sheets';
                                    elseif ($ext === 'pptx') echo 'slides';
                                    elseif ($ext === 'docx') echo 'paragraphs';
                                    elseif ($ext === 'txt') echo 'lines';
                                    else echo 'pages';
                                ?>
                            </td>
                            <td>
                                <?= round($pdf['file_size'] / (1024 * 1024), 2) ?> MB
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">
                                <?= date('M d, Y H:i', strtotime($pdf['uploaded_at'])) ?>
                            </td>
                            <td>
                                <?php if ($pdf['has_exam_prep'] > 0 && $pdf['has_summary'] > 0): ?>
                                    <span class="status-badge status-success">✓ Notes &amp; Exam Prep</span>
                                <?php elseif ($pdf['has_exam_prep'] > 0): ?>
                                    <span class="status-badge status-success">📝 Exam Prep Ready</span>
                                <?php elseif ($pdf['has_summary'] > 0 && $pdf['has_mcqs'] > 0): ?>
                                    <span class="status-badge status-success">✓ Notes &amp; MCQs</span>
                                <?php elseif ($pdf['has_summary'] > 0): ?>
                                    <span class="status-badge status-success">⚡ Notes Ready</span>
                                <?php elseif ($pdf['has_mcqs'] > 0): ?>
                                    <span class="status-badge status-success">🎯 MCQs Ready</span>
                                <?php elseif ($pdf['status'] === 'completed' || $pdf['status'] === 'uploaded'): ?>
                                    <span class="status-badge status-warning">ℹ Ready</span>
                                <?php else: ?>
                                    <span class="status-badge status-danger">⚠ Error</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <!-- Exam Prep Action Button -->
                                    <a href="<?= url('exam-preparation.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" style="border-color: #f59e0b; color: #b45309;" title="Generate or View Exam Preparation Kit">
                                        📝 Exam Prep
                                    </a>

                                    <!-- AI Notes Action Button -->
                                    <a href="<?= url('pdf-results.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-primary" title="View or Generate AI Short Notes">
                                        ⚡ Notes
                                    </a>

                                    <!-- MCQ Practice Action Button -->
                                    <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" style="border-color: var(--primary-300); color: var(--primary-700);" title="Practice MCQs">
                                        🎯 MCQs
                                    </a>

                                    <!-- Ask Document Button -->
                                    <a href="<?= url('ask-from-pdf.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" style="border-color: #818cf8; color: #4f46e5;" title="Ask Questions from Document">
                                        🤖 Ask
                                    </a>

                                    <!-- View/Open File -->
                                    <a href="<?= url('view-pdf.php?id=' . $pdf['id']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Open File in Browser">
                                        👁️ View File
                                    </a>

                                    <!-- View Extracted Text -->
                                    <a href="<?= url('view-text.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-secondary" title="View Extracted Text">
                                        📑 Text
                                    </a>

                                    <!-- Delete Document -->
                                    <form action="<?= url('delete-pdf.php') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete \'<?= htmlspecialchars(addslashes($pdf['original_filename'])) ?>\'? This action cannot be undone.');">
                                        <input type="hidden" name="pdf_id" value="<?= $pdf['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--accent-rose); border-color: #fca5a5;" title="Delete Document">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

