<?php
// branch-water-get.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// --------------------------
// Validate input
// --------------------------
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid branch."
    ]);
    exit;
}

// --------------------------
// Fetch the branch record
// --------------------------
$sql = "
    SELECT *
    FROM tbl_admin_branch_water
    WHERE id = $id
    LIMIT 1
";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Branch not found."
    ]);
    exit;
}

$row = mysqli_fetch_assoc($res);

userlog("✏️ Branch Master Edit Loaded | ID: $id | User: ".$_SESSION['name']);

// --------------------------
// Prepare JSON Response
// --------------------------
echo json_encode([
    "success" => true,

    "id"                 => $row["id"],
    "branch_code"        => $row["branch_code"],
    "branch_name"        => $row["branch_name"],
    "vendor_name"        => $row["vendor_name"],
    "water_type"         => $row["water_type"],
    "account_number"     => $row["account_number"],

    "no_of_machines"     => (int)$row["no_of_machines"],

    "monthly_charge"     => $row["monthly_charge"],
    "rate"               => $row["rate"],

    // bottle-related
    "bottle_rate"        => $row["bottle_rate"],
    "cooler_rental_rate" => $row["cooler_rental_rate"],
    "sscl_percentage"    => $row["sscl_percentage"],
    "vat_percentage"     => $row["vat_percentage"],

    "updated_at"         => $row["updated_at"]
]);
?>
