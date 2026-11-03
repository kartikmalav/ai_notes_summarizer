<?php
/**
 * Automated Verification Test Suite for Step 1: Horizontal Navigation & Dual Themes
 */

$testCount = 0;
$passCount = 0;
$failCount = 0;

function assert_test($condition, $testName, $details = '') {
    global $testCount, $passCount, $failCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] Test $testCount: $testName\n";
    } else {
        $failCount++;
        echo "  [FAIL] Test $testCount: $testName - $details\n";
    }
}

echo "=================================================================\n";
echo "STEP 1 VERIFICATION: HORIZONTAL NAVIGATION & LIGHT/DARK THEMES\n";
echo "=================================================================\n\n";

$headerPath = 'c:/xampp/htdocs/ai_notes_summarizer/includes/header.php';
$cssPath    = 'c:/xampp/htdocs/ai_notes_summarizer/assets/css/style.css';
$jsPath     = 'c:/xampp/htdocs/ai_notes_summarizer/assets/js/app.js';

// Group 1: includes/header.php Verification
echo "--- Group 1: Shared Header & Navigation Structure (includes/header.php) ---\n";
$headerContent = file_get_contents($headerPath);
assert_test($headerContent !== false && strlen($headerContent) > 0, "Header file exists and is readable");

// 1.1 Anti-flash script
assert_test(strpos($headerContent, 'localStorage.getItem(\'theme\')') !== false, "Anti-flash synchronous theme script present in <head>");
assert_test(strpos($headerContent, 'document.documentElement.setAttribute(\'data-theme\'') !== false, "Anti-flash script sets data-theme immediately on root html");

// 1.2 Top Navbar Container & Branding
assert_test(strpos($headerContent, 'class="app-navbar"') !== false, "Horizontal navbar element <header class=\"app-navbar\"> exists");
assert_test(strpos($headerContent, 'AI StudyLab') !== false, "Navbar brand name 'AI StudyLab' present");
assert_test(strpos($headerContent, 'nav-logo-badge') !== false, "Brand logo badge present");

// 1.3 Desktop Navigation Links
assert_test(strpos($headerContent, 'class="desktop-nav"') !== false, "Desktop navigation container <nav class=\"desktop-nav\"> present");
assert_test(strpos($headerContent, 'dashboard.php') !== false, "Dashboard link present in desktop nav");
assert_test(strpos($headerContent, 'my-pdfs.php') !== false, "My Documents link present in desktop nav");

// 1.4 Study Tools Dropdown & 5 Sub-tools
assert_test(strpos($headerContent, 'id="studyToolsBtn"') !== false, "Study Tools dropdown trigger button present");
assert_test(strpos($headerContent, 'id="studyToolsMenu"') !== false, "Study Tools dropdown menu present");
assert_test(strpos($headerContent, 'exam-preparation.php') !== false, "Study Tools contains Exam Preparation link");
assert_test(strpos($headerContent, 'mcqs-practice.php') !== false, "Study Tools contains MCQ Practice link");
assert_test(strpos($headerContent, 'Short Notes') !== false, "Study Tools contains Short Notes link");
assert_test(strpos($headerContent, 'Important Questions') !== false, "Study Tools contains Important Questions link");
assert_test(strpos($headerContent, 'ask-from-pdf.php') !== false, "Study Tools contains Ask Your Document link");

// 1.5 Navbar Right Actions
assert_test(strpos($headerContent, 'id="headerSearchInput"') !== false, "Compact header search input present");
assert_test(strpos($headerContent, 'id="themeToggleBtn"') !== false, "Theme toggle button present in navbar");
assert_test(strpos($headerContent, 'id="themeToggleIcon"') !== false, "Theme toggle icon span present");
assert_test(strpos($headerContent, 'id="themeToggleText"') !== false, "Theme toggle text label present");
assert_test(strpos($headerContent, 'id="notificationBtn"') !== false, "Notification bell button present");
assert_test(strpos($headerContent, 'id="userProfileBtn"') !== false, "User profile button present");
assert_test(strpos($headerContent, 'id="userProfileMenu"') !== false, "User profile dropdown menu present");
assert_test(strpos($headerContent, 'logout.php') !== false, "Sign Out / Logout link present in profile menu");
assert_test(strpos($headerContent, 'class="nav-upload-btn"') !== false, "Prominent '+ Upload Document' button present on desktop");
assert_test(strpos($headerContent, 'upload-pdf.php') !== false, "Upload button links to upload-pdf.php");

// 1.6 Mobile Controls & Drawer
assert_test(strpos($headerContent, 'class="mobile-compact-upload-btn"') !== false, "Mobile compact '+' upload button present");
assert_test(strpos($headerContent, 'id="mobileMenuBtn"') !== false, "Mobile hamburger menu button present");
assert_test(strpos($headerContent, 'id="mobileNavDrawer"') !== false, "Mobile navigation drawer (#mobileNavDrawer) present");
assert_test(strpos($headerContent, 'id="mobileNavBackdrop"') !== false, "Mobile navigation backdrop (#mobileNavBackdrop) present");
assert_test(strpos($headerContent, 'id="mobileSearchInput"') !== false, "Mobile drawer search input present");
assert_test(strpos($headerContent, 'id="mobileThemeToggleBtn"') !== false, "Mobile drawer theme switcher button present");

// 1.7 Main Layout Wrapper
assert_test(strpos($headerContent, 'class="app-container"') !== false, "Root app-container present");
assert_test(strpos($headerContent, 'class="main-wrapper"') !== false, "Main-wrapper container present");
assert_test(strpos($headerContent, 'class="content-body"') !== false, "Content-body container present");

// Group 2: assets/css/style.css Verification
echo "\n--- Group 2: CSS Stylesheet & Dual Themes (assets/css/style.css) ---\n";
$cssContent = file_get_contents($cssPath);
assert_test($cssContent !== false && strlen($cssContent) > 0, "CSS file exists and is readable");

// 2.1 Light Theme Custom Properties
assert_test(strpos($cssContent, '--bg-app: #f8fafc;') !== false, "Light theme background is #f8fafc");
assert_test(strpos($cssContent, '--text-title: #0f172a;') !== false, "Light theme heading text is #0f172a");
assert_test(strpos($cssContent, '--primary-600: #4f46e5;') !== false, "Light theme primary accent is #4f46e5");
assert_test(strpos($cssContent, '--navbar-bg: #ffffff;') !== false, "Light theme navbar background is #ffffff");
assert_test(strpos($cssContent, '--navbar-active-bg: #eef2ff;') !== false, "Light theme active nav pill is #eef2ff");

// 2.2 Dark Theme Custom Properties
assert_test(strpos($cssContent, '--bg-app: #0d1117;') !== false, "Dark theme background is #0d1117");
assert_test(strpos($cssContent, '--bg-card: #161b26;') !== false, "Dark theme card/surface is #161b26");
assert_test(strpos($cssContent, '--primary-600: #7c3aed;') !== false, "Dark theme primary accent is #7c3aed");
assert_test(strpos($cssContent, '--navbar-bg: #161b26;') !== false, "Dark theme navbar background is #161b26");
assert_test(strpos($cssContent, '--navbar-active-bg: rgba(139, 92, 246, 0.16);') !== false, "Dark theme active nav pill has subtle violet tint");

// 2.3 Layout Reset
assert_test(strpos($cssContent, '.sidebar {') !== false && strpos($cssContent, 'display: none !important;') !== false, "Sidebar is hidden with display: none !important");
assert_test(strpos($cssContent, '.main-wrapper {') !== false && strpos($cssContent, 'margin-left: 0 !important;') !== false, "Main-wrapper has margin-left: 0 !important");
assert_test(strpos($cssContent, '.app-container {') !== false && strpos($cssContent, 'flex-direction: column;') !== false, "App-container has flex-direction: column");
assert_test(strpos($cssContent, 'overflow-x: hidden;') !== false, "App-container prevents horizontal page scroll");

// 2.4 Navbar Component Styles
assert_test(strpos($cssContent, '.app-navbar {') !== false, "Styles for .app-navbar defined");
assert_test(strpos($cssContent, '.navbar-container {') !== false, "Styles for .navbar-container defined");
assert_test(strpos($cssContent, '.desktop-nav {') !== false, "Styles for .desktop-nav defined");
assert_test(strpos($cssContent, '.nav-item {') !== false, "Styles for .nav-item pill navigation defined");
assert_test(strpos($cssContent, '.nav-dropdown-menu {') !== false, "Styles for .nav-dropdown-menu defined");
assert_test(strpos($cssContent, '.nav-upload-btn {') !== false, "Styles for desktop .nav-upload-btn defined");

// 2.5 Mobile Navigation Styles
assert_test(strpos($cssContent, '.mobile-compact-upload-btn {') !== false, "Styles for .mobile-compact-upload-btn defined");
assert_test(strpos($cssContent, '.mobile-hamburger-btn {') !== false, "Styles for .mobile-hamburger-btn defined");
assert_test(strpos($cssContent, '.mobile-nav-drawer {') !== false, "Styles for .mobile-nav-drawer defined");
assert_test(strpos($cssContent, '.mobile-nav-backdrop {') !== false, "Styles for .mobile-nav-backdrop defined");

// 2.6 Responsive Breakpoints
assert_test(strpos($cssContent, '@media (max-width: 1200px)') !== false, "Breakpoint for 1200px tablet landscape defined");
assert_test(strpos($cssContent, '@media (max-width: 900px)') !== false, "Breakpoint for 900px tablet portrait/mobile navigation defined");
assert_test(strpos($cssContent, '@media (max-width: 768px)') !== false, "Breakpoint for 768px mobile screen defined");

// Group 3: assets/js/app.js Verification
echo "\n--- Group 3: Client JavaScript & Interactions (assets/js/app.js) ---\n";
$jsContent = file_get_contents($jsPath);
assert_test($jsContent !== false && strlen($jsContent) > 0, "JavaScript file exists and is readable");

// 3.1 Theme Switching & Sync
assert_test(strpos($jsContent, 'applyTheme(theme)') !== false, "Function applyTheme exists");
assert_test(strpos($jsContent, 'localStorage.setItem(\'theme\', theme)') !== false, "applyTheme stores theme preference in localStorage");
assert_test(strpos($jsContent, 'mobileThemeToggleBtn') !== false, "applyTheme synchronizes mobile theme button");
assert_test(strpos($jsContent, 'themeToggleBtn') !== false, "applyTheme synchronizes desktop theme button");
assert_test(strpos($jsContent, 'initThemeSwitcher()') !== false, "Function initThemeSwitcher binds toggle events");

// 3.2 Navigation Dropdowns & Mobile Drawer
assert_test(strpos($jsContent, 'studyToolsBtn') !== false, "Study Tools dropdown click handler bound");
assert_test(strpos($jsContent, 'userProfileBtn') !== false, "User Profile dropdown click handler bound");
assert_test(strpos($jsContent, 'mobileMenuBtn') !== false, "Mobile hamburger menu click handler bound");
assert_test(strpos($jsContent, 'mobileNavBackdrop') !== false, "Mobile backdrop dismiss handler bound");
assert_test(strpos($jsContent, 'Escape') !== false, "Keyboard Escape dismiss handler present");
assert_test(strpos($jsContent, 'my-pdfs.php?search=') !== false, "Header and mobile search inputs redirect to my-pdfs.php?search=");

// Group 4: PHP Page Renders with Mock Session
echo "\n--- Group 4: Page Rendering & Integration (Headless Evaluation) ---\n";
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['full_name'] = 'Kartik Malav';
$_SESSION['user_email'] = 'kartikmalav431@gmail.com';
$_SESSION['user_course'] = 'BCA 3rd Sem';

// Test dashboard.php output
ob_start();
try {
    include 'c:/xampp/htdocs/ai_notes_summarizer/dashboard.php';
    $dashboardHtml = ob_get_clean();
    assert_test(strpos($dashboardHtml, 'app-navbar') !== false, "dashboard.php renders top navbar");
    assert_test(strpos($dashboardHtml, 'AI StudyLab') !== false, "dashboard.php renders brand title");
    assert_test(strpos($dashboardHtml, 'Welcome back, Kartik') !== false, "dashboard.php renders welcome banner");
    assert_test(strpos($dashboardHtml, 'app-footer') !== false, "dashboard.php renders shared footer");
} catch (Throwable $t) {
    ob_end_clean();
    assert_test(false, "dashboard.php rendered with exception: " . $t->getMessage());
}

// Test my-pdfs.php output
ob_start();
try {
    include 'c:/xampp/htdocs/ai_notes_summarizer/my-pdfs.php';
    $myPdfsHtml = ob_get_clean();
    assert_test(strpos($myPdfsHtml, 'app-navbar') !== false, "my-pdfs.php renders top navbar");
    assert_test(strpos($myPdfsHtml, 'My Document Library') !== false, "my-pdfs.php renders library title");
} catch (Throwable $t) {
    ob_end_clean();
    assert_test(false, "my-pdfs.php rendered with exception: " . $t->getMessage());
}

// Test exam-preparation.php output
ob_start();
try {
    include 'c:/xampp/htdocs/ai_notes_summarizer/exam-preparation.php';
    $examPrepHtml = ob_get_clean();
    assert_test(strpos($examPrepHtml, 'app-navbar') !== false, "exam-preparation.php renders top navbar");
    assert_test(strpos($examPrepHtml, 'Exam Preparation') !== false, "exam-preparation.php renders exam prep title");
} catch (Throwable $t) {
    ob_end_clean();
    assert_test(false, "exam-preparation.php rendered with exception: " . $t->getMessage());
}

// Test mcqs-practice.php output
ob_start();
try {
    include 'c:/xampp/htdocs/ai_notes_summarizer/mcqs-practice.php';
    $mcqHtml = ob_get_clean();
    assert_test(strpos($mcqHtml, 'app-navbar') !== false, "mcqs-practice.php renders top navbar");
    assert_test(strpos($mcqHtml, 'MCQ Practice') !== false, "mcqs-practice.php renders MCQ practice title");
} catch (Throwable $t) {
    ob_end_clean();
    assert_test(false, "mcqs-practice.php rendered with exception: " . $t->getMessage());
}

// Test ask-from-pdf.php output
ob_start();
try {
    include 'c:/xampp/htdocs/ai_notes_summarizer/ask-from-pdf.php';
    $askHtml = ob_get_clean();
    assert_test(strpos($askHtml, 'app-navbar') !== false, "ask-from-pdf.php renders top navbar");
    assert_test(strpos($askHtml, 'Ask Your Document') !== false, "ask-from-pdf.php renders Ask Your Document title");
} catch (Throwable $t) {
    ob_end_clean();
    assert_test(false, "ask-from-pdf.php rendered with exception: " . $t->getMessage());
}

echo "\n=================================================================\n";
echo "STEP 1 SUMMARY: Total: $testCount | Passed: $passCount | Failed: $failCount\n";
echo "=================================================================\n";

if ($failCount === 0) {
    echo "SUCCESS: ALL STEP 1 TESTS PASSED PERFECTLY!\n";
    exit(0);
} else {
    echo "FAILURE: Some tests failed.\n";
    exit(1);
}
