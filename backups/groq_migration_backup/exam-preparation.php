<?php
/**
 * Exam Preparation Page
 * AI-Based PDF Notes Summarizer and Question Generator
 * 
 * Generates and displays:
 * - Section A: 5 questions of 2 marks (Short Answer)
 * - Section B: 3 questions of 5 marks (Medium Answer)
 * - Section C: 2 questions of 10 marks (Long Answer)
 * - Section D: Topic-wise revision notes
 * - Section E: Key definitions and important concepts
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';
require_once __DIR__ . '/includes/gemini.php';

require_login();

$userId = $_SESSION['user_id'];
$pdo = getDBConnection();

$pdfId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'full';

$pdf = null;
$examPrepRecord = null;
$examPrepData = null;
$errorMessage = null;

// If a document ID is provided, fetch and verify ownership
if ($pdfId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        $errorMessage = "Document not found or you do not have permission to access it.";
    } else {
        // Fetch existing exam preparation material
        $prepStmt = $pdo->prepare("
            SELECT * FROM generated_content 
            WHERE pdf_id = ? AND user_id = ? AND content_type = 'exam_prep' 
            ORDER BY id DESC LIMIT 1
        ");
        $prepStmt->execute([$pdfId, $userId]);
        $examPrepRecord = $prepStmt->fetch();

        if ($examPrepRecord && !empty($examPrepRecord['content_data'])) {
            $decoded = json_decode($examPrepRecord['content_data'], true);
            if (is_array($decoded) && isset($decoded['section_a'])) {
                $examPrepData = $decoded;
            } else {
                // Legacy or raw markdown fallback: parse into sections
                $parsed = parse_exam_prep_sections($examPrepRecord['content_data']);
                $examPrepData = [
                    'section_a' => $parsed['secA'],
                    'section_b' => $parsed['secB'],
                    'section_c' => $parsed['secC'],
                    'revision_notes' => $parsed['secD'],
                    'key_definitions' => $parsed['secE'],
                    'full_markdown' => $examPrepRecord['content_data'],
                    'generated_at' => $examPrepRecord['created_at']
                ];
            }
        }
    }
}

// Fetch user's uploaded documents for selection list and switcher
$docsStmt = $pdo->prepare("
    SELECT p.*, 
        (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u1 AND content_type = 'exam_prep') AS has_exam_prep,
        (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u2 AND content_type IN ('summary', 'short_notes')) AS has_notes,
        (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u3 AND content_type = 'mcqs') AS has_mcqs
    FROM pdf_documents p
    WHERE p.user_id = :u4
    ORDER BY p.uploaded_at DESC
");
$docsStmt->execute([
    'u1' => $userId,
    'u2' => $userId,
    'u3' => $userId,
    'u4' => $userId
]);
$userDocuments = $docsStmt->fetchAll();

$pageTitle = $pdf ? 'Exam Preparation: ' . htmlspecialchars($pdf['original_filename']) : 'Exam Preparation';
$pageSubTitle = 'High-yield 2, 5 & 10 Marks Questions, Topic-wise Notes and Key Definitions';

include __DIR__ . '/includes/header.php';
?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px;">
        <div class="alert-icon">⚠</div>
        <div class="alert-body"><?= htmlspecialchars($errorMessage) ?></div>
    </div>
    <div style="margin-bottom: 24px;">
        <a href="<?= url('exam-preparation.php') ?>" class="btn btn-primary">&larr; Back to Document Selection</a>
    </div>
<?php endif; ?>

<?php if (!$pdf): ?>
    <!-- ============================================================== -->
    <!-- VIEW A: DOCUMENT SELECTION MODE                                -->
    <!-- ============================================================== -->
    <div class="page-card">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 class="page-title">📝 Exam Preparation Kit</h2>
                <p class="page-subtitle">Select an uploaded document to generate or view complete exam revision materials</p>
            </div>
            <div>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-sm btn-primary">
                    📤 Upload New Document
                </a>
            </div>
        </div>

        <?php display_flash_message(); ?>

        <!-- Feature Overview Banner -->
        <div class="exam-feature-banner">
            <h3 class="exam-feature-title">
                <span>🎓</span> Comprehensive 5-Part Exam Study Kit
            </h3>
            <p class="exam-feature-desc">
                Select any document to automatically create exam-targeted study material grounded strictly in your content:
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div class="exam-feature-pill">
                    <div style="font-weight: 700; color: #92400e; font-size: 0.88rem;">⚡ Section A</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">5x 2-Mark Short Questions</div>
                </div>
                <div class="exam-feature-pill">
                    <div style="font-weight: 700; color: #92400e; font-size: 0.88rem;">💡 Section B</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">3x 5-Mark Medium Questions</div>
                </div>
                <div class="exam-feature-pill">
                    <div style="font-weight: 700; color: #92400e; font-size: 0.88rem;">🔑 Section C</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">2x 10-Mark Long Questions</div>
                </div>
                <div class="exam-feature-pill">
                    <div style="font-weight: 700; color: #92400e; font-size: 0.88rem;">📑 Section D</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">Topic-wise Revision Notes</div>
                </div>
                <div class="exam-feature-pill">
                    <div style="font-weight: 700; color: #92400e; font-size: 0.88rem;">📖 Section E</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">Key Definitions &amp; Concepts</div>
                </div>
            </div>
        </div>

        <?php if (empty($userDocuments)): ?>
            <div style="text-align: center; padding: 50px 20px; color: var(--text-muted); background: var(--slate-50); border-radius: var(--radius-lg); border: 2px dashed var(--slate-300);">
                <div style="font-size: 3.5rem; margin-bottom: 12px; color: var(--primary-500);">📚</div>
                <h3 style="color: var(--slate-800); margin-bottom: 6px;">No Documents Uploaded Yet</h3>
                <p style="font-size: 0.9rem; max-width: 480px; margin: 0 auto 20px;">
                    Upload a lecture PDF, Word doc, presentation, spreadsheet, or text notes to generate an Exam Preparation Kit.
                </p>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary">
                    📤 Upload First Document
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Format</th>
                            <th>Document Name</th>
                            <th>Subject</th>
                            <th>Exam Prep Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userDocuments as $doc): ?>
                            <?php 
                                $ext = strtolower($doc['file_type'] ?? pathinfo($doc['original_filename'], PATHINFO_EXTENSION));
                                $icon = get_format_icon($ext);
                            ?>
                            <tr>
                                <td style="width: 80px;">
                                    <span class="badge" style="background: var(--slate-100); border: 1px solid var(--slate-200); font-weight: 700; font-size: 0.78rem;">
                                        <?= $icon ?> <?= strtoupper($ext) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($doc['original_filename']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        Uploaded: <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?> &bull; <?= round($doc['file_size'] / (1024 * 1024), 2) ?> MB
                                    </div>
                                </td>
                                <td>
                                    <span class="tag-item"><?= htmlspecialchars($doc['subject'] ?: 'General') ?></span>
                                </td>
                                <td>
                                    <?php if ($doc['has_exam_prep'] > 0): ?>
                                        <span class="status-badge status-success">✓ Exam Kit Ready</span>
                                    <?php else: ?>
                                        <span class="status-badge status-warning">⚡ Not Generated</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?= url('exam-preparation.php?id=' . $doc['id']) ?>" class="btn btn-sm <?= $doc['has_exam_prep'] > 0 ? 'btn-primary' : 'btn-outline' ?>" style="<?= $doc['has_exam_prep'] <= 0 ? 'border-color: #f59e0b; color: #b45309;' : '' ?>">
                                        <?= $doc['has_exam_prep'] > 0 ? '📖 Open Exam Kit' : '⚡ Generate Kit' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ============================================================== -->
    <!-- VIEW B: EXAM PREPARATION MATERIAL VIEWER & GENERATOR           -->
    <!-- ============================================================== -->
    <?php 
        $ext = strtolower($pdf['file_type'] ?? pathinfo($pdf['original_filename'], PATHINFO_EXTENSION));
        $icon = get_format_icon($ext);
    ?>

    <div class="page-card" style="max-width: 1000px; margin: 0 auto;">
        <!-- Document Header Bar -->
        <div class="section-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 24px;">
            <div>
                <h2 class="page-title">📝 Exam Preparation Kit</h2>
                <p class="page-subtitle" style="margin-top: 4px;">
                    Document: <strong style="color: var(--text-title);"><?= $icon ?> <?= htmlspecialchars($pdf['original_filename']) ?></strong> &bull; 
                    Subject: <span class="tag-item"><?= htmlspecialchars($pdf['subject'] ?: 'General') ?></span> &bull; 
                    Format: <strong><?= strtoupper($ext) ?></strong>
                </p>
            </div>
            
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <!-- Document Switcher Dropdown -->
                <?php if (count($userDocuments) > 1): ?>
                    <select id="docSwitcher" class="form-control" style="width: auto; padding: 6px 12px; font-size: 0.85rem;" onchange="if(this.value) window.location.href='<?= url('exam-preparation.php?id=') ?>' + this.value;">
                        <?php foreach ($userDocuments as $doc): ?>
                            <option value="<?= $doc['id'] ?>" <?= $doc['id'] == $pdf['id'] ? 'selected' : '' ?>>
                                📁 <?= htmlspecialchars(mb_strimwidth($doc['original_filename'], 0, 28, '...')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if ($examPrepData): ?>
                    <button type="button" class="btn btn-sm btn-outline" onclick="window.print();" title="Print or Save Study Kit as PDF">
                        🖨️ Print Kit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="copyFullExamKit();" id="copyKitBtn" title="Copy Complete Kit Markdown to Clipboard">
                        📋 Copy Kit
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="confirmRegenerate(<?= $pdf['id'] ?>)" title="Regenerate Kit with Gemini AI">
                        🔄 Regenerate
                    </button>
                <?php endif; ?>

                <a href="<?= url('pdf-results.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline">
                    ⚡ Short Notes
                </a>
                <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline">
                    🎯 Practice MCQs
                </a>
                <a href="<?= url('ask-from-pdf.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline">
                    🤖 Ask
                </a>
                <a href="<?= url('exam-preparation.php') ?>" class="btn btn-sm btn-outline">
                    📁 All Documents
                </a>
            </div>
        </div>

        <?php display_flash_message(); ?>

        <!-- Loading & Progress Box (Hidden by default) -->
        <div id="aiLoadingBox" class="alert alert-info" style="display: none; padding: 30px; border-radius: var(--radius-lg); text-align: center; margin-bottom: 24px;">
            <div style="font-size: 3rem; margin-bottom: 12px; animation: pulse 1.5s infinite;">🤖</div>
            <h3 id="aiLoadingTitle" style="font-size: 1.25rem; font-weight: 700; margin-bottom: 8px;">
                Gemini AI is generating your Exam Preparation Kit...
            </h3>
            <p id="aiLoadingSub" style="font-size: 0.92rem; max-width: 600px; margin: 0 auto;">
                Analyzing document content and constructing 2-Mark, 5-Mark, and 10-Mark questions with high-yield revision notes and key definitions. This takes 5–15 seconds.
            </p>
        </div>

        <!-- Error Alert Box (Hidden by default) -->
        <div id="aiErrorBox" class="alert alert-danger" style="display: none; margin-bottom: 24px;">
            <div class="alert-icon">⚠</div>
            <div id="aiErrorText" class="alert-body" style="flex: 1;">An error occurred while generating exam preparation material.</div>
            <button type="button" class="btn btn-sm btn-outline" style="color: var(--accent-rose); border-color: #fca5a5; margin-left: 12px;" onclick="document.getElementById('aiErrorBox').style.display='none';">Dismiss</button>
        </div>

        <?php if (!$examPrepData): ?>
            <!-- ============================================================== -->
            <!-- STATE 1: NOT GENERATED YET                                     -->
            <!-- ============================================================== -->
            <div style="text-align: center; padding: 50px 24px; background: var(--bg-card-subtle); border: 2px dashed var(--border-color); border-radius: var(--radius-xl); margin-bottom: 24px;">
                <div style="font-size: 3.8rem; margin-bottom: 14px;">📝</div>
                <h3 style="font-size: 1.4rem; font-weight: 700; color: var(--text-title); margin-bottom: 10px;">
                    No Exam Preparation Kit Generated Yet
                </h3>
                <p style="font-size: 0.95rem; color: var(--text-muted); max-width: 620px; margin: 0 auto 28px; line-height: 1.6;">
                    Click below to generate a comprehensive 5-part study kit for <strong><?= htmlspecialchars($pdf['original_filename']) ?></strong>.
                    Gemini AI will extract short answers, medium conceptual questions, essay prompts, topic summaries, and essential definitions.
                </p>

                <!-- Section Highlights Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; max-width: 800px; margin: 0 auto 32px; text-align: left;">
                    <div style="background: var(--bg-card); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid var(--primary-600); border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <strong style="color: var(--primary-600); font-size: 0.9rem;">⚡ Section A: 2 Marks</strong>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">5 concise, short-answer questions targeting foundational recall.</p>
                    </div>
                    <div style="background: var(--bg-card); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid #10b981; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <strong style="color: #10b981; font-size: 0.9rem;">💡 Section B: 5 Marks</strong>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">3 medium analytical/application questions exploring key mechanisms.</p>
                    </div>
                    <div style="background: var(--bg-card); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid #f59e0b; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <strong style="color: #f59e0b; font-size: 0.9rem;">🔑 Section C: 10 Marks</strong>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">2 comprehensive essay/synthesis questions testing deep mastery.</p>
                    </div>
                    <div style="background: var(--bg-card); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid #8b5cf6; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <strong style="color: #8b5cf6; font-size: 0.9rem;">📑 Section D: Revision Notes</strong>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">High-yield bulleted notes structured by document topics.</p>
                    </div>
                    <div style="background: var(--bg-card); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid #ec4899; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <strong style="color: #ec4899; font-size: 0.9rem;">📖 Section E: Key Definitions</strong>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0;">5–10 core terms and definitions for quick exam memorization.</p>
                    </div>
                </div>

                <button type="button" id="generateKitBtn" class="btn btn-primary" style="padding: 14px 36px; font-size: 1.05rem; background: linear-gradient(135deg, #d97706, #b45309); border: none; box-shadow: var(--shadow-md);" onclick="triggerExamPrepGeneration(<?= $pdf['id'] ?>)">
                    ⚡ Generate Exam Preparation Kit Now
                </button>
            </div>

        <?php else: ?>
            <!-- ============================================================== -->
            <!-- STATE 2: EXAM PREPARATION MATERIAL IS READY                    -->
            <!-- ============================================================== -->
            
            <!-- Navigation Tabs -->
            <div class="exam-tab-bar" style="display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 2px solid var(--border-color); margin-bottom: 24px;">
                <button type="button" class="exam-tab-item <?= $activeTab === 'full' ? 'active' : '' ?>" onclick="switchExamTab('full')">
                    📋 Full Study Kit
                </button>
                <button type="button" class="exam-tab-item <?= $activeTab === 'sec_a' ? 'active' : '' ?>" onclick="switchExamTab('sec_a')">
                    ⚡ Section A (2M &bull; 5 Qs)
                </button>
                <button type="button" class="exam-tab-item <?= $activeTab === 'sec_b' ? 'active' : '' ?>" onclick="switchExamTab('sec_b')">
                    💡 Section B (5M &bull; 3 Qs)
                </button>
                <button type="button" class="exam-tab-item <?= $activeTab === 'sec_c' ? 'active' : '' ?>" onclick="switchExamTab('sec_c')">
                    🔑 Section C (10M &bull; 2 Qs)
                </button>
                <button type="button" class="exam-tab-item <?= $activeTab === 'notes' ? 'active' : '' ?>" onclick="switchExamTab('notes')">
                    📑 Revision Notes
                </button>
                <button type="button" class="exam-tab-item <?= $activeTab === 'defs' ? 'active' : '' ?>" onclick="switchExamTab('defs')">
                    📖 Key Definitions
                </button>
            </div>

            <!-- TAB PANE 1: FULL STUDY KIT -->
            <div id="tabPane-full" class="exam-tab-pane <?= $activeTab === 'full' ? 'active' : '' ?>">
                <!-- Section A Card -->
                <div class="exam-section-card" style="margin-bottom: 28px; border-left: 4px solid var(--primary-600);">
                    <div class="exam-section-header">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <h3 class="exam-section-title">
                                ⚡ Section A: 2-Mark Short Answer Questions
                            </h3>
                            <span class="exam-marks-badge badge-marks-2">
                                5 Questions &bull; 2 Marks Each (10 Marks Total)
                            </span>
                        </div>
                    </div>
                    <div class="exam-questions-list">
                        <?php foreach ($examPrepData['section_a'] as $i => $q): ?>
                            <div class="exam-question-item">
                                <span class="exam-q-num" style="background: var(--primary-600);">Q<?= $i + 1 ?></span>
                                <div class="exam-q-text"><?= htmlspecialchars($q) ?></div>
                                <span class="exam-q-marks">[2 Marks]</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section B Card -->
                <div class="exam-section-card" style="margin-bottom: 28px; border-left: 4px solid #10b981;">
                    <div class="exam-section-header">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <h3 class="exam-section-title">
                                💡 Section B: 5-Mark Medium Answer Questions
                            </h3>
                            <span class="exam-marks-badge badge-marks-5">
                                3 Questions &bull; 5 Marks Each (15 Marks Total)
                            </span>
                        </div>
                    </div>
                    <div class="exam-questions-list">
                        <?php foreach ($examPrepData['section_b'] as $i => $q): ?>
                            <div class="exam-question-item">
                                <span class="exam-q-num" style="background: #10b981;">Q<?= $i + 1 ?></span>
                                <div class="exam-q-text"><?= htmlspecialchars($q) ?></div>
                                <span class="exam-q-marks">[5 Marks]</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section C Card -->
                <div class="exam-section-card" style="margin-bottom: 28px; border-left: 4px solid #f59e0b;">
                    <div class="exam-section-header">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <h3 class="exam-section-title">
                                🔑 Section C: 10-Mark Long Answer Questions
                            </h3>
                            <span class="exam-marks-badge badge-marks-10">
                                2 Questions &bull; 10 Marks Each (20 Marks Total)
                            </span>
                        </div>
                    </div>
                    <div class="exam-questions-list">
                        <?php foreach ($examPrepData['section_c'] as $i => $q): ?>
                            <div class="exam-question-item">
                                <span class="exam-q-num" style="background: #f59e0b;">Q<?= $i + 1 ?></span>
                                <div class="exam-q-text"><?= htmlspecialchars($q) ?></div>
                                <span class="exam-q-marks">[10 Marks]</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section D Card -->
                <div class="exam-section-card" style="margin-bottom: 28px; border-left: 4px solid #8b5cf6;">
                    <div class="exam-section-header">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <h3 class="exam-section-title">
                                📑 Section D: Topic-wise Revision Notes
                            </h3>
                            <span class="exam-marks-badge badge-marks-notes">
                                High-Yield Exam Review
                            </span>
                        </div>
                    </div>
                    <div class="markdown-body" style="padding-top: 10px;">
                        <?= render_markdown($examPrepData['revision_notes']) ?>
                    </div>
                </div>

                <!-- Section E Card -->
                <div class="exam-section-card" style="margin-bottom: 28px; border-left: 4px solid #ec4899;">
                    <div class="exam-section-header">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <h3 class="exam-section-title">
                                📖 Section E: Key Definitions and Important Concepts
                            </h3>
                            <span class="exam-marks-badge badge-marks-defs">
                                Essential Core Terms
                            </span>
                        </div>
                    </div>
                    <div class="markdown-body" style="padding-top: 10px;">
                        <?= render_markdown($examPrepData['key_definitions']) ?>
                    </div>
                </div>
            </div>

            <!-- TAB PANE 2: SECTION A -->
            <div id="tabPane-sec_a" class="exam-tab-pane <?= $activeTab === 'sec_a' ? 'active' : '' ?>">
                <div class="exam-section-card" style="border-left: 4px solid var(--primary-600);">
                    <div class="exam-section-header">
                        <h3 class="exam-section-title">⚡ Section A: 2-Mark Short Answer Questions</h3>
                        <span class="exam-marks-badge badge-marks-2">5 Questions</span>
                    </div>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
                        These questions test fundamental definitions, key characteristics, and immediate recall points from the document.
                    </p>
                    <div class="exam-questions-list">
                        <?php foreach ($examPrepData['section_a'] as $i => $q): ?>
                            <div class="exam-question-item">
                                <span class="exam-q-num" style="background: var(--primary-600);">Q<?= $i + 1 ?></span>
                                <div class="exam-q-text"><?= htmlspecialchars($q) ?></div>
                                <span class="exam-q-marks">[2 Marks]</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB PANE 3: SECTION B -->
            <div id="tabPane-sec_b" class="exam-tab-pane <?= $activeTab === 'sec_b' ? 'active' : '' ?>">
                <div class="exam-section-card" style="border-left: 4px solid #10b981;">
                    <div class="exam-section-header">
                        <h3 class="exam-section-title">💡 Section B: 5-Mark Medium Answer Questions</h3>
                        <span class="exam-marks-badge badge-marks-5">3 Questions</span>
                    </div>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
                        These questions require structured explanations, mechanism breakdowns, and comparative analysis based on the document.
                    </p>
                    <div class="exam-questions-list">
                        <?php foreach ($examPrepData['section_b'] as $i => $q): ?>
                            <div class="exam-question-item">
                                <span class="exam-q-num" style="background: #10b981;">Q<?= $i + 1 ?></span>
                                <div class="exam-q-text"><?= htmlspecialchars($q) ?></div>
                                <span class="exam-q-marks">[5 Marks]</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB PANE 4: SECTION C -->
            <div id="tabPane-sec_c" class="exam-tab-pane <?= $activeTab === 'sec_c' ? 'active' : '' ?>">
                <div class="exam-section-card" style="border-left: 4px solid #f59e0b;">
                    <div class="exam-section-header">
                        <h3 class="exam-section-title">🔑 Section C: 10-Mark Long Answer Questions</h3>
                        <span class="exam-marks-badge badge-marks-10">2 Questions</span>
                    </div>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
                        These questions evaluate comprehensive understanding, architectural design, end-to-end workflows, and deep synthesis.
                    </p>
                    <div class="exam-questions-list">
                        <?php foreach ($examPrepData['section_c'] as $i => $q): ?>
                            <div class="exam-question-item">
                                <span class="exam-q-num" style="background: #f59e0b;">Q<?= $i + 1 ?></span>
                                <div class="exam-q-text"><?= htmlspecialchars($q) ?></div>
                                <span class="exam-q-marks">[10 Marks]</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB PANE 5: SECTION D (REVISION NOTES) -->
            <div id="tabPane-notes" class="exam-tab-pane <?= $activeTab === 'notes' ? 'active' : '' ?>">
                <div class="exam-section-card" style="border-left: 4px solid #8b5cf6;">
                    <div class="exam-section-header">
                        <h3 class="exam-section-title">📑 Section D: Topic-wise Revision Notes</h3>
                        <span class="exam-marks-badge badge-marks-notes">High-Yield Summary</span>
                    </div>
                    <div class="markdown-body" style="padding-top: 10px;">
                        <?= render_markdown($examPrepData['revision_notes']) ?>
                    </div>
                </div>
            </div>

            <!-- TAB PANE 6: SECTION E (KEY DEFINITIONS) -->
            <div id="tabPane-defs" class="exam-tab-pane <?= $activeTab === 'defs' ? 'active' : '' ?>">
                <div class="exam-section-card" style="border-left: 4px solid #ec4899;">
                    <div class="exam-section-header">
                        <h3 class="exam-section-title">📖 Section E: Key Definitions and Important Concepts</h3>
                        <span class="exam-marks-badge badge-marks-defs">Core Terminology</span>
                    </div>
                    <div class="markdown-body" style="padding-top: 10px;">
                        <?= render_markdown($examPrepData['key_definitions']) ?>
                    </div>
                </div>
            </div>

            <!-- Hidden Raw Markdown for Copying -->
            <textarea id="rawExamMarkdown" style="display: none;"><?= htmlspecialchars($examPrepData['full_markdown']) ?></textarea>

        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function switchExamTab(tabName) {
    document.querySelectorAll('.exam-tab-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.exam-tab-pane').forEach(el => el.classList.remove('active'));

    const tabBtn = Array.from(document.querySelectorAll('.exam-tab-item')).find(btn => btn.getAttribute('onclick').includes(tabName));
    const targetPane = document.getElementById('tabPane-' + tabName);

    if (tabBtn) tabBtn.classList.add('active');
    if (targetPane) targetPane.classList.add('active');

    // Update query string without full reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
}

function triggerExamPrepGeneration(pdfId) {
    const btn = document.getElementById('generateKitBtn');
    const loadingBox = document.getElementById('aiLoadingBox');
    const loadingTitle = document.getElementById('aiLoadingTitle');
    const loadingSub = document.getElementById('aiLoadingSub');
    const errorBox = document.getElementById('aiErrorBox');
    const errorText = document.getElementById('aiErrorText');

    if (btn) btn.disabled = true;
    if (loadingTitle) loadingTitle.textContent = 'Gemini AI is generating your 5-part Exam Kit...';
    if (loadingSub) loadingSub.textContent = 'Generating 2-Mark, 5-Mark, 10-Mark Questions, Revision Notes, and Key Definitions. This takes 5–15 seconds.';
    if (loadingBox) loadingBox.style.display = 'block';
    if (errorBox) errorBox.style.display = 'none';

    window.scrollTo({ top: 120, behavior: 'smooth' });

    fetch('<?= url("api/generate-exam-prep.php") ?>', {
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
            if (errorText) errorText.textContent = res.body.error || 'Failed to generate Exam Preparation Kit.';
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

function confirmRegenerate(pdfId) {
    if (confirm('Regenerate this Exam Preparation Kit? This will query Gemini AI to create fresh questions, revision notes, and definitions.')) {
        triggerExamPrepGeneration(pdfId);
    }
}

function copyFullExamKit() {
    const textarea = document.getElementById('rawExamMarkdown');
    const copyBtn = document.getElementById('copyKitBtn');
    if (!textarea) return;

    navigator.clipboard.writeText(textarea.value).then(() => {
        if (copyBtn) {
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = '✓ Copied!';
            copyBtn.style.color = '#059669';
            setTimeout(() => {
                copyBtn.innerHTML = originalText;
                copyBtn.style.color = '';
            }, 2500);
        }
    }).catch(err => {
        console.error('Failed to copy kit:', err);
        alert('Failed to copy to clipboard. Please copy manually.');
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
