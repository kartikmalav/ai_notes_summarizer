<?php
/**
 * User Registration / Signup Page
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Prevent logged in users from visiting signup
require_guest();

$error = '';
$success = '';
$fullName = '';
$email = '';
$course = 'BCA 3rd Sem';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $course   = trim($_POST['course'] ?? 'BCA 3rd Sem');

    // Basic Validations
    if (empty($fullName) || empty($email) || empty($password) || empty($course)) {
        $error = 'All fields are required. Please fill out the entire form.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email already registered
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$email]);

            if ($checkStmt->fetch()) {
                $error = 'This email address is already registered. Please login or use a different email.';
            } else {
                // Securely hash password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user into MySQL
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, password_hash, course, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([$fullName, $email, $passwordHash, $course]);

                set_flash_message('Registration successful! You can now log in with your credentials.', 'success');
                header('Location: ' . url('login.php'));
                exit();
            }
        } catch (PDOException $e) {
            error_log("Signup Error: " . $e->getMessage());
            $error = 'Database error during registration. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | <?= APP_NAME ?></title>
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
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-theme-switcher">
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle Theme" title="Switch Theme (Light / Dark)">
                <span class="theme-toggle-icon" id="themeToggleIcon">☀️</span>
                <span class="theme-toggle-text" id="themeToggleText">Light</span>
            </button>
        </div>
        <div class="auth-box">
            <div class="auth-header">
                <div class="auth-logo">🎓</div>
                <h1 class="auth-title">Create Account</h1>
                <p class="auth-sub">Register to access AI Notes Summarizer & Quiz Generator</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <div class="alert-icon">⚠</div>
                    <div class="alert-body"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= url('signup.php') ?>" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Rahul Sharma" value="<?= htmlspecialchars($fullName) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="student@example.com" value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="form-group">
                    <label for="course" class="form-label">Course / Semester</label>
                    <select id="course" name="course" class="form-control" required>
                        <option value="BCA 3rd Sem" <?= $course === 'BCA 3rd Sem' ? 'selected' : '' ?>>BCA 3rd Semester</option>
                        <option value="BCA 1st Sem" <?= $course === 'BCA 1st Sem' ? 'selected' : '' ?>>BCA 1st Semester</option>
                        <option value="BCA 2nd Sem" <?= $course === 'BCA 2nd Sem' ? 'selected' : '' ?>>BCA 2nd Semester</option>
                        <option value="BCA 4th Sem" <?= $course === 'BCA 4th Sem' ? 'selected' : '' ?>>BCA 4th Semester</option>
                        <option value="BCA 5th Sem" <?= $course === 'BCA 5th Sem' ? 'selected' : '' ?>>BCA 5th Semester</option>
                        <option value="BCA 6th Sem" <?= $course === 'BCA 6th Sem' ? 'selected' : '' ?>>BCA 6th Semester</option>
                        <option value="BSc CS" <?= $course === 'BSc CS' ? 'selected' : '' ?>>BSc Computer Science</option>
                        <option value="Other" <?= $course === 'Other' ? 'selected' : '' ?>>Other Course</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; margin-top: 8px;">
                    Register Account
                </button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="<?= url('login.php') ?>"><strong>Log in here</strong></a>
            </div>
        </div>
    </div>
    <script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
