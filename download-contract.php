<!-- download-contract.php -->

<?php
session_start();
include 'connections/connection.php';

if (!isset($_SESSION['secure_user'])) {
    $_SESSION['requested_file'] = $_GET['id'] ?? '';
    header("Location: secure-login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
$result = mysqli_query($conn, "SELECT contract_pdf FROM tbl_admin_branch_contracts WHERE id = $id LIMIT 1");

if ($row = mysqli_fetch_assoc($result)) {
    $file = 'uploads/contracts/' . $row['contract_pdf'];
    if (file_exists($file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    } else {
        echo "File not found.";
    }
} else {
    echo "Invalid contract.";
}
