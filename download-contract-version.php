<?php
include 'connections/connection.php';
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

$res = mysqli_query($conn, "SELECT file_name FROM tbl_admin_branch_contract_versions WHERE id = $id");
if ($row = mysqli_fetch_assoc($res)) {
    $filePath = 'uploads/contracts/' . $row['file_name'];
    if (file_exists($filePath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        readfile($filePath);
        exit;
    }
}
echo "File not found.";
