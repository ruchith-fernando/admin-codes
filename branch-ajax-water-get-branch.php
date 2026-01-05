<?php
// branch-ajax-water-get-branch.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');

if ($branch_code === '') {
    echo json_encode([
        'success' => false,
        'message' => 'No branch code provided.'
    ]);
    exit;
}

$sql = "
    SELECT
        branch_code,
        branch_name,
        water_type,
        account_number,
        no_of_machines,
        monthly_charge,
        bottle_rate,
        cooler_rental_rate,
        sscl_percentage,
        vat_percentage
    FROM tbl_admin_branch_water
    WHERE branch_code = '" . mysqli_real_escape_string($conn, $branch_code) . "'
    LIMIT 1
";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {

    userlog("❌ Water Branch Lookup FAILED | Branch: {$branch_code}");

    echo json_encode([
        'success' => false,
        'message' => 'Branch not found.'
    ]);
    exit;
}

$row = mysqli_fetch_assoc($res);

userlog("🔍 Water Branch Lookup SUCCESS | Branch: {$branch_code}");

$response = [
    'success'        => true,
    'branch_code'    => $row['branch_code'],
    'branch_name'    => $row['branch_name'],
    'water_type'     => $row['water_type'],
    'account_number' => $row['account_number'] ?? '',

    // machine water
    'monthly_charge' => (float)($row['monthly_charge'] ?? 0),
    'no_of_machines' => (int)($row['no_of_machines'] ?? 1),
    'sscl'           => (float)($row['sscl_percentage'] ?? 0),
    'vat'            => (float)($row['vat_percentage'] ?? 0),

    // bottled water
    'bottle_rate'    => (float)($row['bottle_rate'] ?? 0),
    'cooler_rental'  => (float)($row['cooler_rental_rate'] ?? 0)
];

echo json_encode($response);
?>
