<?php
/**
 * Ask Your Document (Document Q&A Chat Assistant)
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/markdown.php';

require_login();

$userId = $_SESSION['user_id'];
$pdo = getDBConnection();

$pdfId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pdf = null;
$messages = [];
$errorMessage = null;

if ($pdfId > 0) {
    // 1. Ownership & IDOR verification
    $stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pdfId, $userId]);
    $pdf = $stmt->fetch();

    if (!$pdf) {
        $errorMessage = "Document not found or you do not have permission to access it.";
    } else {
        // 2. Fetch existing conversation history from generated_content
        $qnaStmt = $pdo->prepare("
            SELECT content_data FROM generated_content 
            WHERE pdf_id = ? AND user_id = ? AND content_type = 'qna' 
            ORDER BY id DESC LIMIT 1
        ");
        $qnaStmt->execute([$pdfId, $userId]);
        $historyRow = $qnaStmt->fetch();

        if ($historyRow && !empty($historyRow['content_data'])) {
            $decoded = json_decode($historyRow['content_data'], true);
            if (is_array($decoded)) {
                $messages = $decoded;
            }
        }
    }
}

// If no document selected or invalid, fetch list of user's documents
$userDocuments = [];
if (!$pdf) {
    $docsStmt = $pdo->prepare("
        SELECT p.*, 
            (SELECT COUNT(*) FROM generated_content WHERE pdf_id = p.id AND user_id = :u1 AND content_type = 'qna') AS has_chat,
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
}

$pageTitle = $pdf ? 'Ask Document: ' . htmlspecialchars($pdf['original_filename']) : 'Ask Your Document';
$pageSubTitle = 'Chat with your document and receive ground-truth AI answers';

include __DIR__ . '/includes/header.php';
?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px;">
        <div class="alert-icon">⚠</div>
        <div class="alert-body"><?= htmlspecialchars($errorMessage) ?></div>
    </div>
    <div style="margin-bottom: 24px;">
        <a href="<?= url('ask-from-pdf.php') ?>" class="btn btn-primary">&larr; Back to Document Selection</a>
    </div>
<?php endif; ?>

<?php if (!$pdf): ?>
    <!-- ========================================================= -->
    <!-- VIEW A: DOCUMENT SELECTION MODE                           -->
    <!-- ========================================================= -->
    <div class="page-card">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 class="page-title">🤖 Ask Your Document</h2>
                <p class="page-subtitle">Select an uploaded document to chat with and ask conceptual questions</p>
            </div>
            <div>
                <a href="<?= url('upload-pdf.php') ?>" class="btn btn-sm btn-primary">
                    📤 Upload New Document
                </a>
            </div>
        </div>

        <?php if (empty($userDocuments)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--slate-50); border-radius: var(--radius-lg); border: 2px dashed var(--slate-300); margin-top: 16px;">
                <div style="font-size: 3.5rem; margin-bottom: 14px; color: var(--primary-600);">📚</div>
                <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--slate-900); margin-bottom: 8px;">No Documents Available</h3>
                <p style="font-size: 0.92rem; color: var(--text-muted); max-width: 480px; margin: 0 auto 20px;">
                    Upload a lecture note, PDF, Word file, text file, or presentation to start asking questions!
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
                            <th>Document</th>
                            <th>Subject</th>
                            <th>Chat History</th>
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
                                            <a href="<?= url('ask-from-pdf.php?id=' . $doc['id']) ?>" style="font-weight: 600; color: var(--slate-900);">
                                                <?= htmlspecialchars($doc['original_filename']) ?>
                                            </a>
                                            <div style="font-size: 0.78rem; color: var(--text-muted);">
                                                <?= round($doc['file_size'] / (1024 * 1024), 2) ?> MB &bull; <?= strtoupper($doc['file_type']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag-item"><?= htmlspecialchars($doc['subject'] ?: 'General') ?></span>
                                </td>
                                <td>
                                    <?php if ($doc['has_chat'] > 0): ?>
                                        <span class="status-badge status-success">💬 Active Chat</span>
                                    <?php else: ?>
                                        <span class="status-badge status-warning">⚡ Ready to Ask</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-muted); font-size: 0.85rem;">
                                    <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?= url('ask-from-pdf.php?id=' . $doc['id']) ?>" class="btn btn-sm btn-primary">
                                        💬 Ask Document &rarr;
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
    <!-- ========================================================= -->
    <!-- VIEW B: DOCUMENT CHAT MODE                                -->
    <!-- ========================================================= -->
    <div class="chat-container">

        <!-- Top Document Header Bar -->
        <div class="mcq-header-card" style="margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                        Document Q&amp;A Assistant
                    </div>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--slate-900); margin-top: 2px;">
                        <?= htmlspecialchars($pdf['original_filename']) ?>
                    </h2>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px; font-size: 0.85rem; color: var(--text-muted);">
                        <span class="tag-item"><?= htmlspecialchars($pdf['subject'] ?: 'General') ?></span>
                        <span>&bull;</span>
                        <span><?= strtoupper($pdf['file_type']) ?></span>
                        <span>&bull;</span>
                        <span style="color: #059669; font-weight: 600;">🔒 Document Grounded</span>
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
                    <a href="<?= url('mcqs-practice.php?id=' . $pdf['id']) ?>" class="btn btn-sm btn-outline" title="Practice MCQs">
                        🎯 MCQs
                    </a>
                    <button type="button" class="btn btn-sm btn-outline" style="color: var(--accent-rose); border-color: #fca5a5;" onclick="clearConversation(<?= $pdf['id'] ?>)" title="Clear Chat History">
                        🗑️ Clear Chat
                    </button>
                    <a href="<?= url('ask-from-pdf.php') ?>" class="btn btn-sm btn-secondary" title="Switch to another document">
                        📁 Switch Doc
                    </a>
                </div>
            </div>
        </div>

        <!-- Chat Window -->
        <div class="chat-window">
            <!-- Messages Area -->
            <div id="chatMessages" class="chat-messages-area">

                <!-- Welcome / Starter Banner -->
                <div id="welcomeBanner" class="chat-welcome-banner" style="<?= !empty($messages) ? 'display: none;' : '' ?>">
                    <div style="font-size: 2.4rem; margin-bottom: 8px;">🤖</div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--slate-900); margin-bottom: 6px;">
                        Ask anything about "<?= htmlspecialchars($pdf['original_filename']) ?>"
                    </h3>
                    <p style="font-size: 0.88rem; color: var(--text-muted); max-width: 520px; margin: 0 auto;">
                        Answers are grounded strictly in your document text. Click a suggested question below or type your own.
                    </p>

                    <div class="chat-starter-chips">
                        <button type="button" class="chat-chip" onclick="askQuickPrompt('Summarize the main points of this document in 3 concise bullet points.')">
                            📌 3 Key Takeaways
                        </button>
                        <button type="button" class="chat-chip" onclick="askQuickPrompt('What are the most important definitions and core concepts in this text?')">
                            📖 Core Definitions
                        </button>
                        <button type="button" class="chat-chip" onclick="askQuickPrompt('What are the primary formulas, steps, or algorithms explained here?')">
                            ⚙️ Formulas / Procedures
                        </button>
                        <button type="button" class="chat-chip" onclick="askQuickPrompt('What are the exam-critical questions or takeaways from this document?')">
                            🎯 Exam Highlights
                        </button>
                    </div>
                </div>

                <!-- Existing messages render container -->
                <div id="messageHistoryContainer" style="display: flex; flex-direction: column; gap: 18px;">
                    <?php foreach ($messages as $msg): ?>
                        <div class="chat-msg-row <?= $msg['role'] === 'user' ? 'user' : 'ai' ?>">
                            <div class="chat-avatar <?= $msg['role'] === 'user' ? 'user' : 'ai' ?>">
                                <?= $msg['role'] === 'user' ? '🎓' : '🤖' ?>
                            </div>
                            <div class="chat-bubble">
                                <div class="chat-bubble-content">
                                    <?php if ($msg['role'] === 'assistant'): ?>
                                        <?= render_markdown($msg['content']) ?>
                                    <?php else: ?>
                                        <?= nl2br(htmlspecialchars($msg['content'])) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-bubble-meta">
                                    <span><?= $msg['role'] === 'user' ? 'You' : 'AI Assistant' ?></span>
                                    <span>&bull;</span>
                                    <span><?= !empty($msg['timestamp']) ? date('H:i', strtotime($msg['timestamp'])) : '' ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Typing indicator (hidden by default) -->
                <div id="typingIndicator" class="chat-msg-row ai" style="display: none;">
                    <div class="chat-avatar ai">🤖</div>
                    <div class="chat-bubble" style="padding: 10px 16px;">
                        <div class="chat-typing-dots">
                            <span class="chat-dot"></span>
                            <span class="chat-dot"></span>
                            <span class="chat-dot"></span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-left: 6px;">Reading document &amp; thinking...</span>
                    </div>
                </div>

            </div>

            <!-- Chat Input Area -->
            <div class="chat-input-container">
                <form id="chatForm" class="chat-input-form" onsubmit="handleChatSubmit(event)">
                    <textarea 
                        id="chatInput" 
                        class="chat-textarea" 
                        rows="1" 
                        placeholder="Ask a question about this document... (Enter to send, Shift+Enter for newline)" 
                        onkeydown="handleTextareaKeydown(event)"
                        oninput="autoResizeTextarea(this)"></textarea>

                    <button type="submit" id="sendBtn" class="btn btn-primary" style="padding: 12px 22px; height: 48px; display: inline-flex; align-items: center; gap: 6px;" title="Send question">
                        <span>Send</span>
                        <span>&rarr;</span>
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- Client-side Chat Handler Script -->
    <script>
    const currentPdfId = <?= (int) $pdf['id'] ?>;
    let isSubmitting = false;

    function autoResizeTextarea(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 140) + 'px';
    }

    function handleTextareaKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleChatSubmit(e);
        }
    }

    function askQuickPrompt(text) {
        const input = document.getElementById('chatInput');
        input.value = text;
        autoResizeTextarea(input);
        input.focus();
        handleChatSubmit(new Event('submit'));
    }

    function handleChatSubmit(e) {
        if (e) e.preventDefault();
        if (isSubmitting) return;

        const input = document.getElementById('chatInput');
        const question = input.value.trim();

        if (question.length < 2) return;

        isSubmitting = true;
        document.getElementById('sendBtn').disabled = true;
        input.disabled = true;

        // Hide welcome banner if visible
        const welcomeBanner = document.getElementById('welcomeBanner');
        if (welcomeBanner) welcomeBanner.style.display = 'none';

        // Append user bubble to UI immediately
        appendMessageBubble('user', question, getCurrentTime());
        input.value = '';
        input.style.height = '48px';

        // Show typing indicator
        const typing = document.getElementById('typingIndicator');
        typing.style.display = 'flex';
        scrollToBottom();

        // Send to backend API
        fetch('<?= url("api/ask-document.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'ask',
                pdf_id: currentPdfId,
                question: question
            })
        })
        .then(response => response.json().then(data => ({ status: response.status, body: data })))
        .then(res => {
            typing.style.display = 'none';
            isSubmitting = false;
            document.getElementById('sendBtn').disabled = false;
            input.disabled = false;
            input.focus();

            if (res.status === 200 && res.body.success) {
                const htmlToUse = (res.body.answer_html && typeof res.body.answer_html === 'string' && res.body.answer_html.trim().length > 0)
                    ? res.body.answer_html
                    : null;
                appendMessageBubble('assistant', res.body.answer, getCurrentTime(), true, htmlToUse);
            } else {
                appendErrorNotice(res.body.error || 'Failed to get an answer. Please try again.');
            }
            scrollToBottom();
        })
        .catch(err => {
            typing.style.display = 'none';
            isSubmitting = false;
            document.getElementById('sendBtn').disabled = false;
            input.disabled = false;
            input.focus();
            appendErrorNotice('Network connection error: ' + err.message);
            scrollToBottom();
        });
    }

    function appendMessageBubble(role, content, timeStr, isMarkdown = false, preRenderedHtml = null) {
        const container = document.getElementById('messageHistoryContainer');
        const row = document.createElement('div');
        row.className = 'chat-msg-row ' + (role === 'user' ? 'user' : 'ai');

        const avatar = document.createElement('div');
        avatar.className = 'chat-avatar ' + (role === 'user' ? 'user' : 'ai');
        avatar.innerHTML = (role === 'user' ? '🎓' : '🤖');

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'chat-bubble-content';

        if (preRenderedHtml && typeof preRenderedHtml === 'string' && preRenderedHtml.trim().length > 0) {
            contentDiv.innerHTML = preRenderedHtml;
        } else if (isMarkdown) {
            contentDiv.innerHTML = formatClientMarkdown(content);
        } else {
            contentDiv.textContent = content;
        }

        const meta = document.createElement('div');
        meta.className = 'chat-bubble-meta';
        meta.innerHTML = `<span>${role === 'user' ? 'You' : 'AI Assistant'}</span><span>&bull;</span><span>${timeStr}</span>`;

        bubble.appendChild(contentDiv);
        bubble.appendChild(meta);

        row.appendChild(avatar);
        row.appendChild(bubble);

        container.appendChild(row);
        scrollToBottom();
    }

    function appendErrorNotice(msg) {
        const container = document.getElementById('messageHistoryContainer');
        const errDiv = document.createElement('div');
        errDiv.className = 'alert alert-danger';
        errDiv.style.margin = '8px 0';
        errDiv.innerHTML = `<div class="alert-icon">⚠</div><div class="alert-body">${escapeHtml(msg)}</div>`;
        container.appendChild(errDiv);
    }

    function clearConversation(pdfId) {
        if (!confirm('Are you sure you want to clear this conversation history?')) return;

        fetch('<?= url("api/ask-document.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'clear',
                pdf_id: pdfId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('messageHistoryContainer').innerHTML = '';
                const banner = document.getElementById('welcomeBanner');
                if (banner) banner.style.display = 'block';
            } else {
                alert(data.error || 'Failed to clear conversation.');
            }
        })
        .catch(err => {
            alert('Error clearing conversation: ' + err.message);
        });
    }

    function scrollToBottom() {
        const chatWindow = document.getElementById('chatMessages');
        if (chatWindow) {
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }
    }

    function getCurrentTime() {
        const now = new Date();
        return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function renderClientInline(str) {
        if (!str) return '';
        let escaped = escapeHtml(str);

        // Bold-italic
        escaped = escaped.replace(/\*\*\*([^*\r\n]+?)\*\*\*/g, '<strong><em>$1</em></strong>');
        escaped = escaped.replace(/(?<!\w)___([^_\r\n]+?)___(?!\w)/g, '<strong><em>$1</em></strong>');

        // Bold
        escaped = escaped.replace(/\*\*[ \t]*([^*\r\n]+?)[ \t]*\*\*/g, '<strong>$1</strong>');
        escaped = escaped.replace(/(?<!\w)__[ \t]*([^_\r\n]+?)[ \t]*__(?!\w)/g, '<strong>$1</strong>');

        // Italic
        escaped = escaped.replace(/(?<!\*|\w)\*(?!\s|\*)([^*\r\n]+?)(?<!\s|\*)\*(?!\*|\w)/g, '<em>$1</em>');
        escaped = escaped.replace(/(?<!\w)_(?!\s|_)([^_\r\n]+?)(?<!\s|_)_(?!\w)/g, '<em>$1</em>');

        // Inline code
        escaped = escaped.replace(/`([^`\r\n]+)`/g, '<code class="markdown-inline-code">$1</code>');

        return escaped.trim();
    }

    function formatClientMarkdown(raw) {
        if (!raw) return '';

        let text = String(raw).replace(/\r\n/g, '\n').replace(/\r/g, '\n');

        // 1. Protect code blocks ```lang ... ```
        const codeBlocks = [];
        text = text.replace(/```(?:[a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, function(match, code) {
            const idx = codeBlocks.length;
            codeBlocks.push('<pre class="markdown-code"><code>' + escapeHtml(code.trim()) + '</code></pre>');
            return `%%%CODEBLOCK_${idx}%%%\n`;
        });

        // 2. Protect inline code `code`
        const inlineCodes = [];
        text = text.replace(/`([^`\n]+)`/g, function(match, code) {
            const idx = inlineCodes.length;
            inlineCodes.push('<code class="markdown-inline-code">' + escapeHtml(code) + '</code>');
            return `%%%INLINECODE_${idx}%%%`;
        });

        // 3. Remove decorative emojis & pictographs (preserves math operators & arrows)
        text = text.replace(/[\u{1F300}-\u{1FAFF}\u{1F000}-\u{1F2FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/gu, '');

        // 4. Normalize decorative markers to standard bullets and remove ornamental icons
        text = text.replace(/^([ \t]*)[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]+[ \t]*/gmu, '$1- ');
        text = text.replace(/^([ \t]*(?:[-*+]|\d+\.))[ \t]+[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]+[ \t]*/gmu, '$1 ');
        text = text.replace(/[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]/gu, '');

        // Clean up spaces before punctuation
        text = text.replace(/[ \t]+([.,;:?])(?=\s|$)/gm, '$1');

        // Collapse excessive blank lines
        text = text.replace(/\n{3,}/g, '\n\n');

        // Remove redundant leading/trailing horizontal rules
        text = text.replace(/^\s*[-*_]{3,}\s*\n+/g, '');
        text = text.replace(/\n+\s*[-*_]{3,}\s*$/g, '');
        text = text.replace(/(\n[ \t]*[-*_]{3,}[ \t]*){2,}/g, '\n---');

        // 5. Protect and render Markdown Tables (including cell inline bold, italic, code)
        const tables = [];
        text = text.replace(/(?:^\|[^\n]+\|\n)(?:^\|[-:\s|]+\|\n)(?:^\|[^\n]+\|\n?)+/gm, function(tableMatch) {
            const rawLines = tableMatch.trim().split('\n');
            const lines = [];
            for (let l of rawLines) {
                const tr = l.trim();
                if (tr !== '') lines.push(tr);
            }
            if (lines.length < 2) return tableMatch;

            const headerLine = lines.shift();
            const alignLine = lines.shift();

            const headers = headerLine.replace(/^\||\|$/g, '').split('|').map(s => s.trim()).filter(s => s !== '');

            let tableHtml = '<div class="markdown-table-wrap"><table class="table-custom"><thead><tr>';
            for (let h of headers) {
                tableHtml += '<th>' + renderClientInline(h) + '</th>';
            }
            tableHtml += '</tr></thead><tbody>';

            for (let line of lines) {
                const cols = line.replace(/^\||\|$/g, '').split('|').map(s => s.trim());
                if (cols.length > 0) {
                    tableHtml += '<tr>';
                    for (let c of cols) {
                        tableHtml += '<td>' + renderClientInline(c) + '</td>';
                    }
                    tableHtml += '</tr>';
                }
            }
            tableHtml += '</tbody></table></div>';
            const idx = tables.length;
            tables.push(tableHtml);
            return `\n%%%TABLE_${idx}%%%\n`;
        });

        // 6. Escape HTML for remaining prose
        let escaped = escapeHtml(text);

        // 7. Headings (###### down to # to prevent partial matching)
        escaped = escaped.replace(/^######[ \t]+(.*$)/gim, '<h6 class="markdown-h6">$1</h6>');
        escaped = escaped.replace(/^#####[ \t]+(.*$)/gim, '<h5 class="markdown-h5">$1</h5>');
        escaped = escaped.replace(/^####[ \t]+(.*$)/gim, '<h4 class="markdown-h4">$1</h4>');
        escaped = escaped.replace(/^###[ \t]+(.*$)/gim, '<h3 class="markdown-h3">$1</h3>');
        escaped = escaped.replace(/^##[ \t]+(.*$)/gim, '<h2 class="markdown-h2">$1</h2>');
        escaped = escaped.replace(/^#[ \t]+(.*$)/gim, '<h1 class="markdown-h1">$1</h1>');

        // 8. Horizontal rules (---, ***, ___)
        escaped = escaped.replace(/^[ \t]*[-*_]{3,}[ \t]*$/gim, '<hr class="markdown-hr">');

        // 9. Blockquotes (> quote)
        escaped = escaped.replace(/^[ \t]*&gt;[ \t]?(.*$)/gim, '<blockquote class="markdown-blockquote">$1</blockquote>');

        // 10. Unordered bullet lists (- or * or +) - converted before italic parsing
        escaped = escaped.replace(/^[ \t]*[-*+][ \t]+(.*$)/gim, '<li class="markdown-li">$1</li>');
        escaped = escaped.replace(/((?:<li class="markdown-li">.*?<\/li>\s*)+)/gis, '<ul class="markdown-ul">$1</ul>');

        // 11. Ordered numbered lists (1. item)
        escaped = escaped.replace(/^[ \t]*\d+\.[ \t]+(.*$)/gim, '<li class="markdown-oli">$1</li>');
        escaped = escaped.replace(/((?:<li class="markdown-oli">.*?<\/li>\s*)+)/gis, '<ol class="markdown-ol">$1</ol>');
        escaped = escaped.replace(/<li class="markdown-oli">/g, '<li class="markdown-li">');

        // 12. Bold & Italic inline formatting
        escaped = escaped.replace(/\*\*\*([^*\r\n]+?)\*\*\*/g, '<strong><em>$1</em></strong>');
        escaped = escaped.replace(/(?<!\w)___([^_\r\n]+?)___(?!\w)/g, '<strong><em>$1</em></strong>');
        escaped = escaped.replace(/\*\*[ \t]*((?:[^*\r\n]|\r?\n(?!\r?\n))+?)[ \t]*\*\*/g, '<strong>$1</strong>');
        escaped = escaped.replace(/(?<!\w)__[ \t]*((?:[^_\r\n]|\r?\n(?!\r?\n))+?)[ \t]*__(?!\w)/g, '<strong>$1</strong>');
        escaped = escaped.replace(/(?<!\*|\w)\*(?!\s|\*)([^*\r\n]+?)(?<!\s|\*)\*(?!\*|\w)/g, '<em>$1</em>');
        escaped = escaped.replace(/(?<!\w)_(?!\s|_)([^_\r\n]+?)(?<!\s|_)_(?!\w)/g, '<em>$1</em>');

        // 13. Convert newlines to <br>
        escaped = escaped.replace(/\n/g, '<br>');

        // 14. Clean up unnecessary <br> tags around block elements
        const blockTags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'hr', 'blockquote'];
        blockTags.forEach(tag => {
            escaped = escaped.replace(new RegExp('<br\\s*\\/?>\\s*(<' + tag + '[^>]*>)', 'gi'), '$1');
            escaped = escaped.replace(new RegExp('(</' + tag + '>)\\s*<br\\s*\\/?>', 'gi'), '$1');
            escaped = escaped.replace(new RegExp('(<' + tag + '[^>]*>)\\s*<br\\s*\\/?>', 'gi'), '$1');
        });
        escaped = escaped.replace(/<hr class="markdown-hr">\s*<br\s*\/?>/gi, '<hr class="markdown-hr">');

        // 15. Restore Tables
        tables.forEach((tbl, i) => {
            escaped = escaped.replace(new RegExp(`%%%TABLE_${i}%%%`, 'g'), tbl);
        });
        escaped = escaped.replace(/<br\s*\/?>\s*(<div class="markdown-table-wrap">)/gi, '$1');
        escaped = escaped.replace(/(<\/div>)\s*<br\s*\/?>/gi, '$1');

        // 16. Restore Code Blocks and Inline Code
        codeBlocks.forEach((code, i) => {
            escaped = escaped.replace(new RegExp(`%%%CODEBLOCK_${i}%%%`, 'g'), code);
        });
        inlineCodes.forEach((code, i) => {
            escaped = escaped.replace(new RegExp(`%%%INLINECODE_${i}%%%`, 'g'), code);
        });

        escaped = escaped.replace(/<br\s*\/?>\s*(<pre\b)/gi, '$1');
        escaped = escaped.replace(/(<\/pre>)\s*<br\s*\/?>/gi, '$1');
        escaped = escaped.replace(/(?:<br\s*\/?>\s*){3,}/gi, '<br><br>');

        return escaped.trim();
    }

    // Scroll chat to bottom on initial load
    document.addEventListener('DOMContentLoaded', () => {
        scrollToBottom();
    });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
