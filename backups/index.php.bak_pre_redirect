<?php
/**
 * Landing Page / Index
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already authenticated, redirect to dashboard directly
if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> | Smart Study Companion for BCA Students</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
    <div class="landing-hero">
        <nav class="landing-nav">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="logo-badge">⚡</div>
                <div>
                    <div class="logo-text" style="font-size: 1.2rem;"><?= APP_NAME ?></div>
                    <span class="logo-sub"><?= APP_SUBTITLE ?></span>
                </div>
            </div>
            <div style="display: flex; gap: 14px;">
                <a href="<?= url('login.php') ?>" class="btn btn-outline" style="color: #fff; border-color: rgba(255,255,255,0.3);">Login</a>
                <a href="<?= url('signup.php') ?>" class="btn btn-primary">Create Account</a>
            </div>
        </nav>

        <div class="landing-body">
            <div class="hero-content">
                <div class="hero-pill">
                    <span>✨ BCA 3rd Semester Major AI Project</span>
                </div>
                <h1 class="hero-title">Transform Dense PDF Notes into AI Summaries & Quiz Sets</h1>
                <p class="hero-subtitle">
                    Upload your lecture notes, textbooks, and syllabus PDFs. Automatically extract key definitions, generate exam-ready 2/5/10 marks questions, practice interactive MCQs, and query your notes instantly.
                </p>
                <div class="hero-actions">
                    <a href="<?= url('signup.php') ?>" class="btn btn-primary" style="padding: 14px 32px; font-size: 1rem;">
                        Get Started Free &rarr;
                    </a>
                    <a href="<?= url('login.php') ?>" class="btn btn-secondary" style="padding: 14px 28px; font-size: 1rem;">
                        Student Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
