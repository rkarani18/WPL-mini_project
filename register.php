<?php
// register.php
session_start();
require 'includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['full_name']);
    $email   = trim($_POST['email']);
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $pass    = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    // --- Backend Validations ---
    if (strlen($name) < 2 || strlen($name) > 60) {
        $error = "Full name must be between 2 and 60 characters.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
        $error = "Full name can only contain letters and spaces.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!empty($phone) && !preg_match('/^[6-9]\d{9}$/', $phone)) {
        $error = "Phone must be a valid 10-digit Indian mobile number.";
    } elseif (strlen($pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($pass !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (empty($address) || strlen($address) < 10) {
        $error = "Please enter a valid delivery address (at least 10 characters).";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email already registered. Please login.";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (full_name, email, phone, address, password) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("sssss", $name, $email, $phone, $address, $hashed);
            if ($ins->execute()) {
                $success = "Account created! <a href='login.php'>Login here</a>";
            } else {
                $error = "Something went wrong. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | QuickMed</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <h2>Quick<span>Med</span> — Register</h2>

        <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>

        <form method="POST" id="reg-form" novalidate>
            <div class="field-wrap">
                <input type="text" name="full_name" id="full_name" placeholder="Full Name" required maxlength="60"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                <span class="field-error" id="err-name"></span>
            </div>
            <div class="field-wrap">
                <input type="email" name="email" id="email" placeholder="Email Address" required maxlength="100"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <span class="field-error" id="err-email"></span>
            </div>
            <div class="field-wrap">
                <input type="tel" name="phone" id="phone" placeholder="Phone Number (10 digits)" maxlength="10"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                <span class="field-error" id="err-phone"></span>
            </div>
            <div class="field-wrap">
                <textarea name="address" id="address" placeholder="Delivery Address (min 10 chars)" rows="2" maxlength="300"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                <span class="field-error" id="err-address"></span>
            </div>
            <div class="field-wrap">
                <input type="password" name="password" id="password" placeholder="Password (min 6 chars)" required>
                <span class="field-error" id="err-pass"></span>
            </div>
            <div class="field-wrap">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                <span class="field-error" id="err-confirm"></span>
            </div>
            <button type="submit">Create Account</button>
        </form>

        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</div>
<style>
.field-wrap { margin-bottom: 14px; }
.field-wrap input, .field-wrap textarea { margin-bottom: 2px; }
.field-error { display: block; font-size: 12px; color: #dc2626; min-height: 16px; padding-left: 2px; }
</style>
<script>
// Allow only digits for phone field
document.getElementById('phone').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

document.getElementById('reg-form').addEventListener('submit', function (e) {
    let valid = true;

    function showErr(id, msg) {
        document.getElementById(id).textContent = msg;
        if (msg) valid = false;
    }

    const name    = document.getElementById('full_name').value.trim();
    const email   = document.getElementById('email').value.trim();
    const phone   = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    const pass    = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;

    showErr('err-name',    !name || name.length < 2                           ? 'Name must be at least 2 characters.'           : '');
    showErr('err-name',    name && !/^[a-zA-Z\s]+$/.test(name)               ? 'Name can only contain letters and spaces.'       : document.getElementById('err-name').textContent);
    showErr('err-email',   !email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? 'Enter a valid email address.'                : '');
    showErr('err-phone',   phone && !/^[6-9]\d{9}$/.test(phone)              ? 'Enter a valid 10-digit Indian mobile number.'    : '');
    showErr('err-address', address.length > 0 && address.length < 10         ? 'Address must be at least 10 characters.'        : '');
    showErr('err-pass',    pass.length < 6                                    ? 'Password must be at least 6 characters.'        : '');
    showErr('err-confirm', pass !== confirm                                   ? 'Passwords do not match.'                        : '');

    if (!valid) e.preventDefault();
});
</script>
</body>
</html>