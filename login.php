<?php
// login.php
session_start();
require 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

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

        if ($id && password_verify($pass, $hashed)) {
            $_SESSION['user_id']   = $id;
            $_SESSION['user_name'] = $full_name;
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

        <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>

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