<?php
// contact.php
session_start();
require 'includes/db.php';

$success = false;
$error   = '';

// Pre-fill name/email if logged in
$prefill_name  = '';
$prefill_email = '';
$user_id       = null;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $u = $conn->query("SELECT full_name, email FROM users WHERE id = $user_id")->fetch_assoc();
    $prefill_name  = $u['full_name'] ?? '';
    $prefill_email = $u['email'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || strlen($name) < 2) {
        $error = 'Please enter your name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$subject) {
        $error = 'Please select a subject.';
    } elseif (strlen($message) < 10) {
        $error = 'Message must be at least 10 characters.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO feedback (name, email, subject, message, user_id) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssi", $name, $email, $subject, $message, $user_id);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | QuickMed</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f7fafc; color: #2d3748; }

        /* Reuse header from style.css */
        .user-nav { display: flex; align-items: center; gap: 12px; font-size: 14px; color: white; }
        .user-nav a { color: white; text-decoration: none; background: rgba(255,255,255,0.15); padding: 7px 16px; border-radius: 20px; font-weight: 600; }
        .user-nav a:hover { background: rgba(255,255,255,0.3); }

        /* Page layout */
        .page-wrap {
            max-width: 860px;
            margin: 50px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 40px;
            align-items: start;
        }

        /* Left info panel */
        .info-panel h2 { font-size: 26px; font-weight: 700; color: #1a202c; margin-bottom: 10px; }
        .info-panel h2 span { color: #00796b; }
        .info-panel .sub { font-size: 15px; color: #64748b; line-height: 1.7; margin-bottom: 32px; }

        .info-item { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 24px; }
        .info-icon {
            width: 42px; height: 42px; border-radius: 10px;
            background: #e6f4f3; display: flex; align-items: center;
            justify-content: center; font-size: 20px; flex-shrink: 0;
        }
        .info-item .info-label { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px; }
        .info-item .info-value { font-size: 14px; color: #1e293b; font-weight: 500; }

        /* Form card */
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        }
        .form-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 24px; color: #1a202c; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
        .field input, .field select, .field textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafafa;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none;
            border-color: #00796b;
            box-shadow: 0 0 0 3px rgba(0,121,107,0.1);
            background: white;
        }
        .field textarea { resize: vertical; min-height: 120px; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .submit-btn {
            width: 100%;
            background: #00796b;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .submit-btn:hover { background: #005f56; transform: translateY(-1px); }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

        /* Success state */
        .success-state { text-align: center; padding: 20px 0; }
        .success-state .tick { font-size: 52px; margin-bottom: 16px; }
        .success-state h3 { font-size: 20px; font-weight: 700; color: #166534; margin-bottom: 8px; }
        .success-state p { font-size: 14px; color: #64748b; margin-bottom: 24px; }
        .success-state a { display: inline-block; padding: 11px 24px; background: #00796b; color: white; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; }
        .success-state a:hover { background: #005f56; }

        @media (max-width: 640px) {
            .page-wrap { grid-template-columns: 1fr; margin: 24px auto; }
            .field-row { grid-template-columns: 1fr; }
            .info-panel { display: none; }
        }
    </style>
</head>
<body>

<header>
    <div class="nav-container">
        <h1 class="logo">Quick<span>Med</span></h1>
        <div class="user-nav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span>👋 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="index.php">🏠 Home</a>
                <a href="order_status.php">📦 Orders</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="index.php">🏠 Home</a>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="page-wrap">

    <!-- Left: Info panel -->
    <div class="info-panel">
        <h2>Get in <span>Touch</span></h2>
        <p class="sub">Have a question about your order, a medicine, or feedback about QuickMed? We'd love to hear from you.</p>

        <div class="info-item">
            <div class="info-icon">⏱️</div>
            <div>
                <div class="info-label">Response Time</div>
                <div class="info-value">Within 24 hours</div>
            </div>
        </div>

        <div class="info-item">
            <div class="info-icon">📦</div>
            <div>
                <div class="info-label">Order Issues</div>
                <div class="info-value">Use the subject "Order Issue" and include your order number</div>
            </div>
        </div>

        <div class="info-item">
            <div class="info-icon">📄</div>
            <div>
                <div class="info-label">Prescription Help</div>
                <div class="info-value">For prescription queries, select "Prescription" as the subject</div>
            </div>
        </div>

        <div class="info-item">
            <div class="info-icon">💬</div>
            <div>
                <div class="info-label">General Feedback</div>
                <div class="info-value">We read every message and use it to improve QuickMed</div>
            </div>
        </div>
    </div>

    <!-- Right: Form -->
    <div class="form-card">
        <?php if ($success): ?>
        <div class="success-state">
            <div class="tick">✅</div>
            <h3>Message Sent!</h3>
            <p>Thanks for reaching out. We'll get back to you at <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong> within 24 hours.</p>
            <a href="index.php">Back to Home</a>
        </div>

        <?php else: ?>
        <h3>Send us a message</h3>

        <?php if ($error): ?>
            <div class="alert error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="contact-form" novalidate>
            <div class="field-row">
                <div class="field">
                    <label>Your Name *</label>
                    <input type="text" name="name" placeholder="Full name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? $prefill_name) ?>">
                </div>
                <div class="field">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="you@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? $prefill_email) ?>">
                </div>
            </div>

            <div class="field">
                <label>Subject *</label>
                <select name="subject" required>
                    <option value="">— Select a subject —</option>
                    <option value="Order Issue"        <?= (($_POST['subject'] ?? '') === 'Order Issue')        ? 'selected' : '' ?>>📦 Order Issue</option>
                    <option value="Prescription Query" <?= (($_POST['subject'] ?? '') === 'Prescription Query') ? 'selected' : '' ?>>📄 Prescription Query</option>
                    <option value="Medicine Enquiry"   <?= (($_POST['subject'] ?? '') === 'Medicine Enquiry')   ? 'selected' : '' ?>>💊 Medicine Enquiry</option>
                    <option value="Delivery Problem"   <?= (($_POST['subject'] ?? '') === 'Delivery Problem')   ? 'selected' : '' ?>>🚚 Delivery Problem</option>
                    <option value="Feedback"           <?= (($_POST['subject'] ?? '') === 'Feedback')           ? 'selected' : '' ?>>💬 General Feedback</option>
                    <option value="Other"              <?= (($_POST['subject'] ?? '') === 'Other')              ? 'selected' : '' ?>>❓ Other</option>
                </select>
            </div>

            <div class="field">
                <label>Message *</label>
                <textarea name="message" placeholder="Describe your issue or feedback in detail…" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="submit-btn">Send Message →</button>
        </form>

        <script>
        document.getElementById('contact-form').addEventListener('submit', function(e) {
            const name    = this.name.value.trim();
            const email   = this.email.value.trim();
            const subject = this.subject.value;
            const message = this.message.value.trim();
            if (!name || !email || !subject || message.length < 10) {
                e.preventDefault();
                alert('Please fill in all fields correctly before submitting.');
            }
        });
        </script>
        <?php endif; ?>
    </div>

</div>
</body>
</html>