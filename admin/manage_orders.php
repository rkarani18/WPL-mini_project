<?php
// admin/manage_orders.php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$msg = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $order_id   = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    $allowed    = ['confirmed', 'dispatched', 'delivered', 'cancelled'];

    if (in_array($new_status, $allowed)) {
        $conn->query("UPDATE orders SET status = '$new_status' WHERE id = $order_id");
        $msg = "Order #$order_id updated to " . strtoupper($new_status);
    }
}

// Fetch all orders with shop info
$orders = $conn->query("
    SELECT o.id, o.status, o.grand_total, o.placed_at, o.delivery_address,
           u.full_name, u.email,
           p.status AS rx_status, p.id AS rx_id,
           s.name AS shop_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN prescriptions p ON o.prescription_id = p.id
    LEFT JOIN shops s ON o.shop_id = s.id
    ORDER BY o.placed_at DESC
");

$pending_rx = $conn->query("SELECT COUNT(*) as c FROM prescriptions WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders | Admin QuickMed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #1e293b; padding: 24px 0; }
        .sidebar .logo { color: white; font-size: 20px; font-weight: 700; padding: 0 24px 24px; border-bottom: 1px solid #334155; }
        .sidebar .logo span { color: #ffb300; }
        .sidebar a { display: block; padding: 14px 24px; color: #94a3b8; text-decoration: none; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #00796b; color: white; }
        .alert-pill { background: #dc2626; color: white; border-radius: 20px; padding: 2px 8px; font-size: 11px; font-weight: 700; margin-left: 6px; }
        .main { margin-left: 220px; padding: 30px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 20px; }
        .msg { background: #dcfce7; color: #166534; padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        th { background: #00796b; color: white; padding: 12px 16px; text-align: left; font-size: 13px; }
        td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.pending    { background:#fef9c3; color:#854d0e; }
        .badge.rx_pending { background:#fef3c7; color:#92400e; }
        .badge.confirmed  { background:#dbeafe; color:#1e40af; }
        .badge.dispatched { background:#ede9fe; color:#5b21b6; }
        .badge.delivered  { background:#dcfce7; color:#166534; }
        .badge.cancelled  { background:#fee2e2; color:#991b1b; }
        select { padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 12px; }
        .update-btn { background: #00796b; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .shop-tag { background: #e6f4f3; color: #00796b; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 8px; display: inline-block; margin-top: 3px; }
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
    <a href="manage_orders.php" class="active">📦 Orders</a>
    <a href="manage_shops.php">🏪 Shops</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
    <div class="page-title">📦 Manage Orders</div>
    <?php if ($msg): ?><div class="msg">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Shop</th>
                <th>Amount</th>
                <th>Prescription</th>
                <th>Address</th>
                <th>Placed At</th>
                <th>Status</th>
                <th>Update</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
            <tr>
                <td><strong>#<?= $o['id'] ?></strong></td>
                <td>
                    <?= htmlspecialchars($o['full_name']) ?><br>
                    <small style="color:#64748b"><?= htmlspecialchars($o['email']) ?></small>
                </td>
                <td>
                    <?php if ($o['shop_name']): ?>
                        <span class="shop-tag">🏪 <?= htmlspecialchars($o['shop_name']) ?></span>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td><strong>₹<?= number_format($o['grand_total'], 2) ?></strong></td>
                <td>
                    <?php if ($o['rx_id']): ?>
                        <a href="view_prescriptions.php?filter=all#rx<?= $o['rx_id'] ?>">
                            <span class="badge <?= $o['rx_status'] ?>"><?= ucfirst($o['rx_status']) ?></span>
                        </a>
                    <?php else: ?>
                        <span style="color:#94a3b8">Not required</span>
                    <?php endif; ?>
                </td>
                <td style="max-width:130px;word-break:break-word;"><?= htmlspecialchars($o['delivery_address']) ?></td>
                <td><?= date('d M y', strtotime($o['placed_at'])) ?></td>
                <td><span class="badge <?= $o['status'] ?>"><?= ucfirst(str_replace('_',' ',$o['status'])) ?></span></td>
                <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="new_status">
                            <option value="confirmed"  <?= $o['status']==='confirmed'  ? 'selected':'' ?>>Confirmed</option>
                            <option value="dispatched" <?= $o['status']==='dispatched' ? 'selected':'' ?>>Dispatched</option>
                            <option value="delivered"  <?= $o['status']==='delivered'  ? 'selected':'' ?>>Delivered</option>
                            <option value="cancelled"  <?= $o['status']==='cancelled'  ? 'selected':'' ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="update-btn">Update</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>