<?php
// upload_prescription.php
session_start();
require 'includes/db.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['prescription'])) {
    $file     = $_FILES['prescription'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $allowed_exts  = ['jpg', 'jpeg', 'png', 'pdf'];
    $maxSize  = 5 * 1024 * 1024; // 5 MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed. Please try again.";
    } elseif (!in_array($file['type'], $allowed_types) || !in_array($ext, $allowed_exts)) {
        $error = "Only JPG, PNG, or PDF files are allowed.";
    } elseif ($file['size'] > $maxSize) {
        $error = "File too large. Maximum size is 5MB.";
    } elseif ($file['size'] === 0) {
        $error = "Uploaded file is empty. Please choose a valid file.";
    } else {
        // Create upload directory if it doesn't exist
        $uploadDir = 'uploads/prescriptions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename to avoid overwriting
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'rx_' . $user_id . '_' . time() . '.' . $ext;
        $filePath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Save record in DB
            $stmt = $conn->prepare(
                "INSERT INTO prescriptions (user_id, file_path, original_filename) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iss", $user_id, $filePath, $file['name']);

            if ($stmt->execute()) {
                $rx_id = $conn->insert_id;
                $_SESSION['last_rx_id'] = $rx_id; // used when placing order
                $success = "Prescription uploaded successfully! Our pharmacist will review it shortly.";
            } else {
                $error = "Database error. Please try again.";
            }
        } else {
            $error = "Could not save file. Check folder permissions.";
        }
    }
}

// Fetch user's past prescriptions
$history = $conn->prepare(
    "SELECT id, original_filename, status, admin_note, uploaded_at FROM prescriptions 
     WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 10"
);
$history->bind_param("i", $user_id);
$history->execute();
$rxList = $history->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Prescription | QuickMed</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .upload-container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .upload-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .upload-card h2 { color: #00796b; margin-bottom: 5px; }
        .drop-zone { border: 2px dashed #00796b; border-radius: 12px; padding: 40px; text-align: center; cursor: pointer; transition: 0.2s; background: #f0faf9; margin: 20px 0; }
        .drop-zone:hover { background: #e0f5f2; }
        .drop-zone input { display: none; }
        .drop-zone label { cursor: pointer; display: block; }
        .drop-zone .icon { font-size: 48px; }
        .drop-zone p { color: #666; font-size: 14px; margin: 8px 0 0; }
        .submit-btn { background: #00796b; color: white; border: none; padding: 14px 30px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; }
        .submit-btn:hover { background: #005f56; }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; font-weight: 500; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.success { background: #dcfce7; color: #166534; }
        .rx-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .rx-table th { background: #00796b; color: white; padding: 10px; text-align: left; }
        .rx-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge.pending  { background: #fef9c3; color: #854d0e; }
        .badge.approved { background: #dcfce7; color: #166534; }
        .badge.rejected { background: #fee2e2; color: #991b1b; }
        #file-preview { margin-top: 10px; font-size: 13px; color: #00796b; font-weight: 600; }
    </style>
</head>
<body>
<div class="upload-container">

    <!-- Back nav -->
    <p><a href="index.php" style="color:#00796b; text-decoration:none;">← Back to medicines</a></p>

    <div class="upload-card">
        <h2>📄 Upload Prescription</h2>
        <p style="color:#666; margin-top:0;">For medicines that require a prescription, upload a clear photo or PDF of your doctor's prescription.</p>

        <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="rx-form" novalidate>
            <div class="drop-zone" onclick="document.getElementById('rx-file').click()">
                <div class="icon">📋</div>
                <label for="rx-file">
                    <strong>Click to choose file</strong> or drag & drop here
                </label>
                <p>Accepted: JPG, PNG, PDF · Max size: 5MB</p>
                <input type="file" id="rx-file" name="prescription" accept=".jpg,.jpeg,.png,.pdf"
                       onchange="validateRxFile(this)">
            </div>
            <div id="file-preview"></div>
            <span id="err-rx-file" style="display:block; color:#dc2626; font-size:13px; margin: 4px 0 10px;"></span>
            <button type="submit" class="submit-btn">Upload Prescription</button>
        </form>
    </div>

    <!-- Upload History -->
    <?php if ($rxList->num_rows > 0): ?>
    <div class="upload-card" style="margin-top: 24px;">
        <h3>Your Upload History</h3>
        <table class="rx-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>File</th>
                    <th>Uploaded</th>
                    <th>Status</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($rx = $rxList->fetch_assoc()): ?>
                <tr>
                    <td><?= $rx['id'] ?></td>
                    <td><?= htmlspecialchars($rx['original_filename']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($rx['uploaded_at'])) ?></td>
                    <td><span class="badge <?= $rx['status'] ?>"><?= ucfirst($rx['status']) ?></span></td>
                    <td><?= htmlspecialchars($rx['admin_note'] ?? '—') ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<script>
const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'pdf'];
const MAX_SIZE_MB  = 5;

function validateRxFile(input) {
    const errEl   = document.getElementById('err-rx-file');
    const preview = document.getElementById('file-preview');
    errEl.textContent = '';
    preview.textContent = '';

    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const ext  = file.name.split('.').pop().toLowerCase();
    const sizeMB = file.size / (1024 * 1024);

    if (!ALLOWED_EXTS.includes(ext)) {
        errEl.textContent = '❌ Invalid file type. Only JPG, PNG, or PDF allowed.';
        input.value = '';
        return;
    }
    if (sizeMB > MAX_SIZE_MB) {
        errEl.textContent = `❌ File too large (${sizeMB.toFixed(1)} MB). Max allowed is 5 MB.`;
        input.value = '';
        return;
    }
    preview.textContent = `✅ Selected: ${file.name} (${sizeMB.toFixed(2)} MB)`;
}

document.getElementById('rx-form').addEventListener('submit', function (e) {
    const fileInput = document.getElementById('rx-file');
    const errEl     = document.getElementById('err-rx-file');
    if (!fileInput.files || fileInput.files.length === 0) {
        errEl.textContent = '❌ Please select a prescription file before uploading.';
        e.preventDefault();
    }
});
</script>
</body>
</html>