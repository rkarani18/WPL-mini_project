<?php
// logout.php
session_start();

// ── Clear the Remember Me cookie ────────────────────────────────────────────
if (isset($_COOKIE['remember_token'])) {
    // Invalidate the token in the database
    if (isset($_SESSION['user_id'])) {
        require 'includes/db.php';
        $null_token  = null;
        $null_expiry = null;
        $upd = $conn->prepare(
            "UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE id = ?"
        );
        $upd->bind_param("i", $_SESSION['user_id']);
        $upd->execute();
    }
    // Delete cookie by setting expiry in the past
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    unset($_COOKIE['remember_token']);
}

// ── Destroy session ──────────────────────────────────────────────────────────
$_SESSION = [];                         // clear all session variables
if (ini_get("session.use_cookies")) {  // also delete the session cookie
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p["path"], $p["domain"], $p["secure"], $p["httponly"]
    );
}
session_destroy();

header("Location: index.php");
exit;
?>