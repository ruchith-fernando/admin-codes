<?php
include 'connections/connection.php';
header('Content-Type: application/json; charset=utf-8');

// Debug log
function logUploadAction($message) {
    file_put_contents('logs/vehicle-actions.log', "[" . date("Y-m-d H:i:s") . "] $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['contract_file']) && isset($_POST['file_ref'])) {
    $fileRef = mysqli_real_escape_string($conn, $_POST['file_ref']);
    $uploadDir = 'uploads/contracts/';
    $extension = pathinfo($_FILES['contract_file']['name'], PATHINFO_EXTENSION);
    $uniqueName = 'contract_' . $fileRef . '_' . uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $uniqueName;

    logUploadAction("Uploading contract for file_ref=$fileRef");

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        logUploadAction("Created directory: $uploadDir");
    }

    if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $uploadPath)) {
        $stmt = $conn->prepare("UPDATE tbl_admin_fixed_assets SET contract_file = ? WHERE file_ref = ?");
        $stmt->bind_param("ss", $uniqueName, $fileRef);

        if ($stmt->execute()) {
            logUploadAction("SUCCESS: File uploaded and DB updated. Saved as $uniqueName");
            echo json_encode(['status' => 'success', 'file' => $uploadPath]);
        } else {
            logUploadAction("DB ERROR: " . $stmt->error);
            echo json_encode(['status' => 'db_error', 'message' => $stmt->error]);
        }
    } else {
        logUploadAction("UPLOAD FAILED for $fileRef");
        echo json_encode(['status' => 'upload_failed']);
    }
} else {
    logUploadAction("INVALID REQUEST structure.");
    echo json_encode(['status' => 'invalid_request']);
}
?>
