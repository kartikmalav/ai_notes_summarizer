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

    <!-- Include Navigation Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Top Header Bar -->
        <header class="top-header">
            <div class="header-left-section">
                <button type="button" class="toggle-sidebar-btn" id="toggleSidebarBtn" title="Toggle Menu" aria-label="Toggle Navigation">
                    ☰
                </button>
                <div class="header-title-section">
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                    <p><?= htmlspecialchars($pageSubTitle) ?></p>
                </div>
            </div>

            <div class="header-right-actions">
                <!-- Search Input Box -->
                <div class="header-search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="headerSearchInput" class="header-search-input" placeholder="Search your documents..." autocomplete="off">
                </div>

                <!-- Sun / Moon Theme Switcher Toggle -->
                <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle Theme" title="Switch Theme (Light / Dark)">
                    <span class="theme-toggle-icon" id="themeToggleIcon">☀️</span>
                    <span class="theme-toggle-text" id="themeToggleText">Light</span>
                </button>

                <!-- Notification Bell with Alert Dot -->
                <div class="header-notification-btn" title="Notifications" id="notificationBtn">
                    <span class="notification-icon">🔔</span>
                    <span class="notification-dot"></span>
                </div>

                <!-- User Profile Badge -->
                <div class="header-user-profile">
                    <div class="header-user-avatar">
                        <?= strtoupper(substr($currentUser['full_name'] ?? 'K', 0, 1)) ?>
                    </div>
                    <div class="header-user-meta">
                        <span class="header-user-name"><?= htmlspecialchars($currentUser['full_name'] ?? 'Kartik Malav') ?></span>
                        <span class="header-user-role"><?= htmlspecialchars($currentUser['course'] ?? 'BCA 3rd Sem') ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body Content Container -->
        <main class="content-body">
            <?php display_flash_message(); ?>
