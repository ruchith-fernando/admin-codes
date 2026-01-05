<?php
// branch-water-save.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

function cleanNum($v){
    $v = trim($v);
    if ($v === "") return "NULL";
    if (!is_numeric($v)) return "NULL";
    if ($v < 0) return "NULL";
    return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
}

/* --------------------------
   INPUTS
---------------------------*/
$id                = intval($_POST['id'] ?? 0);
$branch_code       = trim($_POST['branch_code'] ?? '');
$branch_name       = trim($_POST['branch_name'] ?? '');
$vendor_name       = trim($_POST['vendor_name'] ?? '');
$water_type        = trim($_POST['water_type'] ?? '');

$account_number    = trim($_POST['account_number'] ?? '');

$no_of_machines    = trim($_POST['no_of_machines'] ?? '');
$monthly_charge    = trim($_POST['monthly_charge'] ?? '');

$bottle_rate       = trim($_POST['bottle_rate'] ?? '');
$cooler_rental     = trim($_POST['cooler_rental_rate'] ?? '');
$sscl              = trim($_POST['sscl_percentage'] ?? '');
$vat               = trim($_POST['vat_percentage'] ?? '');


/* --------------------------
   VALIDATION
---------------------------*/
if ($id <= 0){
    echo json_encode(["success" => false, "message" => "Invalid branch ID."]);
    exit;
}

if ($branch_code === "" || $branch_name === "" || $water_type === ""){
    echo json_encode([
        "success" => false,
        "message" => "Please fill Branch Code, Branch Name and Water Type."
    ]);
    exit;
}

/* --------------------------
   FIELD CLEANUP BASED ON TYPE
---------------------------*/

// --- MACHINE ---
if ($water_type === "MACHINE") {
    $no_of_machines = ($no_of_machines !== "" && $no_of_machines >= 1) ? $no_of_machines : 1;
    $monthly_charge = ($monthly_charge !== "" && $monthly_charge >= 0) ? $monthly_charge : 0;

    $bottle_rate = $cooler_rental = $sscl = $vat = "";
}

// --- BOTTLE ---
if ($water_type === "BOTTLE") {
    $no_of_machines = "";
    $monthly_charge = "";

    $bottle_rate   = ($bottle_rate !== "" && $bottle_rate >= 0) ? $bottle_rate : 0;
    $cooler_rental = ($cooler_rental !== "" && $cooler_rental >= 0) ? $cooler_rental : 0;
    $sscl          = ($sscl !== "" && $sscl >= 0) ? $sscl : 0;
    $vat           = ($vat !== "" && $vat >= 0) ? $vat : 0;
}

// --- NWSDB ---
if ($water_type === "NWSDB") {
    $no_of_machines = "";
    $monthly_charge = "";
    $bottle_rate = $cooler_rental = $sscl = $vat = "";
}

/* --------------------------
   SQL UPDATE
---------------------------*/
$sql = "
UPDATE tbl_admin_branch_water SET
    branch_code        = '".mysqli_real_escape_string($conn, $branch_code)."',
    branch_name        = '".mysqli_real_escape_string($conn, $branch_name)."',
    vendor_name        = ".($vendor_name==="" ? "NULL" : "'".mysqli_real_escape_string($conn,$vendor_name)."'").",
    water_type         = '".mysqli_real_escape_string($conn, $water_type)."',
    account_number     = ".($account_number==="" ? "NULL" : "'".mysqli_real_escape_string($conn,$account_number)."'").",

    no_of_machines     = ".cleanNum($no_of_machines).",
    monthly_charge     = ".cleanNum($monthly_charge).",

    bottle_rate        = ".cleanNum($bottle_rate).",
    cooler_rental_rate = ".cleanNum($cooler_rental).",
    sscl_percentage    = ".cleanNum($sscl).",
    vat_percentage     = ".cleanNum($vat).",

    updated_at = NOW()
WHERE id = $id
LIMIT 1
";

if (mysqli_query($conn, $sql)) {
    userlog("✔️ Branch Master Updated | ID: $id | Branch: $branch_code | User: ".$_SESSION['name']);
    echo json_encode([
        "success" => true,
        "message" => "Branch updated successfully."
    ]);
} else {
    userlog("❌ Branch Update Failed | ID: $id | ".mysqli_error($conn));
    echo json_encode([
        "success" => false,
        "message" => "Database error. Could not save."
    ]);
}
?>
