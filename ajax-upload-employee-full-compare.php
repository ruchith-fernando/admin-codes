<?php
// ajax-upload-employee-full-compare.php
session_start();
include 'connections/connection.php';

$log_file = "upload_log.txt";
$uploadedFileName = $_FILES['csvFile']['name'] ?? 'unknown';
$update_month = date('F-Y');
$currentTimestamp = date('Y-m-d H:i:s');

function write_log($content) {
    global $log_file;
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] $content" . PHP_EOL, FILE_APPEND);
}

function logSummaryEntry($conn, $hris, $action, $file_name, $month, $snapshot = null) {

    // ---- Normalize action types to match DB enum('insert','update','resign') ----
    $action = strtolower(trim((string)$action));
    if ($action === 'inserted') $action = 'insert';
    if ($action === 'updated')  $action = 'update';
    if ($action === 'resigned') $action = 'resign';

    // Fallback safety
    if (!in_array($action, ['insert','update','resign'], true)) {
        $action = 'update';
    }

    // Existing summary table (unchanged)
    // NOTE: your upload_log table enum is ('inserted','updated') so we convert back for that table
    $uploadLogAction = ($action === 'insert') ? 'inserted' : (($action === 'update') ? 'updated' : 'updated');

    $stmt = $conn->prepare("INSERT INTO tbl_admin_employee_upload_log 
        (hris, action_type, file_name, upload_month) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $hris, $uploadLogAction, $file_name, $month);
    $stmt->execute();
    $stmt->close();

    // History insert
    if ($snapshot !== null) {
        $stmt2 = $conn->prepare("INSERT INTO tbl_admin_employee_history 
            (hris, action_type, snapshot) VALUES (?, ?, ?)");
        $stmt2->bind_param("sss", $hris, $action, $snapshot);
        $stmt2->execute();
        $stmt2->close();
    }
}

$inserted = 0;
$updated = 0;

write_log("=== Full compare upload started ===");

if ($conn->connect_error) {
    write_log("Connection failed: " . $conn->connect_error);
    echo '<div class="alert alert-danger">Database connection failed.</div>';
    exit;
}

if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== 0) {
    write_log("No valid file uploaded.");
    echo '<div class="alert alert-danger">Please upload a valid CSV file.</div>';
    exit;
}

$file = $_FILES['csvFile']['tmp_name'];
$csvData = [];
if (($handle = fopen($file, "r")) !== FALSE) {
    $header = fgetcsv($handle); // Skip header
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $csvData[] = array_map('trim', $data);
    }
    fclose($handle);
} else {
    echo '<div class="alert alert-danger">Failed to open uploaded CSV.</div>';
    exit;
}

// Fetch existing employee data
$existingData = [];
$res = $conn->query("SELECT * FROM tbl_admin_employee_details");
while ($row = $res->fetch_assoc()) {
    $existingData[$row['hris']] = $row;
}

// Process CSV
$row_num = 1;
foreach ($csvData as $data) {
    $row_num++;
    if (count(array_filter($data)) == 0) continue;

    list($emp_no, $epf_no, $company_hierarchy, $title, $full_name, $designation, $display_name,
        $location, $nic_no, $category, $type, $date_joined, $date_resigned,
        $category_ops_sales, $status) = array_pad($data, 15, null);

    $uploadData = [
        'epf_no' => $epf_no,
        'company_hierarchy' => $company_hierarchy,
        'title' => $title,
        'name_of_employee' => $full_name,
        'designation' => $designation,
        'display_name' => $display_name,
        'location' => $location,
        'nic_no' => $nic_no,
        'category' => $category,
        'employment_categories' => $type,
        'date_joined' => $date_joined,
        'date_resigned' => $date_resigned,
        'category_ops_sales' => $category_ops_sales,
        'status' => $status
    ];

    if (!isset($existingData[$emp_no])) {
        // INSERT new employee
        $sql = "INSERT INTO tbl_admin_employee_details (
            hris, epf_no, company_hierarchy, title, name_of_employee, designation, display_name,
            location, nic_no, category, employment_categories, date_joined, date_resigned,
            category_ops_sales, status, upload_timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $bindParams = array_merge([$emp_no], array_values($uploadData), [$currentTimestamp]);
        $stmt->bind_param("ssssssssssssssss", ...$bindParams);
        $stmt->execute();
        $stmt->close();

        // ✅ Generate SR number
        include_once 'includes/sr-generator.php';
        $inserted_id = $conn->insert_id;
        generate_sr_number($conn, 'tbl_admin_employee_details', $inserted_id);

        // History insert
        $snapshot = json_encode($uploadData, JSON_UNESCAPED_UNICODE);
        logSummaryEntry($conn, $emp_no, 'inserted', $uploadedFileName, $update_month, $snapshot);

        write_log("Row $row_num: Inserted HRIS $emp_no");
        $inserted++;
    } else {
        // COMPARE for UPDATE
        $existing = $existingData[$emp_no];
        $changes = false;
        foreach ($uploadData as $field => $newVal) {
            if (trim((string)$existing[$field]) != trim((string)$newVal)) {
                $changes = true;
                break;
            }
        }

        if ($changes) {
            $sql = "UPDATE tbl_admin_employee_details SET
                epf_no=?, company_hierarchy=?, title=?, name_of_employee=?, designation=?, display_name=?,
                location=?, nic_no=?, category=?, employment_categories=?, date_joined=?, date_resigned=?,
                category_ops_sales=?, status=?, upload_timestamp=?
                WHERE hris=?";
            $stmt = $conn->prepare($sql);
            $bindParams = array_merge(array_values($uploadData), [$currentTimestamp, $emp_no]);
            $stmt->bind_param("ssssssssssssssss", ...$bindParams);
            $stmt->execute();
            $stmt->close();

            // History update
            $snapshot = json_encode([
                'old' => $existing,
                'new' => $uploadData
            ], JSON_UNESCAPED_UNICODE);
            logSummaryEntry($conn, $emp_no, 'updated', $uploadedFileName, $update_month, $snapshot);

            write_log("Row $row_num: Updated HRIS $emp_no");
            $updated++;
        }
    }
}

// Final Result
echo "
<div class='alert alert-success fw-bold'>
✅ Full Compare Upload Completed!<br>
<ul>
    <li><strong>Upload Month:</strong> $update_month</li>
    <li><strong>File Name:</strong> $uploadedFileName</li>
    <li><strong>Inserted:</strong> $inserted</li>
    <li><strong>Updated:</strong> $updated</li>
    <li><strong>View Log:</strong> 
        <a href='view-employee-upload-log.php?month=" . urlencode($update_month) . "&file=" . urlencode($uploadedFileName) . "' target='_self'>
            Click here
        </a>
    </li>
</ul>
</div>";

// === USER ACTIVITY LOG ===
require_once 'includes/userlog.php';

$hris = $_SESSION['hris'] ?? '';
$username = $_SESSION['name'] ?? getUserInfo();

// Build action message
$actionMessage = sprintf(
    'Uploaded employee CSV: %s | Inserted: %d | Updated: %d | Month: %s',
    $uploadedFileName,
    $inserted,
    $updated,
    $update_month
);

// Log the activity
userlog($actionMessage);

$conn->close();
?>
