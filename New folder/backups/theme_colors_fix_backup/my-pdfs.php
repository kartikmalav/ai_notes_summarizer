<?php
/**
 * My Documents Library Page
 * AI-Based Study Assistant (PDF, DOCX, TXT, CSV, XLSX, PPTX)
 * Responsive Desktop Table & Mobile Card Views
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
    // Fetch logged-in user's documents with summary, questions, mcq, and exam_prep status
    $sql = "
        SELECT p.*, 
               (SELECT COUNT(*) FROM generated_content g WHERE g.pdf_id = p.id AND g.user_id = :u1 AND g.content_type IN ('summary', 'short_notes')) AS has_summary,
               (SELECT COUNT(*) FROM generated_content g WHERE g.pdf_id = p.id AND g.user_id = :u2 AND g.content_type IN ('questions', 'important_questions')) AS has_questions,
               (SELECT COUNT(*) FROM generated_content g WHERE g.pdf_id = p.id AND g.user_id = :u3 AND g.content_type = 'mcqs') AS has_mcqs,
               (SELECT COUNT(*) FROM generated_content g WHERE g.pdf_id = p.id AND g.user_id = :u4 AND g.content_type = 'exam_prep') AS has_exam_prep
        FROM pdf_documents p 
        WHERE p.user_id = :u5 
    ";
    $params = ['u1' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId, 'u5' => $userId];

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

<div class="my-docs-container">
    <!-- Header Row -->
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
        <div>
            <h2 class="page-title">📁 My Document Library</h2>
            <p class="page-subtitle">
                <?php if ($searchQuery !== ''): ?>
                    Showing search results for "<strong><?= htmlspecialchars($searchQuery) ?></strong>" 
                    (<a href="<?= url('my-pdfs.php') ?>" style="color: var(--primary-600); text-decoration: underline;">Clear filter</a>)
                <?php else: ?>
                    Showing <?= count($pdfs) ?> uploaded <?= count($pdfs) === 1 ? 'document' : 'documents' ?>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary" style="white-space: nowrap;">
                📤 Upload New Document
            </a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <?php if (empty($pdfs)): ?>
        <!-- Empty State -->
        <div style="text-align: center; padding: 60px 20px; color: var(--text-muted); background: var(--bg-card); border-radius: var(--radius-lg); border: 2px dashed var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--primary-500);">📚</div>
            <?php if ($searchQuery !== ''): ?>
                <h3 style="color: var(--text-title); margin-bottom: 8px;">No documents match "<?= htmlspecialchars($searchQuery) ?>"</h3>
                <p style="font-size: 0.92rem; max-width: 480px; margin: 0 auto 20px; color: var(--text-muted);">
                    Try searching with another keyword or view all your uploaded documents.
                </p>
                <a href="<?= url('my-pdfs.php') ?>" class="btn btn-outline">View All Documents</a>
            <?php else: ?>
                <h3 style="color: var(--text-title); margin-bottom: 8px;">Your document library is empty</h3>
                <p style="font-size: 0.92rem; max-width: 480px; margin: 0 auto 20px; color: var(--text-muted);">
                    Upload your lecture notes, Word documents, text files, spreadsheets, or presentations to generate AI notes, exam questions, and practice quizzes!
                </p>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary">📤 Upload First Document</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- ============================================================== -->
        <!-- 1. DESKTOP TABLE VIEW (Visible on >= 900px screens)            -->
        <!-- ============================================================== -->
        <div class="my-docs-table-wrapper">
            <table class="my-docs-table">
                <thead>
                    <tr>
                        <th style="width: 75px; text-align: center;">Format</th>
                        <th style="min-width: 220px; max-width: 300px;">Document Filename</th>
                        <th style="width: 120px;">Subject</th>
                        <th style="width: 110px;">Size &amp; Items</th>
                        <th style="width: 130px;">AI Status</th>
                        <th style="text-align: right; min-width: 320px;">Study Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pdfs as $pdf): 
                        $ext = strtolower($pdf['file_type'] ?? pathinfo($pdf['original_filename'], PATHINFO_EXTENSION));
                        $icon = get_format_icon($ext);
                    ?>
                        <tr>
                            <!-- Format Badge -->
                            <td style="text-align: center;">
                                <span class="tag-item" style="font-weight: 700; font-size: 0.75rem; background: var(--bg-card-subtle); color: var(--text-body); border-color: var(--border-color); padding: 3px 8px; border-radius: 6px;">
                                    <?= $icon ?> <?= strtoupper($ext) ?>
                                </span>
                            </td>

                            <!-- Document Name & Upload Meta -->
                            <td class="doc-title-cell">
                                <span class="doc-title-text" title="<?= htmlspecialchars($pdf['original_filename']) ?>">
                                    <?= htmlspecialchars($pdf['original_filename']) ?>
                                </span>
                                <div class="doc-meta-sub">
                                    Uploaded: <?= date('M d, Y', strtotime($pdf['uploaded_at'])) ?>
                                </div>
                            </td>

                            <!-- Subject Tag -->
                            <td>
                                <span class="tag-item" style="background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-200); font-size: 0.76rem; max-width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block;">
                                    <?= htmlspecialchars($pdf['subject'] ?: 'General') ?>
                                </span>
                            </td>

                            <!-- Size & Items -->
                            <td style="font-size: 0.82rem; color: var(--text-muted);">
                                <div><strong><?= round($pdf['file_size'] / (1024 * 1024), 2) ?> MB</strong></div>
                                <div style="font-size: 0.75rem;">
                                    <?= (int) $pdf['page_count'] ?> 
                                    <?php 
                                        if ($ext === 'csv') echo 'rows';
                                        elseif ($ext === 'xlsx') echo 'sheets';
                                        elseif ($ext === 'pptx') echo 'slides';
                                        elseif ($ext === 'docx') echo 'paras';
                                        elseif ($ext === 'txt') echo 'lines';
                                        else echo 'pages';
                                    ?>
                                </div>
                            </td>

                            <!-- AI Status Badge -->
                            <td>
                                <?php if ($pdf['has_exam_prep'] > 0 && $pdf['has_summary'] > 0): ?>
                                    <span class="status-badge status-success">✓ Notes &amp; Prep</span>
                                <?php elseif ($pdf['has_exam_prep'] > 0): ?>
                                    <span class="status-badge status-success">📝 Prep Ready</span>
                                <?php elseif ($pdf['has_summary'] > 0 && $pdf['has_mcqs'] > 0): ?>
                                    <span class="status-badge status-success">✓ Notes &amp; MCQs</span>
                                <?php elseif ($pdf['has_summary'] > 0): ?>
                                    <span class="status-badge status-success">⚡ Notes Ready</span>
                                <?php elseif ($pdf['has_questions'] > 0): ?>
                                    <span class="status-badge status-success">❓ Qs Ready</span>
                                <?php elseif ($pdf['has_mcqs'] > 0): ?>
                                    <span class="status-badge status-success">🎯 MCQs Ready</span>
                                <?php else: ?>
                                    <span class="status-badge status-warning">ℹ Ready</span>
                                <?php endif; ?>
                            </td>

                            <!-- Compact Actions -->
                            <td class="doc-actions-cell">
                                <div class="doc-actions-group">
                                    <!-- Short Notes Action Button -->
                                    <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=summary') ?>" class="btn-doc-action" style="background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-200);" title="View or Generate AI Short Notes">
                                        ⚡ Notes
                                    </a>

                                    <!-- Important Questions Action Button -->
                                    <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=questions') ?>" class="btn-doc-action" style="background: var(--bg-card-subtle); color: var(--text-body); border-color: var(--border-color);" title="View or Generate Important Questions">
                                        ❓ Qs
                                    </a>

                                    <!-- MCQ Practice Action Button -->
                                    <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn-doc-action" style="background: var(--bg-card-subtle); color: var(--text-body); border-color: var(--border-color);" title="Practice 10 Interactive MCQs">
                                        🎯 MCQs
                                    </a>

                                    <!-- Exam Prep Action Button -->
                                    <a href="<?= url('exam-preparation.php?id=' . $pdf['id']) ?>" class="btn-doc-action" style="background: rgba(245, 158, 11, 0.1); color: #b45309; border-color: rgba(245, 158, 11, 0.3);" title="2, 5 & 10 Marks Exam Prep Kit">
                                        📝 Prep
                                    </a>

                                    <!-- Ask Document Button -->
                                    <a href="<?= url('ask-from-pdf.php?id=' . $pdf['id']) ?>" class="btn-doc-action" style="background: rgba(99, 102, 241, 0.08); color: var(--primary-600); border-color: var(--primary-200);" title="Ask Questions from Document">
                                        🤖 Ask
                                    </a>

                                    <!-- View/Open File -->
                                    <a href="<?= url('view-pdf.php?id=' . $pdf['id']) ?>" target="_blank" class="btn-doc-action" style="background: var(--bg-card-subtle); color: var(--text-muted); border-color: var(--border-color);" title="Open File in New Tab">
                                        👁️
                                    </a>

                                    <!-- Delete Document -->
                                    <form action="<?= url('delete-pdf.php') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete \'<?= htmlspecialchars(addslashes($pdf['original_filename'])) ?>\'? This action cannot be undone.');">
                                        <input type="hidden" name="pdf_id" value="<?= $pdf['id'] ?>">
                                        <button type="submit" class="btn-doc-action" style="background: rgba(239, 68, 68, 0.08); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); cursor: pointer;" title="Delete Document">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================================== -->
        <!-- 2. MOBILE & TABLET RESPONSIVE CARDS VIEW (< 900px screens)     -->
        <!-- ============================================================== -->
        <div class="my-docs-cards-grid">
            <?php foreach ($pdfs as $pdf): 
                $ext = strtolower($pdf['file_type'] ?? pathinfo($pdf['original_filename'], PATHINFO_EXTENSION));
                $icon = get_format_icon($ext);
            ?>
                <div class="my-doc-card">
                    <!-- Card Top Header -->
                    <div class="doc-card-header">
                        <div class="doc-card-title-group">
                            <span class="tag-item" style="font-weight: 700; font-size: 0.78rem; background: var(--bg-card-subtle); color: var(--text-body); border-color: var(--border-color); padding: 4px 8px; border-radius: 6px; flex-shrink: 0;">
                                <?= $icon ?> <?= strtoupper($ext) ?>
                            </span>
                            <div class="doc-card-title">
                                <?= htmlspecialchars($pdf['original_filename']) ?>
                            </div>
                        </div>
                        <span class="tag-item" style="background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-200); font-size: 0.74rem; flex-shrink: 0;">
                            <?= htmlspecialchars($pdf['subject'] ?: 'General') ?>
                        </span>
                    </div>

                    <!-- Meta Row -->
                    <div class="doc-card-meta-row">
                        <span>📅 <?= date('M d, Y', strtotime($pdf['uploaded_at'])) ?></span>
                        <span>💾 <?= round($pdf['file_size'] / (1024 * 1024), 2) ?> MB</span>
                        <span>📄 <?= (int) $pdf['page_count'] ?> items</span>
                    </div>

                    <!-- Status Row -->
                    <div class="doc-card-status-row">
                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">Status:</span>
                        <?php if ($pdf['has_exam_prep'] > 0 && $pdf['has_summary'] > 0): ?>
                            <span class="status-badge status-success">✓ Notes &amp; Prep Ready</span>
                        <?php elseif ($pdf['has_exam_prep'] > 0): ?>
                            <span class="status-badge status-success">📝 Exam Prep Ready</span>
                        <?php elseif ($pdf['has_summary'] > 0 && $pdf['has_mcqs'] > 0): ?>
                            <span class="status-badge status-success">✓ Notes &amp; MCQs Ready</span>
                        <?php elseif ($pdf['has_summary'] > 0): ?>
                            <span class="status-badge status-success">⚡ Notes Ready</span>
                        <?php elseif ($pdf['has_questions'] > 0): ?>
                            <span class="status-badge status-success">❓ Questions Ready</span>
                        <?php elseif ($pdf['has_mcqs'] > 0): ?>
                            <span class="status-badge status-success">🎯 MCQs Ready</span>
                        <?php else: ?>
                            <span class="status-badge status-warning">ℹ Uploaded</span>
                        <?php endif; ?>
                    </div>

                    <!-- Card Actions Grid -->
                    <div class="doc-card-actions-grid">
                        <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=summary') ?>" class="btn-card-action btn-card-primary">
                            ⚡ Short Notes
                        </a>
                        <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=questions') ?>" class="btn-card-action">
                            ❓ Questions
                        </a>
                        <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn-card-action">
                            🎯 MCQ Practice
                        </a>
                        <a href="<?= url('exam-preparation.php?id=' . $pdf['id']) ?>" class="btn-card-action">
                            📝 Exam Prep
                        </a>
                        <a href="<?= url('ask-from-pdf.php?id=' . $pdf['id']) ?>" class="btn-card-action">
                            🤖 Ask Document
                        </a>
                        <a href="<?= url('view-pdf.php?id=' . $pdf['id']) ?>" target="_blank" class="btn-card-action">
                            👁️ View File
                        </a>
                        <a href="<?= url('view-text.php?id=' . $pdf['id']) ?>" class="btn-card-action">
                            📑 Text
                        </a>
                        <form action="<?= url('delete-pdf.php') ?>" method="POST" style="display: contents;" onsubmit="return confirm('Are you sure you want to delete \'<?= htmlspecialchars(addslashes($pdf['original_filename'])) ?>\'?');">
                            <input type="hidden" name="pdf_id" value="<?= $pdf['id'] ?>">
                            <button type="submit" class="btn-card-action btn-card-danger" style="cursor: pointer;">
                                🗑️ Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
