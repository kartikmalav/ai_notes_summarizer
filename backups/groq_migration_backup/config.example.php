<?php
/**
 * Safe configuration template. The application reads secrets from environment
 * variables; set GEMINI_API_KEY in Apache/PHP and do not paste it into this file.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'AI PDF Notes Summarizer');
define('APP_SUBTITLE', 'Smart AI Study Assistant for BCA Students');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/ai_notes_summarizer/');
define('UPLOAD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('MAX_FILE_SIZE', 50 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'docx', 'txt', 'csv', 'xlsx', 'pptx']);

$geminiApiKey = getenv('GEMINI_API_KEY');
define('GEMINI_API_KEY', $geminiApiKey === false ? '' : trim($geminiApiKey));

function get_format_icon($extension) {
    $ext = strtolower(trim($extension));
    switch ($ext) {
        case 'docx': return '📝';
        case 'txt':  return '📑';
        case 'csv':  return '📊';
        case 'xlsx': return '📈';
        case 'pptx': return '📊';
        case 'pdf': default: return '📄';
    }
}

function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';

        echo '<div class="alert alert-' . htmlspecialchars($type) . ' flash-alert" id="flashAlert">';
        echo '<div class="alert-icon">';
        if ($type === 'success') echo '✓';
        elseif ($type === 'danger') echo '⚠';
        else echo 'ℹ';
        echo '</div>';
        echo '<div class="alert-body">' . htmlspecialchars($msg) . '</div>';
        echo '<button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>';
        echo '</div>';

        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}