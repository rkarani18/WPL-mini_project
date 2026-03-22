<?php
// admin/manage_shops.php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

$msg = '';
$err = '';

function geocodeAddress($address) {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($address);
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: QuickMed/1.0\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return [null, null];
    $data = json_decode($raw, true);
    if (empty($data)) return [null, null];
    return [(float)$data[0]['lat'], (float)$data[0]['lon']];
}

function handleMedicineImage($fileKey, $existingUrl = '') {
    if (empty($_FILES[$fileKey]['name'])) return $existingUrl;
    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK) return $existingUrl;
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return $existingUrl;
    if ($file['size'] > 2 * 1024 * 1024) return $existingUrl;
    $uploadDir = dirname(__DIR__) . '/uploads/medicines/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'med_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) return 'uploads/medicines/' . $filename;
    return $existingUrl;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_shop') {
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $maps    = trim($_POST['maps_link'] ?? '');
        if (!$name) {
            $err = 'Shop name is required.';
        } else {
            [$lat, $lng] = geocodeAddress($address);
            $stmt = $conn->prepare("INSERT INTO shops (name, address, phone, maps_link, lat, lng) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssdd", $name, $address, $phone, $maps, $lat, $lng);
            $stmt->execute();
            $geo = ($lat && $lng) ? " Location detected." : " Could not detect location — add pincode and click Detect.";
            $msg = "Shop \"$name\" added.$geo";
        }
    }

    if ($action === 'regeocode') {
        $id = (int)$_POST['shop_id'];
        $r  = $conn->query("SELECT address FROM shops WHERE id = $id")->fetch_assoc();
        if ($r) {
            [$lat, $lng] = geocodeAddress($r['address']);
            if ($lat && $lng) {
                $conn->query("UPDATE shops SET lat=$lat, lng=$lng WHERE id=$id");
                $msg = "Location updated: $lat, $lng";
            } else {
                $err = "Could not geocode this address. Try adding city and pincode.";
            }
        }
    }

    if ($action === 'toggle_shop') {
        $id  = (int)$_POST['shop_id'];
        $cur = (int)$_POST['current'];
        $new = $cur ? 0 : 1;
        $conn->query("UPDATE shops SET is_active=$new WHERE id=$id");
        $msg = "Shop status updated.";
    }

    if ($action === 'delete_shop') {
        $id = (int)$_POST['shop_id'];
        $conn->query("DELETE FROM shops WHERE id=$id");
        $msg = "Shop deleted.";
        if (isset($_GET['shop']) && (int)$_GET['shop'] === $id) {
            header("Location: manage_shops.php"); exit;
        }
    }

    // Add medicine to shop — stock only, price comes from medicines table default
    if ($action === 'add_medicine') {
        $shop_id = (int)$_POST['shop_id'];
        $med_id  = (int)$_POST['medicine_id'];
        $stock   = (int)$_POST['stock'];
        if ($shop_id && $med_id && $stock >= 0) {
            // Fetch default price from medicines table
            $med_row = $conn->query("SELECT price FROM medicines WHERE id = $med_id")->fetch_assoc();
            $price   = $med_row ? (float)$med_row['price'] : 0.00;
            $stmt = $conn->prepare("
                INSERT INTO shop_medicines (shop_id, medicine_id, price, stock)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE stock = VALUES(stock), price = VALUES(price)
            ");
            $stmt->bind_param("iidi", $shop_id, $med_id, $price, $stock);
            $stmt->execute();
            $msg = "Medicine added to shop.";
        }
    }

    // Update stock only (inline save on inventory table)
    if ($action === 'update_stock') {
        $shop_id = (int)$_POST['shop_id'];
        $med_id  = (int)$_POST['medicine_id'];
        $stock   = (int)$_POST['stock'];
        $stmt = $conn->prepare("UPDATE shop_medicines SET stock = ? WHERE shop_id = ? AND medicine_id = ?");
        $stmt->bind_param("iii", $stock, $shop_id, $med_id);
        $stmt->execute();
        $msg = "Stock updated.";
    }

    if ($action === 'remove_medicine') {
        $shop_id = (int)$_POST['shop_id'];
        $med_id  = (int)$_POST['medicine_id'];
        $conn->query("DELETE FROM shop_medicines WHERE shop_id=$shop_id AND medicine_id=$med_id");
        $msg = "Medicine removed from shop.";
    }

    if ($action === 'add_catalogue_medicine') {
        $name  = trim($_POST['med_name'] ?? '');
        $brand = trim($_POST['med_brand'] ?? '');
        $price = (float)($_POST['med_price'] ?? 0);
        $stock = (int)($_POST['med_stock'] ?? 100);
        $rx    = isset($_POST['requires_prescription']) ? 1 : 0;
        $desc  = trim($_POST['med_description'] ?? '');
        $img   = handleMedicineImage('med_image');
        if (!$name) {
            $err = 'Medicine name is required.';
        } elseif ($price < 0) {
            $err = 'Price cannot be negative.';
        } else {
            $stmt = $conn->prepare("INSERT INTO medicines (name, brand, price, stock, requires_prescription, image_url, description) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssdisss", $name, $brand, $price, $stock, $rx, $img, $desc);
            if ($stmt->execute()) {
                $msg = "Medicine \"$name\" added to catalogue.";
            } else {
                $err = "Could not add medicine. Try again.";
            }
        }
    }

    if ($action === 'edit_catalogue_medicine') {
        $id    = (int)$_POST['med_id'];
        $name  = trim($_POST['med_name'] ?? '');
        $brand = trim($_POST['med_brand'] ?? '');
        $price = (float)($_POST['med_price'] ?? 0);
        $stock = (int)($_POST['med_stock'] ?? 0);
        $rx    = isset($_POST['requires_prescription']) ? 1 : 0;
        $desc  = trim($_POST['med_description'] ?? '');
        $existing = $conn->query("SELECT image_url FROM medicines WHERE id=$id")->fetch_assoc()['image_url'] ?? '';
        $img = handleMedicineImage('med_image', $existing);
        if (!$name) {
            $err = 'Medicine name is required.';
        } else {
            $stmt = $conn->prepare("UPDATE medicines SET name=?, brand=?, price=?, stock=?, requires_prescription=?, image_url=?, description=? WHERE id=?");
            $stmt->bind_param("ssdisssi", $name, $brand, $price, $stock, $rx, $img, $desc, $id);
            $stmt->execute();
            $msg = "Medicine updated.";
        }
    }

    if ($action === 'delete_catalogue_medicine') {
        $id = (int)$_POST['med_id'];
        $conn->query("DELETE FROM medicines WHERE id=$id");
        $msg = "Medicine deleted from catalogue.";
    }
}

$active_shop_id = isset($_GET['shop']) ? (int)$_GET['shop'] : null;
$shops          = $conn->query("SELECT * FROM shops ORDER BY name ASC");
$shops_arr      = [];
while ($s = $shops->fetch_assoc()) $shops_arr[] = $s;

$all_medicines  = $conn->query("SELECT * FROM medicines ORDER BY name ASC");
$medicines_arr  = [];
while ($m = $all_medicines->fetch_assoc()) $medicines_arr[] = $m;

$shop_inventory = [];
$active_shop    = null;
$in_shop_ids    = [];

if ($active_shop_id) {
    $r = $conn->query("SELECT * FROM shops WHERE id=$active_shop_id");
    $active_shop = $r->fetch_assoc();
    if ($active_shop) {
        $inv = $conn->query("
            SELECT sm.*, m.name AS med_name, m.brand, m.price AS default_price, m.requires_prescription
            FROM shop_medicines sm
            JOIN medicines m ON m.id = sm.medicine_id
            WHERE sm.shop_id = $active_shop_id
            ORDER BY m.name ASC
        ");
        while ($i = $inv->fetch_assoc()) $shop_inventory[] = $i;
        $in_shop_ids = array_column($shop_inventory, 'medicine_id');
    }
}

$pending_rx = $conn->query("SELECT COUNT(*) as c FROM prescriptions WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Shops | Admin QuickMed</title>
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
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 20px; }
        .msg { background: #dcfce7; color: #166534; padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; }
        .err { background: #fee2e2; color: #991b1b; padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; }
        .layout { display: grid; grid-template-columns: 300px 1fr 320px; gap: 20px; align-items: start; }
        .card { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 700; margin-bottom: 16px; color: #1e293b; }
        .field { margin-bottom: 12px; }
        .field label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; }
        .field input, .field textarea, .field select { width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-family: 'Inter', sans-serif; color: #1e293b; }
        .field input:focus, .field textarea:focus, .field select:focus { outline: none; border-color: #00796b; }
        .field textarea { resize: vertical; }
        .field-hint { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        .field-row { display: flex; gap: 10px; }
        .field-row .field { flex: 1; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 13px; color: #475569; }
        .checkbox-row input { width: auto; }
        .btn { display: inline-block; background: #00796b; color: white; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 13px; }
        .btn:hover { background: #005f56; }
        .btn-full { width: 100%; text-align: center; }
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; }
        .btn-green  { background: #00796b; color: white; } .btn-green:hover  { background: #005f56; }
        .btn-red    { background: #dc2626; color: white; } .btn-red:hover    { background: #b91c1c; }
        .btn-amber  { background: #d97706; color: white; } .btn-amber:hover  { background: #b45309; }
        .btn-blue   { background: #2563eb; color: white; } .btn-blue:hover   { background: #1d4ed8; }
        .btn-ghost  { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; } .btn-ghost:hover { background: #e2e8f0; }
        .shop-row { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 8px; border: 1px solid #e8eff0; margin-bottom: 8px; }
        .shop-row.active-shop { border-color: #00796b; background: #e6f4f3; }
        .shop-row .shop-info { flex: 1; min-width: 0; }
        .shop-row .shop-name { font-size: 13px; font-weight: 700; color: #1a202c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .shop-row .shop-addr { font-size: 11px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .shop-row .shop-actions { display: flex; gap: 4px; flex-shrink: 0; }
        .geo-ok  { font-size: 10px; background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 6px; font-weight: 700; }
        .geo-no  { font-size: 10px; background: #fef9c3; color: #854d0e; padding: 2px 6px; border-radius: 6px; font-weight: 700; }
        .inactive-label { font-size: 10px; background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 6px; font-weight: 700; }
        .inv-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .inv-table th { background: #00796b; color: white; padding: 9px 12px; text-align: left; font-size: 12px; }
        .inv-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .inv-table tr:last-child td { border-bottom: none; }
        .rx-pill { background: #fef9c3; color: #854d0e; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 6px; margin-left: 4px; }
        .inline-form { display: flex; gap: 5px; align-items: center; }
        .inline-form input[type=number] { width: 80px; padding: 5px 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px; }
        .inline-form input[type=number]:focus { outline: none; border-color: #00796b; }
        .cat-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .cat-table th { background: #1e293b; color: white; padding: 9px 12px; text-align: left; font-size: 12px; }
        .cat-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .cat-table tr:last-child td { border-bottom: none; }
        .upload-zone { border: 2px dashed #b2dfdb; border-radius: 10px; padding: 16px; text-align: center; cursor: pointer; background: #f0faf9; transition: 0.15s; font-size: 13px; color: #64748b; }
        .upload-zone:hover { border-color: #00796b; background: #e6f4f3; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: white; border-radius: 14px; padding: 28px; width: 480px; max-width: 95vw; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 18px; }
        .modal-close { float: right; background: none; border: none; font-size: 22px; cursor: pointer; color: #94a3b8; margin-top: -4px; }
        .empty-panel { text-align: center; padding: 40px 20px; color: #94a3b8; }
        .empty-panel strong { display: block; color: #64748b; font-size: 15px; margin-bottom: 6px; }
        .tab-bar { display: flex; gap: 4px; margin-bottom: 18px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0; }
        .tab-btn { background: none; border: none; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: #00796b; border-bottom-color: #00796b; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .price-tag { font-size: 12px; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 6px; }
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
    <a href="manage_shops.php" class="active">🏪 Shops</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
    <div class="page-title">🏪 Manage Shops &amp; Medicines</div>

    <?php if ($msg): ?><div class="msg">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="layout">

        <!-- COL 1: Shop list + Add shop -->
        <div>
            <div class="card">
                <div class="card-title">➕ Add New Shop</div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_shop">
                    <div class="field">
                        <label>Shop Name *</label>
                        <input type="text" name="name" placeholder="e.g. Apollo Pharmacy" required>
                    </div>
                    <div class="field">
                        <label>Address (include pincode)</label>
                        <textarea name="address" rows="2" placeholder="Shop 4, LBS Marg, Ghatkopar West, Mumbai 400086"></textarea>
                        <div class="field-hint">📍 Pincode helps auto-detect location for nearest store</div>
                    </div>
                    <div class="field">
                        <label>Phone</label>
                        <input type="text" name="phone" placeholder="022-12345678">
                    </div>
                    <div class="field">
                        <label>Google Maps Link (optional)</label>
                        <input type="url" name="maps_link" placeholder="https://maps.google.com/?q=...">
                    </div>
                    <button type="submit" class="btn btn-full">Add Shop</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title">All Shops</div>
                <?php if (empty($shops_arr)): ?>
                    <p style="color:#94a3b8;font-size:13px">No shops added yet.</p>
                <?php endif; ?>
                <?php foreach ($shops_arr as $s): ?>
                <div class="shop-row <?= ($active_shop_id == $s['id']) ? 'active-shop' : '' ?>">
                    <div class="shop-info">
                        <div class="shop-name"><?= htmlspecialchars($s['name']) ?></div>
                        <div class="shop-addr"><?= htmlspecialchars(mb_substr($s['address'] ?? '', 0, 38)) ?><?= mb_strlen($s['address'] ?? '') > 38 ? '…' : '' ?></div>
                        <div style="margin-top:4px;display:flex;gap:4px;flex-wrap:wrap">
                            <?php if ($s['lat'] && $s['lng']): ?>
                                <span class="geo-ok">📍 Located</span>
                            <?php else: ?>
                                <span class="geo-no">⚠ No location</span>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="regeocode">
                                    <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn-sm btn-blue" style="font-size:10px;padding:2px 8px">Detect</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$s['is_active']): ?>
                                <span class="inactive-label">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="shop-actions">
                        <a href="?shop=<?= $s['id'] ?>" class="btn-sm btn-green" style="text-decoration:none">Manage</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_shop">
                            <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="current" value="<?= $s['is_active'] ?>">
                            <button type="submit" class="btn-sm btn-amber"><?= $s['is_active'] ? 'Off' : 'On' ?></button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this shop?')">
                            <input type="hidden" name="action" value="delete_shop">
                            <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-sm btn-red">✕</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- COL 2: Shop inventory -->
        <div>
            <?php if ($active_shop): ?>
            <div class="card">
                <div class="card-title">🏪 <?= htmlspecialchars($active_shop['name']) ?></div>

                <div class="tab-bar">
                    <button class="tab-btn active" onclick="switchTab(event,'tab-inv')">📦 Inventory</button>
                    <button class="tab-btn" onclick="switchTab(event,'tab-add')">➕ Add Medicine</button>
                </div>

                <!-- Current inventory -->
                <div id="tab-inv" class="tab-panel active">
                    <?php if (empty($shop_inventory)): ?>
                        <div class="empty-panel">
                            <strong>No medicines added yet</strong>
                            <p style="font-size:13px;margin-top:4px">Go to "Add Medicine" tab to add from the catalogue.</p>
                        </div>
                    <?php else: ?>
                    <table class="inv-table">
                        <thead>
                            <tr><th>Medicine</th><th>Default Price</th><th>Stock</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($shop_inventory as $inv): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($inv['med_name']) ?>
                                <?php if ($inv['requires_prescription']): ?><span class="rx-pill">Rx</span><?php endif; ?>
                                <?php if ($inv['brand']): ?><br><small style="color:#94a3b8"><?= htmlspecialchars($inv['brand']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="price-tag">₹<?= number_format($inv['default_price'], 2) ?></span></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="update_stock">
                                    <input type="hidden" name="shop_id" value="<?= $active_shop_id ?>">
                                    <input type="hidden" name="medicine_id" value="<?= $inv['medicine_id'] ?>">
                                    <input type="number" name="stock" value="<?= $inv['stock'] ?>" min="0" required title="Stock qty">
                                    <button type="submit" class="btn-sm btn-green">Save</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Remove from this shop?')">
                                    <input type="hidden" name="action" value="remove_medicine">
                                    <input type="hidden" name="shop_id" value="<?= $active_shop_id ?>">
                                    <input type="hidden" name="medicine_id" value="<?= $inv['medicine_id'] ?>">
                                    <button type="submit" class="btn-sm btn-red">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Add medicine to shop — stock only -->
                <div id="tab-add" class="tab-panel">
                    <p style="font-size:13px;color:#64748b;margin-bottom:16px;">
                        Price is always the medicine's default price from the catalogue.
                        Just select the medicine and enter how many units this shop has.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_medicine">
                        <input type="hidden" name="shop_id" value="<?= $active_shop_id ?>">
                        <div class="field">
                            <label>Medicine</label>
                            <select name="medicine_id" required onchange="showDefaultPrice(this)">
                                <option value="">— Select medicine —</option>
                                <?php foreach ($medicines_arr as $m): ?>
                                <option value="<?= $m['id'] ?>" data-price="<?= $m['price'] ?>">
                                    <?= htmlspecialchars($m['name']) ?><?= $m['brand'] ? ' ('.$m['brand'].')' : '' ?>
                                    <?= $m['requires_prescription'] ? ' [Rx]' : '' ?>
                                    — ₹<?= number_format($m['price'], 2) ?>
                                    <?= in_array($m['id'], $in_shop_ids) ? ' ✓ already added' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="price-preview" style="display:none;background:#f0faf9;border:1px solid #b2dfdb;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#00796b;">
                            <strong>Price:</strong> ₹<span id="price-val"></span> <span style="color:#64748b;font-weight:400;">(default from catalogue)</span>
                        </div>
                        <div class="field">
                            <label>Stock Quantity</label>
                            <input type="number" name="stock" min="0" value="100" required placeholder="e.g. 50">
                        </div>
                        <button type="submit" class="btn btn-full">Add to <?= htmlspecialchars($active_shop['name']) ?></button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <div class="card">
                <div class="empty-panel">
                    <div style="font-size:40px;margin-bottom:12px">🏪</div>
                    <strong>Select a shop to manage its inventory</strong>
                    <p style="font-size:13px;margin-top:6px">Click "Manage" next to any shop on the left</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- COL 3: Medicine catalogue -->
        <div>
            <div class="card">
                <div class="card-title">💊 Add New Medicine to Catalogue</div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_catalogue_medicine">
                    <div class="field">
                        <label>Medicine Name *</label>
                        <input type="text" name="med_name" placeholder="e.g. Paracetamol 500mg" required>
                    </div>
                    <div class="field">
                        <label>Brand / Manufacturer</label>
                        <input type="text" name="med_brand" placeholder="e.g. Cipla">
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label>Default Price (₹) *</label>
                            <input type="number" name="med_price" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="field">
                            <label>Default Stock</label>
                            <input type="number" name="med_stock" min="0" value="100">
                        </div>
                    </div>
                    <div class="checkbox-row">
                        <input type="checkbox" name="requires_prescription" id="rx_check">
                        <label for="rx_check">Requires Prescription (Rx)</label>
                    </div>
                    <div class="field">
                        <label>Medicine Image</label>
                        <div class="upload-zone" onclick="document.getElementById('add-img-input').click()">
                            <img id="add-img-preview" src="" alt="" style="display:none;max-height:80px;border-radius:6px;margin-bottom:6px;">
                            <div id="add-img-placeholder">📷 Click to choose image (JPG, PNG, WebP · max 2 MB)</div>
                            <input type="file" id="add-img-input" name="med_image" accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none" onchange="previewImage(this,'add-img-preview','add-img-placeholder')">
                        </div>
                    </div>
                    <div class="field">
                        <label>Description (optional)</label>
                        <textarea name="med_description" rows="2" placeholder="Brief description"></textarea>
                    </div>
                    <button type="submit" class="btn btn-full">Add to Catalogue</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title">All Medicines <span style="color:#94a3b8;font-weight:400;font-size:13px">(<?= count($medicines_arr) ?>)</span></div>
                <table class="cat-table">
                    <thead><tr><th>Name</th><th>Price</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($medicines_arr as $m): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($m['name']) ?>
                            <?php if ($m['requires_prescription']): ?><span class="rx-pill">Rx</span><?php endif; ?>
                            <?php if ($m['brand']): ?><br><small style="color:#94a3b8"><?= htmlspecialchars($m['brand']) ?></small><?php endif; ?>
                        </td>
                        <td>₹<?= number_format($m['price'], 2) ?></td>
                        <td>
                            <div style="display:flex;gap:4px">
                                <button class="btn-sm btn-ghost" onclick="openEdit(<?= htmlspecialchars(json_encode($m)) ?>)">Edit</button>
                                <form method="POST" onsubmit="return confirm('Delete from entire catalogue?')">
                                    <input type="hidden" name="action" value="delete_catalogue_medicine">
                                    <input type="hidden" name="med_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn-sm btn-red">✕</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Edit medicine modal -->
<div class="modal-overlay" id="edit-modal" onclick="closeEditOnBackdrop(event)">
    <div class="modal">
        <button class="modal-close" onclick="closeEdit()">×</button>
        <div class="modal-title">✏️ Edit Medicine</div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_catalogue_medicine">
            <input type="hidden" name="med_id" id="edit-id">
            <div class="field">
                <label>Medicine Name *</label>
                <input type="text" name="med_name" id="edit-name" required>
            </div>
            <div class="field">
                <label>Brand / Manufacturer</label>
                <input type="text" name="med_brand" id="edit-brand">
            </div>
            <div class="field-row">
                <div class="field">
                    <label>Default Price (₹)</label>
                    <input type="number" name="med_price" id="edit-price" step="0.01" min="0" required>
                </div>
                <div class="field">
                    <label>Default Stock</label>
                    <input type="number" name="med_stock" id="edit-stock" min="0">
                </div>
            </div>
            <div class="checkbox-row">
                <input type="checkbox" name="requires_prescription" id="edit-rx">
                <label for="edit-rx">Requires Prescription (Rx)</label>
            </div>
            <div class="field">
                <label>Medicine Image</label>
                <div class="upload-zone" onclick="document.getElementById('edit-img-input').click()">
                    <img id="edit-img-preview" src="" alt="" style="display:none;max-height:80px;border-radius:6px;margin-bottom:6px;">
                    <div id="edit-img-placeholder">📷 Click to replace image</div>
                    <input type="file" id="edit-img-input" name="med_image" accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none" onchange="previewImage(this,'edit-img-preview','edit-img-placeholder')">
                </div>
                <div id="edit-current-img" style="margin-top:6px;font-size:11px;color:#64748b;"></div>
            </div>
            <div class="field">
                <label>Description</label>
                <textarea name="med_description" id="edit-desc" rows="2"></textarea>
            </div>
            <div style="display:flex;gap:10px;margin-top:4px">
                <button type="submit" class="btn" style="flex:1">Save Changes</button>
                <button type="button" class="btn btn-ghost" onclick="closeEdit()" style="flex:1">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(e, id) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    e.target.classList.add('active');
    document.getElementById(id).classList.add('active');
}

function showDefaultPrice(sel) {
    const opt     = sel.options[sel.selectedIndex];
    const price   = opt.dataset.price;
    const preview = document.getElementById('price-preview');
    if (price && sel.value) {
        document.getElementById('price-val').textContent = parseFloat(price).toFixed(2);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function previewImage(input, previewId, placeholderId) {
    const preview     = document.getElementById(previewId);
    const placeholder = document.getElementById(placeholderId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.textContent = '✅ ' + input.files[0].name;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openEdit(med) {
    document.getElementById('edit-id').value    = med.id;
    document.getElementById('edit-name').value  = med.name;
    document.getElementById('edit-brand').value = med.brand || '';
    document.getElementById('edit-price').value = med.price;
    document.getElementById('edit-stock').value = med.stock;
    document.getElementById('edit-rx').checked  = med.requires_prescription == 1;
    document.getElementById('edit-desc').value  = med.description || '';
    const preview   = document.getElementById('edit-img-preview');
    const curr      = document.getElementById('edit-current-img');
    const fileInput = document.getElementById('edit-img-input');
    fileInput.value = '';
    if (med.image_url) {
        preview.src = '../' + med.image_url;
        preview.style.display = 'block';
        curr.textContent = 'Current: ' + med.image_url.split('/').pop();
        document.getElementById('edit-img-placeholder').textContent = '📷 Click to replace image';
    } else {
        preview.style.display = 'none';
        curr.textContent = 'No image yet';
        document.getElementById('edit-img-placeholder').textContent = '📷 Click to choose image';
    }
    document.getElementById('edit-modal').classList.add('show');
}
function closeEdit() { document.getElementById('edit-modal').classList.remove('show'); }
function closeEditOnBackdrop(e) { if (e.target === document.getElementById('edit-modal')) closeEdit(); }
</script>
</body>
</html>