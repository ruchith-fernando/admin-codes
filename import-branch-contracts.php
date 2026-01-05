<?php
include 'connections/connection.php';
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
    $file_tmp = $_FILES['csv_file']['tmp_name'];

    // Open the file in read mode
    $handle = fopen($file_tmp, "r");

    if ($handle !== false) {
        $row = 0;
        $successCount = 0;
        $errorCount = 0;

        while (($data = fgetcsv($handle, 10000, ",")) !== false) {
            if ($row === 0) {
                $row++;
                continue; // skip header row
            }

            // Handle encoding for each cell
            $data = array_map(function($value) {
                return mb_convert_encoding(trim($value), 'UTF-8', 'auto');
            }, $data);

            // Assign variables
            $branch_number = $conn->real_escape_string($data[0] ?? '');
            $branch_name = $conn->real_escape_string($data[1] ?? '');
            $lease_agreement_number = $conn->real_escape_string($data[2] ?? '');
            $contract_period = $conn->real_escape_string($data[3] ?? '');
            $start_date = $conn->real_escape_string($data[4] ?? '');
            $end_date = $conn->real_escape_string($data[5] ?? '');
            $total_rent = $conn->real_escape_string($data[6] ?? '');
            $increase_of_rent = $conn->real_escape_string($data[7] ?? '');
            $advance_payment_key_money = $conn->real_escape_string($data[8] ?? '');
            $monthly_rental_notes = $conn->real_escape_string($data[9] ?? '');
            $floor_area = $conn->real_escape_string($data[10] ?? '');
            $repairs_by_cdb = $conn->real_escape_string($data[11] ?? '');
            $deviations_within_contract = $conn->real_escape_string($data[12] ?? '');

            $sql = "INSERT INTO tbl_admin_branch_contracts (
                branch_number, branch_name, lease_agreement_number, contract_period, start_date,
                end_date, total_rent, increase_of_rent, advance_payment_key_money,
                monthly_rental_notes, floor_area, repairs_by_cdb, deviations_within_contract
            ) VALUES (
                '$branch_number', '$branch_name', '$lease_agreement_number', '$contract_period', '$start_date',
                '$end_date', '$total_rent', '$increase_of_rent', '$advance_payment_key_money',
                '$monthly_rental_notes', '$floor_area', '$repairs_by_cdb', '$deviations_within_contract'
            )";

            if ($conn->query($sql)) {
                $successCount++;
            } else {
                $errorCount++;
            }

            $row++;
        }

        fclose($handle);
        echo "<p>✅ Upload complete.</p>";
        echo "<p>Rows inserted: $successCount</p>";
        echo "<p>Errors: $errorCount</p>";
    } else {
        echo "❌ Failed to open the file.";
    }
} else {
    echo "❌ No file selected or upload failed.";
}

$conn->close();
?>
