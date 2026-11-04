<?php
/**
 * Shared Header Component
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

$currentUser = get_logged_in_user();
$pageTitle = $pageTitle ?? 'Student Dashboard';
$pageSubTitle = $pageSubTitle ?? 'AI-Powered Study Assistant';
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    
    <!-- Anti-Flash Synchronous Theme Initializer -->
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('theme');
                var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var activeTheme = savedTheme ? savedTheme : (systemDark ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', activeTheme);
            } catch (e) {}
        })();
    </script>

    <!-- Core Application Stylesheet -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="app-container">

    <!-- Horizontal Top Navigation Bar -->
    <header class="app-navbar" id="appNavbar">
        <div class="navbar-container">
            <!-- Left: Brand / Logo -->
            <a href="<?= url('dashboard.php') ?>" class="nav-brand" aria-label="AI StudyLab Dashboard">
                <div class="nav-logo-badge">🎓</div>
                <div class="nav-brand-text">
                    <span class="nav-brand-name">AI StudyLab</span>
                    <span class="nav-brand-sub">Smart Notes. Better Learning.</span>
                </div>
            </a>

            <!-- Desktop Navigation: Dashboard, My Documents, Study Tools Dropdown -->
            <nav class="desktop-nav" aria-label="Main Navigation">
                <a href="<?= url('dashboard.php') ?>" class="nav-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-item-icon">🏠</span>
                    <span>Dashboard</span>
                </a>

                <a href="<?= url('my-pdfs.php') ?>" class="nav-item <?= ($currentScript === 'my-pdfs.php' && empty($_GET['tab'])) ? 'active' : '' ?>">
                    <span class="nav-item-icon">📁</span>
                    <span>My Documents</span>
                </a>

                <!-- Study Tools Dropdown -->
                <div class="nav-dropdown" id="studyToolsDropdown">
                    <button type="button" class="nav-item nav-dropdown-trigger <?= in_array($currentScript, ['exam-preparation.php', 'mcqs-practice.php', 'pdf-results.php', 'ask-from-pdf.php']) ? 'active' : '' ?>" id="studyToolsBtn" aria-haspopup="true" aria-expanded="false">
                        <span class="nav-item-icon">🛠️</span>
                        <span>Study Tools</span>
                        <span class="dropdown-chevron" aria-hidden="true">▾</span>
                    </button>
                    <div class="nav-dropdown-menu" id="studyToolsMenu" role="menu" aria-labelledby="studyToolsBtn">
                        <a href="<?= url('exam-preparation.php') ?>" class="dropdown-link <?= $currentScript === 'exam-preparation.php' ? 'active' : '' ?>" role="menuitem">
                            <span class="dropdown-link-icon">📝</span>
                            <div class="dropdown-link-content">
                                <span class="dropdown-link-title">Exam Preparation</span>
                                <span class="dropdown-link-desc">2, 5 &amp; 10 Marks Revision Kit</span>
                            </div>
                        </a>

                        <a href="<?= url('mcqs-practice.php') ?>" class="dropdown-link <?= $currentScript === 'mcqs-practice.php' ? 'active' : '' ?>" role="menuitem">
                            <span class="dropdown-link-icon">🎯</span>
                            <div class="dropdown-link-content">
                                <span class="dropdown-link-title">MCQ Practice</span>
                                <span class="dropdown-link-desc">10 Interactive Exam MCQs</span>
                            </div>
                        </a>

                        <a href="<?= !empty($_GET['id']) ? url('pdf-results.php?id=' . (int)$_GET['id'] . '&tab=summary') : url('my-pdfs.php') ?>" class="dropdown-link <?= ($currentScript === 'pdf-results.php' && ($_GET['tab'] ?? '') === 'summary') ? 'active' : '' ?>" role="menuitem">
                            <span class="dropdown-link-icon">⚡</span>
                            <div class="dropdown-link-content">
                                <span class="dropdown-link-title">Short Notes</span>
                                <span class="dropdown-link-desc">Key Concepts &amp; Clean Summaries</span>
                            </div>
                        </a>

                        <a href="<?= !empty($_GET['id']) ? url('pdf-results.php?id=' . (int)$_GET['id'] . '&tab=questions') : url('my-pdfs.php') ?>" class="dropdown-link <?= ($currentScript === 'pdf-results.php' && ($_GET['tab'] ?? '') === 'questions') ? 'active' : '' ?>" role="menuitem">
                            <span class="dropdown-link-icon">❓</span>
                            <div class="dropdown-link-content">
                                <span class="dropdown-link-title">Important Questions</span>
                                <span class="dropdown-link-desc">High-Yield Exam Questions</span>
                            </div>
                        </a>

                        <a href="<?= url('ask-from-pdf.php') ?>" class="dropdown-link <?= $currentScript === 'ask-from-pdf.php' ? 'active' : '' ?>" role="menuitem">
                            <span class="dropdown-link-icon">💬</span>
                            <div class="dropdown-link-content">
                                <span class="dropdown-link-title">Ask Your Document</span>
                                <span class="dropdown-link-desc">AI Q&amp;A Chat Assistant</span>
                            </div>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Right Controls: Search, Theme Toggle, Notification, Profile, + Upload Document -->
            <div class="navbar-right">
                <!-- Search Box -->
                <div class="navbar-search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="headerSearchInput" class="navbar-search-input" placeholder="Search..." autocomplete="off" aria-label="Search documents">
                </div>

                <!-- Sun / Moon Theme Switcher Toggle -->
                <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle Theme" title="Switch Theme (Light / Dark)">
                    <span class="theme-toggle-icon" id="themeToggleIcon">☀️</span>
                    <span class="theme-toggle-text" id="themeToggleText">Light</span>
                </button>

                <!-- Notification Bell with Alert Dot -->
                <div class="navbar-notification-btn header-notification-btn" title="Notifications" id="notificationBtn" tabindex="0" role="button" aria-label="Notifications">
                    <span class="notification-icon">🔔</span>
                    <span class="notification-dot"></span>
                </div>

                <!-- User Profile Dropdown -->
                <div class="user-profile-dropdown" id="userProfileDropdown">
                    <button type="button" class="user-profile-trigger" id="userProfileBtn" aria-haspopup="true" aria-expanded="false" title="Account Menu">
                        <div class="user-avatar-badge">
                            <?= strtoupper(substr($currentUser['full_name'] ?? 'K', 0, 1)) ?>
                        </div>
                        <span class="user-display-name"><?= htmlspecialchars(explode(' ', $currentUser['full_name'] ?? 'User')[0]) ?></span>
                        <span class="dropdown-chevron" aria-hidden="true">▾</span>
                    </button>
                    <div class="user-profile-menu" id="userProfileMenu" role="menu" aria-labelledby="userProfileBtn">
                        <div class="user-profile-header">
                            <strong class="user-full-name"><?= htmlspecialchars($currentUser['full_name'] ?? 'Student') ?></strong>
                            <span class="user-email-text"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
                            <span class="user-course-badge">🎓 <?= htmlspecialchars($currentUser['course'] ?? 'BCA 3rd Sem') ?></span>
                        </div>
                        <div class="user-profile-divider"></div>
                        <a href="<?= url('logout.php') ?>" class="user-logout-link" role="menuitem">
                            <span>🚪</span> Sign Out
                        </a>
                    </div>
                </div>

                <!-- Prominent "+ Upload Document" Button (Desktop) -->
                <a href="<?= url('upload-pdf.php') ?>" class="nav-upload-btn" title="Upload Document">
                    <span class="nav-upload-plus">+</span>
                    <span class="nav-upload-text">Upload Document</span>
                </a>

                <!-- Mobile Compact "+" Upload Button (Narrow screens) -->
                <a href="<?= url('upload-pdf.php') ?>" class="mobile-compact-upload-btn" title="Upload Document" aria-label="Upload Document">
                    <span>+</span>
                </a>

                <!-- Hamburger Menu Button (Narrow screens) -->
                <button type="button" class="mobile-hamburger-btn" id="mobileMenuBtn" aria-label="Open Navigation Menu" aria-expanded="false" aria-controls="mobileNavDrawer">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Drawer & Backdrop -->
        <div class="mobile-nav-backdrop" id="mobileNavBackdrop"></div>
        <div class="mobile-nav-drawer" id="mobileNavDrawer" aria-hidden="true">
            <div class="mobile-nav-drawer-inner">
                <!-- Mobile Search -->
                <div class="mobile-search-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="mobileSearchInput" class="mobile-search-input" placeholder="Search your documents..." autocomplete="off" aria-label="Search documents">
                </div>

                <!-- Mobile Navigation Links -->
                <div class="mobile-nav-group">
                    <span class="mobile-group-title">Main Navigation</span>
                    <a href="<?= url('dashboard.php') ?>" class="mobile-nav-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                        <span class="mobile-link-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                    <a href="<?= url('my-pdfs.php') ?>" class="mobile-nav-link <?= ($currentScript === 'my-pdfs.php' && empty($_GET['tab'])) ? 'active' : '' ?>">
                        <span class="mobile-link-icon">📁</span>
                        <span>My Documents</span>
                    </a>
                    <a href="<?= url('upload-pdf.php') ?>" class="mobile-nav-link <?= $currentScript === 'upload-pdf.php' ? 'active' : '' ?>">
                        <span class="mobile-link-icon">☁️</span>
                        <span>Upload Document</span>
                    </a>
                </div>

                <div class="mobile-nav-group">
                    <span class="mobile-group-title">Study Tools</span>
                    <a href="<?= url('exam-preparation.php') ?>" class="mobile-nav-link <?= $currentScript === 'exam-preparation.php' ? 'active' : '' ?>">
                        <span class="mobile-link-icon">📝</span>
                        <span>Exam Preparation</span>
                    </a>
                    <a href="<?= url('mcqs-practice.php') ?>" class="mobile-nav-link <?= $currentScript === 'mcqs-practice.php' ? 'active' : '' ?>">
                        <span class="mobile-link-icon">🎯</span>
                        <span>MCQ Practice</span>
                    </a>
                    <a href="<?= !empty($_GET['id']) ? url('pdf-results.php?id=' . (int)$_GET['id'] . '&tab=summary') : url('my-pdfs.php') ?>" class="mobile-nav-link <?= ($currentScript === 'pdf-results.php' && ($_GET['tab'] ?? '') === 'summary') ? 'active' : '' ?>">
                        <span class="mobile-link-icon">⚡</span>
                        <span>Short Notes</span>
                    </a>
                    <a href="<?= !empty($_GET['id']) ? url('pdf-results.php?id=' . (int)$_GET['id'] . '&tab=questions') : url('my-pdfs.php') ?>" class="mobile-nav-link <?= ($currentScript === 'pdf-results.php' && ($_GET['tab'] ?? '') === 'questions') ? 'active' : '' ?>">
                        <span class="mobile-link-icon">❓</span>
                        <span>Important Questions</span>
                    </a>
                    <a href="<?= url('ask-from-pdf.php') ?>" class="mobile-nav-link <?= $currentScript === 'ask-from-pdf.php' ? 'active' : '' ?>">
                        <span class="mobile-link-icon">💬</span>
                        <span>Ask Your Document</span>
                    </a>
                </div>

                <div class="mobile-nav-group mobile-user-section">
                    <div class="mobile-user-card">
                        <div class="user-avatar-badge">
                            <?= strtoupper(substr($currentUser['full_name'] ?? 'K', 0, 1)) ?>
                        </div>
                        <div class="mobile-user-meta">
                            <div class="mobile-user-name"><?= htmlspecialchars($currentUser['full_name'] ?? 'Kartik Malav') ?></div>
                            <div class="mobile-user-email"><?= htmlspecialchars($currentUser['email'] ?? '') ?></div>
                            <div class="mobile-user-course">🎓 <?= htmlspecialchars($currentUser['course'] ?? 'BCA 3rd Sem') ?></div>
                        </div>
                    </div>
                    <div class="mobile-action-row">
                        <!-- Mobile theme toggle button -->
                        <button type="button" class="theme-toggle-btn mobile-theme-btn" id="mobileThemeToggleBtn">
                            <span class="theme-toggle-icon" id="mobileThemeToggleIcon">☀️</span>
                            <span class="theme-toggle-text" id="mobileThemeToggleText">Light</span>
                        </button>
                        <!-- Mobile logout link -->
                        <a href="<?= url('logout.php') ?>" class="mobile-logout-btn">
                            <span>🚪</span> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Main Body Content Container -->
        <main class="content-body">
            <?php display_flash_message(); ?>
