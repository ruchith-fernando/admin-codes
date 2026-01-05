<?php
include 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $logFile = 'upload-pickme.log';
    $successCount = 0;
    $errorCount = 0;
    $duplicateCount = 0;

    function logMessage($message) {
        global $logFile;
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    }

    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== false) {
        $header = fgetcsv($handle); // Skip header
        $seen = [];

        while (($row = fgetcsv($handle)) !== false) {
            $tripId = mysqli_real_escape_string($conn, trim($row[0]));

            // Skip duplicates within file
            if (in_array($tripId, $seen)) {
                $duplicateCount++;
                logMessage("Duplicate trip_id in file: $tripId");
                continue;
            }
            $seen[] = $tripId;

            // Skip if already in DB
            $check = mysqli_query($conn, "SELECT id FROM tbl_admin_pickme_data WHERE trip_id = '$tripId'");
            if (mysqli_num_rows($check) > 0) {
                $duplicateCount++;
                logMessage("Duplicate trip_id in DB: $tripId");
                continue;
            }

            // Clean fields
            $fields = array_map(function($field, $index) use ($conn) {
                $clean = trim($field);
                // Remove thousand separators for numeric fields
                if (in_array($index, [15, 16, 18, 19, 20])) {
                    $clean = str_replace(',', '', $clean);
                }
                return mysqli_real_escape_string($conn, $clean);
            }, $row, array_keys($row));

            if (count($fields) < 21) {
                $errorCount++;
                logMessage("Insufficient fields for $tripId");
                continue;
            }

            $query = "INSERT INTO tbl_admin_pickme_data (
                trip_id, service, vehicle_type, passenger_name, department,
                phone, epf, driver_name, vehicle_number, pickup_location,
                pickup_time, drop_location, drop_time, ride_remark, surge_applicable,
                trip_distance, distance_fare, trip_duration, journey_time_fare,
                additional_fare, total_fare
            ) VALUES (
                '$fields[0]', '$fields[1]', '$fields[2]', '$fields[3]', '$fields[4]',
                '$fields[5]', '$fields[6]', '$fields[7]', '$fields[8]', '$fields[9]',
                '$fields[10]', '$fields[11]', '$fields[12]', '$fields[13]', '$fields[14]',
                '$fields[15]', '$fields[16]', '$fields[17]', '$fields[18]',
                '$fields[19]', '$fields[20]'
            )";

            if (mysqli_query($conn, $query)) {
                $successCount++;
            } else {
                $errorCount++;
                logMessage("Insert error for $tripId: " . mysqli_error($conn));
            }
        }

        fclose($handle);
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Upload complete.<br>✅ Success: $successCount<br>⚠️ Duplicate Skipped: $duplicateCount<br>❌ Errors: $errorCount",
        'log_file' => $logFile
    ]);
    exit;
}
?>
