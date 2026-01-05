<?php
include 'connections/connection.php';
header('Content-Type: application/json');

// Read input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check input
if (!isset($data['hris']) || empty($data['hris'])) {
    echo json_encode(["status" => "error", "message" => "Invalid HRIS"]);
    exit;
}

$hris = mysqli_real_escape_string($conn, $data['hris']);

// Query 1 - Basic Employee Info
$query = "SELECT name_of_employee, nic_no, designation, location, company_hierarchy 
          FROM tbl_admin_employee_details 
          WHERE hris = '$hris' AND status = 'Active'";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);

    $company_hierarchy = mysqli_real_escape_string($conn, $row['company_hierarchy']);

    // Query to get the department_route
    $dept_query = "SELECT department_route FROM tbl_admin_company_hierarchy WHERE company_hierarchy = '$company_hierarchy' LIMIT 1";
    $dept_result = mysqli_query($conn, $dept_query);

    $department_route = "";
    if ($dept_result && mysqli_num_rows($dept_result) > 0) {
        $dept_row = mysqli_fetch_assoc($dept_result);
        $department_route = $dept_row['department_route'];
    }

    // --- Separate Query 2 - Mobile Numbers ---
    $mobiles = [];
    $mobile_query = "SELECT mobile_no, voice_data, company_contribution 
                     FROM tbl_admin_mobile_issues 
                     WHERE hris_no = '$hris' 
                     AND mobile_no IS NOT NULL 
                     AND mobile_no <> ''";
    $mobile_result = mysqli_query($conn, $mobile_query);

    if ($mobile_result && mysqli_num_rows($mobile_result) > 0) {
        while ($mobile_row = mysqli_fetch_assoc($mobile_result)) {
            $mobiles[] = [
                "mobile_no" => $mobile_row['mobile_no'],
                "voice_data" => $mobile_row['voice_data'],
                "company_contribution" => $mobile_row['company_contribution']
            ];
        }
    }

    // --- Separate Query 3 - Phone Issues ---
    $phones = [];
    $phone_query = "SELECT issue_date, imei_number 
                    FROM tbl_admin_phone_issues 
                    WHERE hris = '$hris'";
    $phone_result = mysqli_query($conn, $phone_query);

    if ($phone_result && mysqli_num_rows($phone_result) > 0) {
        while ($phone_row = mysqli_fetch_assoc($phone_result)) {
            $phones[] = [
                "issue_date" => $phone_row['issue_date'],
                "imei_number" => $phone_row['imei_number']
            ];
        }
    }

    // ✅ Final Output
    echo json_encode([
        "status" => "success",
        "full_name" => $row['name_of_employee'],
        "nic_no" => $row['nic_no'],
        "designation" => $row['designation'],
        "location" => $row['location'],
        "department_route" => $department_route,
        "company_hierarchy" => $row['company_hierarchy'],
        "mobiles" => $mobiles,
        "phones" => $phones
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Employee not found"]);
}
?>
