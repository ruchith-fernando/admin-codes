<!-- upload-contract-pdf.php -->
<?php
include 'connections/connection.php';

if (!isset($_POST['contract_id']) || !is_numeric($_POST['contract_id'])) {
    echo "Invalid contract ID.";
    exit;
}

$id = intval($_POST['contract_id']);
$uploadDir = 'uploads/contracts/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['contract_pdf']) || $_FILES['contract_pdf']['error'] !== UPLOAD_ERR_OK) {
    echo "File upload error.";
    exit;
}

$filename = basename($_FILES['contract_pdf']['name']);
$extension = pathinfo($filename, PATHINFO_EXTENSION);

if (strtolower($extension) !== 'pdf') {
    echo "Only PDF files are allowed.";
    exit;
}

$newName = 'contract_' . $id . '_' . time() . '.pdf';
$targetFile = $uploadDir . $newName;

if (move_uploaded_file($_FILES['contract_pdf']['tmp_name'], $targetFile)) {
    $sql = "UPDATE tbl_admin_branch_contracts SET contract_pdf = '$newName' WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        echo "PDF uploaded and saved successfully.";
    } else {
        echo "❌ Failed to update database.";
    }
} else {
    echo "❌ Failed to move uploaded file.";
}
