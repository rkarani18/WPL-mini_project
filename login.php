<?php
// login.php
session_start();

// ── Remember Me: auto-login from cookie ─────────────────────────────────────
// If user visits login page but already has a valid remember-me cookie,
// restore their session and redirect to home.
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    require 'includes/db.php';
    $token = $_COOKIE['remember_token'];

    // Tokens are stored hashed in the DB for security
    $hashed_token = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT id, full_name FROM users WHERE remember_token = ? AND token_expiry > NOW()"
    );
    $stmt->bind_param("s", $hashed_token);
   $stmt->execute();
$stmt->bind_result($uid, $uname);
$stmt->fetch();
$stmt->close(); // ✅ CRITICAL FIX

if ($uid) {
    $_SESSION['user_id']   = $uid;
    $_SESSION['user_name'] = $uname;

    $new_token  = bin2hex(random_bytes(32));
    $new_hashed = hash('sha256', $new_token);
    $expiry     = date('Y-m-d H:i:s', strtotime('+30 days'));

   
    $upd = $conn->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
    $upd->bind_param("ssi", $new_hashed, $expiry, $uid);
    $upd->execute();
    $upd->close(); 

    setcookie('remember_token', $new_token, time() + (30 * 24 * 3600), '/', '', false, true);

    header("Location: index.php");
    exit;
}
}

require_once 'includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($pass) < 1) {
        $error = "Password is required.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($id, $full_name, $hashed);
        $stmt->fetch();
        $stmt->fetch();
$stmt->close();  

        if ($id && password_verify($pass, $hashed)) {
            // ── Session variables ────────────────────────────────────────────
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id']      = $id;
            $_SESSION['user_name']    = $full_name;
            $_SESSION['login_time']   = time();        // track when session started
            $_SESSION['ip_address']   = $_SERVER['REMOTE_ADDR']; // basic session binding
            $_SESSION['user_agent']   = $_SERVER['HTTP_USER_AGENT'];

            // ── Remember Me Cookie ───────────────────────────────────────────
            if ($remember) {
                $token        = bin2hex(random_bytes(32));        // cryptographically secure
                $hashed_token = hash('sha256', $token);
                $expiry       = date('Y-m-d H:i:s', strtotime('+30 days'));

                // Store hashed token in DB (never store raw token)
                $upd = $conn->prepare(
                    "UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?"
                );
                $upd->bind_param("ssi", $hashed_token, $expiry, $id);
                $upd->execute();

                // Set cookie: HttpOnly flag prevents JS access (XSS mitigation)
                setcookie(
                    'remember_token',        // name
                    $token,                  // raw token (only raw token sent to client)
                    time() + (30 * 24 * 3600), // expires in 30 days
                    '/',                     // path
                    '',                      // domain
                    false,                   // secure (set true in production with HTTPS)
                    true                     // HttpOnly
                );
            }

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | QuickMed</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <h2>Quick<span>Med</span> — Login</h2>

        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" id="login-form" novalidate>
            <div class="field-wrap">
                <input type="email" name="email" id="login-email" placeholder="Email Address" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <span class="field-error" id="err-login-email"></span>
            </div>
            <div class="field-wrap">
                <input type="password" name="password" id="login-pass" placeholder="Password" required>
                <span class="field-error" id="err-login-pass"></span>
            </div>

            <!-- Remember Me checkbox -->
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-size:14px;color:#4a5568;">
                <input type="checkbox" name="remember_me" id="remember_me" value="1"
                       style="width:16px;height:16px;accent-color:#00796b;cursor:pointer;">
                <label for="remember_me" style="cursor:pointer;">Remember me for 30 days</label>
            </div>

            <button type="submit">Login</button>
        </form>

        <p>New here? <a href="register.php">Create an account</a></p>
    </div>
</div>
<style>
.field-wrap { margin-bottom: 14px; }
.field-wrap input { margin-bottom: 2px; }
.field-error { display: block; font-size: 12px; color: #dc2626; min-height: 16px; padding-left: 2px; }
</style>
<script>
document.getElementById('login-form').addEventListener('submit', function (e) {
    let valid = true;
    function showErr(id, msg) { document.getElementById(id).textContent = msg; if (msg) valid = false; }
    const email = document.getElementById('login-email').value.trim();
    const pass  = document.getElementById('login-pass').value;
    showErr('err-login-email', !email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? 'Enter a valid email address.' : '');
    showErr('err-login-pass',  !pass ? 'Password is required.' : '');
    if (!valid) e.preventDefault();
});
</script>
</body>
</html>