<?php
/**
 * MCQ Generator & Interactive Practice Mode
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$userId = $_SESSION['user_id'];
$pdo = getDBConnection();

$pdfId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pdf = null;
$mcqs = null;
$mcqRecord = null;
$errorMessage = null;

if ($pdfId > 0) {
    // 1. Fetch document and check ownership
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        $errorMessage = "Document not found or you do not have permission to access it.";
    } else {
        // 2. Fetch existing MCQs if generated
        $mcqStmt = $pdo->prepare("
            SELECT * FROM generated_content 
            WHERE pdf_id = ? AND user_id = ? AND content_type = 'mcqs' 
            ORDER BY id DESC LIMIT 1
        ");
        $mcqStmt->execute([$pdfId, $userId]);
        $mcqRecord = $mcqStmt->fetch();

        if ($mcqRecord && !empty($mcqRecord['content_data'])) {
            $decoded = json_decode($mcqRecord['content_data'], true);
            if (is_array($decoded) && count($decoded) >= 1) {
                $mcqs = $decoded;
            }
        }
    }
}

// If no document selected or invalid, fetch list of user's documents for selection
$userDocuments = [];
if (!$pdf) {
    $docsStmt = $pdo->prepare("
        SELECT p.*, 
            (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u1 AND content_type = 'mcqs') AS has_mcqs,
            (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u2 AND content_type IN ('summary', 'short_notes')) AS has_notes
        FROM pdf_documents p
        WHERE p.user_id = :u3
        ORDER BY p.uploaded_at DESC
    ");
    $docsStmt->execute([
        'u1' => $userId,
        'u2' => $userId,
        'u3' => $userId
    ]);
    $userDocuments = $docsStmt->fetchAll();
}

$pageTitle = $pdf ? 'Practice MCQs: ' . htmlspecialchars($pdf['original_filename']) : 'MCQ Practice Mode';
$pageSubTitle = 'Test your comprehension with 10 interactive AI-generated questions';

include __DIR__ . '/includes/header.php';
?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px;">
        <div class="alert-icon">⚠</div>
        <div class="alert-body"><?= htmlspecialchars($errorMessage) ?></div>
    </div>
    <div style="margin-bottom: 24px;">
        <a href="<?= url('mcqs-practice.php') ?>" class="btn btn-primary">&larr; Back to Document Selection</a>
    </div>
<?php endif; ?>

<?php if (!$pdf): ?>
    <!-- ========================================== -->
    <!-- VIEW A: DOCUMENT SELECTION MODE -->
    <!-- ========================================== -->
    <div class="page-card">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 class="page-title">🎯 MCQ Practice Mode</h2>
                <p class="page-subtitle">Select an uploaded document to practice or generate 10 interactive MCQs</p>
            </div>
            <div>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-sm btn-primary">
                    📤 Upload New Document
                </a>
            </div>
        </div>

        <?php if (empty($userDocuments)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-card-subtle); border-radius: var(--radius-lg); border: 2px dashed var(--border-color); margin-top: 16px;">
                <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--primary-600);">📚</div>
                <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-title); margin-bottom: 8px;">No Documents Available Yet</h3>
                <p style="font-size: 0.92rem; color: var(--text-muted); max-width: 480px; margin: 0 auto 20px;">
                    Upload a PDF, Word, PowerPoint, or text document first to automatically extract content and practice MCQs.
                </p>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-primary">
                    📤 Upload Document Now
                </a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; margin-top: 16px;">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Subject</th>
                            <th>MCQ Status</th>
                            <th>Uploaded</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userDocuments as $doc): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 1.3rem;">📄</span>
                                        <div>
                                            <a href="<?= url('mcqs-practice.php?id=' . $doc['id']) ?>" style="font-weight: 600; color: var(--text-title);">
                                                <?= htmlspecialchars($doc['original_filename']) ?>
                                            </a>
                                            <div style="font-size: 0.78rem; color: var(--text-muted);">
                                                <?= round($doc['file_size'] / (1024 * 1024), 2) ?> MB &bull; <?= htmlspecialchars($doc['file_type']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag-item"><?= htmlspecialchars($doc['subject'] ?: 'General') ?></span>
                                </td>
                                <td>
                                    <?php if ($doc['has_mcqs'] > 0): ?>
                                        <span class="status-badge status-success">🎯 10 MCQs Ready</span>
                                    <?php else: ?>
                                        <span class="status-badge status-warning">⚡ Not Generated Yet</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-muted); font-size: 0.85rem;">
                                    <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($doc['has_mcqs'] > 0): ?>
                                        <a href="<?= url('mcqs-practice.php?id=' . $doc['id']) ?>" class="btn btn-sm btn-primary">
                                            🎯 Practice Quiz
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('mcqs-practice.php?id=' . $doc['id']) ?>" class="btn btn-sm btn-outline">
                                            ⚡ Generate MCQs
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ========================================== -->
    <!-- VIEW B: DOCUMENT SELECTED MODE -->
    <!-- ========================================== -->
    <div class="mcq-container">
        
        <!-- Document Meta Navigation Header -->
        <div class="mcq-header-card">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                        Interactive MCQ Quiz
                    </div>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-title); margin-top: 2px;">
                        <?= htmlspecialchars($pdf['original_filename']) ?>
                    </h2>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px; font-size: 0.85rem; color: var(--text-muted);">
                        <span class="tag-item"><?= htmlspecialchars($pdf['subject'] ?: 'General') ?></span>
                        <span>&bull;</span>
                        <span>10 Questions</span>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="<?= url('exam-preparation.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" style="border-color: #f59e0b; color: #b45309;" title="View Exam Preparation Kit">
                        📝 Exam Prep
                    </a>
                    <a href="<?= url('pdf-results.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" title="View AI Short Notes">
                        ⚡ Notes
                    </a>
                    <a href="<?= url('pdf-results.php?id=' . $pdf['id'] . '&tab=questions') ?>" class="btn btn-sm btn-outline" title="View Important Questions">
                        ❓ Questions
                    </a>
                    <a href="<?= url('mcqs-practice.php') ?>" class="btn btn-sm btn-secondary" title="Choose another document">
                        📁 Switch File
                    </a>
                </div>
            </div>

            <?php if ($mcqs): ?>
                <!-- Live Progress Bar -->
                <div style="width: 100%; margin-top: 14px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.82rem; font-weight: 700; color: var(--text-muted);">
                        <span id="progressText">Question 1 of 10</span>
                        <span id="scoreText">Score: 0 / 0</span>
                    </div>
                    <div class="mcq-progress-bar-bg">
                        <div id="progressBarFill" class="mcq-progress-bar-fill" style="width: 10%;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- AI Loading Indicator Box -->
        <div id="mcqLoadingBox" class="alert alert-info" style="display: none; padding: 28px; border-radius: var(--radius-lg); text-align: center; margin-bottom: 24px;">
            <div style="font-size: 2.8rem; margin-bottom: 10px; animation: pulse 1.5s infinite;">🤖</div>
            <h3 id="mcqLoadingTitle" style="font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">
                Groq AI is generating your 10 MCQs...
            </h3>
            <p id="mcqLoadingSub" style="font-size: 0.92rem;">
                Analyzing document concepts, formulating rigorous questions, and verifying options. This takes about 5–10 seconds.
            </p>
        </div>

        <!-- Error Alert Box -->
        <div id="mcqErrorBox" class="alert alert-danger" style="display: none; margin-bottom: 24px;">
            <div class="alert-icon">⚠</div>
            <div id="mcqErrorText" class="alert-body">An error occurred while generating MCQs.</div>
        </div>

        <?php if (!$mcqs): ?>
            <!-- ========================================== -->
            <!-- EMPTY STATE: GENERATE MCQS -->
            <!-- ========================================== -->
            <div id="mcqEmptyState" class="page-card" style="text-align: center; padding: 60px 24px;">
                <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--primary-600);">🎯</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; color: var(--text-title); margin-bottom: 8px;">
                    No MCQs Generated for this Document Yet
                </h3>
                <p style="font-size: 0.95rem; color: var(--text-muted); max-width: 520px; margin: 0 auto 24px; line-height: 1.6;">
                    Click below to generate 10 rigorous, document-grounded Multiple Choice Questions with 4 options, instant answers, and detailed explanations.
                </p>

                <button type="button" id="startGenerateBtn" class="btn btn-primary" style="padding: 14px 34px; font-size: 1.05rem;" onclick="triggerMcqGeneration(<?= $pdf['id'] ?>)">
                    🎯 Generate 10 Practice MCQs Now
                </button>
            </div>
        <?php else: ?>
            <!-- ========================================== -->
            <!-- INTERACTIVE QUIZ RUNNER -->
            <!-- ========================================== -->
            <div id="quizContainer">
                <div class="mcq-question-card">
                    <div class="mcq-question-meta">
                        <span id="questionBadge" class="mcq-pill">Question 1 of 10</span>
                        <span id="questionTopicBadge" style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">
                            Single Choice (A, B, C, D)
                        </span>
                    </div>

                    <h3 id="questionTitle" class="mcq-question-text">
                        <!-- Populated by JS -->
                    </h3>

                    <div id="optionsList" class="mcq-options-list">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Feedback Box (Shown after submission) -->
                    <div id="feedbackBox" class="mcq-feedback-box" style="display: none;">
                        <div id="feedbackTitle" style="font-weight: 700; font-size: 1rem; margin-bottom: 6px;"></div>
                        <div id="feedbackText" style="font-size: 0.92rem;"></div>
                    </div>

                    <!-- Question Navigation Bar -->
                    <div class="mcq-nav-row" style="margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                        <div>
                            <button type="button" id="prevBtn" class="btn btn-outline" onclick="prevQuestion()" disabled>
                                &larr; Previous
                            </button>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="button" id="submitBtn" class="btn btn-primary" onclick="submitCurrentAnswer()" disabled>
                                Submit Answer &check;
                            </button>
                            <button type="button" id="nextBtn" class="btn btn-primary" onclick="nextQuestion()" style="display: none;">
                                Next Question &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- QUIZ COMPLETION SCORECARD (Hidden by default) -->
            <!-- ========================================== -->
            <div id="scorecardContainer" style="display: none;">
                <div class="mcq-score-card">
                    <div id="scoreDial" class="mcq-score-dial">
                        <div id="scoreVal" class="mcq-score-num">0</div>
                        <div class="mcq-score-total">out of 10</div>
                    </div>

                    <h2 id="scoreTitle" style="font-size: 1.6rem; font-weight: 800; color: var(--text-title); margin-bottom: 6px;">
                        Quiz Completed!
                    </h2>
                    <p id="scoreSubtitle" style="font-size: 1rem; color: var(--text-muted); max-width: 500px; margin: 0 auto 24px;">
                        <!-- Feedback text -->
                    </p>

                    <!-- Stat pills -->
                    <div style="display: flex; justify-content: center; gap: 16px; margin-bottom: 32px; flex-wrap: wrap;">
                        <div class="mcq-stat-pill-correct">
                            <div class="mcq-stat-val" id="statCorrect">0</div>
                            <div class="mcq-stat-lbl">Correct</div>
                        </div>
                        <div class="mcq-stat-pill-incorrect">
                            <div class="mcq-stat-val" id="statIncorrect">0</div>
                            <div class="mcq-stat-lbl">Incorrect</div>
                        </div>
                        <div class="mcq-stat-pill-accuracy">
                            <div class="mcq-stat-val" id="statAccuracy">0%</div>
                            <div class="mcq-stat-lbl">Accuracy</div>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-primary" onclick="restartQuiz()">
                            🔄 Retake Quiz
                        </button>
                        <button type="button" id="toggleReviewBtn" class="btn btn-outline" onclick="toggleReviewSection()">
                            📋 Review All Answers
                        </button>
                        <button type="button" class="btn btn-outline" onclick="triggerMcqGeneration(<?= $pdf['id'] ?>)">
                            ⚡ Regenerate New MCQs
                        </button>
                        <a href="<?= url('mcqs-practice.php') ?>" class="btn btn-secondary">
                            📁 Choose Another Document
                        </a>
                    </div>
                </div>

                <!-- Review Section (Expanded on click) -->
                <div id="reviewSection" style="display: none; margin-top: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-title);">
                            Detailed Question Review
                        </h3>
                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                            Review all 10 questions and explanations
                        </span>
                    </div>

                    <div id="reviewCardsList">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <!-- Quiz Engine Script -->
            <script>
            const mcqsData = <?= json_encode($mcqs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            
            let currentIndex = 0;
            const totalQuestions = mcqsData.length;
            
            // Array storing user's answer state for each question
            const quizState = mcqsData.map(() => ({
                selectedOption: null,
                isSubmitted: false,
                isCorrect: false
            }));

            function initQuiz() {
                currentIndex = 0;
                document.getElementById('quizContainer').style.display = 'block';
                document.getElementById('scorecardContainer').style.display = 'none';
                renderQuestion(currentIndex);
            }

            function renderQuestion(idx) {
                const q = mcqsData[idx];
                const state = quizState[idx];

                // Update Header Progress
                document.getElementById('progressText').textContent = `Question ${idx + 1} of ${totalQuestions}`;
                const fillPct = Math.round(((idx + 1) / totalQuestions) * 100);
                document.getElementById('progressBarFill').style.width = fillPct + '%';

                // Update Score pill
                const answeredCount = quizState.filter(s => s.isSubmitted).length;
                const correctCount = quizState.filter(s => s.isSubmitted && s.isCorrect).length;
                document.getElementById('scoreText').textContent = `Score: ${correctCount} / ${answeredCount}`;

                // Update Question Meta & Text
                document.getElementById('questionBadge').textContent = `Question ${idx + 1} of ${totalQuestions}`;
                document.getElementById('questionTitle').textContent = q.question;

                // Render Options
                const optionsContainer = document.getElementById('optionsList');
                optionsContainer.innerHTML = '';

                const letters = ['A', 'B', 'C', 'D'];
                letters.forEach(letter => {
                    const optionText = q.options[letter] || '';
                    const optDiv = document.createElement('div');
                    optDiv.className = 'mcq-option-item';
                    optDiv.dataset.letter = letter;

                    // Apply visual states
                    if (state.isSubmitted) {
                        optDiv.classList.add('locked');
                        if (letter === q.correct_answer) {
                            optDiv.classList.add('correct');
                        } else if (state.selectedOption === letter) {
                            optDiv.classList.add('incorrect');
                        } else {
                            optDiv.classList.add('dimmed');
                        }
                    } else {
                        if (state.selectedOption === letter) {
                            optDiv.classList.add('selected');
                        }
                        optDiv.onclick = () => selectOption(letter);
                    }

                    optDiv.innerHTML = `
                        <div class="mcq-option-circle">${letter}</div>
                        <div class="mcq-option-label">${escapeHtml(optionText)}</div>
                    `;
                    optionsContainer.appendChild(optDiv);
                });

                // Feedback Box
                const feedbackBox = document.getElementById('feedbackBox');
                const feedbackTitle = document.getElementById('feedbackTitle');
                const feedbackText = document.getElementById('feedbackText');

                if (state.isSubmitted) {
                    feedbackBox.style.display = 'block';
                    if (state.isCorrect) {
                        feedbackBox.className = 'mcq-feedback-box correct';
                        feedbackTitle.innerHTML = '✓ Correct Answer!';
                    } else {
                        feedbackBox.className = 'mcq-feedback-box incorrect';
                        feedbackTitle.innerHTML = `✗ Incorrect. The correct answer is Option ${q.correct_answer}.`;
                    }
                    feedbackText.innerHTML = `<strong>Explanation:</strong> ${escapeHtml(q.explanation)}`;
                } else {
                    feedbackBox.style.display = 'none';
                }

                // Navigation Controls
                const prevBtn = document.getElementById('prevBtn');
                const submitBtn = document.getElementById('submitBtn');
                const nextBtn = document.getElementById('nextBtn');

                prevBtn.disabled = (idx === 0);

                if (state.isSubmitted) {
                    submitBtn.style.display = 'none';
                    nextBtn.style.display = 'inline-flex';
                    if (idx === totalQuestions - 1) {
                        nextBtn.innerHTML = 'Finish Quiz 🏁';
                    } else {
                        nextBtn.innerHTML = 'Next Question &rarr;';
                    }
                } else {
                    submitBtn.style.display = 'inline-flex';
                    submitBtn.disabled = (state.selectedOption === null);
                    nextBtn.style.display = 'none';
                }
            }

            function selectOption(letter) {
                const state = quizState[currentIndex];
                if (state.isSubmitted) return;

                state.selectedOption = letter;

                // Update selected highlight
                const items = document.querySelectorAll('#optionsList .mcq-option-item');
                items.forEach(el => {
                    if (el.dataset.letter === letter) {
                        el.classList.add('selected');
                    } else {
                        el.classList.remove('selected');
                    }
                });

                document.getElementById('submitBtn').disabled = false;
            }

            function submitCurrentAnswer() {
                const state = quizState[currentIndex];
                if (state.selectedOption === null || state.isSubmitted) return;

                const q = mcqsData[currentIndex];
                state.isSubmitted = true;
                state.isCorrect = (state.selectedOption === q.correct_answer);

                renderQuestion(currentIndex);
            }

            function prevQuestion() {
                if (currentIndex > 0) {
                    currentIndex--;
                    renderQuestion(currentIndex);
                }
            }

            function nextQuestion() {
                if (currentIndex < totalQuestions - 1) {
                    currentIndex++;
                    renderQuestion(currentIndex);
                } else {
                    showScorecard();
                }
            }

            function showScorecard() {
                document.getElementById('quizContainer').style.display = 'none';
                const scorecard = document.getElementById('scorecardContainer');
                scorecard.style.display = 'block';

                const correctCount = quizState.filter(s => s.isCorrect).length;
                const incorrectCount = totalQuestions - correctCount;
                const pct = Math.round((correctCount / totalQuestions) * 100);

                document.getElementById('scoreVal').textContent = correctCount;
                document.getElementById('statCorrect').textContent = correctCount;
                document.getElementById('statIncorrect').textContent = incorrectCount;
                document.getElementById('statAccuracy').textContent = pct + '%';

                const scoreTitle = document.getElementById('scoreTitle');
                const scoreSub = document.getElementById('scoreSubtitle');

                if (pct >= 90) {
                    scoreTitle.textContent = '🌟 Mastered! Outstanding Performance';
                    scoreSub.textContent = 'You have thoroughly understood the core facts and concepts from this document.';
                } else if (pct >= 70) {
                    scoreTitle.textContent = '🎉 Great Job! Solid Understanding';
                    scoreSub.textContent = 'You scored well on this practice quiz. Review the incorrect answers below to achieve 100%.';
                } else if (pct >= 50) {
                    scoreTitle.textContent = '👍 Good Effort! Keep Going';
                    scoreSub.textContent = 'You grasped several key topics, but revision of the short notes will reinforce your memory.';
                } else {
                    scoreTitle.textContent = '📚 Keep Practicing!';
                    scoreSub.textContent = 'Review the AI Short Notes for this document and retake this quiz to improve your score.';
                }

                // Render review items
                renderReviewCards();
            }

            function renderReviewCards() {
                const list = document.getElementById('reviewCardsList');
                list.innerHTML = '';

                mcqsData.forEach((q, idx) => {
                    const state = quizState[idx];
                    const card = document.createElement('div');
                    card.className = 'mcq-review-card';

                    const statusBadge = state.isCorrect 
                        ? '<span class="status-badge status-success" style="font-size:0.8rem;">✓ Correct</span>'
                        : '<span class="status-badge status-danger" style="font-size:0.8rem;">✗ Incorrect</span>';

                    let optionsHtml = '';
                    ['A', 'B', 'C', 'D'].forEach(let => {
                        const optText = q.options[let] || '';
                        let style = 'padding: 8px 12px; border-radius: 6px; margin-bottom: 6px; font-size: 0.9rem;';
                        let prefix = '';

                        if (let === q.correct_answer) {
                            style += ' background: var(--bg-card-subtle); border: 1px solid #10b981; color: #10b981; font-weight: 600;';
                            prefix = '✓ [Correct Answer] ';
                        } else if (state.selectedOption === let && !state.isCorrect) {
                            style += ' background: var(--bg-card-subtle); border: 1px solid #f43f5e; color: #f43f5e;';
                            prefix = '✗ [Your Choice] ';
                        } else {
                            style += ' background: var(--bg-card-subtle); border: 1px solid var(--border-color); color: var(--text-body);';
                        }

                        optionsHtml += `<div style="${style}">${let}. ${prefix}${escapeHtml(optText)}</div>`;
                    });

                    card.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-weight: 700; color: var(--primary-600); font-size: 0.88rem;">Question ${idx + 1}</span>
                            ${statusBadge}
                        </div>
                        <div style="font-weight: 700; color: var(--text-title); font-size: 1.05rem; margin-bottom: 14px;">
                            ${escapeHtml(q.question)}
                        </div>
                        <div style="margin-bottom: 14px;">
                            ${optionsHtml}
                        </div>
                        <div style="background: var(--bg-card-subtle); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px 14px; font-size: 0.88rem; color: var(--text-body);">
                            <strong style="color: var(--text-title);">Explanation:</strong> ${escapeHtml(q.explanation)}
                        </div>
                    `;
                    list.appendChild(card);
                });
            }

            function toggleReviewSection() {
                const section = document.getElementById('reviewSection');
                const btn = document.getElementById('toggleReviewBtn');
                if (section.style.display === 'none') {
                    section.style.display = 'block';
                    btn.textContent = 'Hide Answer Review';
                    section.scrollIntoView({ behavior: 'smooth' });
                } else {
                    section.style.display = 'none';
                    btn.textContent = '📋 Review All Answers';
                }
            }

            function restartQuiz() {
                quizState.forEach(s => {
                    s.selectedOption = null;
                    s.isSubmitted = false;
                    s.isCorrect = false;
                });
                initQuiz();
            }

            function escapeHtml(text) {
                if (!text) return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, m => map[m]);
            }

            // Start quiz on load
            document.addEventListener('DOMContentLoaded', initQuiz);
            </script>
        <?php endif; ?>

    </div>
<?php endif; ?>

<!-- Client-side Generation Handler Script -->
<script>
function triggerMcqGeneration(pdfId) {
    const btn = document.getElementById('startGenerateBtn');
    const loadingBox = document.getElementById('mcqLoadingBox');
    const emptyState = document.getElementById('mcqEmptyState');
    const errorBox = document.getElementById('mcqErrorBox');
    const errorText = document.getElementById('mcqErrorText');

    if (btn) btn.disabled = true;
    if (emptyState) emptyState.style.display = 'none';
    if (loadingBox) loadingBox.style.display = 'block';
    if (errorBox) errorBox.style.display = 'none';

    window.scrollTo({ top: 100, behavior: 'smooth' });

    fetch('<?= url("api/generate-mcqs.php") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ pdf_id: pdfId })
    })
    .then(response => response.json().then(data => ({ status: response.status, body: data })))
    .then(res => {
        if (res.status === 200 && res.body.success) {
            // Success: reload to initialize interactive quiz runner
            window.location.reload();
        } else {
            if (loadingBox) loadingBox.style.display = 'none';
            if (emptyState) emptyState.style.display = 'block';
            if (btn) btn.disabled = false;
            if (errorBox) errorBox.style.display = 'flex';
            if (errorText) errorText.textContent = res.body.error || 'Failed to generate MCQs. Please try again.';
        }
    })
    .catch(err => {
        if (loadingBox) loadingBox.style.display = 'none';
        if (emptyState) emptyState.style.display = 'block';
        if (btn) btn.disabled = false;
        if (errorBox) errorBox.style.display = 'flex';
        if (errorText) errorText.textContent = 'Network or server error while connecting to MCQ generation API: ' + err.message;
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
