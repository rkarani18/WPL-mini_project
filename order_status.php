<?php
// order_status.php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$placed     = isset($_GET['placed']) && $_GET['placed'] == '1';
$highlight  = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

// Fetch all orders for this user
$orders = $conn->query("
    SELECT o.id, o.status, o.grand_total, o.placed_at, o.delivery_address,
           o.cgst, o.sgst, o.delivery_fee, o.subtotal,
           p.status AS rx_status, p.id AS rx_id, p.admin_note
    FROM orders o
    LEFT JOIN prescriptions p ON o.prescription_id = p.id
    WHERE o.user_id = $user_id
    ORDER BY o.placed_at DESC
");

// Fetch order items per order
function getOrderItems($conn, $order_id) {
    return $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
}

$statusSteps = ['pending', 'rx_pending', 'confirmed', 'dispatched', 'delivered'];
$statusLabel = [
    'pending'    => 'Order Placed',
    'rx_pending' => 'Awaiting RX Approval',
    'confirmed'  => 'Confirmed',
    'dispatched' => 'Out for Delivery',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders | RKTMed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; }

        .topbar { background: #00796b; color: white; padding: 14px 5%; display: flex; align-items: center; justify-content: space-between; }
        .topbar .logo { font-size: 20px; font-weight: 700; }
        .topbar .logo span { color: #ffb300; }
        .topbar a { color: white; text-decoration: none; font-size: 14px; background: rgba(255,255,255,0.15); padding: 7px 16px; border-radius: 20px; }

        .container { max-width: 860px; margin: 30px auto; padding: 0 20px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 20px; }

        .success-banner { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; font-weight: 500; }

        /* Order card */
        .order-card { background: white; border-radius: 14px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); border: 2px solid transparent; }
        .order-card.highlight { border-color: #00796b; }
        .order-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .order-id { font-size: 16px; font-weight: 700; }
        .order-meta { font-size: 13px; color: #64748b; margin-top: 2px; }

        /* Status badge */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .badge.pending    { background:#fef9c3; color:#854d0e; }
        .badge.rx_pending { background:#fef3c7; color:#92400e; }
        .badge.confirmed  { background:#dbeafe; color:#1e40af; }
        .badge.dispatched { background:#ede9fe; color:#5b21b6; }
        .badge.delivered  { background:#dcfce7; color:#166534; }
        .badge.cancelled  { background:#fee2e2; color:#991b1b; }

        /* Progress bar */
        .progress-bar { display: flex; align-items: center; margin: 16px 0; }
        .step { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; }
        .step .dot { width: 18px; height: 18px; border-radius: 50%; background: #e2e8f0; border: 2px solid #e2e8f0; z-index: 1; }
        .step.done .dot   { background: #00796b; border-color: #00796b; }
        .step.active .dot { background: #ffb300; border-color: #ffb300; box-shadow: 0 0 0 4px #fef9c3; }
        .step .label { font-size: 10px; color: #94a3b8; margin-top: 5px; text-align: center; }
        .step.done .label, .step.active .label { color: #1e293b; font-weight: 600; }
        .step::before { content:''; position:absolute; top:9px; left:-50%; width:100%; height:2px; background:#e2e8f0; z-index:0; }
        .step:first-child::before { display:none; }
        .step.done::before { background: #00796b; }

        /* RX notice */
        .rx-notice { background: #fef9c3; border: 1px solid #fbbf24; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #854d0e; margin-bottom: 14px; }
        .rx-notice.approved { background: #dcfce7; border-color: #86efac; color: #166534; }
        .rx-notice.rejected { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

        /* Items list */
        .items-toggle { background: none; border: none; color: #00796b; font-weight: 600; cursor: pointer; font-size: 13px; padding: 0; margin-bottom: 10px; }
        .items-list { font-size: 13px; }
        .items-list table { width: 100%; border-collapse: collapse; }
        .items-list td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .items-list .amount { text-align: right; font-weight: 600; color: #00796b; }

        /* Bill summary */
        .bill-mini { margin-top: 12px; font-size: 13px; border-top: 1px solid #f1f5f9; padding-top: 12px; }
        .bill-mini .row { display: flex; justify-content: space-between; margin-bottom: 4px; color: #64748b; }
        .bill-mini .total { font-weight: 700; color: #1e293b; font-size: 15px; margin-top: 6px; }

        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-state h3 { font-size: 20px; margin-bottom: 8px; }
        .empty-state a { color: #00796b; font-weight: 600; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="logo">RKT<span>Med</span></div>
    <a href="index.php">← Back to Shop</a>
</div>

<div class="container">
    <div class="page-title">📦 My Orders</div>

    <?php if ($placed): ?>
    <div class="success-banner">
        ✅ Your order has been placed successfully! We'll notify you once it's confirmed.
    </div>
    <?php endif; ?>

    <?php if ($orders->num_rows === 0): ?>
    <div class="empty-state">
        <h3>No orders yet</h3>
        <p>Start shopping! <a href="index.php">Browse medicines →</a></p>
    </div>
    <?php endif; ?>

    <?php while ($order = $orders->fetch_assoc()):
        $isCancelled = $order['status'] === 'cancelled';
        $progressSteps = ['confirmed', 'dispatched', 'delivered'];
        $currentIdx = array_search($order['status'], $progressSteps);
    ?>
    <div class="order-card <?= ($highlight === $order['id']) ? 'highlight' : '' ?>">
        <div class="order-header">
            <div>
                <div class="order-id">Order #<?= $order['id'] ?></div>
                <div class="order-meta">
                    Placed on <?= date('d M Y, h:i A', strtotime($order['placed_at'])) ?>
                    · <?= htmlspecialchars($order['delivery_address']) ?>
                </div>
            </div>
            <span class="badge <?= $order['status'] ?>"><?= $statusLabel[$order['status']] ?? ucfirst($order['status']) ?></span>
        </div>

        <?php if ($order['rx_id']): ?>
        <div class="rx-notice <?= $order['rx_status'] ?>">
            <?php if ($order['rx_status'] === 'pending'): ?>
                ⏳ Your prescription is under review by our pharmacist.
            <?php elseif ($order['rx_status'] === 'approved'): ?>
                ✅ Prescription approved by pharmacist.
            <?php elseif ($order['rx_status'] === 'rejected'): ?>
                ❌ Prescription rejected.
                <?php if ($order['admin_note']): ?>
                    Reason: <strong><?= htmlspecialchars($order['admin_note']) ?></strong>
                    — <a href="upload_prescription.php">Upload a new one</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!$isCancelled): ?>
        <!-- Progress Steps -->
        <div class="progress-bar">
            <?php foreach ($progressSteps as $idx => $step): ?>
            <div class="step <?= ($currentIdx !== false && $idx < $currentIdx) ? 'done' : (($idx === $currentIdx) ? 'active' : '') ?>">
                <div class="dot"></div>
                <div class="label"><?= $statusLabel[$step] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Order Items (collapsible) -->
        <button class="items-toggle" onclick="toggleItems(<?= $order['id'] ?>)">
            🧾 View Items
        </button>
        <div class="items-list" id="items-<?= $order['id'] ?>" style="display:none;">
            <?php $items = getOrderItems($conn, $order['id']); ?>
            <table>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($item['medicine_name']) ?></td>
                    <td style="color:#64748b">× <?= $item['qty'] ?></td>
                    <td class="amount">₹<?= number_format($item['price'] * $item['qty'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </table>

            <div class="bill-mini">
                <div class="row"><span>Subtotal</span><span>₹<?= number_format($order['subtotal'], 2) ?></span></div>
                <div class="row"><span>CGST (6%)</span><span>₹<?= number_format($order['cgst'], 2) ?></span></div>
                <div class="row"><span>SGST (6%)</span><span>₹<?= number_format($order['sgst'], 2) ?></span></div>
                <div class="row"><span>Delivery</span><span>₹<?= number_format($order['delivery_fee'], 2) ?></span></div>
                <div class="row total"><span>Grand Total</span><span>₹<?= number_format($order['grand_total'], 2) ?></span></div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<script>
function toggleItems(orderId) {
    const el = document.getElementById('items-' + orderId);
    const btn = el.previousElementSibling;
    if (el.style.display === 'none') {
        el.style.display = 'block';
        btn.textContent = '🧾 Hide Items';
    } else {
        el.style.display = 'none';
        btn.textContent = '🧾 View Items';
    }
}
</script>
</body>
</html>