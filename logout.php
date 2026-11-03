<?php
/**
 * Logout Action Handler
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Destroy session safely
logout_user();

// Start a fresh session for the flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

set_flash_message('You have been logged out successfully.', 'info');
header('Location: ' . url('login.php'));
exit();
