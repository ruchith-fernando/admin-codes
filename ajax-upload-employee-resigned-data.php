<?php
session_start();
include 'connections/connection.php';

$log_file = "upload_log.txt";
$uploadedFileName = $_FILES['csvFile']['name'] ?? 'unknown';
$update_date = date('F-Y');
$currentTimestamp = date('Y-m-d H:i:s');

function write_log($content) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $content" . PHP_EOL, FILE_APPEND);
}

function logSummaryEntry($conn, $hris, $action, $file_name, $month) {
    $stmt = $conn->prepare("INSERT INTO tbl_admin_employee_upload_log (hris, action_type, file_name, upload_month) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $hris, $action, $file_name, $month);
    $stmt->execute();
    $stmt->close();
}

$inserted = 0;

write_log("=== Resigned employee upload started ===");

if ($conn->connect_error) {
    write_log("Connection failed: " . $conn->connect_error);
    echo '<div class="alert alert-danger">❌ Database connection failed.</div>';
    exit;
}

if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] === 0) {
    $file = $_FILES['csvFile']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        fgetcsv($handle); // skip header
        $row_num = 1;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_num++;
            if (count(array_filter($data)) == 0) {
                write_log("Row $row_num skipped: Empty row.");
                continue;
            }

            list($emp_no, $epf_no, $company_hierarchy, $title, $full_name, $designation, $display_name,
                $location, $nic_no, $category, $type, $date_joined, $date_resigned, $category_ops_sales, $status) = array_pad($data, 15, null);

            // INSERT ONLY
            $sql = "INSERT INTO tbl_admin_employee_details (
                        hris, epf_no, company_hierarchy, title, name_of_employee, designation, display_name,
                        location, nic_no, category, employment_categories, date_joined, date_resigned, category_ops_sales, status, upload_timestamp
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssssssssss",
                $emp_no, $epf_no, $company_hierarchy, $title, $full_name,
                $designation, $display_name, $location, $nic_no, $category,
                $type, $date_joined, $date_resigned, $category_ops_sales, $status, $currentTimestamp
            );
            $stmt->execute();
            $stmt->close();

            write_log("Row $row_num: Inserted (Employee No: $emp_no)");
            logSummaryEntry($conn, $emp_no, 'inserted', $uploadedFileName, $update_date);
            $inserted++;
        }

        fclose($handle);

        echo "
        <div class='alert alert-success fw-bold'>
        ✅ Upload Complete<br>
        <ul>
            <li><strong>Upload Month:</strong> $update_date</li>
            <li><strong>File Name:</strong> $uploadedFileName</li>
            <li><strong>Total Inserted:</strong> $inserted</li>
            <li><strong>View Details:</strong> 
                <a href='view-employee-upload-log.php?month=" . urlencode($update_date) . "&file=" . urlencode($uploadedFileName) . "' target='_self'>
                    Click here
                </a>
            </li>
        </ul></div>";
        write_log("=== Upload finished: $inserted inserted ===");
    } else {
        write_log("Error opening the uploaded file.");
        echo '<div class="alert alert-danger">❌ Error opening the file.</div>';
    }
} else {
    $error = $_FILES['csvFile']['error'] ?? 4;
    $errors = [
        1 => 'File exceeds upload_max_filesize in php.ini.',
        2 => 'File exceeds MAX_FILE_SIZE in form.',
        3 => 'File only partially uploaded.',
        4 => 'No file uploaded.',
        6 => 'Missing temporary folder.',
        7 => 'Failed to write to disk.',
        8 => 'PHP extension stopped upload.'
    ];
    $msg = $errors[$error] ?? 'Unknown upload error.';
    write_log("Upload failed: $msg");
    echo '<div class="alert alert-danger">❌ Upload error: ' . $msg . '</div>';
}

$conn->close();
?>
