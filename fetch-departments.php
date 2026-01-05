<?php
include 'connections/connection.php';

$search = $_GET['term'] ?? '';
$sql = "SELECT DISTINCT department_name AS id, department_name AS text FROM tbl_admin_departments WHERE department_name LIKE ? ORDER BY department_name ASC";
$stmt = $conn->prepare($sql);
$like = "%$search%";
$stmt->bind_param("s", $like);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$stmt->close();
$conn->close();
?>
