<!-- delete-contract-pdf.php -->
<?php
include 'connections/connection.php';

if (!isset($_POST['contract_id']) || !is_numeric($_POST['contract_id'])) {
    die("Invalid request.");
}

$id = intval($_POST['contract_id']);
$result = mysqli_query($conn, "SELECT contract_pdf FROM tbl_admin_branch_contracts WHERE id = $id LIMIT 1");
$row = mysqli_fetch_assoc($result);

if ($row && !empty($row['contract_pdf'])) {
    $file = 'uploads/contracts/' . $row['contract_pdf'];
    if (file_exists($file)) {
        unlink($file);
    }
    mysqli_query($conn, "UPDATE tbl_admin_branch_contracts SET contract_pdf = NULL WHERE id = $id");
}

header("Location: branch-contracts-report.php");
exit;


