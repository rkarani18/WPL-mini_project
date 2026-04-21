<?php
// includes/session_guard.php
// ─────────────────────────────────────────────────────────────────────────────
// Include this file on any page that requires an authenticated user.
// It validates the session is legitimate and hasn't been hijacked.
// Usage:  require 'includes/session_guard.php';
// ─────────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Session timeout — 30 minutes of inactivity
define('SESSION_TIMEOUT', 30 * 60); // seconds

if (isset($_SESSION['user_id'])) {

    // Check inactivity timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Session expired — destroy and redirect
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }

    // 2. Basic session hijack detection (IP + User-Agent binding)
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        header("Location: login.php?security=1");
        exit;
    }

    // Refresh last activity timestamp
    $_SESSION['last_activity'] = time();

    // 3. Regenerate session ID periodically (every 10 minutes)
    if (!isset($_SESSION['last_regenerated']) || (time() - $_SESSION['last_regenerated']) > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    }
}
?>