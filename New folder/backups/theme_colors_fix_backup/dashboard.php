<?php
/**
 * Student Dashboard Page
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Enforce authentication
require_login();

$currentUser = get_logged_in_user();
$userId = $_SESSION['user_id'];

$totalPDFs = 0;
$totalNotes = 0;
$totalQuestions = 0;
$totalMCQs = 0;
$recentPDFs = [];

try {
    $pdo = getDBConnection();

    // Total uploaded documents
    $stmtPDF = $pdo->prepare("SELECT COUNT(*) FROM pdf_documents WHERE user_id = ?");
    $stmtPDF->execute([$userId]);
    $totalPDFs = (int) $stmtPDF->fetchColumn();

    // Total generated notes (summary & short_notes)
    $stmtNotes = $pdo->prepare("SELECT COUNT(*) FROM generated_content WHERE user_id = ? AND content_type IN ('summary', 'short_notes')");
    $stmtNotes->execute([$userId]);
    $totalNotes = (int) $stmtNotes->fetchColumn();

    // Total generated questions (questions & important_questions)
    $stmtQ = $pdo->prepare("SELECT COUNT(*) FROM generated_content WHERE user_id = ? AND content_type IN ('questions', 'important_questions')");
    $stmtQ->execute([$userId]);
    $totalQuestions = (int) $stmtQ->fetchColumn();

    // Total MCQs generated
    $stmtMCQ = $pdo->prepare("SELECT COUNT(*) FROM generated_content WHERE user_id = ? AND content_type = 'mcqs'");
    $stmtMCQ->execute([$userId]);
    $totalMCQs = (int) $stmtMCQ->fetchColumn();

    // Fetch up to 4 recently uploaded documents
    $stmtRecent = $pdo->prepare("SELECT * FROM pdf_documents WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 4");
    $stmtRecent->execute([$userId]);
    $recentPDFs = $stmtRecent->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard DB Error: " . $e->getMessage());
}

$pageTitle = 'Dashboard';
$pageSubTitle = 'Turn your study materials into smart notes, questions and insights with AI.';

include __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner-modern">
    <div>
        <h1 class="welcome-heading">Welcome back, <?= htmlspecialchars($currentUser['full_name'] ?? 'Kartik') ?>! 👋</h1>
        <p class="welcome-subtext">Turn your study materials into smart notes, questions and insights with AI.</p>
    </div>
</div>

<!-- 4 Modern Stat Cards Grid (Matching Reference Images) -->
<div class="stats-grid-modern">
    <!-- Stat 1: Total Documents -->
    <div class="stat-card-modern stat-docs">
        <div class="stat-icon-wrapper">
            <span class="stat-icon-emoji">📄</span>
        </div>
        <div class="stat-details">
            <span class="stat-title">Total Documents</span>
            <div class="stat-number"><?= number_format($totalPDFs) ?></div>
            <span class="stat-caption">Uploaded files</span>
        </div>
        <div class="stat-wave-decor"></div>
    </div>

    <!-- Stat 2: Notes Generated -->
    <div class="stat-card-modern stat-notes">
        <div class="stat-icon-wrapper">
            <span class="stat-icon-emoji">🧠</span>
        </div>
        <div class="stat-details">
            <span class="stat-title">Notes Generated</span>
            <div class="stat-number"><?= number_format($totalNotes) ?></div>
            <span class="stat-caption">From your documents</span>
        </div>
        <div class="stat-wave-decor"></div>
    </div>

    <!-- Stat 3: Questions Created -->
    <div class="stat-card-modern stat-questions">
        <div class="stat-icon-wrapper">
            <span class="stat-icon-emoji">💬</span>
        </div>
        <div class="stat-details">
            <span class="stat-title">Questions Created</span>
            <div class="stat-number"><?= number_format($totalQuestions) ?></div>
            <span class="stat-caption">Study &amp; practice</span>
        </div>
        <div class="stat-wave-decor"></div>
    </div>

    <!-- Stat 4: MCQs Generated -->
    <div class="stat-card-modern stat-mcqs">
        <div class="stat-icon-wrapper">
            <span class="stat-icon-emoji">📈</span>
        </div>
        <div class="stat-details">
            <span class="stat-title">MCQs Generated</span>
            <div class="stat-number"><?= number_format($totalMCQs) ?></div>
            <span class="stat-caption">Test your knowledge</span>
        </div>
        <div class="stat-wave-decor"></div>
    </div>
</div>

<!-- Middle Section: Assistant Banner + Quick Actions Grid -->
<div class="dashboard-middle-grid">
    <!-- Left Column: AI Study Assistant Card -->
    <div class="assistant-banner">
        <div class="assistant-content">
            <h2 class="assistant-title">Your AI Study Assistant</h2>
            <p class="assistant-desc">
                Upload your study materials and get instant summaries, notes, questions and more — powered by AI.
            </p>
            <a href="<?= url('upload-pdf.php') ?>" class="assistant-upload-btn">
                <span>☁️</span> Upload Document &rsaquo;
            </a>
        </div>
        <div class="assistant-illustration">
            <div class="doc-badge-stack">
                <div class="doc-badge-item badge-pdf">PDF</div>
                <div class="doc-badge-item badge-word">W</div>
                <div class="doc-badge-item badge-excel">X</div>
                <div class="doc-badge-item badge-ppt">P</div>
            </div>
        </div>
    </div>

    <!-- Right Column: Quick Actions Card -->
    <div class="quick-actions-card">
        <div class="quick-actions-header">
            <h3 class="quick-actions-title">Quick Actions</h3>
        </div>
        <div class="quick-actions-list">
            <a href="<?= url('my-pdfs.php') ?>" class="quick-action-row">
                <div class="quick-action-icon-box icon-purple">
                    <span>📄</span>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">Generate Summary</span>
                    <span class="quick-action-sub">Get key points in seconds</span>
                </div>
                <span class="quick-action-arrow">&rsaquo;</span>
            </a>

            <a href="<?= url('my-pdfs.php') ?>" class="quick-action-row">
                <div class="quick-action-icon-box icon-orange">
                    <span>📝</span>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">Create Short Notes</span>
                    <span class="quick-action-sub">Simple &amp; easy to revise</span>
                </div>
                <span class="quick-action-arrow">&rsaquo;</span>
            </a>

            <a href="<?= url('mcqs-practice.php') ?>" class="quick-action-row">
                <div class="quick-action-icon-box icon-blue">
                    <span>🎯</span>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">Generate MCQs</span>
                    <span class="quick-action-sub">Practice and improve</span>
                </div>
                <span class="quick-action-arrow">&rsaquo;</span>
            </a>

            <a href="<?= url('ask-from-pdf.php') ?>" class="quick-action-row">
                <div class="quick-action-icon-box icon-indigo">
                    <span>💬</span>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">Ask Your Document</span>
                    <span class="quick-action-sub">Chat with your document</span>
                </div>
                <span class="quick-action-arrow">&rsaquo;</span>
            </a>
        </div>
    </div>
</div>

<!-- Bottom Section: Recent Documents -->
<div class="recent-documents-section">
    <div class="recent-header">
        <div>
            <h3 class="recent-title">Recent Documents</h3>
            <p class="recent-subtitle">Your recently uploaded and processed documents</p>
        </div>
        <a href="<?= url('my-pdfs.php') ?>" class="recent-view-all-btn">
            View All &rsaquo;
        </a>
    </div>

    <?php if (empty($recentPDFs)): ?>
        <div class="recent-empty-card">
            <div style="font-size: 3rem; margin-bottom: 12px;">📂</div>
            <p style="font-weight: 700; color: var(--text-main); margin-bottom: 4px;">No documents uploaded yet</p>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 20px;">Upload your first document to unlock AI summaries, questions, and quizzes!</p>
            <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary">Upload First Document</a>
        </div>
    <?php else: ?>
        <div class="recent-docs-grid">
            <?php foreach ($recentPDFs as $doc): 
                $ext = strtolower($doc['file_type'] ?? pathinfo($doc['original_filename'], PATHINFO_EXTENSION));
                $formatClass = 'fmt-' . $ext;
            ?>
                <div class="doc-card-modern">
                    <div class="doc-card-top">
                        <div class="doc-format-icon-circle <?= $formatClass ?>">
                            <?php 
                                if ($ext === 'pdf') echo '📄';
                                elseif ($ext === 'docx') echo '📘';
                                elseif ($ext === 'xlsx') echo '📊';
                                elseif ($ext === 'pptx') echo '📙';
                                elseif ($ext === 'txt') echo '📑';
                                else echo '📁';
                            ?>
                        </div>
                        <div class="doc-options-dots">•••</div>
                    </div>

                    <div class="doc-card-body">
                        <h4 class="doc-card-filename" title="<?= htmlspecialchars($doc['original_filename']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($doc['original_filename'], 0, 24, '...')) ?>
                        </h4>
                        <div class="doc-card-meta">
                            <span class="doc-card-type"><?= strtoupper($ext) ?></span>
                            <span class="doc-card-date">🕒 <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></span>
                            <span class="doc-card-status status-processed">Processed</span>
                        </div>
                    </div>

                    <div class="doc-card-actions">
                        <a href="<?= url('pdf-results.php?id=' . $doc['id'] . '&tab=summary') ?>" class="doc-action-pill pill-summary" title="Summary">
                            <span>📄</span> Summary
                        </a>
                        <a href="<?= url('pdf-results.php?id=' . $doc['id'] . '&tab=summary') ?>" class="doc-action-pill pill-notes" title="Notes">
                            <span>⚡</span> Notes
                        </a>
                        <a href="<?= url('pdf-results.php?id=' . $doc['id'] . '&tab=questions') ?>" class="doc-action-pill pill-questions" title="Questions">
                            <span>❓</span> Questions
                        </a>
                        <a href="<?= url('ask-from-pdf.php?id=' . $doc['id']) ?>" class="doc-action-pill pill-chat" title="Chat">
                            <span>💬</span> Chat
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
