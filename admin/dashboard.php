<?php
// admin/dashboard.php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Stats
$total_users   = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_orders  = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$pending_rx    = $conn->query("SELECT COUNT(*) as c FROM prescriptions WHERE status='pending'")->fetch_assoc()['c'];
$total_revenue = $conn->query("SELECT SUM(grand_total) as t FROM orders WHERE status != 'cancelled'")->fetch_assoc()['t'] ?? 0;

// Recent orders
$recent = $conn->query("
    SELECT o.id, o.status, o.grand_total, o.placed_at, u.full_name
    FROM orders o JOIN users u ON o.user_id = u.id
    ORDER BY o.placed_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Admin RKTMed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #1e293b; padding: 24px 0; }
        .sidebar .logo { color: white; font-size: 20px; font-weight: 700; padding: 0 24px 24px; border-bottom: 1px solid #334155; }
        .sidebar .logo span { color: #ffb300; }
        .sidebar a { display: block; padding: 14px 24px; color: #94a3b8; text-decoration: none; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #00796b; color: white; }
        .main { margin-left: 220px; padding: 30px; }
        .welcome { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat .num { font-size: 30px; font-weight: 700; }
        .stat .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .stat.green .num { color: #16a34a; }
        .stat.blue  .num { color: #2563eb; }
        .stat.amber .num { color: #d97706; }
        .stat.teal  .num { color: #00796b; }
        h3 { margin-bottom: 14px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        th { background: #00796b; color: white; padding: 12px 16px; text-align: left; font-size: 13px; }
        td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.pending    { background:#fef9c3; color:#854d0e; }
        .badge.rx_pending { background:#fef3c7; color:#92400e; }
        .badge.confirmed  { background:#dbeafe; color:#1e40af; }
        .badge.dispatched { background:#ede9fe; color:#5b21b6; }
        .badge.delivered  { background:#dcfce7; color:#166534; }
        .badge.cancelled  { background:#fee2e2; color:#991b1b; }
        .alert-pill { background: #dc2626; color: white; border-radius: 20px; padding: 2px 8px; font-size: 11px; font-weight: 700; margin-left: 6px; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="logo">RKT<span>Med</span> Admin</div>
    <a href="dashboard.php" class="active">📊 Dashboard</a>
    <a href="view_prescriptions.php">
        📄 Prescriptions
        <?php if ($pending_rx > 0): ?><span class="alert-pill"><?= $pending_rx ?></span><?php endif; ?>
    </a>
    <a href="manage_orders.php">📦 Orders</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
    <div class="welcome">👋 Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</div>

    <div class="stats">
        <div class="stat teal">
            <div class="num"><?= $total_users ?></div>
            <div class="label">Registered Users</div>
        </div>
        <div class="stat blue">
            <div class="num"><?= $total_orders ?></div>
            <div class="label">Total Orders</div>
        </div>
        <div class="stat amber">
            <div class="num"><?= $pending_rx ?></div>
            <div class="label">Pending Prescriptions</div>
        </div>
        <div class="stat green">
            <div class="num">₹<?= number_format($total_revenue, 0) ?></div>
            <div class="label">Total Revenue</div>
        </div>
    </div>

    <h3>🕐 Recent Orders</h3>
    <table>
        <thead>
            <tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th><th>Placed At</th></tr>
        </thead>
        <tbody>
        <?php while ($o = $recent->fetch_assoc()): ?>
            <tr>
                <td><strong>#<?= $o['id'] ?></strong></td>
                <td><?= htmlspecialchars($o['full_name']) ?></td>
                <td>₹<?= number_format($o['grand_total'], 2) ?></td>
                <td><span class="badge <?= $o['status'] ?>"><?= ucfirst(str_replace('_',' ',$o['status'])) ?></span></td>
                <td><?= date('d M Y, h:i A', strtotime($o['placed_at'])) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>