<?php
// photocopy-monthly-missing.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$month = trim($_POST['month'] ?? '');
if ($month === '') {
    echo json_encode(['error' => 'No month selected']);
    exit;
}

// Get all branches
$branches = [];
$branch_sql = "SELECT branch_code, branch_name FROM tbl_admin_branch_photocopy ORDER BY branch_name";
$branch_res = mysqli_query($conn, $branch_sql);
while ($row = mysqli_fetch_assoc($branch_res)) {
    $branches[$row['branch_code']] = $row['branch_name'];
}

// Get all branches that already have actuals for this month
$actual_sql = "
    SELECT DISTINCT branch_code 
    FROM tbl_admin_actual_photocopy
    WHERE TRIM(record_date) = '".mysqli_real_escape_string($conn, $month)."'
";
$actual_res = mysqli_query($conn, $actual_sql);
$have_data = [];
while ($r = mysqli_fetch_assoc($actual_res)) {
    $have_data[] = trim($r['branch_code']);
}

// Compare
$missing = [];
foreach ($branches as $code => $name) {
    if (!in_array($code, $have_data)) {
        $missing[] = "$name ($code)";
    }
}

// Response
if (!empty($missing)) {
    $msg = "<b>".count($missing)." branches need to be entered for $month:</b><br>" . implode(', ', $missing);
} else {
    $msg = "<b>All branches have data entered for $month ✅</b>";
}

echo json_encode([
    'success' => true,
    'missing' => $missing,
    'missing_html' => $msg
]);
?>
