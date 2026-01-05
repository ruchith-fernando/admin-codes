<?php
session_start();
include 'connections/connection.php'; 


if (!isset($_SESSION['name']) || !in_array($_SESSION['user_level'], ['manager', 'super-admin'])) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentNumber = $_POST['document_number'] ?? '';
    $description = $_POST['description'] ?? '';
    $accessLevel = $_POST['access_level'] ?? '';

    // Check for uploaded PDF file
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['type'] === 'application/pdf') {
        $uploadDir = '../uploads/secure-docs/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Sanitize and construct meaningful filename
        $docSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $documentNumber);
        $descSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($description, 0, 30));
        $timestamp = date('Ymd_His');
        $fileName = "{$docSafe}_{$descSafe}_{$timestamp}.pdf";
        $filePath = $uploadDir . $fileName;

        // Move uploaded file
        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $filePath)) {
            // Save record in database (relative path for web use)
            $relativePath = 'uploads/secure-docs/' . $fileName;

            $stmt = $conn->prepare("INSERT INTO tbl_admin_secure_documents (document_number, description, file_path, access_level) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $documentNumber, $description, $relativePath, $accessLevel);

            if ($stmt->execute()) {
                echo "✅ Document uploaded successfully.";
            } else {
                echo "❌ Failed to save document info to database.";
            }
        } else {
            echo "❌ Failed to move uploaded file.";
        }
    } else {
        echo "❌ Only PDF files are allowed.";
    }
} else {
    http_response_code(405);
    echo "Invalid request method.";
}
