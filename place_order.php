<?php
// place_order.php
session_start();
require 'includes/db.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error   = '';

// --- Handle Order Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {

    $cart_data = json_decode($_POST['cart_data'], true);

    if (!is_array($cart_data) || count($cart_data) === 0) {
        $error = "Your cart is empty.";
    } elseif (count($cart_data) > 50) {
        $error = "Too many items in cart.";
    } else {
        // Validate each cart item shape
        $valid_items = true;
        foreach ($cart_data as $item) {
            $id  = isset($item['id'])  ? (int)$item['id']  : 0;
            $qty = isset($item['qty']) ? (int)$item['qty'] : 0;
            if ($id <= 0 || $qty <= 0 || $qty > 99) {
                $valid_items = false;
                break;
            }
        }
        if (!$valid_items) {
            $error = "Invalid cart data. Please refresh and try again.";
        } else {
        // Fetch delivery address from user profile
        $u = $conn->query("SELECT address FROM users WHERE id = $user_id")->fetch_assoc();
        $delivery_address = $u['address'] ?? 'Not provided';

        // Check if any item needs prescription
        $needs_rx  = false;
        $rx_id     = null;
        $subtotal  = 0;

        // Validate each item against DB price (security: don't trust client-side price)
        $cart_ids     = array_map(fn($i) => (int)$i['id'], $cart_data);
        $ids_str      = implode(',', $cart_ids);
        $db_medicines = [];
        $res = $conn->query("SELECT id, name, price, requires_prescription FROM medicines WHERE id IN ($ids_str)");
        while ($m = $res->fetch_assoc()) {
            $db_medicines[$m['id']] = $m;
        }

        foreach ($cart_data as $item) {
            $med = $db_medicines[(int)$item['id']] ?? null;
            if (!$med) continue;
            $subtotal += $med['price'] * (int)$item['qty'];
            if ($med['requires_prescription']) $needs_rx = true;
        }

        // Check if user has an approved prescription
        if ($needs_rx) {
            $rx_res = $conn->query(
                "SELECT id FROM prescriptions WHERE user_id = $user_id AND status = 'approved'
                 ORDER BY reviewed_at DESC LIMIT 1"
            );
            if ($rx_res->num_rows > 0) {
                $rx_id        = $rx_res->fetch_assoc()['id'];
                $order_status = 'confirmed';
            } else {
                // Check if pending prescription exists
                $pending = $conn->query(
                    "SELECT id FROM prescriptions WHERE user_id = $user_id AND status = 'pending'
                     ORDER BY uploaded_at DESC LIMIT 1"
                );
                if ($pending->num_rows > 0) {
                    $rx_id        = $pending->fetch_assoc()['id'];
                    $order_status = 'rx_pending'; // waiting for admin approval
                } else {
                    // No prescription uploaded at all
                    $_SESSION['checkout_cart'] = $_POST['cart_data'];
                    header("Location: upload_prescription.php?needs_rx=1");
                    exit;
                }
            }
        } else {
            $order_status = 'confirmed';
        }

        $cgst          = round($subtotal * 0.06, 2);
        $sgst          = round($subtotal * 0.06, 2);
        $delivery_fee  = 20.00;
        $grand_total   = $subtotal + $cgst + $sgst + $delivery_fee;

        // Insert order
        $stmt = $conn->prepare(
            "INSERT INTO orders (user_id, prescription_id, subtotal, cgst, sgst, delivery_fee, grand_total, delivery_address, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iidddddss",
            $user_id, $rx_id,
            $subtotal, $cgst, $sgst, $delivery_fee, $grand_total,
            $delivery_address, $order_status
        );
        $stmt->execute();
        $order_id = $conn->insert_id;

        // Insert order items
        $item_stmt = $conn->prepare(
            "INSERT INTO order_items (order_id, medicine_id, medicine_name, price, qty) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($cart_data as $item) {
            $med = $db_medicines[(int)$item['id']] ?? null;
            if (!$med) continue;
            $med_id    = (int)$med['id'];
            $med_name  = (string)$med['name'];
            $med_price = (float)$med['price'];
            $item_qty  = (int)$item['qty'];
            $item_stmt->bind_param("iisdi",
                $order_id,
                $med_id,
                $med_name,
                $med_price,
                $item_qty
            );
            $item_stmt->execute();
        }

        // Redirect to order status page
        header("Location: order_status.php?order_id=$order_id&placed=1");
        exit;
        } // end valid_items
    }
}

// If GET or error, redirect home
header("Location: index.php");
exit;
?>