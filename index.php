<?php
/**
 * Application Entry Router
 * AI-Based PDF Notes Summarizer and Question Generator
 *
 * Redirects unauthenticated visitors directly to the Login Page,
 * and authenticated users to their Main Dashboard.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit();
}

header('Location: ' . url('login.php'));
exit();
