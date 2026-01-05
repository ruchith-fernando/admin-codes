<?php
require_once 'connections/connection.php';

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($fileTmpPath, 'r');
    
    if ($handle !== false) {
        // Skip header row
        fgetcsv($handle);

        $stmt = $conn->prepare("INSERT INTO tbl_admin_branch_electricity (branch_code, branch_name, account_no, bank_paid_to)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE branch_name = VALUES(branch_name), account_no = VALUES(account_no), bank_paid_to = VALUES(bank_paid_to)");

        $rowCount = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            if (count($data) >= 4) {
                $stmt->bind_param("ssss", $data[0], $data[1], $data[2], $data[3]);
                $stmt->execute();
                $rowCount++;
            }
        }

        fclose($handle);
        echo "<div style='padding: 20px; font-family: sans-serif;'>
                <h4>✅ Upload complete. $rowCount rows inserted/updated.</h4>
                <a href='upload-branch-csv.php'>Upload another</a>
              </div>";
    } else {
        echo "❌ Failed to open file.";
    }
} else {
    echo "❌ Error uploading file.";
}
?>
