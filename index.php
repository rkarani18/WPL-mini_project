<?php
session_start();
require 'includes/db.php';

// Fetch medicines from DB
$search = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : '';
$where  = $search ? "WHERE name LIKE '%$search%' OR brand LIKE '%$search%'" : '';
$medicines = $conn->query("SELECT * FROM medicines $where ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickMed | Pharmacy Portal</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .rx-badge { background: #fef9c3; color: #854d0e; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-bottom: 6px; }
        .user-nav { display: flex; align-items: center; gap: 12px; font-size: 14px; color: white; }
        .user-nav a { color: white; text-decoration: none; background: rgba(255,255,255,0.15); padding: 7px 16px; border-radius: 20px; font-weight: 600; }
        .user-nav a:hover { background: rgba(255,255,255,0.3); }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state h3 { font-size: 20px; margin-bottom: 8px; }
    </style>
</head>
<body>

<header>
    <div class="nav-container">
        <h1 class="logo">Quick<span>Med</span></h1>

        <form method="GET" class="search-wrapper" style="margin:0">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search medicines (e.g. Crocin)..."
                   oninput="this.form.submit()">
        </form>

        <div class="user-nav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span>👋 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="upload_prescription.php">📄 My RX</a>
                <a href="order_status.php">📦 Orders</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>

            <div class="cart-trigger" onclick="toggleCart()">
                🛒 Basket <span id="cart-count">0</span>
            </div>
        </div>
    </div>
</header>

<div class="app-layout">
    <aside class="sidebar">
        <div class="side-card">
            <h3>📍 Medical Store</h3>
            <p id="store-display">Loading...</p>
            <button class="btn-small" onclick="shuffleStore()">Change Store</button>
        </div>

        <div class="side-card">
            <h3>📄 RX Upload</h3>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="upload_prescription.php" class="label-upload" style="display:block; text-align:center;">Upload Prescription</a>
            <?php else: ?>
                <a href="login.php" class="label-upload" style="display:block; text-align:center;">Login to Upload RX</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="side-card">
            <h3>📦 My Orders</h3>
            <a href="order_status.php" style="color:#00796b; font-weight:600; text-decoration:none;">View Order Status →</a>
        </div>
        <?php endif; ?>
    </aside>

    <main class="content-area">
        <h2 class="section-title">
            Available Medicines
            <?php if ($search): ?>
                <small style="font-size:14px; color:#64748b; font-weight:400;">— results for "<?= htmlspecialchars($search) ?>"</small>
            <?php endif; ?>
        </h2>

        <?php if ($medicines->num_rows === 0): ?>
            <div class="empty-state">
                <h3>No medicines found</h3>
                <p>Try a different search term.</p>
            </div>
        <?php else: ?>
        <div class="medicine-grid">
            <?php while ($med = $medicines->fetch_assoc()): ?>
            <div class="med-card">
                <img src="<?= htmlspecialchars($med['image_url'] ?? '') ?>"
                     alt="<?= htmlspecialchars($med['name']) ?>"
                     onerror="this.src='https://via.placeholder.com/150?text=Med'">
                <?php if ($med['requires_prescription']): ?>
                    <div class="rx-badge">Rx Required</div>
                <?php endif; ?>
                <h4><?= htmlspecialchars($med['name']) ?></h4>
                <?php if ($med['brand']): ?>
                    <small style="color:#94a3b8;"><?= htmlspecialchars($med['brand']) ?></small><br>
                <?php endif; ?>
                <p style="color:var(--primary); font-weight:700; margin:8px 0;">₹<?= number_format($med['price'], 2) ?></p>
                <button class="add-btn"
                        onclick="addToCart(<?= $med['id'] ?>, '<?= addslashes($med['name']) ?>', <?= $med['price'] ?>, <?= $med['requires_prescription'] ?>)">
                    Add to Cart
                </button>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Cart Panel -->
<div id="cart-panel" class="cart-panel">
    <div class="cart-header">
        <h3>Tax Invoice</h3>
        <button class="close-cart" onclick="toggleCart()">&times;</button>
    </div>
    <div id="cart-items" class="cart-items-body"></div>
    <div class="cart-footer">
        <div class="bill-details">
            <div class="bill-row"><span>Items Total:</span><span>₹<span id="items-total">0</span></span></div>
            <div class="bill-row"><span>CGST (6%):</span><span>₹<span id="cgst">0</span></span></div>
            <div class="bill-row"><span>SGST (6%):</span><span>₹<span id="sgst">0</span></span></div>
            <div class="bill-row"><span>Delivery Fee:</span><span>₹<span id="delivery-fee">0</span></span></div>
            <hr>
            <div class="bill-row total-row"><strong>Grand Total:</strong><strong>₹<span id="total-price">0</span></strong></div>
        </div>
        <div id="rx-warning" style="display:none; background:#fef9c3; border:1px solid #fbbf24; border-radius:8px; padding:10px; margin:10px 0; font-size:13px; color:#854d0e;">
            ⚠️ Your cart has Rx medicines. Please <a href="upload_prescription.php">upload a prescription</a> before placing the order.
        </div>
        <button class="checkout-btn" onclick="checkout()">Confirm Order</button>
    </div>
</div>

<script>
// Cart stored in JS (submitted as JSON on checkout)
let cart = [];
let hasRx = false;

const stores = ["K J Somaiya Pharmacy", "Apollo Pharmacy", "Noble Plus", "MedPlus", "Wellness Forever"];
function shuffleStore() {
    document.getElementById('store-display').innerText = stores[Math.floor(Math.random() * stores.length)];
}

function addToCart(id, name, price, requiresRx) {
    const existing = cart.find(i => i.id === id);
    if (existing) {
        if (existing.qty >= 99) { alert('Maximum 99 units per item allowed.'); return; }
        existing.qty += 1;
    }
    else { cart.push({ id, name, price, qty: 1, requiresRx: !!requiresRx }); }
    updateUI();
    document.getElementById('cart-panel').classList.add('active');
}

function changeQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const newQty = item.qty + delta;
    if (newQty > 99) { alert('Maximum 99 units per item allowed.'); return; }
    item.qty = newQty;
    if (item.qty <= 0) cart = cart.filter(i => i.id !== id);
    updateUI();
}

function updateUI() {
    document.getElementById('cart-count').innerText = cart.reduce((s, i) => s + i.qty, 0);
    hasRx = cart.some(i => i.requiresRx);
    document.getElementById('rx-warning').style.display = hasRx ? 'block' : 'none';

    let subtotal = 0;
    const list = document.getElementById('cart-items');
    list.innerHTML = cart.map(item => {
        subtotal += item.price * item.qty;
        return `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <div style="flex:1">
                <strong>${item.name}</strong>
                ${item.requiresRx ? '<span style="font-size:10px;background:#fef9c3;color:#854d0e;padding:1px 6px;border-radius:8px;margin-left:4px;">Rx</span>' : ''}
                <br><small>₹${item.price} × ${item.qty}</small>
            </div>
            <div class="qty-controls">
                <button class="qty-btn" onclick="changeQty(${item.id}, -1)">-</button>
                <span>${item.qty}</span>
                <button class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
            </div>
        </div>`;
    }).join('');

    const cgst = subtotal * 0.06, sgst = subtotal * 0.06;
    const delivery = cart.length > 0 ? 20 : 0;
    document.getElementById('items-total').innerText  = subtotal.toFixed(2);
    document.getElementById('cgst').innerText         = cgst.toFixed(2);
    document.getElementById('sgst').innerText         = sgst.toFixed(2);
    document.getElementById('delivery-fee').innerText = delivery;
    document.getElementById('total-price').innerText  = (subtotal + cgst + sgst + delivery).toFixed(2);
}

function toggleCart() { document.getElementById('cart-panel').classList.toggle('active'); }

function checkout() {
    if (cart.length === 0) { alert("Cart is empty!"); return; }

    <?php if (!isset($_SESSION['user_id'])): ?>
        if (confirm("Please login to place an order. Go to login page?")) {
            window.location.href = 'login.php';
        }
        return;
    <?php endif; ?>

    // Send cart to place_order.php
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'place_order.php';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'cart_data';
    input.value = JSON.stringify(cart);
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();
}

window.onload = () => { shuffleStore(); };
</script>
</body>
</html>