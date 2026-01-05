<?php
include 'connections/connection.php';

if (!isset($_POST['contract_id']) || !is_numeric($_POST['contract_id'])) {
    echo "❌ Invalid contract ID.";
    exit;
}

$contractId = intval($_POST['contract_id']);
$versionNote = mysqli_real_escape_string($conn, $_POST['version_note'] ?? 'No label');

// Validate file upload
if (!isset($_FILES['contract_pdf']) || $_FILES['contract_pdf']['error'] !== UPLOAD_ERR_OK) {
    echo "❌ File upload error.";
    exit;
}

$uploadDir = 'uploads/contracts/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$originalName = basename($_FILES['contract_pdf']['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
if (strtolower($extension) !== 'pdf') {
    echo "❌ Only PDF files are allowed.";
    exit;
}

// Generate unique filename
$newFileName = 'contract_' . $contractId . '_' . time() . '.pdf';
$targetPath = $uploadDir . $newFileName;

// Move and save
if (move_uploaded_file($_FILES['contract_pdf']['tmp_name'], $targetPath)) {
    $insert = "INSERT INTO tbl_admin_branch_contract_versions 
        (branch_contract_id, file_name, version_note) 
        VALUES ($contractId, '$newFileName', '$versionNote')";
    
    if (mysqli_query($conn, $insert)) {
        echo "New contract version uploaded successfully.";
    } else {
        echo "❌ Failed to save to database.";
    }
} else {
    echo "❌ Failed to move uploaded file.";
}
