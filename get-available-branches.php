<?php
require_once 'connections/connection.php';

header('Content-Type: application/json');

$month = $_GET['month'] ?? '';
$search = $_GET['q'] ?? '';

if (!$month) {
    echo json_encode([]);
    exit;
}

// Get branch codes to exclude
$excluded = [];
$ex_query = mysqli_query($conn, "
    SELECT branch_code 
    FROM tbl_admin_actual_security 
    WHERE month_applicable = '$month' 
      AND actual_shifts > 0 
      AND provision = 'no'
");

while ($r = mysqli_fetch_assoc($ex_query)) {
    $excluded[] = $r['branch_code'];
}

// Get branches from budget table
$results = [];
$branch_query = mysqli_query($conn, "
    SELECT DISTINCT branch_code, branch 
    FROM tbl_admin_budget_security 
    WHERE branch_code LIKE '%$search%' 
    ORDER BY branch_code
");

while ($row = mysqli_fetch_assoc($branch_query)) {
    if (!in_array($row['branch_code'], $excluded)) {
        $results[] = [
            'id' => $row['branch_code'],
            'text' => $row['branch_code'] . ' - ' . $row['branch']
        ];
    }
}

echo json_encode($results);
