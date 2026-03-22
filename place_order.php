<?php
// place_order.php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {

    $cart_data = json_decode($_POST['cart_data'], true);
    $shop_id   = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;

    // Basic shape checks
    if (!is_array($cart_data) || count($cart_data) === 0 || count($cart_data) > 50) {
        header("Location: index.php"); exit;
    }
    if (!$shop_id) {
        header("Location: index.php"); exit;
    }

    // Validate shop exists and is active
    $shop_res = $conn->query("SELECT id FROM shops WHERE id = $shop_id AND is_active = 1");
    if ($shop_res->num_rows === 0) {
        header("Location: index.php"); exit;
    }

    // Validate each cart item shape
    foreach ($cart_data as $item) {
        $id  = isset($item['id'])  ? (int)$item['id']  : 0;
        $qty = isset($item['qty']) ? (int)$item['qty'] : 0;
        if ($id <= 0 || $qty <= 0 || $qty > 99) {
            header("Location: index.php"); exit;
        }
    }

    // Fetch each medicine from DB with shop-specific price AND stock
    $cart_ids = array_map(fn($i) => (int)$i['id'], $cart_data);
    $ids_str  = implode(',', $cart_ids);

    $db_medicines = [];
    $res = $conn->query("
        SELECT m.id, m.name, m.requires_prescription,
               COALESCE(sm.price, m.price)   AS price,
               COALESCE(sm.stock, m.stock)   AS stock
        FROM medicines m
        LEFT JOIN shop_medicines sm
               ON sm.medicine_id = m.id AND sm.shop_id = $shop_id
        WHERE m.id IN ($ids_str)
    ");
    while ($m = $res->fetch_assoc()) {
        $db_medicines[$m['id']] = $m;
    }

    // ── Stock check ──────────────────────────────────────────
    $stock_errors = [];
    foreach ($cart_data as $item) {
        $med = $db_medicines[(int)$item['id']] ?? null;
        if (!$med) continue;
        $qty_requested = (int)$item['qty'];
        if ($qty_requested > (int)$med['stock']) {
            $stock_errors[] = "{$med['name']}: only {$med['stock']} left in stock (you requested $qty_requested)";
        }
    }

    if (!empty($stock_errors)) {
        // Store errors in session and redirect back with flag
        $_SESSION['order_error'] = implode('<br>', $stock_errors);
        header("Location: index.php?stock_error=1");
        exit;
    }
    // ─────────────────────────────────────────────────────────

    // Delivery address
    $u = $conn->query("SELECT address FROM users WHERE id = $user_id")->fetch_assoc();
    $delivery_address = $u['address'] ?? 'Not provided';

    $subtotal = 0;
    $needs_rx = false;
    foreach ($cart_data as $item) {
        $med = $db_medicines[(int)$item['id']] ?? null;
        if (!$med) continue;
        $subtotal += $med['price'] * (int)$item['qty'];
        if ($med['requires_prescription']) $needs_rx = true;
    }

    // Prescription logic
    $rx_id = null;
    if ($needs_rx) {
        $rx_res = $conn->query(
            "SELECT id FROM prescriptions WHERE user_id = $user_id AND status = 'approved'
             ORDER BY reviewed_at DESC LIMIT 1"
        );
        if ($rx_res->num_rows > 0) {
            $rx_id        = $rx_res->fetch_assoc()['id'];
            $order_status = 'confirmed';
        } else {
            $pending = $conn->query(
                "SELECT id FROM prescriptions WHERE user_id = $user_id AND status = 'pending'
                 ORDER BY uploaded_at DESC LIMIT 1"
            );
            if ($pending->num_rows > 0) {
                $rx_id        = $pending->fetch_assoc()['id'];
                $order_status = 'rx_pending';
            } else {
                $_SESSION['checkout_cart']    = $_POST['cart_data'];
                $_SESSION['checkout_shop_id'] = $shop_id;
                header("Location: upload_prescription.php?needs_rx=1");
                exit;
            }
        }
    } else {
        $order_status = 'confirmed';
    }

    $cgst         = round($subtotal * 0.06, 2);
    $sgst         = round($subtotal * 0.06, 2);
    $delivery_fee = 20.00;
    $grand_total  = $subtotal + $cgst + $sgst + $delivery_fee;

    // ── Insert order ─────────────────────────────────────────
    $stmt = $conn->prepare(
        "INSERT INTO orders
            (user_id, shop_id, prescription_id, subtotal, cgst, sgst, delivery_fee, grand_total, delivery_address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iiidddddss",
        $user_id, $shop_id, $rx_id,
        $subtotal, $cgst, $sgst, $delivery_fee, $grand_total,
        $delivery_address, $order_status
    );

    if (!$stmt->execute()) {
        error_log("Order insert failed: " . $stmt->error);
        $_SESSION['order_error'] = "Could not place order. Please try again.";
        header("Location: index.php?order_error=1");
        exit;
    }

    $order_id = $conn->insert_id;

    // ── Insert order items & deduct stock ─────────────────────
    $item_stmt = $conn->prepare(
        "INSERT INTO order_items (order_id, medicine_id, medicine_name, price, qty) VALUES (?, ?, ?, ?, ?)"
    );
    // Deduct from shop_medicines first, fall back to medicines table
    $deduct_shop = $conn->prepare(
        "UPDATE shop_medicines SET stock = GREATEST(0, stock - ?) WHERE shop_id = ? AND medicine_id = ?"
    );
    $deduct_med = $conn->prepare(
        "UPDATE medicines SET stock = GREATEST(0, stock - ?) WHERE id = ?"
    );

    foreach ($cart_data as $item) {
        $med = $db_medicines[(int)$item['id']] ?? null;
        if (!$med) continue;

        $med_id    = (int)$med['id'];
        $med_name  = (string)$med['name'];
        $med_price = (float)$med['price'];
        $item_qty  = (int)$item['qty'];

        // Insert order item
        $item_stmt->bind_param("iisdi", $order_id, $med_id, $med_name, $med_price, $item_qty);
        $item_stmt->execute();

        // Deduct from shop_medicines stock
        $deduct_shop->bind_param("iii", $item_qty, $shop_id, $med_id);
        $deduct_shop->execute();

        // Also deduct from the main medicines table (global stock)
        $deduct_med->bind_param("ii", $item_qty, $med_id);
        $deduct_med->execute();
    }
    // ─────────────────────────────────────────────────────────

    header("Location: order_status.php?order_id=$order_id&placed=1");
    exit;
}

header("Location: index.php");
exit;
?>