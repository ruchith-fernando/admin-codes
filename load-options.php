<?php
include 'connections/connection.php';

$type = $_GET['type'] ?? '';

$options = [];

if ($type === 'designation') {
    $query = "SELECT id, designation_name as name FROM tbl_admin_designation";
} elseif ($type === 'branch') {
    $query = "SELECT id, branch_name as name FROM tbl_admin_branch_information";
} elseif ($type === 'category') {
    $query = "SELECT id, category_name as name FROM tbl_admin_employee_category";
}

$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $options[] = ['id' => $row['name'], 'text' => $row['name']];
}

echo json_encode($options);
?>