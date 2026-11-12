<?php
/**
 * User Login Page
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Prevent logged in users from visiting login page
require_guest();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, course FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Password matches, regenerate session ID for security
                session_regenerate_id(true);

                $_SESSION['user_id']     = $user['id'];
                $_SESSION['full_name']   = $user['full_name'];
                $_SESSION['user_email']  = $user['email'];
                $_SESSION['user_course'] = $user['course'];

                set_flash_message("Welcome back, " . $user['full_name'] . "!", "success");
                header('Location: ' . url('dashboard.php'));
                exit();
            } else {
                $error = 'Invalid email address or password. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'Database error during authentication. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login | <?= APP_NAME ?></title>
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
                <div class="auth-logo">⚡</div>
                <h1 class="auth-title">Student Login</h1>
                <p class="auth-sub">Sign in to access your AI study notes and quizzes</p>
            </div>

            <?php display_flash_message(); ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <div class="alert-icon">⚠</div>
                    <div class="alert-body"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= url('login.php') ?>" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="student@example.com" value="<?= htmlspecialchars($email) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; margin-top: 8px;">
                    Log In &rarr;
                </button>
            </form>

            <div class="auth-footer">
                Don't have an account yet? <a href="<?= url('signup.php') ?>"><strong>Register here</strong></a>
            </div>
        </div>
    </div>
    <script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
