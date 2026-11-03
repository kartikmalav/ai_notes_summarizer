<?php
/**
 * Verification Test for Dual Theme Support (Light Mode & Dark Mode)
 */

$totalTests = 0;
$passedTests = 0;

function assertThemeTest($description, $condition) {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "=== 1. Testing CSS Dual Theme Architecture in assets/css/style.css ===\n";
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');

assertThemeTest("CSS contains :root definition", strpos($cssContent, ':root') !== false);
assertThemeTest("CSS contains [data-theme=\"light\"] definition", strpos($cssContent, '[data-theme="light"]') !== false);
assertThemeTest("CSS contains [data-theme=\"dark\"] definition", strpos($cssContent, '[data-theme="dark"]') !== false);

// Check Light Mode color tokens (soft white / lavender, pastel cards)
assertThemeTest("Light Mode background #f2f4fb defined", strpos($cssContent, '#f2f4fb') !== false);
assertThemeTest("Light Mode pastel docs card defined", strpos($cssContent, '--stat-docs-bg: linear-gradient(135deg, #f0edff') !== false);
assertThemeTest("Light Mode pastel notes card defined", strpos($cssContent, '--stat-notes-bg: linear-gradient(135deg, #e7f6fe') !== false);
assertThemeTest("Light Mode pastel questions card defined", strpos($cssContent, '--stat-questions-bg: linear-gradient(135deg, #ffeff3') !== false);
assertThemeTest("Light Mode pastel mcqs card defined", strpos($cssContent, '--stat-mcqs-bg: linear-gradient(135deg, #e7faf1') !== false);

// Check Dark Mode color tokens (dark navy & slate-blue backgrounds, glowing accents)
assertThemeTest("Dark Mode background #0c101a defined", strpos($cssContent, '#0c101a') !== false);
assertThemeTest("Dark Mode card #151c2c defined", strpos($cssContent, '#151c2c') !== false);
assertThemeTest("Dark Mode glowing docs card defined", strpos($cssContent, '--stat-docs-bg: linear-gradient(135deg, #1f1836') !== false);
assertThemeTest("Dark Mode glowing notes card defined", strpos($cssContent, '--stat-notes-bg: linear-gradient(135deg, #102434') !== false);
assertThemeTest("Dark Mode glowing questions card defined", strpos($cssContent, '--stat-questions-bg: linear-gradient(135deg, #2c1626') !== false);
assertThemeTest("Dark Mode glowing mcqs card defined", strpos($cssContent, '--stat-mcqs-bg: linear-gradient(135deg, #102824') !== false);

// Check Component Classes
assertThemeTest("Theme switcher toggle button CSS defined", strpos($cssContent, '.theme-toggle-btn') !== false);
assertThemeTest("Modern stats grid CSS defined", strpos($cssContent, '.stats-grid-modern') !== false);
assertThemeTest("Assistant banner CSS defined", strpos($cssContent, '.assistant-banner') !== false);
assertThemeTest("Quick actions CSS defined", strpos($cssContent, '.quick-actions-card') !== false);
assertThemeTest("Recent documents modern grid CSS defined", strpos($cssContent, '.recent-docs-grid') !== false);

echo "\n=== 2. Testing Anti-Flash Script & Header Components in includes/header.php ===\n";
$headerContent = file_get_contents(__DIR__ . '/../includes/header.php');

assertThemeTest("Header contains synchronous Anti-Flash theme script", strpos($headerContent, 'Anti-Flash Synchronous Theme Initializer') !== false);
assertThemeTest("Anti-Flash script reads localStorage 'theme'", strpos($headerContent, "localStorage.getItem('theme')") !== false);
assertThemeTest("Anti-Flash script sets data-theme on documentElement", strpos($headerContent, "document.documentElement.setAttribute('data-theme'") !== false);
assertThemeTest("Header contains themeToggleBtn", strpos($headerContent, 'id="themeToggleBtn"') !== false);
assertThemeTest("Header contains themeToggleIcon", strpos($headerContent, 'id="themeToggleIcon"') !== false);
assertThemeTest("Header contains themeToggleText", strpos($headerContent, 'id="themeToggleText"') !== false);
assertThemeTest("Header contains search box input", strpos($headerContent, 'id="headerSearchInput"') !== false);
assertThemeTest("Header contains notification bell with alert dot", strpos($headerContent, 'header-notification-btn') !== false);

echo "\n=== 3. Testing Theme Management in assets/js/app.js ===\n";
$jsContent = file_get_contents(__DIR__ . '/../assets/js/app.js');

assertThemeTest("app.js contains initThemeSwitcher()", strpos($jsContent, 'initThemeSwitcher') !== false);
assertThemeTest("app.js checks and saves to localStorage", strpos($jsContent, "localStorage.setItem('theme', theme)") !== false);
assertThemeTest("app.js updates documentElement data-theme attribute", strpos($jsContent, "document.documentElement.setAttribute('data-theme', theme)") !== false);
assertThemeTest("app.js toggles between light and dark", strpos($jsContent, "(current === 'dark') ? 'light' : 'dark'") !== false);
assertThemeTest("app.js updates toggle button icon and text", strpos($jsContent, "icon.textContent = (theme === 'dark') ? '🌙' : '☀️'") !== false);
assertThemeTest("app.js handles search input enter key", strpos($jsContent, 'headerSearchInput') !== false);

echo "\n=== 4. Testing Dashboard Layout in dashboard.php ===\n";
$dashboardContent = file_get_contents(__DIR__ . '/../dashboard.php');

assertThemeTest("Dashboard calculates all 4 metrics (PDFs, Notes, Questions, MCQs)", 
    strpos($dashboardContent, '$totalPDFs') !== false &&
    strpos($dashboardContent, '$totalNotes') !== false &&
    strpos($dashboardContent, '$totalQuestions') !== false &&
    strpos($dashboardContent, '$totalMCQs') !== false
);
assertThemeTest("Dashboard contains stats-grid-modern with 4 stat cards", strpos($dashboardContent, 'stats-grid-modern') !== false);
assertThemeTest("Dashboard contains stat-docs card", strpos($dashboardContent, 'stat-docs') !== false);
assertThemeTest("Dashboard contains stat-notes card", strpos($dashboardContent, 'stat-notes') !== false);
assertThemeTest("Dashboard contains stat-questions card", strpos($dashboardContent, 'stat-questions') !== false);
assertThemeTest("Dashboard contains stat-mcqs card", strpos($dashboardContent, 'stat-mcqs') !== false);
assertThemeTest("Dashboard contains Your AI Study Assistant banner", strpos($dashboardContent, 'assistant-banner') !== false);
assertThemeTest("Dashboard contains Quick Actions card with 4 rows", strpos($dashboardContent, 'quick-actions-card') !== false);
assertThemeTest("Dashboard contains Recent Documents modern grid", strpos($dashboardContent, 'recent-docs-grid') !== false);

echo "\n=== 5. Testing Anti-Flash Script in Auth Pages ===\n";
$loginContent = file_get_contents(__DIR__ . '/../login.php');
$signupContent = file_get_contents(__DIR__ . '/../signup.php');

assertThemeTest("login.php contains Anti-Flash script", strpos($loginContent, 'Anti-Flash Synchronous Theme Initializer') !== false);
assertThemeTest("login.php contains themeToggleBtn", strpos($loginContent, 'id="themeToggleBtn"') !== false);
assertThemeTest("signup.php contains Anti-Flash script", strpos($signupContent, 'Anti-Flash Synchronous Theme Initializer') !== false);
assertThemeTest("signup.php contains themeToggleBtn", strpos($signupContent, 'id="themeToggleBtn"') !== false);

echo "\n=== 6. Testing Sidebar Alignment in includes/sidebar.php ===\n";
$sidebarContent = file_get_contents(__DIR__ . '/../includes/sidebar.php');

assertThemeTest("Sidebar contains AI StudyLab header", strpos($sidebarContent, 'AI StudyLab') !== false);
assertThemeTest("Sidebar contains Smart Notes subtitle", strpos($sidebarContent, 'Smart Notes. Better Learning.') !== false);
assertThemeTest("Sidebar contains Exam Preparation link", strpos($sidebarContent, 'exam-preparation.php') !== false);
assertThemeTest("Sidebar contains MCQ Practice link", strpos($sidebarContent, 'mcqs-practice.php') !== false);
assertThemeTest("Sidebar contains Short Notes link", strpos($sidebarContent, 'Short Notes') !== false);
assertThemeTest("Sidebar contains Important Questions link", strpos($sidebarContent, 'Important Questions') !== false);
assertThemeTest("Sidebar contains Ask from PDF link", strpos($sidebarContent, 'ask-from-pdf.php') !== false);

echo "\n=== Test Results Summary ===\n";
echo "Total Tests: {$totalTests} | Passed: {$passedTests} | Failed: " . ($totalTests - $passedTests) . "\n";

if ($totalTests === $passedTests) {
    echo "ALL THEME TESTS PASSED SUCCESSFULLY! \n";
    exit(0);
} else {
    echo "SOME THEME TESTS FAILED!\n";
    exit(1);
}
