<?php
// admin/view_prescriptions.php
session_start();
require '../includes/db.php';

// Admin auth guard
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$msg = '';

// Handle Approve / Reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rx_id'], $_POST['action'])) {
    $rx_id  = (int) $_POST['rx_id'];
    $action = trim($_POST['action']);
    $note   = trim($_POST['admin_note'] ?? '');

    // Sanitize note — strip tags, max 300 chars
    $note = strip_tags($note);
    $note = substr($note, 0, 300);

    if ($rx_id <= 0) {
        $msg = "Invalid prescription ID.";
    } elseif (!in_array($action, ['approved', 'rejected'], true)) {
        $msg = "Invalid action.";
    } elseif ($action === 'rejected' && empty($note)) {
        $msg = "Please provide a reason when rejecting a prescription.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE prescriptions 
             SET status = ?, admin_note = ?, reviewed_at = NOW(), reviewed_by = ?
             WHERE id = ?"
        );
        $stmt->bind_param("ssii", $action, $note, $admin_id, $rx_id);
        $stmt->execute();

        // If approved, update linked order status to 'confirmed'
        if ($action === 'approved') {
            $upd = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE prescription_id = ? AND status = 'rx_pending'");
            $upd->bind_param("i", $rx_id);
            $upd->execute();
        }

        // If rejected, cancel linked order
        if ($action === 'rejected') {
            $upd = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE prescription_id = ? AND status = 'rx_pending'");
            $upd->bind_param("i", $rx_id);
            $upd->execute();
        }

        $msg = "Prescription #$rx_id has been " . strtoupper($action) . ".";
    }
}

// Filter
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters)) $filter = 'pending';

$where = ($filter === 'all') ? '' : "WHERE p.status = '$filter'";

$query = "
    SELECT p.id, p.file_path, p.original_filename, p.status, p.admin_note,
           p.uploaded_at, p.reviewed_at,
           u.full_name, u.email, u.phone
    FROM prescriptions p
    JOIN users u ON p.user_id = u.id
    $where
    ORDER BY p.uploaded_at DESC
";
$result = $conn->query($query);

// Stats
$stats = $conn->query("SELECT status, COUNT(*) as cnt FROM prescriptions GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$statMap = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($stats as $s) $statMap[$s['status']] = $s['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescriptions | Admin QuickMed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; }

        /* Sidebar */
        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #1e293b; padding: 24px 0; }
        .sidebar .logo { color: white; font-size: 20px; font-weight: 700; padding: 0 24px 24px; border-bottom: 1px solid #334155; }
        .sidebar .logo span { color: #ffb300; }
        .sidebar a { display: block; padding: 14px 24px; color: #94a3b8; text-decoration: none; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #00796b; color: white; }

        /* Main */
        .main { margin-left: 220px; padding: 30px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 20px; }

        /* Stat cards */
        .stats { display: flex; gap: 16px; margin-bottom: 24px; }
        .stat-card { flex: 1; background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
        .stat-card .num { font-size: 32px; font-weight: 700; }
        .stat-card .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .pending-num  { color: #d97706; }
        .approved-num { color: #16a34a; }
        .rejected-num { color: #dc2626; }

        /* Filter tabs */
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .tabs a { padding: 8px 20px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 600; background: white; color: #64748b; border: 1px solid #e2e8f0; }
        .tabs a.active { background: #00796b; color: white; border-color: #00796b; }

        /* Alert msg */
        .msg { background: #dcfce7; color: #166534; padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; }

        /* Table */
        .rx-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .rx-table th { background: #00796b; color: white; padding: 12px 16px; text-align: left; font-size: 13px; }
        .rx-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
        .rx-table tr:last-child td { border-bottom: none; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.pending  { background: #fef9c3; color: #854d0e; }
        .badge.approved { background: #dcfce7; color: #166534; }
        .badge.rejected { background: #fee2e2; color: #991b1b; }

        /* Image preview */
        .rx-img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; cursor: pointer; }

        /* Action form */
        .action-form textarea { width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px; resize: vertical; margin-bottom: 8px; }
        .btn-approve { background: #16a34a; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; }
        .btn-reject  { background: #dc2626; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; }
        .btn-approve:hover { background: #15803d; }
        .btn-reject:hover  { background: #b91c1c; }

        /* Lightbox */
        #lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center; }
        #lightbox.show { display:flex; }
        #lightbox img { max-width:90vw; max-height:90vh; border-radius:10px; }
        #lightbox .close { position:absolute; top:20px; right:30px; color:white; font-size:36px; cursor:pointer; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">Quick<span>Med</span> Admin</div>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="view_prescriptions.php" class="active">📄 Prescriptions</a>
    <a href="manage_orders.php">📦 Orders</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
    <div class="page-title">📄 Prescription Review</div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="num pending-num"><?= $statMap['pending'] ?></div>
            <div class="label">Pending Review</div>
        </div>
        <div class="stat-card">
            <div class="num approved-num"><?= $statMap['approved'] ?></div>
            <div class="label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="num rejected-num"><?= $statMap['rejected'] ?></div>
            <div class="label">Rejected</div>
        </div>
    </div>

    <?php if ($msg): ?><div class="msg">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <!-- Filter Tabs -->
    <div class="tabs">
        <a href="?filter=pending"  class="<?= $filter==='pending'  ? 'active':'' ?>">⏳ Pending</a>
        <a href="?filter=approved" class="<?= $filter==='approved' ? 'active':'' ?>">✅ Approved</a>
        <a href="?filter=rejected" class="<?= $filter==='rejected' ? 'active':'' ?>">❌ Rejected</a>
        <a href="?filter=all"      class="<?= $filter==='all'      ? 'active':'' ?>">📋 All</a>
    </div>

    <!-- Table -->
    <table class="rx-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Patient</th>
                <th>Prescription</th>
                <th>Uploaded</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No prescriptions found.</td></tr>
        <?php endif; ?>
        <?php while ($rx = $result->fetch_assoc()): ?>
            <tr>
                <td><strong>#<?= $rx['id'] ?></strong></td>
                <td>
                    <strong><?= htmlspecialchars($rx['full_name']) ?></strong><br>
                    <small style="color:#64748b;"><?= htmlspecialchars($rx['email']) ?></small><br>
                    <small style="color:#64748b;"><?= htmlspecialchars($rx['phone'] ?? '') ?></small>
                </td>
                <td>
                    <?php
                    $ext = strtolower(pathinfo($rx['file_path'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png'])):
                    ?>
                        <img src="../<?= htmlspecialchars($rx['file_path']) ?>"
                             class="rx-img"
                             onclick="openLightbox(this.src)"
                             title="Click to enlarge">
                    <?php else: ?>
                        <a href="../<?= htmlspecialchars($rx['file_path']) ?>" target="_blank">
                            📄 <?= htmlspecialchars($rx['original_filename']) ?>
                        </a>
                    <?php endif; ?>
                </td>
                <td><?= date('d M Y', strtotime($rx['uploaded_at'])) ?><br>
                    <small><?= date('h:i A', strtotime($rx['uploaded_at'])) ?></small>
                </td>
                <td>
                    <span class="badge <?= $rx['status'] ?>"><?= ucfirst($rx['status']) ?></span>
                    <?php if ($rx['admin_note']): ?>
                        <br><small style="color:#64748b; margin-top:4px; display:block;"><?= htmlspecialchars($rx['admin_note']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rx['status'] === 'pending'): ?>
                    <div class="action-form">
                        <form method="POST" onsubmit="return validateRxAction(this)">
                            <input type="hidden" name="rx_id" value="<?= $rx['id'] ?>">
                            <textarea name="admin_note" placeholder="Note to patient (required if rejecting)..." rows="2" maxlength="300"></textarea>
                            <div style="display:flex; gap:8px;">
                                <button type="submit" name="action" value="approved" class="btn-approve">✅ Approve</button>
                                <button type="submit" name="action" value="rejected" class="btn-reject">❌ Reject</button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                        <small style="color:#94a3b8;">Reviewed on<br><?= date('d M Y', strtotime($rx['reviewed_at'])) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Lightbox for image preview -->
<div id="lightbox" onclick="closeLightbox()">
    <span class="close">&times;</span>
    <img id="lightbox-img" src="" alt="Prescription">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('show');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
}

function validateRxAction(form) {
    const action = document.activeElement.value; // 'approved' or 'rejected'
    const note   = form.querySelector('textarea[name="admin_note"]').value.trim();
    if (action === 'rejected' && note.length === 0) {
        alert('Please provide a reason before rejecting this prescription.');
        return false;
    }
    return confirm(action === 'approved'
        ? 'Approve this prescription and confirm the linked order?'
        : 'Reject this prescription and cancel the linked order?');
}
</script>
</body>
</html>