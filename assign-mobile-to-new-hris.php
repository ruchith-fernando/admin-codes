<?php
include 'connections/connection.php';

$old_mobile_no = $_POST['mobile_no']; // The mobile number you're transferring
$new_hris_no = $_POST['new_hris_no']; // The new HRIS number

// Step 1: Fetch the existing mobile details
$sql = "SELECT id, mobile_no, voice_data, location, company_contribution, remarks, connection_status 
        FROM tbl_admin_mobile_issues 
        WHERE mobile_no = ? 
        ORDER BY id DESC 
        LIMIT 1"; 

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $old_mobile_no);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $existing = $result->fetch_assoc();
    $existing_id = $existing['id']; // Get the ID to update later

    // Step 2: Check connection status
    if ($existing['connection_status'] !== 'disconnected') {
        echo "Error: The current connection is not disconnected. Please disconnect before assigning a new HRIS.";
        exit;
    }

    // Step 3: Insert new record for new HRIS
    $insert_sql = "INSERT INTO tbl_admin_mobile_issues 
        (hris_no, mobile_no, voice_data, location, company_contribution, remarks, status, connection_status)
        VALUES (?, ?, ?, ?, ?, ?, 'Active', 'Connected')";

    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param(
        "ssssss", 
        $new_hris_no, 
        $existing['mobile_no'], 
        $existing['voice_data'], 
        $existing['location'], 
        $existing['company_contribution'], 
        $existing['remarks']
    );

    if ($insert_stmt->execute()) {
        // Step 4: Update the OLD record to Inactive + Disconnected
        $update_sql = "UPDATE tbl_admin_mobile_issues 
                       SET connection_status = 'Disconnected' 
                       WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $existing_id);
        $update_stmt->execute();
        $update_stmt->close();

        echo "success";
    } else {
        echo "Insert error: " . $insert_stmt->error;
    }

    $insert_stmt->close();
} else {
    echo "No existing record found with that mobile number.";
}

$stmt->close();
$conn->close();
?>
