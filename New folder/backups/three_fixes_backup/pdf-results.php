<?php
/**
 * PDF Results View Page (AI Short Notes & Important Questions)
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
$activeTab = $_GET['tab'] ?? 'summary';

if ($pdfId <= 0) {
    set_flash_message("Invalid document requested.", "danger");
    header('Location: ' . url('my-pdfs.php'));
    exit();
}

$pdf = null;
$summaryRecord = null;
$questionsRecord = null;

try {
    $pdo = getDBConnection();

    // IDOR Check: Ensure user owns this PDF
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        set_flash_message("Access denied or document not found.", "danger");
        header('Location: ' . url('my-pdfs.php'));
        exit();
    }

    // Fetch existing AI generated short notes
    $sumStmt = $pdo->prepare("
        SELECT * FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type IN ('summary', 'short_notes') 
        LIMIT 1
    ");
    $sumStmt->execute([$pdfId, $userId]);
    $summaryRecord = $sumStmt->fetch();

    // Fetch existing AI generated important questions
    $qStmt = $pdo->prepare("
        SELECT * FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type IN ('important_questions', 'questions') 
        LIMIT 1
    ");
    $qStmt->execute([$pdfId, $userId]);
    $questionsRecord = $qStmt->fetch();

    // Fetch existing AI generated MCQs
    $mcqStmt = $pdo->prepare("
        SELECT * FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type = 'mcqs' 
        LIMIT 1
    ");
    $mcqStmt->execute([$pdfId, $userId]);
    $mcqRecord = $mcqStmt->fetch();

    // Fetch existing AI generated Exam Prep
    $epStmt = $pdo->prepare("
        SELECT id FROM generated_content 
        WHERE pdf_id = ? AND user_id = ? AND content_type = 'exam_prep' 
        LIMIT 1
    ");
    $epStmt->execute([$pdfId, $userId]);
    $examPrepRecord = $epStmt->fetch();

    // Fetch format icon
    $ext = strtolower($pdf['file_type'] ?? pathinfo($pdf['original_filename'], PATHINFO_EXTENSION));
    $icon = get_format_icon($ext);

} catch (PDOException $e) {
    error_log("Results Page DB Error: " . $e->getMessage());
    set_flash_message("Database error loading results.", "danger");
}

$pageTitle = ($activeTab === 'questions') ? 'Important Exam Questions' : 'AI Short Notes';
$pageSubTitle = 'Study Material for ' . htmlspecialchars($pdf['original_filename']);

include __DIR__ . '/includes/header.php';
?>

<div class="page-card" style="max-width: 960px; margin: 0 auto;">
    <!-- Document Header Info -->
    <div class="section-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
        <div>
            <h2 class="page-title">
                <?= $activeTab === 'questions' ? '❓ Important Exam Questions (2/5/10 Marks)' : '⚡ AI Short Notes & Key Concepts' ?>
            </h2>
            <p class="page-subtitle" style="margin-top: 4px;">
                Document: <strong style="color: var(--slate-900);"><?= $icon ?> <?= htmlspecialchars($pdf['original_filename']) ?></strong> | 
                Subject: <span class="tag-item"><?= htmlspecialchars($pdf['subject'] ?: 'General') ?></span> | 
                Items: <strong><?= $pdf['page_count'] ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
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
                👁️ View Original File
            </a>
            <a href="<?= url('view-text.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-secondary">
                📑 View Extracted Text
            </a>
            <a href="<?= url('my-pdfs.php') ?>" class="btn btn-sm btn-outline">
                📁 My Documents
            </a>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <!-- Navigation Tabs -->
    <div class="tab-nav-bar" style="display: flex; gap: 12px; border-bottom: 2px solid var(--border-color); margin-bottom: 24px;">
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
        <h3 id="aiLoadingTitle" style="font-size: 1.2rem; font-weight: 700; color: #1e40af; margin-bottom: 6px;">Gemini AI is processing...</h3>
        <p id="aiLoadingSub" style="font-size: 0.9rem; color: #1e3a8a;">
            Analyzing PDF text and generating structured study material. This takes 5–10 seconds.
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
            <div class="summary-actions-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--slate-50); padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    ✓ Short notes generated on <?= date('M d, Y \a\t H:i', strtotime($summaryRecord['created_at'])) ?>
                </div>
                <div style="display: flex; gap: 10px;">
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

            <div class="notes-content-container" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm); line-height: 1.7;">
                <?= render_markdown($summaryRecord['content_data']) ?>
            </div>
        <?php else: ?>
            <!-- Empty State: Generate Short Notes -->
            <div style="text-align: center; padding: 60px 20px; background: var(--slate-50); border-radius: var(--radius-lg); border: 2px dashed var(--slate-300);">
                <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--primary-600);">⚡</div>
                <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--slate-900); margin-bottom: 8px;">
                    No AI Short Notes Generated Yet
                </h3>
                <p style="font-size: 0.92rem; color: var(--text-muted); max-width: 540px; margin: 0 auto 24px;">
                    Click below to generate executive summary, key definitions, core concepts, formulas, and an exam revision checklist.
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
            <div class="summary-actions-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--slate-50); padding: 12px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    ✓ Questions generated on <?= date('M d, Y \a\t H:i', strtotime($questionsRecord['created_at'])) ?>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="generateQuestionsBtn" class="btn btn-sm btn-outline" onclick="triggerQuestionsGeneration(<?= $pdf['id'] ?>)">
                        ❓ Regenerate Questions
                    </button>
                    <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-primary">
                        🎯 Practice MCQs &rarr;
                    </a>
                </div>
            </div>

            <div class="notes-content-container" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm); line-height: 1.7;">
                <?= render_markdown($questionsRecord['content_data']) ?>
            </div>
        <?php else: ?>
            <!-- Empty State: Generate Important Questions -->
            <div style="text-align: center; padding: 60px 20px; background: var(--slate-50); border-radius: var(--radius-lg); border: 2px dashed var(--slate-300);">
                <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--accent-purple);">❓</div>
                <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--slate-900); margin-bottom: 8px;">
                    No Important Questions Generated Yet
                </h3>
                <p style="font-size: 0.92rem; color: var(--text-muted); max-width: 540px; margin: 0 auto 24px;">
                    Click below to generate exam-ready 2-Mark, 5-Mark, and 10-Mark Questions with answers derived directly from your PDF document!
                </p>

                <button type="button" id="generateQuestionsBtn" class="btn btn-primary" style="padding: 14px 32px; font-size: 1rem; background: linear-gradient(135deg, var(--accent-purple), var(--primary-700));" onclick="triggerQuestionsGeneration(<?= $pdf['id'] ?>)">
                    ❓ Generate Important Questions Now
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function triggerSummaryGeneration(pdfId) {
    const btn = document.getElementById('generateSummaryBtn');
    const loadingBox = document.getElementById('aiLoadingBox');
    const loadingTitle = document.getElementById('aiLoadingTitle');
    const loadingSub = document.getElementById('aiLoadingSub');
    const errorBox = document.getElementById('aiErrorBox');
    const errorText = document.getElementById('aiErrorText');

    if (btn) btn.disabled = true;
    if (loadingTitle) loadingTitle.textContent = 'Gemini AI is generating your short notes...';
    if (loadingSub) loadingSub.textContent = 'Extracting key concepts, formulas, and revision points from your PDF.';
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
        if (errorText) errorText.textContent = 'Network or server error while requesting Gemini API.';
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
    if (loadingTitle) loadingTitle.textContent = 'Gemini AI is generating 2-Mark, 5-Mark & 10-Mark Exam Questions...';
    if (loadingSub) loadingSub.textContent = 'Analyzing PDF content to construct comprehensive exam answers.';
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
        if (errorText) errorText.textContent = 'Network or server error while requesting Gemini API.';
        if (errorBox) errorBox.style.display = 'flex';
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
