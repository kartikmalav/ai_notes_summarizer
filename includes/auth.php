<?php
/**
 * Authentication Helper Functions
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Check if a user is currently logged in
 * 
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforce authentication for protected pages.
 * Redirects to login page if user is not logged in.
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('Please log in to access this page.', 'danger');
        header('Location: ' . url('login.php'));
        exit();
    }
}

/**
 * Enforce guest mode for login/signup pages.
 * Redirects to dashboard if user is already logged in.
 */
function require_guest() {
    if (is_logged_in()) {
        header('Location: ' . url('dashboard.php'));
        exit();
    }
}

/**
 * Get current logged in user details from session or database
 * 
 * @return array|null
 */
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }

    // Return cached session info if minimal, or fetch fresh user data
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT id, full_name, email, course, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Failed to fetch user details: " . $e->getMessage());
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? 'User',
            'email' => $_SESSION['user_email'] ?? '',
            'course' => $_SESSION['user_course'] ?? 'BCA 3rd Sem'
        ];
    }
}

/**
 * Log out current user and clear session
 */
function logout_user() {
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Generate or get existing CSRF token
 * 
 * @return string
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate submitted CSRF token
 * 
 * @param string|null $token
 * @return bool
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
