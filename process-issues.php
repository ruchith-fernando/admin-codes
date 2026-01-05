<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload-issues.log');
error_reporting(E_ALL);

include 'connections/connection.php';

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== 0) {
    echo "<div class='alert alert-danger fw-bold'>❌ Error: Please upload a valid CSV file.</div>";
    exit;
}

$uploadedFilePath = $_FILES['csv_file']['tmp_name'];
$uploadedFileName = $_FILES['csv_file']['name'] ?? 'N/A';

$handle = fopen($uploadedFilePath, "r");
if ($handle === false) {
    echo "<div class='alert alert-danger fw-bold'>❌ Error: Could not read the CSV file.</div>";
    exit;
}

// Skip header row
fgetcsv($handle);

$inserted = 0;
$skipped  = 0;
$contribs = 0;

while (($data = fgetcsv($handle, 1000, ",")) !== false) {

    // CSV has 9 columns
    list(
        $mobile_no, //ok
        $remarks,
        $voice_data,
        $branch_operational_remarks,
        $name_of_employee,
        $hris_no,
        $company_hierarchy,
        $connection_status,
        $nic_no,
        $company_contribution
    ) = array_pad($data, 10, null);

    // --- Normalize values ---
    $mobile_no                = trim($mobile_no) ?: null;
    $remarks                  = trim($remarks) ?: null;
    $voice_data               = trim($voice_data) ?: null;
    $branch_operational_remarks = trim($branch_operational_remarks) ?: null;
    $name_of_employee         = trim($name_of_employee) ?: null;
    $hris_no                  = trim($hris_no) ?: null;
    $company_hierarchy        = trim($company_hierarchy) ?: null;
    $connection_status        = trim($connection_status) ?: 'Connected';
    $nic_no                   = trim($nic_no) ?: null;
    $company_contribution     = trim($company_contribution) ?: null;

    // Other table fields (default null)
    $epf_no = $title = $designation = $display_name = $location = null;
    $category = $employment_categories = $date_joined = $date_resigned = null;
    $category_ops_sales = $status = $disconnection_date = null;

    // --- Enrich from employee details if HRIS is present ---
    if (!empty($hris_no)) {
        $emp_stmt = $conn->prepare("
            SELECT epf_no, company_hierarchy, title, name_of_employee, designation, 
                   display_name, location, nic_no, category, employment_categories, 
                   date_joined, date_resigned, category_ops_sales, status
            FROM tbl_admin_employee_details
            WHERE TRIM(hris) = ?
            LIMIT 1
        ");
        $emp_stmt->bind_param("s", $hris_no);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        if ($emp_row = $emp_result->fetch_assoc()) {
            $epf_no              = $emp_row['epf_no'] ?? null;
            $company_hierarchy   = $company_hierarchy ?? $emp_row['company_hierarchy'];
            $title               = $emp_row['title'] ?? null;
            $name_of_employee    = $name_of_employee ?? $emp_row['name_of_employee'];
            $designation         = $emp_row['designation'] ?? null;
            $display_name        = $emp_row['display_name'] ?? null;
            $location            = $location ?? $emp_row['location'];
            $nic_no              = $nic_no ?? $emp_row['nic_no'];
            $category            = $emp_row['category'] ?? null;
            $employment_categories = $emp_row['employment_categories'] ?? null;
            $date_joined         = $emp_row['date_joined'] ?? null;
            $date_resigned       = $emp_row['date_resigned'] ?? null;
            $category_ops_sales  = $emp_row['category_ops_sales'] ?? null;
            $status              = $status ?? $emp_row['status'];
        }
        $emp_stmt->close();
    }

    // --- Insert into mobile issues ---
    $stmt = $conn->prepare("
        INSERT INTO tbl_admin_mobile_issues (
            mobile_no, remarks, voice_data, branch_operational_remarks,
            name_of_employee, hris_no, company_contribution, epf_no,
            company_hierarchy, title, designation, display_name,
            location, nic_no, category, employment_categories,
            date_joined, date_resigned, category_ops_sales, status,
            connection_status, disconnection_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param(
            "ssssssdsssssssssssssss",
            $mobile_no,
            $remarks,
            $voice_data,
            $branch_operational_remarks,
            $name_of_employee,
            $hris_no,
            $company_contribution,
            $epf_no,
            $company_hierarchy,
            $title,
            $designation,
            $display_name,
            $location,
            $nic_no,
            $category,
            $employment_categories,
            $date_joined,
            $date_resigned,
            $category_ops_sales,
            $status,
            $connection_status,
            $disconnection_date
        );

        if ($stmt->execute()) {
            $inserted++;

            // --- Insert into HRIS contributions if contribution exists ---
            if (!empty($company_contribution) && !empty($hris_no)) {
                $contrib_stmt = $conn->prepare("
                    INSERT INTO tbl_admin_hris_contributions (
                        hris_no, mobile_no, contribution_amount, effective_from
                    ) VALUES (?, ?, ?, ?)
                ");
                $today = date('Y-m-d');
                $contrib_stmt->bind_param("ssds", $hris_no, $mobile_no, $company_contribution, $today);
                if ($contrib_stmt->execute()) {
                    $contribs++;
                }
                $contrib_stmt->close();
            }

        } else {
            $skipped++;
        }
        $stmt->close();
    } else {
        $skipped++;
    }
}

fclose($handle);
$conn->close();

echo "
<div class='alert alert-success fw-bold'>✅ CSV Upload Complete</div>
<div class='result-block'>
  <div><b>File Name:</b> " . htmlspecialchars($uploadedFileName) . "</div>
  <div><b>Total Records Inserted:</b> $inserted</div>
  <div><b>Skipped Rows:</b> $skipped</div>
  <div><b>HRIS Contributions Added:</b> $contribs</div>
</div>";
?>
