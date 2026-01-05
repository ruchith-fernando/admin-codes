<?php
// ajax-get-photocopy-branch.php
require_once 'connections/connection.php';
header('Content-Type: application/json');

$branch_code = trim($_POST['branch_code'] ?? '');

if ($branch_code === '') {
    echo json_encode([
        'success' => false,
        'html' => '<div class="alert alert-warning" role="alert">
                     ⚠️ Please enter a branch code.
                   </div>'
    ]);
    exit;
}

$sql = "
    SELECT serial_number, branch_code, branch_name, rate
    FROM tbl_admin_branch_photocopy
    WHERE branch_code = '".mysqli_real_escape_string($conn, $branch_code)."'
    LIMIT 1
";
$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    echo json_encode([
        'success' => true,
        'serial_number' => $row['serial_number'],
        'branch_name' => $row['branch_name'],
        'rate' => (float)$row['rate']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'html' => '<div class="alert alert-danger" role="alert">
                     ❌ Branch code not found. Please verify and try again.
                   </div>'
    ]);
}
?>
