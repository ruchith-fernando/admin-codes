<?php
require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

$response = ['status' => 'danger', 'message' => 'Unknown error occurred.'];
$inserted = 0;
$skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        $row = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Skip header
            if ($row === 0 && str_contains(strtolower(implode(',', $data)), 'branch')) {
                $row++;
                continue;
            }

            $branch_code = trim($data[0]);
            $branch = trim($data[1]);
            $shifts = (int) trim($data[2]);
            $total = (float) trim($data[3]);
            $month = trim($data[4]);

            if ($branch_code && $branch && $shifts > 0 && $total > 0 && $month) {

                // Step 1: Insert first without sr_number
                $stmt = $conn->prepare("INSERT INTO tbl_admin_actual_security 
                    (branch_code, branch, actual_shifts, total_amount, month_applicable) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssids", $branch_code, $branch, $shifts, $total, $month);
                $stmt->execute();
                $insert_id = $stmt->insert_id;
                $stmt->close();

                if ($insert_id) {
                    // Step 2: Generate SR Number and update
                    $sr_number = generate_sr_number($conn, 'tbl_admin_actual_security', $insert_id);

                    // Update sr_number into the same record
                    $update_stmt = $conn->prepare("UPDATE tbl_admin_actual_security SET sr_number = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $sr_number, $insert_id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    $inserted++;
                } else {
                    $skipped++;
                }

            } else {
                $skipped++;
            }

            $row++;
        }

        fclose($handle);

        $response['status'] = 'success';
        $response['message'] = "Upload complete. Inserted: $inserted, Skipped: $skipped.";
    } else {
        $response['message'] = "Failed to open file.";
    }
} else {
    $response['message'] = "No file uploaded or invalid request.";
}

echo json_encode($response);
exit;
