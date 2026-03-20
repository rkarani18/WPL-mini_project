<?php
// admin/login.php
session_start();
require '../includes/db.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $pass     = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($id, $uname, $hashed);
    $stmt->fetch();

    if ($id && password_verify($pass, $hashed)) {
        $_SESSION['admin_id']   = $id;
        $_SESSION['admin_name'] = $uname;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | RKTMed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #1a202c; display: flex; height: 100vh; align-items: center; justify-content: center; }
        .card { background: white; padding: 40px; border-radius: 16px; width: 380px; }
        h2 { color: #00796b; margin-bottom: 4px; }
        h2 span { color: #ffb300; }
        p { color: #666; font-size: 13px; margin-bottom: 24px; }
        input { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; margin-bottom: 14px; }
        button { width: 100%; background: #00796b; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; }
        .alert { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h2>RKT<span>Med</span></h2>
    <p>Admin Portal — Please login to continue</p>
    <?php if ($error): ?><div class="alert"><?= $error ?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Admin Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login to Admin Panel</button>
    </form>
</div>
</body>
</html>