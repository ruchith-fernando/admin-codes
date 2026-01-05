<?php
session_start();
require_once 'connections/connection.php';

$logged = $_SESSION['hris'] ?? '';
$search = trim($_GET['search'] ?? '');
$param = "%" . $search . "%";

$sql = "SELECT * FROM tbl_admin_vehicle
        WHERE status='Pending'
        AND created_by <> ?";

if ($search !== '') {
    $sql .= " AND (
        vehicle_number LIKE ?
        OR make_model LIKE ?
        OR assigned_user LIKE ?
        OR assigned_user_hris LIKE ?
        OR created_by LIKE ?
    )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $logged, $param, $param, $param, $param, $param);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $logged);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<div class='alert alert-info'>No pending vehicle entries found.</div>";
    exit;
}

echo "<div class='table-responsive'>
<table class='table table-bordered table-sm align-middle'>
<thead class='table-light'>
<tr>
<th>#</th>
<th>Vehicle Number</th>
<th>Type</th>
<th>Make/Model</th>
<th>Fuel</th>
<th>Purchase Date</th>
<th>Value</th>
<th>Mileage</th>
<th>Assigned User</th>
<th>HRIS</th>
<th>Category</th>
<th>Entered By</th>
<th>Help ID</th>
</tr>
</thead><tbody>";

$counter = 1;

while ($r = $result->fetch_assoc()) {

    $purchaseValue = number_format((float)str_replace(',', '', $r['purchase_value']), 2);
    $mileage = number_format((float)str_replace(',', '', $r['original_mileage']));

    echo "
    <tr class='vehicle-row'
        data-id='{$r['id']}'
        data-created='{$r['created_by']}'>
        <td>{$counter}</td>
        <td>{$r['vehicle_number']}</td>
        <td>{$r['vehicle_type']}</td>
        <td>{$r['make_model']}</td>
        <td>{$r['fuel_type']}</td>
        <td>{$r['purchase_date']}</td>
        <td>{$purchaseValue}</td>
        <td>{$mileage} km</td>
        <td>{$r['assigned_user']}</td>
        <td>{$r['assigned_user_hris']}</td>
        <td>{$r['vehicle_category']}</td>
        <td>{$r['created_by']}</td>
        <td>{$r['sr_number']}</td>
    </tr>";

    $counter++;
}

echo "</tbody></table></div>";
