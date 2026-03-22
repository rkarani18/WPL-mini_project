<?php
// admin/view_feedback.php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = (int)$_POST['feedback_id'];
    $conn->query("UPDATE feedback SET is_read = 1 WHERE id = $id");
    header("Location: view_feedback.php");
    exit;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = (int)$_POST['feedback_id'];
    $conn->query("DELETE FROM feedback WHERE id = $id");
    header("Location: view_feedback.php");
    exit;
}

$filter = $_GET['filter'] ?? 'unread';
$where  = $filter === 'all' ? '' : "WHERE is_read = 0";

$feedback = $conn->query("SELECT * FROM feedback $where ORDER BY submitted_at DESC");
$unread_count = $conn->query("SELECT COUNT(*) as c FROM feedback WHERE is_read = 0")->fetch_assoc()['c'];
$total_count  = $conn->query("SELECT COUNT(*) as c FROM feedback")->fetch_assoc()['c'];
$pending_rx   = $conn->query("SELECT COUNT(*) as c FROM prescriptions WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feedback | Admin QuickMed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; }
        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #1e293b; padding: 24px 0; overflow-y: auto; }
        .sidebar .logo { color: white; font-size: 20px; font-weight: 700; padding: 0 24px 24px; border-bottom: 1px solid #334155; }
        .sidebar .logo span { color: #ffb300; }
        .sidebar a { display: block; padding: 14px 24px; color: #94a3b8; text-decoration: none; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #00796b; color: white; }
        .alert-pill { background: #dc2626; color: white; border-radius: 20px; padding: 2px 8px; font-size: 11px; font-weight: 700; margin-left: 6px; }
        .main { margin-left: 220px; padding: 30px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { font-size: 14px; color: #64748b; margin-bottom: 24px; }

        /* Stats row */
        .stats { display: flex; gap: 14px; margin-bottom: 24px; }
        .stat { background: white; border-radius: 10px; padding: 16px 22px; box-shadow: 0 2px 6px rgba(0,0,0,.05); }
        .stat .num { font-size: 26px; font-weight: 700; }
        .stat .lbl { font-size: 12px; color: #64748b; margin-top: 2px; }
        .stat.unread .num { color: #dc2626; }
        .stat.total  .num { color: #00796b; }

        /* Filter tabs */
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .tabs a { padding: 8px 18px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 600; background: white; color: #64748b; border: 1px solid #e2e8f0; }
        .tabs a.active { background: #00796b; color: white; border-color: #00796b; }

        /* Feedback cards */
        .feedback-card {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            border-left: 4px solid #e2e8f0;
            transition: border-color 0.2s;
        }
        .feedback-card.unread { border-left-color: #00796b; }
        .fb-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .fb-name { font-size: 15px; font-weight: 700; color: #1a202c; }
        .fb-email { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .fb-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .fb-time { font-size: 12px; color: #94a3b8; }
        .subject-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #e6f4f3; color: #00796b; }
        .unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #dc2626; display: inline-block; }
        .fb-message { font-size: 14px; color: #475569; line-height: 1.7; margin-bottom: 14px; white-space: pre-line; }
        .fb-actions { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; }
        .btn-green { background: #00796b; color: white; } .btn-green:hover { background: #005f56; }
        .btn-red   { background: #dc2626; color: white; } .btn-red:hover   { background: #b91c1c; }
        .btn-ghost { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; } .btn-ghost:hover { background: #e2e8f0; }

        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">Quick<span>Med</span> Admin</div>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="view_prescriptions.php">
        📄 Prescriptions
        <?php if ($pending_rx > 0): ?><span class="alert-pill"><?= $pending_rx ?></span><?php endif; ?>
    </a>
    <a href="manage_orders.php">📦 Orders</a>
    <a href="manage_shops.php">🏪 Shops</a>
    <a href="view_feedback.php" class="active">
        💬 Feedback
        <?php if ($unread_count > 0): ?><span class="alert-pill"><?= $unread_count ?></span><?php endif; ?>
    </a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
    <div class="page-title">💬 Customer Feedback</div>
    <div class="page-sub">Messages submitted through the Contact Us page</div>

    <div class="stats">
        <div class="stat unread">
            <div class="num"><?= $unread_count ?></div>
            <div class="lbl">Unread</div>
        </div>
        <div class="stat total">
            <div class="num"><?= $total_count ?></div>
            <div class="lbl">Total messages</div>
        </div>
    </div>

    <div class="tabs">
        <a href="?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">⭕ Unread (<?= $unread_count ?>)</a>
        <a href="?filter=all"    class="<?= $filter === 'all'    ? 'active' : '' ?>">📋 All (<?= $total_count ?>)</a>
    </div>

    <?php if ($feedback->num_rows === 0): ?>
    <div class="empty-state">
        <h3><?= $filter === 'unread' ? 'No unread messages' : 'No messages yet' ?></h3>
        <p><?= $filter === 'unread' ? 'All caught up!' : 'Messages submitted through the contact form will appear here.' ?></p>
    </div>
    <?php endif; ?>

    <?php while ($fb = $feedback->fetch_assoc()): ?>
    <div class="feedback-card <?= !$fb['is_read'] ? 'unread' : '' ?>">
        <div class="fb-header">
            <div>
                <div class="fb-name">
                    <?php if (!$fb['is_read']): ?><span class="unread-dot" style="margin-right:6px"></span><?php endif; ?>
                    <?= htmlspecialchars($fb['name']) ?>
                </div>
                <div class="fb-email"><?= htmlspecialchars($fb['email']) ?></div>
            </div>
            <div class="fb-meta">
                <span class="subject-badge"><?= htmlspecialchars($fb['subject']) ?></span>
                <span class="fb-time"><?= date('d M Y, h:i A', strtotime($fb['submitted_at'])) ?></span>
            </div>
        </div>

        <div class="fb-message"><?= htmlspecialchars($fb['message']) ?></div>

        <div class="fb-actions">
            <?php if (!$fb['is_read']): ?>
            <form method="POST" style="display:inline">
                <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
                <button type="submit" name="mark_read" class="btn-sm btn-green">✓ Mark as Read</button>
            </form>
            <?php else: ?>
            <span class="btn-sm btn-ghost" style="cursor:default;opacity:0.6">✓ Read</span>
            <?php endif; ?>

            

            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this message?')">
                <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
                <button type="submit" name="delete" class="btn-sm btn-red">✕ Delete</button>
            </form>
        </div>
    </div>
    <?php endwhile; ?>
</div>
</body>
</html>