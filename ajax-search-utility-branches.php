<?php
include 'connections/connection.php';

$term = $_POST['term'] ?? '';
$utility = $_POST['utility'] ?? '';

$map = [
    'water'         => 'tbl_admin_branch_water',
    'electricity'   => 'tbl_admin_branch_electricity',
    'newspaper'     => 'tbl_admin_branch_newspaper',
    'courier'       => 'tbl_admin_branch_courier',
    'photocopy'     => 'tbl_admin_branch_photocopy',
    'printing'      => 'tbl_admin_branch_printing',
    'tea-branches'  => 'tbl_admin_branch_tea_branches'
];

$table = $map[$utility] ?? '';

if (!$table) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT branch_code, branch_name 
    FROM $table
    WHERE branch_name LIKE '%$term%'
       OR branch_code LIKE '%$term%'
    ORDER BY branch_name
";

$res = mysqli_query($conn, $sql);
$output = [];

while ($r = mysqli_fetch_assoc($res)) {
    $output[] = [
        "id" => $r['branch_code'],
        "text" => $r['branch_code'] . " - " . $r['branch_name']
    ];
}

echo json_encode($output);
