<?php
// branch-water-monthly-fetch.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$month = trim($_POST['month'] ?? '');
if ($month === '') {
    echo json_encode(['error' => 'No month selected']);
    exit;
}

$user_hris = $_SESSION['hris'] ?? '';
if ($user_hris === '') {
    echo json_encode(['error' => 'User session missing']);
    exit;
}

/* -------------------------------------------------------
   GET BRANCHES ASSIGNED TO THIS USER FOR WATER
------------------------------------------------------- */
$user_branch_sql = "
    SELECT branch_code 
    FROM tbl_admin_user_branch_access
    WHERE user_hris = '" . mysqli_real_escape_string($conn, $user_hris) . "'
      AND utility_name = 'water'
";

$ubr = mysqli_query($conn, $user_branch_sql);
$userBranches = [];

while ($u = mysqli_fetch_assoc($ubr)) {
    $userBranches[] = $u['branch_code'];
}

if (empty($userBranches)) {
    echo json_encode([
        'table' => '',
        'missing' => [],
        'provisions' => [],
        'pending' => []
    ]);
    exit;
}

// convert to SQL-safe list
$in = "'" . implode("','", array_map('mysqli_real_escape_string', array_fill(0, count($userBranches), $conn), $userBranches)) . "'";

/* -------------------------------------------------------
   MASTER BRANCH LIST FOR WATER (LIMITED TO USER)
------------------------------------------------------- */
$branch_sql = "
    SELECT branch_code, branch_name, water_type
    FROM tbl_admin_branch_water
    WHERE branch_code IN ($in)
    ORDER BY branch_code
";

$branch_res = mysqli_query($conn, $branch_sql);
$master = [];

while ($r = mysqli_fetch_assoc($branch_res)) {
    $master[$r['branch_code']] = [
        'branch_name' => $r['branch_name'],
        'water_type'  => $r['water_type']
    ];
}

/* -------------------------------------------------------
   FETCH ACTUAL APPROVED ONLY
------------------------------------------------------- */
$actual_sql = "
    SELECT reference_no,
        branch_code,
        branch,
        water_type,
        total_amount
    FROM tbl_admin_actual_water
    WHERE month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
      AND approval_status = 'approved'
      AND branch_code IN ($in)
";

$actual_res = mysqli_query($conn, $actual_sql);
$approved = [];

while ($r = mysqli_fetch_assoc($actual_res)) {
    $approved[$r['branch_code']] = $r;
}

/* -------------------------------------------------------
   MISSING LIST
------------------------------------------------------- */
$missing = [];

foreach ($master as $code => $m) {
    if (!isset($approved[$code])) {
        $missing[] = $m['branch_name'] . " (" . $code . ")";
    }
}

/* -------------------------------------------------------
   BUILD REPORT TABLE
------------------------------------------------------- */
$table_html = "
<table class='table table-bordered table-striped'>
<thead class='table-light'>
<tr>
    <th>Reference Number</th>
    <th>Branch Code</th>
    <th>Branch Name</th>
    <th>Water Type</th>
    <th>Actual Amount (Approved)</th>
</tr>
</thead>
<tbody>
";

$total_actual = 0;

foreach ($master as $code => $mdata) {

    $branch = $mdata['branch_name'];
    $type   = $mdata['water_type'];

    $ref_no = isset($approved[$code]['reference_no']) ? $approved[$code]['reference_no'] : '';
    $a_amt  = isset($approved[$code]['total_amount'])
                ? (float)str_replace(",", "", $approved[$code]['total_amount'])
                : 0;

    $total_actual += $a_amt;

    $table_html .= "
    <tr>
        <td>{$ref_no}</td>
        <td>{$code}</td>
        <td>{$branch}</td>
        <td>{$type}</td>
        <td>" . number_format($a_amt, 2) . "</td>
    </tr>";
}


$table_html .= "
<tr class='table-secondary fw-bold'>
    <td colspan='4'>Total</td>
    <td>" . number_format($total_actual, 2) . "</td>
</tr>
</tbody>
</table>
";

/* -------------------------------------------------------
   LOG THIS VIEW
------------------------------------------------------- */
userlog("📄 Water Fetch | Month: $month | User: $user_hris");

/* -------------------------------------------------------
   RETURN JSON
------------------------------------------------------- */
echo json_encode([
    'table'      => $table_html,
    'missing'    => $missing,
    'provisions' => [], // no more provisions
    'pending'    => []  // no pending
]);
?>
