<?php
// includes/db.php
// ------------------------------------
// MySQL connection using XAMPP defaults
// ------------------------------------

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // XAMPP default is empty password
define('DB_NAME', 'med_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>