<?php
include 'connections/connection.php';

$search = $_GET['term'] ?? '';

$sql = "SELECT location_name FROM tbl_admin_locations WHERE location_name LIKE ? ORDER BY location_name ASC";
$stmt = $conn->prepare($sql);
$search_param = "%$search%";
$stmt->bind_param("s", $search_param);
$stmt->execute();
$result = $stmt->get_result();

$locations = [];
while ($row = $result->fetch_assoc()) {
    $locations[] = ['id' => $row['location_name'], 'text' => $row['location_name']];
}

echo json_encode($locations);
?>
