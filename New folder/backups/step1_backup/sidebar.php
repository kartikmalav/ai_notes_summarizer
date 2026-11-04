<?php
/**
 * Shared Sidebar Component
 * AI-Based PDF Notes Summarizer and Question Generator
 */

$currentScript = basename($_SERVER['SCRIPT_NAME']);
$user = $currentUser ?? get_logged_in_user();
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-badge">🎓</div>
        <div>
            <div class="logo-text">AI StudyLab</div>
            <span class="logo-sub">Smart Notes. Better Learning.</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="menu-section-title">Navigation</div>
        
        <a href="<?= url('dashboard.php') ?>" class="menu-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
            <span class="menu-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <a href="<?= url('upload-pdf.php') ?>" class="menu-item <?= $currentScript === 'upload-pdf.php' ? 'active' : '' ?>">
            <span class="menu-icon">☁️</span>
            <span>Upload Document</span>
        </a>

        <a href="<?= url('my-pdfs.php') ?>" class="menu-item <?= $currentScript === 'my-pdfs.php' ? 'active' : '' ?>">
            <span class="menu-icon">📁</span>
            <span>My Documents</span>
        </a>

        <div class="menu-section-title" style="margin-top: 15px;">AI Tools</div>

        <a href="<?= url('exam-preparation.php') ?>" class="menu-item <?= $currentScript === 'exam-preparation.php' ? 'active' : '' ?>">
            <span class="menu-icon">📝</span>
            <span>Exam Preparation</span>
        </a>

        <a href="<?= url('mcqs-practice.php') ?>" class="menu-item <?= $currentScript === 'mcqs-practice.php' ? 'active' : '' ?>">
            <span class="menu-icon">🎯</span>
            <span>MCQ Practice</span>
        </a>

        <a href="<?= url('my-pdfs.php') ?>" class="menu-item <?= ($currentScript === 'pdf-results.php' && ($_GET['tab'] ?? '') === 'summary') ? 'active' : '' ?>">
            <span class="menu-icon">⚡</span>
            <span>Short Notes</span>
        </a>

        <a href="<?= url('my-pdfs.php') ?>" class="menu-item <?= ($currentScript === 'pdf-results.php' && ($_GET['tab'] ?? '') === 'questions') ? 'active' : '' ?>">
            <span class="menu-icon">❓</span>
            <span>Important Questions</span>
        </a>

        <a href="<?= url('ask-from-pdf.php') ?>" class="menu-item <?= $currentScript === 'ask-from-pdf.php' ? 'active' : '' ?>">
            <span class="menu-icon">💬</span>
            <span>Ask from PDF</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="user-pill">
            <div class="user-avatar">
                <?= strtoupper(substr($user['full_name'] ?? 'K', 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'Kartik Malav') ?></div>
                <div class="user-role"><?= htmlspecialchars($user['email'] ?? 'kartikmalav431@gmail.com') ?></div>
            </div>
            <a href="<?= url('logout.php') ?>" class="sidebar-logout-btn" title="Sign Out" aria-label="Sign Out">
                <span style="font-size: 1.1rem;">🚪</span>
            </a>
        </div>
    </div>
</aside>
