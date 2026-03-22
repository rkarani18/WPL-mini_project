<?php
session_start();
require 'includes/db.php';

if (isset($_GET['shop_id'])) {
    $_SESSION['selected_shop_id'] = (int)$_GET['shop_id'];
}
$selected_shop_id = $_SESSION['selected_shop_id'] ?? null;

$shops = $conn->query("SELECT * FROM shops WHERE is_active = 1 ORDER BY name ASC");
$shops_list = [];
while ($s = $shops->fetch_assoc()) $shops_list[] = $s;

$selected_shop = null;
if ($selected_shop_id) {
    $r = $conn->query("SELECT * FROM shops WHERE id = $selected_shop_id AND is_active = 1");
    $selected_shop = $r->fetch_assoc();
    if (!$selected_shop) { $selected_shop_id = null; unset($_SESSION['selected_shop_id']); }
}

$search = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : '';

if ($selected_shop_id) {
    $sc = $search ? "AND (m.name LIKE '%$search%' OR m.brand LIKE '%$search%')" : '';
    $medicines = $conn->query("
        SELECT m.*, sm.price AS shop_price, sm.stock AS shop_stock
        FROM medicines m
        JOIN shop_medicines sm ON sm.medicine_id = m.id
        WHERE sm.shop_id = $selected_shop_id $sc
        ORDER BY m.name ASC
    ");
} else {
    $sc = $search ? "WHERE m.name LIKE '%$search%' OR m.brand LIKE '%$search%'" : '';
    $medicines = $conn->query("SELECT m.*, m.price AS shop_price, m.stock AS shop_stock FROM medicines m $sc ORDER BY m.name ASC");
}

// Stock error from a failed order attempt
$stock_error = '';
if (isset($_GET['stock_error']) && isset($_SESSION['order_error'])) {
    $stock_error = $_SESSION['order_error'];
    unset($_SESSION['order_error']);
}

// Pass shop data (with coords) to JS
$shops_json = json_encode(array_map(fn($s) => [
    'id'    => $s['id'],
    'name'  => $s['name'],
    'addr'  => $s['address'] ?? '',
    'phone' => $s['phone'] ?? '',
    'map'   => $s['maps_link'] ?? '',
    'lat'   => isset($s['lat']) && $s['lat'] ? (float)$s['lat'] : null,
    'lng'   => isset($s['lng']) && $s['lng'] ? (float)$s['lng'] : null,
], $shops_list));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickMed | Pharmacy Portal</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .rx-badge { background:#fef9c3;color:#854d0e;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;display:inline-block;margin-bottom:6px; }
        .user-nav { display:flex;align-items:center;gap:12px;font-size:14px;color:white; }
        .user-nav a { color:white;text-decoration:none;background:rgba(255,255,255,0.15);padding:7px 16px;border-radius:20px;font-weight:600; }
        .user-nav a:hover { background:rgba(255,255,255,0.3); }
        .empty-state { text-align:center;padding:60px 20px;color:#94a3b8; }
        .empty-state h3 { font-size:20px;margin-bottom:8px; }

        /* Stock badge on medicine card */
        .stock-badge { font-size:11px;font-weight:600;padding:2px 8px;border-radius:8px;display:inline-block;margin-bottom:6px; }
        .stock-ok  { background:#dcfce7;color:#166534; }
        .stock-low { background:#fef9c3;color:#854d0e; }
        .stock-out { background:#fee2e2;color:#991b1b; }

        /* Stock error banner */
        .stock-error-banner {
            background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;
            padding:14px 18px;margin-bottom:20px;font-size:13px;color:#991b1b;line-height:1.8;
        }
        .stock-error-banner strong { display:block;margin-bottom:4px;font-size:14px; }

        /* Shop selector */
        .shop-card { background:white;border-radius:12px;padding:18px;margin-bottom:20px;box-shadow:0 4px 6px rgba(0,0,0,0.05); }
        .shop-card h3 { margin:0 0 10px;font-size:15px;color:#2d3748; }

        #nearest-btn {
            display:flex;align-items:center;justify-content:center;gap:8px;
            width:100%;padding:10px 14px;background:#00796b;color:white;
            border:none;border-radius:10px;font-size:13px;font-weight:700;
            cursor:pointer;transition:background 0.2s,transform 0.1s;margin-bottom:10px;
        }
        #nearest-btn:hover:not(:disabled) { background:#005f56;transform:translateY(-1px); }
        #nearest-btn:disabled { opacity:0.6;cursor:default;transform:none; }
        .dot-pulse { width:8px;height:8px;border-radius:50%;background:#ffb300;flex-shrink:0;animation:dp 1.4s ease-in-out infinite; }
        @keyframes dp{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.6);opacity:.5}}
        #locate-status { font-size:12px;color:#64748b;margin:0 0 8px;min-height:16px; }
        #locate-status.err { color:#e53e3e; }

        .shop-item { display:flex;flex-direction:column;gap:2px;padding:10px 12px;border-radius:9px;cursor:pointer;border:1.5px solid #e8eff0;margin-bottom:7px;transition:all 0.15s;text-decoration:none;color:inherit;position:relative; }
        .shop-item:hover  { border-color:#00796b;background:#f0faf9; }
        .shop-item.active { border-color:#00796b;background:#e6f4f3; }
        .shop-item .sh-name  { font-size:13px;font-weight:700;color:#1a202c; }
        .shop-item .sh-addr  { font-size:11px;color:#94a3b8; }
        .shop-item .sh-phone { font-size:11px;color:#64748b; }
        .sh-dist { font-size:11px;font-weight:700;color:#00796b;margin-top:2px; }
        .nearest-badge { position:absolute;top:8px;right:10px;background:#00796b;color:white;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px; }

        .selected-banner { background:#e6f4f3;border:1px solid #b2dfdb;border-radius:10px;padding:10px 13px;margin-bottom:10px;font-size:12px;color:#00796b; }
        .selected-banner strong { display:block;font-size:13px;margin-bottom:2px; }
        .selected-banner a { color:#00796b;font-size:11px; }

        .no-shop-notice { text-align:center;padding:16px;color:#94a3b8;font-size:13px;border:2px dashed #e2e8f0;border-radius:10px;margin-bottom:10px; }
        .no-shop-notice strong { display:block;margin-bottom:4px;color:#64748b;font-size:14px; }

        /* ── Search bar ── */
        .search-wrapper { flex-grow:1; max-width:480px; position:relative; }
        .search-icon {
            position:absolute; left:14px; top:50%; transform:translateY(-50%);
            width:16px; height:16px; opacity:0.5; pointer-events:none;
        }
        .search-wrapper input[type="text"] {
            width:100%; box-sizing:border-box;
            padding:11px 40px 11px 42px;
            border:none; border-radius:30px;
            font-size:14px; font-family:'Inter',sans-serif;
            background:rgba(255,255,255,0.15); color:white;
            outline:none;
            transition:background 0.2s, box-shadow 0.2s;
        }
        .search-wrapper input[type="text"]::placeholder { color:rgba(255,255,255,0.55); }
        .search-wrapper input[type="text"]:focus {
            background:rgba(255,255,255,0.25);
            box-shadow:0 0 0 3px rgba(255,255,255,0.18);
        }
        .search-clear {
            position:absolute; right:12px; top:50%; transform:translateY(-50%);
            background:rgba(255,255,255,0.25); border:none; color:white;
            font-size:13px; cursor:pointer; border-radius:50%;
            width:20px; height:20px; line-height:1;
            display:flex; align-items:center; justify-content:center; padding:0;
            opacity:0; pointer-events:none; transition:opacity 0.15s;
        }
        .search-wrapper input[type="text"]:not(:placeholder-shown) ~ .search-clear {
            opacity:1; pointer-events:all;
        }
        .search-clear:hover { background:rgba(255,255,255,0.4); }
    </style>
</head>
<body>

<header>
    <div class="nav-container">
        <h1 class="logo">Quick<span>Med</span></h1>
        <form method="GET" class="search-wrapper" style="margin:0">
            <?php if ($selected_shop_id): ?>
                <input type="hidden" name="shop_id" value="<?= $selected_shop_id ?>">
            <?php endif; ?>
            <svg class="search-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="8.5" cy="8.5" r="5.5" stroke="white" stroke-width="1.8"/>
                <path d="M13 13L17 17" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <input type="text" name="q" id="search-q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search medicines... (press Enter)">
            <button type="button" class="search-clear" onclick="clearSearch()" title="Clear search">&times;</button>
        </form>
        <div class="user-nav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span>👋 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="upload_prescription.php">📄 My RX</a>
                <a href="order_status.php">📦 Orders</a>
                <a href="contact.php">💬 Contact</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
                <a href="contact.php">💬 Contact</a>
            <?php endif; ?>
            <div class="cart-trigger" onclick="toggleCart()">
                🛒 Basket <span id="cart-count">0</span>
            </div>
        </div>
    </div>
</header>

<div class="app-layout">
    <aside class="sidebar">

        <div class="shop-card">
            <h3>🏪 Choose a Medical Store</h3>

            <button id="nearest-btn" onclick="findNearest()">
                <span class="dot-pulse"></span>
                Find Nearest Store
            </button>
            <p id="locate-status"></p>

            <?php if ($selected_shop): ?>
            <div class="selected-banner">
                <strong>✅ <?= htmlspecialchars($selected_shop['name']) ?></strong>
                <?= htmlspecialchars($selected_shop['address'] ?? '') ?>
                <?php if ($selected_shop['maps_link']): ?>
                    <br><a href="<?= htmlspecialchars($selected_shop['maps_link']) ?>" target="_blank">📍 View on map</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div id="shop-list">
            <?php foreach ($shops_list as $shop): ?>
            <a href="?shop_id=<?= $shop['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="shop-item <?= ($selected_shop_id == $shop['id']) ? 'active' : '' ?>"
               id="shop-item-<?= $shop['id'] ?>">
                <span class="sh-name"><?= htmlspecialchars($shop['name']) ?></span>
                <?php if ($shop['address']): ?>
                    <span class="sh-addr">📍 <?= htmlspecialchars($shop['address']) ?></span>
                <?php endif; ?>
                <?php if ($shop['phone']): ?>
                    <span class="sh-phone">📞 <?= htmlspecialchars($shop['phone']) ?></span>
                <?php endif; ?>
                <span class="sh-dist" id="dist-<?= $shop['id'] ?>" style="display:none"></span>
            </a>
            <?php endforeach; ?>
            </div>

            <?php if (empty($shops_list)): ?>
            <div class="no-shop-notice">
                <strong>No stores added yet</strong>
                Admin needs to add stores from the dashboard.
            </div>
            <?php endif; ?>
        </div>

        <div class="side-card">
            <h3>📄 RX Upload</h3>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="upload_prescription.php" class="label-upload" style="display:block;text-align:center;">Upload Prescription</a>
            <?php else: ?>
                <a href="login.php" class="label-upload" style="display:block;text-align:center;">Login to Upload RX</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="side-card">
            <h3>📦 My Orders</h3>
            <a href="order_status.php" style="color:#00796b;font-weight:600;text-decoration:none;">View Order Status →</a>
        </div>
        <?php endif; ?>
    </aside>

    <main class="content-area">
        <h2 class="section-title">
            <?php if ($selected_shop): ?>
                Medicines at <?= htmlspecialchars($selected_shop['name']) ?>
            <?php else: ?>
                All Available Medicines
            <?php endif; ?>
            <?php if ($search): ?>
                <small style="font-size:14px;color:#64748b;font-weight:400;">— results for "<?= htmlspecialchars($search) ?>"</small>
            <?php endif; ?>
        </h2>

        <?php if ($stock_error): ?>
        <div class="stock-error-banner">
            <strong>⚠️ Order could not be placed — insufficient stock:</strong>
            <?= $stock_error ?>
            <div style="margin-top:8px;font-size:12px;color:#7f1d1d;">Please reduce the quantity and try again.</div>
        </div>
        <?php endif; ?>

        <?php if (!$selected_shop_id): ?>
        <div style="background:#fff9e6;border:1px solid #fcd34d;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#92400e;">
            👆 Please select a medical store from the sidebar to see their medicines and place an order.
        </div>
        <?php endif; ?>

        <?php if ($medicines->num_rows === 0): ?>
            <div class="empty-state">
                <h3><?= $selected_shop_id ? 'No medicines listed for this store' : 'No medicines found' ?></h3>
                <p><?= $selected_shop_id ? "The admin hasn't added inventory for this store yet." : 'Try a different search term.' ?></p>
            </div>
        <?php else: ?>
        <div class="medicine-grid">
            <?php while ($med = $medicines->fetch_assoc()):
                $stock = (int)$med['shop_stock'];
                $out   = $stock === 0;
                $low   = $stock > 0 && $stock <= 5;
            ?>
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
                <p style="color:var(--primary);font-weight:700;margin:8px 0;">₹<?= number_format($med['shop_price'], 2) ?></p>

                <?php if ($selected_shop_id): ?>
                    <?php if ($out): ?>
                        <span class="stock-badge stock-out">Out of stock</span>
                        <button class="add-btn" style="background:#94a3b8;cursor:not-allowed;" disabled>Out of Stock</button>
                    <?php else: ?>
                        <?php if ($low): ?>
                            <span class="stock-badge stock-low">Only <?= $stock ?> left</span>
                        <?php else: ?>
                            <span class="stock-badge stock-ok">In stock (<?= $stock ?>)</span>
                        <?php endif; ?>
                        <button class="add-btn"
                                onclick="addToCart(<?= $med['id'] ?>,'<?= addslashes($med['name']) ?>',<?= $med['shop_price'] ?>,<?= $med['requires_prescription'] ?>,<?= $stock ?>)">
                            Add to Cart
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="add-btn" style="background:#94a3b8;cursor:not-allowed;" disabled>Select a Store First</button>
                <?php endif; ?>
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
    <?php if ($selected_shop): ?>
    <div style="background:#e6f4f3;padding:10px 20px;font-size:12px;color:#00796b;font-weight:600;border-bottom:1px solid #b2dfdb;">
        🏪 Delivering from: <?= htmlspecialchars($selected_shop['name']) ?>
    </div>
    <?php endif; ?>
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
        <div id="rx-warning" style="display:none;background:#fef9c3;border:1px solid #fbbf24;border-radius:8px;padding:10px;margin:10px 0;font-size:13px;color:#854d0e;">
            ⚠️ Your cart has Rx medicines. Please <a href="upload_prescription.php">upload a prescription</a>.
        </div>
        <button class="checkout-btn" onclick="checkout()">Confirm Order</button>
    </div>
</div>

<script>
const SHOP_ID    = <?= $selected_shop_id ? $selected_shop_id : 'null' ?>;
const SHOPS      = <?= $shops_json ?>;
const SEARCH_Q   = <?= json_encode($search) ?>;

/* ── Nearest store finder ── */
function haversine(la1, lo1, la2, lo2) {
    const R = 6371000, r = Math.PI / 180;
    const dLa = (la2-la1)*r, dLo = (lo2-lo1)*r;
    const a = Math.sin(dLa/2)**2 + Math.cos(la1*r)*Math.cos(la2*r)*Math.sin(dLo/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}
function fmtDist(m) { return m < 1000 ? `${Math.round(m)} m away` : `${(m/1000).toFixed(1)} km away`; }

function findNearest() {
    if (!navigator.geolocation) { setLocStatus('Geolocation not supported.', true); return; }
    const btn = document.getElementById('nearest-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="dot-pulse"></span> Detecting location…';
    setLocStatus('Requesting your location…');

    navigator.geolocation.getCurrentPosition(pos => {
        const uLat = pos.coords.latitude, uLon = pos.coords.longitude;
        const located = SHOPS.filter(s => s.lat && s.lng);

        if (!located.length) {
            setLocStatus('No shop locations set yet. Admin needs to add addresses with pincode.', true);
            btn.disabled = false;
            btn.innerHTML = '<span class="dot-pulse"></span> Find Nearest Store';
            return;
        }

        const withDist = located.map(s => ({ ...s, dist: haversine(uLat, uLon, s.lat, s.lng) }))
                                 .sort((a, b) => a.dist - b.dist);

        // Show distances and sort sidebar list
        const list = document.getElementById('shop-list');
        withDist.forEach((s, i) => {
            const distEl = document.getElementById('dist-' + s.id);
            if (distEl) { distEl.textContent = '📍 ' + fmtDist(s.dist); distEl.style.display = 'block'; }
            const card = document.getElementById('shop-item-' + s.id);
            if (card) {
                card.querySelector('.nearest-badge')?.remove();
                if (i === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'nearest-badge';
                    badge.textContent = 'Nearest';
                    card.appendChild(badge);
                }
                list.appendChild(card);
            }
        });

        const nearest = withDist[0];
        setLocStatus(`✅ Nearest: ${nearest.name} (${fmtDist(nearest.dist)})`);
        btn.innerHTML = '<span class="dot-pulse"></span> Refresh Location';
        btn.disabled = false;

        // Auto-select nearest store
        const q = SEARCH_Q ? `&q=${encodeURIComponent(SEARCH_Q)}` : '';
        window.location.href = `?shop_id=${nearest.id}${q}`;

    }, err => {
        btn.disabled = false;
        btn.innerHTML = '<span class="dot-pulse"></span> Find Nearest Store';
        const msgs = { 1:'Permission denied. Please allow location access.', 2:'Could not get position. Try again.', 3:'Timed out. Try again.' };
        setLocStatus(msgs[err.code] || 'Location error. Try again.', true);
    }, { timeout: 12000, maximumAge: 60000, enableHighAccuracy: true });
}

function setLocStatus(msg, err) {
    const el = document.getElementById('locate-status');
    el.textContent = msg;
    el.className = err ? 'err' : '';
}

/* ── Cart ── */
let cart = [], hasRx = false;

// stock map: medicine id → available stock (set from PHP when adding to cart)
const stockMap = {};

function addToCart(id, name, price, requiresRx, stock) {
    if (!SHOP_ID) { alert('Please select a store first.'); return; }

    // Store stock limit for this item
    stockMap[id] = stock;

    const ex = cart.find(i => i.id === id);
    if (ex) {
        if (ex.qty >= stock) {
            alert(`Only ${stock} units of "${name}" are available.`);
            return;
        }
        ex.qty++;
    } else {
        cart.push({ id, name, price, qty: 1, requiresRx: !!requiresRx });
    }
    updateUI();
    document.getElementById('cart-panel').classList.add('active');
}

function changeQty(id, delta) {
    const it = cart.find(i => i.id === id);
    if (!it) return;
    const newQty = it.qty + delta;
    if (delta > 0 && stockMap[id] !== undefined && newQty > stockMap[id]) {
        alert(`Only ${stockMap[id]} units available for "${it.name}".`);
        return;
    }
    it.qty = newQty;
    if (it.qty <= 0) cart = cart.filter(i => i.id !== id);
    updateUI();
}

function updateUI() {
    document.getElementById('cart-count').innerText = cart.reduce((s, i) => s + i.qty, 0);
    hasRx = cart.some(i => i.requiresRx);
    document.getElementById('rx-warning').style.display = hasRx ? 'block' : 'none';
    let sub = 0;
    document.getElementById('cart-items').innerHTML = cart.map(it => {
        sub += it.price * it.qty;
        const maxReached = stockMap[it.id] !== undefined && it.qty >= stockMap[it.id];
        return `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #eee;padding-bottom:10px;">
            <div style="flex:1">
                <strong>${it.name}</strong>
                ${it.requiresRx ? '<span style="font-size:10px;background:#fef9c3;color:#854d0e;padding:1px 6px;border-radius:8px;margin-left:4px;">Rx</span>' : ''}
                <br><small>₹${it.price} × ${it.qty}</small>
                ${maxReached ? `<br><small style="color:#e53e3e;">Max stock reached</small>` : ''}
            </div>
            <div class="qty-controls">
                <button class="qty-btn" onclick="changeQty(${it.id},-1)">-</button>
                <span>${it.qty}</span>
                <button class="qty-btn" onclick="changeQty(${it.id},1)" ${maxReached ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''}>+</button>
            </div></div>`;
    }).join('');
    const cgst=sub*.06, sgst=sub*.06, dlv=cart.length?20:0;
    document.getElementById('items-total').innerText  = sub.toFixed(2);
    document.getElementById('cgst').innerText         = cgst.toFixed(2);
    document.getElementById('sgst').innerText         = sgst.toFixed(2);
    document.getElementById('delivery-fee').innerText = dlv;
    document.getElementById('total-price').innerText  = (sub+cgst+sgst+dlv).toFixed(2);
}

function toggleCart() { document.getElementById('cart-panel').classList.toggle('active'); }

function clearSearch() {
    document.getElementById('search-q').value = '';
    const q = SEARCH_Q ? `?shop_id=${SHOP_ID || ''}` : (SHOP_ID ? `?shop_id=${SHOP_ID}` : '?');
    window.location.href = SHOP_ID ? `?shop_id=${SHOP_ID}` : '?';
}

function checkout() {
    if (!cart.length) { alert("Cart is empty!"); return; }
    if (!SHOP_ID) { alert("Please select a store before placing an order."); return; }
    <?php if (!isset($_SESSION['user_id'])): ?>
    if (confirm("Please login to place an order. Go to login page?")) window.location.href = 'login.php';
    return;
    <?php endif; ?>
    const f = document.createElement('form');
    f.method = 'POST'; f.action = 'place_order.php';
    const add = (n, v) => { const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i); };
    add('cart_data', JSON.stringify(cart));
    add('shop_id', SHOP_ID);
    document.body.appendChild(f); f.submit();
}
</script>
</body>
</html>