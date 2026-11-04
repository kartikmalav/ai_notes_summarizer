<?php
/**
 * PDF Results View & Document Selection Page (AI Short Notes & Important Questions)
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';

// Enforce authentication
require_login();

$userId = $_SESSION['user_id'];
$pdfId = (int) ($_GET['id'] ?? 0);
$activeTab = ($_GET['tab'] ?? 'summary') === 'questions' ? 'questions' : 'summary';

$pdf = null;
$summaryRecord = null;
$questionsRecord = null;
$mcqRecord = null;
$examPrepRecord = null;
$userDocuments = [];
$errorMessage = null;

try {
    $pdo = getDBConnection();

    // If document ID is provided, verify ownership and load content
    if ($pdfId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$pdfId, $userId]);
        $pdf = $stmt->fetch();

        if (!$pdf) {
            $errorMessage = "Document not found or you do not have permission to access it.";
        } else {
            // Fetch existing AI generated short notes
            $sumStmt = $pdo->prepare("
                SELECT * FROM generated_content 
                WHERE pdf_id = ? AND user_id = ? AND content_type IN ('summary', 'short_notes') 
                ORDER BY id DESC LIMIT 1
            ");
            $sumStmt->execute([$pdfId, $userId]);
            $summaryRecord = $sumStmt->fetch();

            // Fetch existing AI generated important questions
            $qStmt = $pdo->prepare("
                SELECT * FROM generated_content 
                WHERE pdf_id = ? AND user_id = ? AND content_type IN ('important_questions', 'questions') 
                ORDER BY id DESC LIMIT 1
            ");
            $qStmt->execute([$pdfId, $userId]);
            $questionsRecord = $qStmt->fetch();

            // Fetch existing AI generated MCQs
            $mcqStmt = $pdo->prepare("
                SELECT * FROM generated_content 
                WHERE pdf_id = ? AND user_id = ? AND content_type = 'mcqs' 
                ORDER BY id DESC LIMIT 1
            ");
            $mcqStmt->execute([$pdfId, $userId]);
            $mcqRecord = $mcqStmt->fetch();

            // Fetch existing AI generated Exam Prep
            $epStmt = $pdo->prepare("
                SELECT id FROM generated_content 
                WHERE pdf_id = ? AND user_id = ? AND content_type = 'exam_prep' 
                ORDER BY id DESC LIMIT 1
            ");
            $epStmt->execute([$pdfId, $userId]);
            $examPrepRecord = $epStmt->fetch();

            $ext = strtolower($pdf['file_type'] ?? pathinfo($pdf['original_filename'], PATHINFO_EXTENSION));
            $icon = get_format_icon($ext);
        }
    }

    // If no document selected or if switching, load user documents for selector
    if (!$pdf) {
        $docsStmt = $pdo->prepare("
            SELECT p.*,
                (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u1 AND content_type IN ('summary', 'short_notes')) AS has_summary,
                (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u2 AND content_type IN ('questions', 'important_questions')) AS has_questions,
                (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u3 AND content_type = 'mcqs') AS has_mcqs,
                (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u4 AND content_type = 'exam_prep') AS has_exam_prep
            FROM pdf_documents p
            WHERE p.user_id = :u5
            ORDER BY p.uploaded_at DESC
        ");
        $docsStmt->execute([
            'u1' => $userId,
            'u2' => $userId,
            'u3' => $userId,
            'u4' => $userId,
            'u5' => $userId
        ]);
        $userDocuments = $docsStmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log("Results Page DB Error: " . $e->getMessage());
    $errorMessage = "Database error loading results.";
}

// Set dynamic page title
if ($pdf) {
    $pageTitle = ($activeTab === 'questions') ? 'Important Exam Questions: ' . htmlspecialchars($pdf['original_filename']) : 'AI Short Notes: ' . htmlspecialchars($pdf['original_filename']);
    $pageSubTitle = 'Study Material for ' . htmlspecialchars($pdf['original_filename']);
} else {
    $pageTitle = ($activeTab === 'questions') ? 'Important Questions - Select Document' : 'Short Notes - Select Document';
    $pageSubTitle = ($activeTab === 'questions') ? 'Select an uploaded document to generate or view 2, 5 & 10 Marks exam questions' : 'Select an uploaded document to generate or view concise AI study notes';
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px; max-width: 1100px; margin-left: auto; margin-right: auto;">
        <div class="alert-icon">⚠</div>
        <div class="alert-body"><?= htmlspecialchars($errorMessage) ?></div>
    </div>
    <div style="margin-bottom: 24px; max-width: 1100px; margin-left: auto; margin-right: auto;">
        <a href="<?= url('pdf-results.php?tab=' . urlencode($activeTab)) ?>" class="btn btn-primary">&larr; Back to Document Selection</a>
    </div>
<?php endif; ?>

<?php if (!$pdf): ?>
    <!-- ============================================================== -->
    <!-- VIEW A: DOCUMENT SELECTION MODE                                -->
    <!-- ============================================================== -->
    <div class="page-card" style="max-width: 1100px; margin: 0 auto;">
        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
            <div>
                <h2 class="page-title">
                    <?= $activeTab === 'questions' ? '❓ Important Exam Questions' : '⚡ AI Short Notes & Summaries' ?>
                </h2>
                <p class="page-subtitle">
                    <?= $activeTab === 'questions' 
                        ? 'Select an uploaded document below to generate or view high-yield exam questions' 
                        : 'Select an uploaded document below to generate or view key concepts and revision notes' ?>
                </p>
            </div>
            <div>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-sm btn-primary">
                    📤 Upload New Document
                </a>
            </div>
        </div>

        <?php display_flash_message(); ?>

        <!-- Tool Switcher Navigation Tabs -->
        <div class="tab-nav-bar" style="display: flex; gap: 8px; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; overflow-x: auto;">
            <a href="<?= url('pdf-results.php?tab=summary') ?>" class="tab-item <?= $activeTab === 'summary' ? 'active' : '' ?>">
                ⚡ Short Notes
            </a>
            <a href="<?= url('pdf-results.php?tab=questions') ?>" class="tab-item <?= $activeTab === 'questions' ? 'active' : '' ?>">
                ❓ Important Questions
            </a>
            <a href="<?= url('exam-preparation.php') ?>" class="tab-item">
                📝 Exam Prep
            </a>
            <a href="<?= url('mcqs-practice.php') ?>" class="tab-item">
                🎯 Practice MCQs
            </a>
            <a href="<?= url('ask-from-pdf.php') ?>" class="tab-item">
                🤖 Ask Document
            </a>
        </div>

        <!-- Informative Feature Banner -->
        <?php if ($activeTab === 'summary'): ?>
            <div class="exam-feature-banner" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(139, 92, 246, 0.06) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px;">
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-title); display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <span>⚡</span> What's in AI Short Notes?
                </h3>
                <p style="font-size: 0.88rem; color: var(--text-body); margin-bottom: 14px;">
                    Our AI synthesizes your document into an executive summary, key definitions, core concepts, formulas, and an exam revision checklist:
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                    <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                        <strong>📌 Executive Summary</strong><br><span style="color: var(--text-muted);">Quick high-level digest</span>
                    </div>
                    <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                        <strong>📖 Key Definitions</strong><br><span style="color: var(--text-muted);">Must-know terminology</span>
                    </div>
                    <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                        <strong>💡 Core Concepts</strong><br><span style="color: var(--text-muted);">Detailed revision points</span>
                    </div>
                    <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                        <strong>✅ Exam Checklist</strong><br><span style="color: var(--text-muted);">High-yield topics</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="exam-feature-banner" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(236, 72, 153, 0.06) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px;">
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-title); display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <span>❓</span> What's in Important Questions?
                </h3>
                <p style="font-size: 0.88rem; color: var(--text-body); margin-bottom: 14px;">
                    Exam-style question paper structure grounded directly in your syllabus material:
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                    <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                        <strong>⚡ Section A (2 Marks)</strong><br><span style="color: var(--text-muted);">5 short answer questions</span>
                    </div>
                    <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                        <strong>💡 Section B (5 Marks)</strong><br><span style="color: var(--text-muted);">3 medium conceptual questions</span>
                    </div>
                    <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                        <strong>🔑 Section C (10 Marks)</strong><br><span style="color: var(--text-muted);">2 comprehensive long questions</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($userDocuments)): ?>
            <!-- Empty state: No documents uploaded -->
            <div style="text-align: center; padding: 50px 20px; color: var(--text-muted); background: var(--bg-card-subtle); border-radius: var(--radius-lg); border: 2px dashed var(--border-color); margin-top: 10px;">
                <div style="font-size: 3.5rem; margin-bottom: 12px; color: var(--primary-500);">📚</div>
                <h3 style="color: var(--text-title); margin-bottom: 6px;">No Documents Uploaded Yet</h3>
                <p style="font-size: 0.9rem; max-width: 480px; margin: 0 auto 20px; color: var(--text-muted);">
                    Upload a lecture PDF, Word doc, presentation, spreadsheet, or text notes to generate AI study materials.
                </p>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary">
                    📤 Upload First Document
                </a>
            </div>
        <?php else: ?>
            <!-- Document List for Selection -->
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($userDocuments as $doc): ?>
                    <?php 
                        $docExt = strtolower($doc['file_type'] ?? pathinfo($doc['original_filename'], PATHINFO_EXTENSION));
                        $docIcon = get_format_icon($docExt);
                        $hasNotes = ($doc['has_summary'] > 0);
                        $hasQuestions = ($doc['has_questions'] > 0);
                        $targetUrl = url('pdf-results.php?id=' . (int)$doc['id'] . '&tab=' . urlencode($activeTab));
                    ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; padding: 16px 20px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); transition: var(--transition-fast);">
                        <div style="display: flex; align-items: center; gap: 14px; min-width: 260px; flex: 1;">
                            <span class="badge" style="background: var(--bg-card-subtle); border: 1px solid var(--border-color); font-weight: 700; font-size: 0.8rem; padding: 6px 10px; border-radius: 8px;">
                                <?= $docIcon ?> <?= strtoupper($docExt) ?>
                            </span>
                            <div style="min-width: 0;">
                                <a href="<?= $targetUrl ?>" style="font-weight: 700; color: var(--text-title); font-size: 0.95rem; text-decoration: none; word-break: break-word;" title="<?= htmlspecialchars($doc['original_filename']) ?>">
                                    <?= htmlspecialchars($doc['original_filename']) ?>
                                </a>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                                    Subject: <span class="tag-item" style="font-size: 0.72rem; padding: 1px 6px;"><?= htmlspecialchars($doc['subject'] ?: 'General') ?></span> &bull; 
                                    Uploaded: <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?> &bull; 
                                    <?= round($doc['file_size'] / (1024 * 1024), 2) ?> MB
                                </div>
                            </div>
                        </div>

                        <!-- Status & Action Button -->
                        <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                            <?php if ($activeTab === 'summary'): ?>
                                <?php if ($hasNotes): ?>
                                    <span class="status-badge status-success">✓ Notes Ready</span>
                                    <a href="<?= $targetUrl ?>" class="btn btn-sm btn-primary">
                                        ⚡ View Notes &rarr;
                                    </a>
                                <?php else: ?>
                                    <span class="status-badge status-warning">Not Generated</span>
                                    <a href="<?= $targetUrl ?>" class="btn btn-sm btn-outline">
                                        ⚡ Generate Notes &rarr;
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($hasQuestions): ?>
                                    <span class="status-badge status-success">✓ Questions Ready</span>
                                    <a href="<?= $targetUrl ?>" class="btn btn-sm btn-primary">
                                        ❓ View Questions &rarr;
                                    </a>
                                <?php else: ?>
                                    <span class="status-badge status-warning">Not Generated</span>
                                    <a href="<?= $targetUrl ?>" class="btn btn-sm btn-outline">
                                        ❓ Generate Questions &rarr;
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ============================================================== -->
    <!-- VIEW B: DOCUMENT RESULTS & GENERATION VIEW                     -->
    <!-- ============================================================== -->
    <div class="page-card" style="max-width: 960px; margin: 0 auto;">
        <!-- Document Header Info -->
        <div class="section-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
            <div>
                <h2 class="page-title">
                    <?= $activeTab === 'questions' ? '❓ Important Exam Questions (2/5/10 Marks)' : '⚡ AI Short Notes & Key Concepts' ?>
                </h2>
                <p class="page-subtitle" style="margin-top: 4px;">
                    Document: <strong style="color: var(--text-title);"><?= $icon ?> <?= htmlspecialchars($pdf['original_filename']) ?></strong> | 
                    Subject: <span class="tag-item"><?= htmlspecialchars($pdf['subject'] ?: 'General') ?></span> | 
                    Items: <strong><?= (int)$pdf['page_count'] ?></strong>
                </p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <!-- Document Switcher Button -->
                <a href="<?= url('pdf-results.php?tab=' . urlencode($activeTab)) ?>" class="btn btn-sm btn-outline" title="Switch to another document">
                    &larr; Select Another Document
                </a>
                <a href="<?= url('exam-preparation.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" style="border-color: #f59e0b; color: #b45309;">
                    📝 Exam Prep
                </a>
                <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-primary">
                    🎯 Practice MCQs
                </a>
                <a href="<?= url('ask-from-pdf.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" style="border-color: #818cf8; color: #4f46e5;">
                    🤖 Ask Document
                </a>
                <a href="<?= url('view-pdf.php?id=' . $pdf['id']) ?>" target="_blank" class="btn btn-sm btn-outline">
                    👁️ View File
                </a>
                <a href="<?= url('view-text.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-secondary">
                    📑 Text
                </a>
            </div>
        </div>

        <?php display_flash_message(); ?>

        <!-- Navigation Tabs -->
        <div class="tab-nav-bar" style="display: flex; gap: 12px; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; overflow-x: auto;">
            <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=summary') ?>" class="tab-item <?= $activeTab === 'summary' ? 'active' : '' ?>">
                ⚡ Short Notes
                <?php if ($summaryRecord): ?>
                    <span class="status-badge status-success" style="margin-left: 6px; font-size: 0.7rem;">✓</span>
                <?php endif; ?>
            </a>

            <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=questions') ?>" class="tab-item <?= $activeTab === 'questions' ? 'active' : '' ?>">
                ❓ Important Questions
                <?php if ($questionsRecord): ?>
                    <span class="status-badge status-success" style="margin-left: 6px; font-size: 0.7rem;">✓</span>
                <?php endif; ?>
            </a>

            <a href="<?= url('exam-preparation.php?id=' . $pdf['id']) ?>" class="tab-item">
                📝 Exam Prep
                <?php if ($examPrepRecord): ?>
                    <span class="status-badge status-success" style="margin-left: 6px; font-size: 0.7rem;">✓</span>
                <?php endif; ?>
            </a>

            <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="tab-item">
                🎯 Practice MCQs
                <?php if ($mcqRecord): ?>
                    <span class="status-badge status-success" style="margin-left: 6px; font-size: 0.7rem;">✓</span>
                <?php endif; ?>
            </a>

            <a href="<?= url('ask-from-pdf.php?id=' . $pdf['id']) ?>" class="tab-item">
                🤖 Ask Document
            </a>
        </div>

        <!-- Loading & Progress Box (Hidden by default) -->
        <div id="aiLoadingBox" class="alert alert-info" style="display: none; padding: 24px; border-radius: var(--radius-lg); text-align: center; margin-bottom: 24px;">
            <div style="font-size: 2.5rem; margin-bottom: 10px; animation: pulse 1.5s infinite;">🤖</div>
            <h3 id="aiLoadingTitle" style="font-size: 1.2rem; font-weight: 700; margin-bottom: 6px;">Groq AI is processing...</h3>
            <p id="aiLoadingSub" style="font-size: 0.9rem;">
                Analyzing document content and generating structured study material. This takes 5–10 seconds.
            </p>
        </div>

        <!-- Error Alert Box (Hidden by default) -->
        <div id="aiErrorBox" class="alert alert-danger" style="display: none; margin-bottom: 24px;">
            <div class="alert-icon">⚠</div>
            <div id="aiErrorText" class="alert-body">An error occurred while generating content.</div>
        </div>

        <!-- TAB 1: SHORT NOTES -->
        <?php if ($activeTab === 'summary'): ?>
            <?php if ($summaryRecord && !empty($summaryRecord['content_data'])): ?>
                <!-- Render Generated Short Notes -->
                <div class="summary-actions-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; background: var(--bg-card-subtle); padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        ✓ Short notes generated on <?= date('M d, Y \a\t H:i', strtotime($summaryRecord['created_at'])) ?>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" id="generateSummaryBtn" class="btn btn-sm btn-outline" onclick="triggerSummaryGeneration(<?= $pdf['id'] ?>)">
                            ⚡ Regenerate Notes
                        </button>
                        <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=questions') ?>" class="btn btn-sm btn-outline">
                            View Questions
                        </a>
                        <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-primary">
                            🎯 Practice MCQs &rarr;
                        </a>
                    </div>
                </div>

                <div class="notes-content-container" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm); line-height: 1.7;">
                    <?= render_markdown($summaryRecord['content_data']) ?>
                </div>
            <?php else: ?>
                <!-- Empty State: Generate Short Notes -->
                <div style="text-align: center; padding: 60px 20px; background: var(--bg-card-subtle); border-radius: var(--radius-lg); border: 2px dashed var(--border-color);">
                    <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--primary-600);">⚡</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-title); margin-bottom: 8px;">
                        No AI Short Notes Generated Yet
                    </h3>
                    <p style="font-size: 0.92rem; color: var(--text-muted); max-width: 540px; margin: 0 auto 24px;">
                        Click below to generate executive summary, key definitions, core concepts, formulas, and an exam revision checklist from this document.
                    </p>

                    <button type="button" id="generateSummaryBtn" class="btn btn-primary" style="padding: 14px 32px; font-size: 1rem;" onclick="triggerSummaryGeneration(<?= $pdf['id'] ?>)">
                        ⚡ Generate AI Short Notes Now
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB 2: IMPORTANT QUESTIONS -->
        <?php if ($activeTab === 'questions'): ?>
            <?php if ($questionsRecord && !empty($questionsRecord['content_data'])): ?>
                <!-- Render Generated Important Questions -->
                <div class="summary-actions-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; background: var(--bg-card-subtle); padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        ✓ Questions generated on <?= date('M d, Y \a\t H:i', strtotime($questionsRecord['created_at'])) ?>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" id="generateQuestionsBtn" class="btn btn-sm btn-outline" onclick="triggerQuestionsGeneration(<?= $pdf['id'] ?>)">
                            ❓ Regenerate Questions
                        </button>
                        <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=summary') ?>" class="btn btn-sm btn-outline">
                            View Short Notes
                        </a>
                        <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-primary">
                            🎯 Practice MCQs &rarr;
                        </a>
                    </div>
                </div>

                <div class="notes-content-container" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm); line-height: 1.7;">
                    <?= render_markdown($questionsRecord['content_data']) ?>
                </div>
            <?php else: ?>
                <!-- Empty State: Generate Important Questions -->
                <div style="text-align: center; padding: 60px 20px; background: var(--bg-card-subtle); border-radius: var(--radius-lg); border: 2px dashed var(--border-color);">
                    <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--accent-purple);">❓</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-title); margin-bottom: 8px;">
                        No Important Questions Generated Yet
                    </h3>
                    <p style="font-size: 0.92rem; color: var(--text-muted); max-width: 540px; margin: 0 auto 24px;">
                        Click below to generate exam-ready 2-Mark, 5-Mark, and 10-Mark Questions with model answers derived directly from this document!
                    </p>

                    <button type="button" id="generateQuestionsBtn" class="btn btn-primary" style="padding: 14px 32px; font-size: 1rem; background: linear-gradient(135deg, var(--accent-purple), var(--primary-700));" onclick="triggerQuestionsGeneration(<?= $pdf['id'] ?>)">
                        ❓ Generate Important Questions Now
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function triggerSummaryGeneration(pdfId) {
    const btn = document.getElementById('generateSummaryBtn');
    const loadingBox = document.getElementById('aiLoadingBox');
    const loadingTitle = document.getElementById('aiLoadingTitle');
    const loadingSub = document.getElementById('aiLoadingSub');
    const errorBox = document.getElementById('aiErrorBox');
    const errorText = document.getElementById('aiErrorText');

    if (btn) btn.disabled = true;
    if (loadingTitle) loadingTitle.textContent = 'Groq AI is generating your short notes...';
    if (loadingSub) loadingSub.textContent = 'Extracting key concepts, formulas, and revision points from your document.';
    if (loadingBox) loadingBox.style.display = 'block';
    if (errorBox) errorBox.style.display = 'none';

    window.scrollTo({ top: 150, behavior: 'smooth' });

    fetch('<?= url("api/generate-summary.php") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pdf_id: pdfId })
    })
    .then(response => response.json().then(data => ({ status: response.status, body: data })))
    .then(res => {
        if (loadingBox) loadingBox.style.display = 'none';
        if (res.body.success) {
            window.location.href = res.body.redirect || window.location.href;
        } else {
            if (btn) btn.disabled = false;
            if (errorText) errorText.textContent = res.body.error || 'Failed to generate AI notes.';
            if (errorBox) errorBox.style.display = 'flex';
        }
    })
    .catch(err => {
        console.error('Fetch Error:', err);
        if (loadingBox) loadingBox.style.display = 'none';
        if (btn) btn.disabled = false;
        if (errorText) errorText.textContent = 'Network or server error while requesting Groq API.';
        if (errorBox) errorBox.style.display = 'flex';
    });
}

function triggerQuestionsGeneration(pdfId) {
    const btn = document.getElementById('generateQuestionsBtn');
    const loadingBox = document.getElementById('aiLoadingBox');
    const loadingTitle = document.getElementById('aiLoadingTitle');
    const loadingSub = document.getElementById('aiLoadingSub');
    const errorBox = document.getElementById('aiErrorBox');
    const errorText = document.getElementById('aiErrorText');

    if (btn) btn.disabled = true;
    if (loadingTitle) loadingTitle.textContent = 'Groq AI is generating 2-Mark, 5-Mark & 10-Mark Exam Questions...';
    if (loadingSub) loadingSub.textContent = 'Analyzing content to construct comprehensive exam answers.';
    if (loadingBox) loadingBox.style.display = 'block';
    if (errorBox) errorBox.style.display = 'none';

    window.scrollTo({ top: 150, behavior: 'smooth' });

    fetch('<?= url("api/generate-questions.php") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pdf_id: pdfId })
    })
    .then(response => response.json().then(data => ({ status: response.status, body: data })))
    .then(res => {
        if (loadingBox) loadingBox.style.display = 'none';
        if (res.body.success) {
            window.location.href = res.body.redirect || window.location.href;
        } else {
            if (btn) btn.disabled = false;
            if (errorText) errorText.textContent = res.body.error || 'Failed to generate Important Questions.';
            if (errorBox) errorBox.style.display = 'flex';
        }
    })
    .catch(err => {
        console.error('Fetch Error:', err);
        if (loadingBox) loadingBox.style.display = 'none';
        if (btn) btn.disabled = false;
        if (errorText) errorText.textContent = 'Network or server error while requesting Groq API.';
        if (errorBox) errorBox.style.display = 'flex';
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
