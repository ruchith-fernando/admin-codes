<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function cleanNum($v){
    if ($v === "" || $v === null) return "NULL";
    if (!is_numeric($v)) return "NULL";
    if ($v < 0) return "NULL";
    return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
}

$branch_code = $_POST['branch_code'] ?? '';
$branch_name = $_POST['branch_name'] ?? '';
$vendor_name = $_POST['vendor_name'] ?? '';
$water_type  = $_POST['water_type'] ?? '';
$account_number = $_POST['account_number'] ?? '';

$no_of_machines = $_POST['no_of_machines'] ?? '';
$monthly_charge = $_POST['monthly_charge'] ?? '';

$bottle_rate = $_POST['bottle_rate'] ?? '';
$cooler_rental_rate = $_POST['cooler_rental_rate'] ?? '';
$sscl_percentage = $_POST['sscl_percentage'] ?? '';
$vat_percentage = $_POST['vat_percentage'] ?? '';

$sql = "
INSERT INTO tbl_admin_branch_water SET
    branch_code = '".mysqli_real_escape_string($conn, $branch_code)."',
    branch_name = '".mysqli_real_escape_string($conn, $branch_name)."',
    vendor_name = ".($vendor_name=="" ? "NULL" : "'".mysqli_real_escape_string($conn,$vendor_name)."'").",
    water_type = '".mysqli_real_escape_string($conn, $water_type)."',
    account_number = ".($account_number=="" ? "NULL" : "'".mysqli_real_escape_string($conn,$account_number)."'").",

    no_of_machines = ".cleanNum($no_of_machines).",
    monthly_charge = ".cleanNum($monthly_charge).",

    bottle_rate = ".cleanNum($bottle_rate).",
    cooler_rental_rate = ".cleanNum($cooler_rental_rate).",
    sscl_percentage = ".cleanNum($sscl_percentage).",
    vat_percentage = ".cleanNum($vat_percentage).",

    updated_at = NOW()
";

if (mysqli_query($conn, $sql)) {

    // USER LOG
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $msg = "➕ Added New Water Branch | Code: $branch_code | User: " . $_SESSION['name'] . " | IP: $ip";
        userlog($msg);
    } catch (Throwable $e) {
        // silently ignore log failures
    }

    echo json_encode([
        "success" => true,
        "message" => "New branch added successfully."
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Insert failed. Maybe duplicate branch code."
    ]);

}

?>
