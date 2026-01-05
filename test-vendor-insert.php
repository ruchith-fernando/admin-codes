<?php
require_once 'connections/connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("No mysqli \$conn");
}
$conn->set_charset('utf8mb4');

try {
    $sql = "INSERT INTO tbl_admin_vendors (vendor_name, vendor_type) VALUES ('DEBUG TEST VENDOR', 'WATER')";
    if ($conn->query($sql)) {
        echo "OK, inserted ID: " . $conn->insert_id;
    } else {
        echo "Insert failed: [" . $conn->errno . "] " . $conn->error;
    }
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage();
}
