<?php
// contact.php
session_start();
require 'includes/db.php';

$success = false;
$error   = '';

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f7fafc; color: #2d3748; }

        .user-nav { display: flex; align-items: center; gap: 12px; font-size: 14px; color: white; }
        .user-nav a { color: white; text-decoration: none; background: rgba(255,255,255,0.15); padding: 7px 16px; border-radius: 20px; font-weight: 600; }
        .user-nav a:hover { background: rgba(255,255,255,0.3); }

        /* ── Hero banner ── */
        .contact-hero {
            background: linear-gradient(135deg, #00796b 0%, #004d40 60%, #00251a 100%);
            padding: 60px 5% 70px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .contact-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 70% 50%, rgba(255,179,0,0.12) 0%, transparent 65%);
            pointer-events: none;
        }
        .contact-hero .pill {
            display: inline-block;
            background: rgba(255,179,0,0.2);
            color: #ffb300;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 6px 18px;
            border-radius: 20px;
            border: 1px solid rgba(255,179,0,0.3);
            margin-bottom: 20px;
        }
        .contact-hero h1 {
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 800;
            color: white;
            line-height: 1.1;
            margin-bottom: 12px;
        }
        .contact-hero h1 span { color: #ffb300; }
        .contact-hero .hero-desc {
            color: rgba(255,255,255,0.75);
            font-size: 15px;
            max-width: 520px;
            margin: 0 auto 36px;
            line-height: 1.7;
        }
        /* Feature cards inside hero */
        .hero-features {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            max-width: 860px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .hero-feat {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 12px;
            padding: 16px 14px;
            text-align: center;
            backdrop-filter: blur(4px);
        }
        .hero-feat .feat-icon { font-size: 24px; margin-bottom: 8px; }
        .hero-feat .feat-title { font-size: 13px; font-weight: 700; color: #ffb300; margin-bottom: 4px; }
        .hero-feat .feat-desc { font-size: 11px; color: rgba(255,255,255,0.7); line-height: 1.5; }

        /* ── Quick contact bar ── */
        .quick-bar {
            display: flex;
            justify-content: center;
            gap: 0;
            max-width: 900px;
            margin: -32px auto 0;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }
        .quick-item {
            flex: 1;
            background: white;
            padding: 22px 20px;
            text-align: center;
            border-right: 1px solid #f0f4f8;
            box-shadow: 0 8px 32px rgba(0,0,0,0.10);
        }
        .quick-item:first-child { border-radius: 14px 0 0 14px; }
        .quick-item:last-child  { border-radius: 0 14px 14px 0; border-right: none; }
        .quick-item .qi-icon { font-size: 26px; margin-bottom: 8px; }
        .quick-item .qi-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 5px; }
        .quick-item .qi-value { font-size: 14px; font-weight: 600; color: #1a202c; }
        .quick-item .qi-value a { color: #00796b; text-decoration: none; }
        .quick-item .qi-value a:hover { text-decoration: underline; }

        /* ── Page layout ── */
        .page-wrap {
            max-width: 1060px;
            margin: 60px auto 60px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 40px;
            align-items: start;
        }

        /* ── Info panel ── */
        .info-panel h2 {
            font-size: 26px;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 8px;
        }
        .info-panel h2 span { color: #00796b; }
        .info-panel .sub { font-size: 14px; color: #64748b; line-height: 1.75; margin-bottom: 32px; }

        .info-block {
            background: white;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex;
            gap: 16px;
            align-items: flex-start;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .info-block:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,121,107,0.1); }
        .ib-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #00796b, #004d40);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .ib-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .ib-value { font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.6; }
        .ib-value a { color: #00796b; text-decoration: none; }
        .ib-value a:hover { text-decoration: underline; }
        .ib-note { font-size: 12px; color: #94a3b8; margin-top: 4px; }

        /* Hours table */
        .hours-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; font-size: 13px; margin-top: 2px; }
        .hours-grid .day { color: #64748b; }
        .hours-grid .time { font-weight: 600; color: #1e293b; }
        .hours-grid .time.open { color: #00796b; }
        .hours-grid .time.closed { color: #dc2626; }

        /* Social row */
        .social-row { display: flex; gap: 10px; margin-top: 6px; }
        .social-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px;
            background: #f0faf9; border: 1px solid #b2dfdb;
            font-size: 12px; font-weight: 700; color: #00796b;
            text-decoration: none; transition: background 0.15s;
        }
        .social-btn:hover { background: #e0f5f1; }

        /* ── Form card ── */
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
            position: sticky;
            top: 90px;
        }
        .form-card h3 {
            font-size: 20px; font-weight: 800;
            margin-bottom: 6px; color: #1a202c;
        }
        .form-card .form-sub { font-size: 13px; color: #94a3b8; margin-bottom: 24px; }

        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .field input, .field select, .field textarea {
            width: 100%; padding: 12px 16px;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 14px; font-family: 'Inter', sans-serif;
            color: #1e293b; background: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none; border-color: #00796b;
            box-shadow: 0 0 0 3px rgba(0,121,107,0.1);
            background: white;
        }
        .field textarea { resize: vertical; min-height: 120px; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .submit-btn {
            width: 100%; background: #00796b; color: white;
            border: none; padding: 14px; border-radius: 10px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .submit-btn:hover { background: #005f56; transform: translateY(-1px); }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

        .success-state { text-align: center; padding: 30px 10px; }
        .success-state .tick { font-size: 60px; margin-bottom: 16px; }
        .success-state h3 { font-size: 22px; font-weight: 800; color: #166534; margin-bottom: 8px; }
        .success-state p { font-size: 14px; color: #64748b; margin-bottom: 24px; line-height: 1.6; }
        .success-state a { display: inline-block; padding: 12px 28px; background: #00796b; color: white; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; }
        .success-state a:hover { background: #005f56; }

        @media (max-width: 700px) {
            .quick-bar { flex-direction: column; margin-top: 20px; }
            .quick-item { border-radius: 0 !important; border-right: none; border-bottom: 1px solid #f0f4f8; }
            .quick-item:last-child { border-bottom: none; border-radius: 14px !important; }
            .page-wrap { grid-template-columns: 1fr; margin-top: 30px; }
            .field-row { grid-template-columns: 1fr; }
            .form-card { position: static; }
            .hero-features { grid-template-columns: 1fr 1fr; }
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

<!-- Hero -->
<div class="contact-hero">
    <div class="pill">💊 Your Neighbourhood Pharmacy, Online</div>
    <h1>Get in <span>Touch</span><br>with QuickMed</h1>
    <p class="hero-desc">QuickMed connects patients with trusted local pharmacies across Mumbai for fast, reliable home delivery — with full prescription support and real-time order tracking.</p>

    <div class="hero-features">
        <div class="hero-feat">
            <div class="feat-icon">🏪</div>
            <div class="feat-title">Multi-store</div>
            <div class="feat-desc">Browse & order from multiple local pharmacy stores in one place</div>
        </div>
        <div class="hero-feat">
            <div class="feat-icon">📄</div>
            <div class="feat-title">Rx Support</div>
            <div class="feat-desc">Upload prescriptions digitally — pharmacist reviews before dispatch</div>
        </div>
        <div class="hero-feat">
            <div class="feat-icon">📍</div>
            <div class="feat-title">Nearest Store</div>
            <div class="feat-desc">Auto-detect your location and find the closest pharmacy partner</div>
        </div>
        <div class="hero-feat">
            <div class="feat-icon">📦</div>
            <div class="feat-title">Order Tracking</div>
            <div class="feat-desc">Real-time status updates from confirmed to delivered</div>
        </div>
    </div>
</div>

<!-- Quick contact bar -->
<div class="quick-bar">
    <div class="quick-item">
        <div class="qi-icon">📞</div>
        <div class="qi-label">Phone</div>
        <div class="qi-value"><a href="tel:+912212345678">+91 22 1234 5678</a></div>
    </div>
    <div class="quick-item">
        <div class="qi-icon">✉️</div>
        <div class="qi-label">Email</div>
        <div class="qi-value"><a href="mailto:support@quickmed.in">support@quickmed.in</a></div>
    </div>
    <div class="quick-item">
        <div class="qi-icon">⏰</div>
        <div class="qi-label">Response Time</div>
        <div class="qi-value">Within 24 hours</div>
    </div>
    <div class="quick-item">
        <div class="qi-icon">🏙️</div>
        <div class="qi-label">Headquarters</div>
        <div class="qi-value">Mumbai, Maharashtra</div>
    </div>
</div>

<div class="page-wrap">

    <!-- Left: Info blocks -->
    <div class="info-panel">
        <h2>We'd love to<br><span>hear from you</span></h2>
        <p class="sub">Have a question about your order, a medicine, or need prescription guidance? Our support team is here to help and responds within 24 hours.</p>

        <!-- Address -->
        <div class="info-block">
            <div class="ib-icon">🏢</div>
            <div>
                <div class="ib-label">Registered Office</div>
                <div class="ib-value">QuickMed Health Tech Pvt. Ltd.<br>Level 4, Infinity IT Park<br>Malad East, Mumbai — 400097<br>Maharashtra, India</div>
                <div class="ib-note"><a href="https://maps.google.com" target="_blank">📍 View on Google Maps →</a></div>
            </div>
        </div>

        <!-- Phone -->
        <div class="info-block">
            <div class="ib-icon">📞</div>
            <div>
                <div class="ib-label">Phone Support</div>
                <div class="ib-value">
                    <a href="tel:+912212345678">+91 22 1234 5678</a> — General Enquiries<br>
                    <a href="tel:+912298765432">+91 22 9876 5432</a> — Order Support<br>
                    <a href="tel:18001234567">1800-123-4567</a> — Toll Free
                </div>
                <div class="ib-note">Mon–Sat, 9 AM to 8 PM IST</div>
            </div>
        </div>

        <!-- Email -->
        <div class="info-block">
            <div class="ib-icon">✉️</div>
            <div>
                <div class="ib-label">Email</div>
                <div class="ib-value">
                    <a href="mailto:support@quickmed.in">support@quickmed.in</a> — Customer Support<br>
                    <a href="mailto:rx@quickmed.in">rx@quickmed.in</a> — Prescription Queries<br>
                    <a href="mailto:partners@quickmed.in">partners@quickmed.in</a> — Pharmacy Partners
                </div>
            </div>
        </div>

        <!-- Business hours -->
        <div class="info-block">
            <div class="ib-icon">🕐</div>
            <div>
                <div class="ib-label">Support Hours</div>
                <div class="hours-grid">
                    <span class="day">Mon – Fri</span>     <span class="time open">9:00 AM – 8:00 PM</span>
                    <span class="day">Saturday</span>      <span class="time open">10:00 AM – 6:00 PM</span>
                    <span class="day">Sunday</span>        <span class="time closed">Closed</span>
                    <span class="day">Public Holidays</span><span class="time closed">Closed</span>
                </div>
            </div>
        </div>

        <!-- Emergency note -->
        <div class="info-block" style="border-left: 4px solid #dc2626; border-radius: 14px;">
            <div class="ib-icon" style="background: linear-gradient(135deg, #dc2626, #991b1b);">🚨</div>
            <div>
                <div class="ib-label" style="color: #dc2626;">Medical Emergency</div>
                <div class="ib-value">For medical emergencies, please call <strong>112</strong> or go to your nearest hospital immediately. QuickMed is not an emergency service.</div>
            </div>
        </div>

        <!-- Social -->
        <div class="info-block">
            <div class="ib-icon">💬</div>
            <div>
                <div class="ib-label">Connect With Us</div>
                <div class="ib-note" style="margin-bottom: 10px;">Follow us for health tips, offers & updates</div>
                <div class="social-row">
                    <a href="#" class="social-btn">📘 Facebook</a>
                    <a href="#" class="social-btn">📸 Instagram</a>
                    <a href="#" class="social-btn">🐦 Twitter</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Form -->
    <div class="form-card">
        <?php if ($success): ?>
        <div class="success-state">
            <div class="tick">✅</div>
            <h3>Message Sent!</h3>
            <p>Thanks for reaching out. We'll get back to you at<br><strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong> within 24 hours.</p>
            <a href="index.php">← Back to Home</a>
        </div>

        <?php else: ?>
        <h3>Send us a message</h3>
        <p class="form-sub">Fill in the form and we'll respond by email within one business day.</p>

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
                    <option value="Pharmacy Partnership" <?= (($_POST['subject'] ?? '') === 'Pharmacy Partnership') ? 'selected' : '' ?>>🤝 Pharmacy Partnership</option>
                    <option value="Other"              <?= (($_POST['subject'] ?? '') === 'Other')              ? 'selected' : '' ?>>❓ Other</option>
                </select>
            </div>

            <div class="field">
                <label>Message *</label>
                <textarea name="message" placeholder="Describe your issue or feedback in detail… (For order issues, please include your Order #)" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
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

        <p style="font-size:12px;color:#94a3b8;text-align:center;margin-top:16px;">
            By submitting this form you agree to our <a href="#" style="color:#00796b;">Privacy Policy</a>.
        </p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>