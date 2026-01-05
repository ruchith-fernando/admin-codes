<?php
include 'connections/connection.php';

// Step 1: Get all currently connected/active numbers
$sql = "
    SELECT m.mobile_no, m.hris_no, m.name_of_employee, e.date_joined
    FROM tbl_admin_mobile_issues m
    LEFT JOIN tbl_admin_employee_details e ON m.hris_no = e.hris
    WHERE m.connection_status IN ('Connected','Active')
";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $mobile = $conn->real_escape_string($row['mobile_no']);
        $hris   = $conn->real_escape_string($row['hris_no']);
        $name   = $conn->real_escape_string($row['name_of_employee']);
        $eff    = !empty($row['date_joined']) ? $row['date_joined'] : date('Y-m-d');

        // Step 2: Check if this allocation already exists & is active
        $chk = $conn->query("
            SELECT id FROM tbl_admin_mobile_allocations
            WHERE mobile_number='$mobile' AND hris_no='$hris' AND status='Active'
            LIMIT 1
        ");

        if ($chk->num_rows == 0) {
            // Step 3: Inactivate any old allocation for this mobile number (different HRIS)
            $conn->query("
                UPDATE tbl_admin_mobile_allocations
                SET status='Inactive', effective_to=CURDATE()
                WHERE mobile_number='$mobile' AND status='Active'
                  AND hris_no <> '$hris'
            ");

            // Step 4: Insert new allocation
            $conn->query("
                INSERT INTO tbl_admin_mobile_allocations (mobile_number, hris_no, owner_name, effective_from, status)
                VALUES ('$mobile', '$hris', '$name', '$eff', 'Active')
            ");
            echo "✅ Synced allocation for $mobile → $name ($hris)<br>";
        } else {
            echo "ℹ️ Already active: $mobile → $name ($hris)<br>";
        }
    }
} else {
    echo "⚠️ No active/connected numbers found.";
}
?>
