<?php
// ajax-get-pending-vehicles.php
session_start();
require_once 'connections/connection.php';

$logged_hris = $_SESSION['hris'] ?? '';
$search = trim($_GET['search'] ?? '');
$param = '%' . $search . '%';

$sql = "SELECT id, vehicle_type, vehicle_number, make_model, fuel_type, purchase_date, purchase_value,
        original_mileage, assigned_user, assigned_user_hris, sr_number, vehicle_category, created_by 
        FROM tbl_admin_vehicle 
        WHERE status = 'Pending'";

if (!empty($search)) {
    $sql .= " AND (
        vehicle_number LIKE ? OR
        make_model LIKE ? OR
        assigned_user LIKE ? OR
        assigned_user_hris LIKE ? OR
        created_by LIKE ?
    )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $param, $param, $param, $param);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
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
          </thead>
          <tbody>";

$counter = 1;
while ($row = $result->fetch_assoc()) {
    $isCreator = ($row['created_by'] === $logged_hris);
    $rowClass = $isCreator ? "not-allowed" : "vehicle-row";
    $cursor = $isCreator ? "not-allowed" : "pointer";

    echo "<tr class='{$rowClass}' 
              data-id='{$row['id']}' 
              data-created='{$row['created_by']}' 
              style='cursor: {$cursor};'>
            <td>{$counter}</td>
            <td>{$row['vehicle_number']}</td>
            <td>{$row['vehicle_type']}</td>
            <td>{$row['make_model']}</td>
            <td>{$row['fuel_type']}</td>
            <td>{$row['purchase_date']}</td>
            <td>" . number_format($row['purchase_value'], 2) . "</td>
            <td>" . number_format($row['original_mileage']) . " km</td>
            <td>{$row['assigned_user']}</td>
            <td>{$row['assigned_user_hris']}</td>
            <td>{$row['vehicle_category']}</td>
            <td>{$row['created_by']}</td>
            <td>{$row['sr_number']}</td>
          </tr>";
    $counter++;
}

echo "</tbody></table></div>";
?>
