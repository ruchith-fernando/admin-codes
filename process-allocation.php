<?php
include 'connections/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile_number  = $conn->real_escape_string($_POST['mobile_number']);
    $new_hris       = $conn->real_escape_string($_POST['hris_no']);
    $effective_from = $conn->real_escape_string($_POST['effective_from']);

    // Step 1: Get last record for this mobile number
    $last = $conn->query("
        SELECT * FROM tbl_admin_mobile_issues
        WHERE mobile_no='$mobile_number'
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$last || $last->num_rows == 0) {
        echo "<div class='alert alert-danger'>❌ No record found for this mobile number.</div>";

        // ✅ Log failure + capture all POST
        try {
            require_once 'includes/userlog.php';
            $hris = $_SESSION['hris'] ?? 'UNKNOWN';
            $username = $_SESSION['name'] ?? (function_exists('getUserInfo') ? getUserInfo() : 'UNKNOWN');
            $actionMessage = sprintf(
                '❌ Reassignment failed (no last record) | Mobile: %s | New HRIS: %s | Effective From: %s | POST: %s',
                $mobile_number,
                $new_hris,
                $effective_from,
                json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            );
            userlog($actionMessage);
        } catch (Throwable $e) {}
        exit;
    }
    $lastRow = $last->fetch_assoc();

    // Step 2: Disconnect previous assignment
    $conn->query("
        UPDATE tbl_admin_mobile_issues
        SET connection_status='Disconnected',
            disconnection_date = CURDATE()
        WHERE id=".$lastRow['id']
    );

    // Step 3: Get new employee details
    $emp = $conn->query("
        SELECT hris, name_of_employee, epf_no, company_hierarchy, title,
               designation, location, category, employment_categories, date_joined
        FROM tbl_admin_employee_details
        WHERE hris='$new_hris' AND status='Active'
        LIMIT 1
    ");
    if (!$emp || $emp->num_rows == 0) {
        echo "<div class='alert alert-danger'>❌ New employee not found or inactive.</div>";

        // ✅ Log failure + capture all POST
        try {
            require_once 'includes/userlog.php';
            $hris = $_SESSION['hris'] ?? 'UNKNOWN';
            $username = $_SESSION['name'] ?? (function_exists('getUserInfo') ? getUserInfo() : 'UNKNOWN');
            $actionMessage = sprintf(
                '❌ Reassignment failed (employee not found/inactive) | Mobile: %s | New HRIS: %s | Effective From: %s | POST: %s',
                $mobile_number,
                $new_hris,
                $effective_from,
                json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            );
            userlog($actionMessage);
        } catch (Throwable $e) {}
        exit;
    }
    $e = $emp->fetch_assoc();

    // Step 4: Insert new mobile issue record
    $conn->query("
        INSERT INTO tbl_admin_mobile_issues (
            mobile_no, remarks, voice_data, branch_operational_remarks,
            name_of_employee, hris_no, company_contribution, epf_no,
            company_hierarchy, title, designation, display_name, location,
            nic_no, category, employment_categories, date_joined,
            date_resigned, category_ops_sales, status,
            connection_status, department_name
        ) VALUES (
            '".$mobile_number."',
            '".$conn->real_escape_string($lastRow['remarks'])."',
            '".$conn->real_escape_string($lastRow['voice_data'])."',
            '".$conn->real_escape_string($lastRow['branch_operational_remarks'])."',
            '".$conn->real_escape_string($e['name_of_employee'])."',
            '".$conn->real_escape_string($e['hris'])."',
            '".$conn->real_escape_string($lastRow['company_contribution'])."',
            '".$conn->real_escape_string($e['epf_no'])."',
            '".$conn->real_escape_string($e['company_hierarchy'])."',
            '".$conn->real_escape_string($e['title'])."',
            '".$conn->real_escape_string($e['designation'])."',
            '".$conn->real_escape_string($e['name_of_employee'])."',
            '".$conn->real_escape_string($e['location'])."',
            '".$conn->real_escape_string($lastRow['nic_no'])."',
            '".$conn->real_escape_string($e['category'])."',
            '".$conn->real_escape_string($e['employment_categories'])."',
            '".$conn->real_escape_string($e['date_joined'])."',
            NULL,
            '".$conn->real_escape_string($lastRow['category_ops_sales'])."',
            'Active',
            'Connected',
            '".$conn->real_escape_string($e['company_hierarchy'])."'
        )
    ");

    // Step 5: Update tbl_admin_mobile_allocations

    // Mark previous allocation inactive
    $conn->query("
        UPDATE tbl_admin_mobile_allocations
        SET status='Inactive', effective_to='".$effective_from."'
        WHERE mobile_number='$mobile_number' AND status='Active'
    ");

    // Add new allocation
    $conn->query("
        INSERT INTO tbl_admin_mobile_allocations
        (mobile_number, hris_no, owner_name, effective_from, status)
        VALUES (
            '".$mobile_number."',
            '".$conn->real_escape_string($e['hris'])."',
            '".$conn->real_escape_string($e['name_of_employee'])."',
            '".$effective_from."',
            'Active'
        )
    ");

    echo "<div class='alert alert-success'>✅ Mobile connection reassigned successfully.</div>";

    // ✅ Log success (same style as your snippet, but tailored here) + capture ALL POST
    try {
        require_once 'includes/userlog.php';
        $hris = $_SESSION['hris'] ?? 'UNKNOWN';
        $username = $_SESSION['name'] ?? (function_exists('getUserInfo') ? getUserInfo() : 'UNKNOWN');
        // We’ll keep your sprintf style and include POST for “capture all information entered by the user”
        $actionMessage = sprintf(
            '✅ Mobile reassignment completed | Mobile: %s | New HRIS: %s | Effective From: %s | POST: %s',
            $mobile_number,
            $new_hris,
            $effective_from,
            json_encode($_POST, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        );
        userlog($actionMessage);
    } catch (Throwable $e) {}
}
?>
