<?php
include 'connections/connection.php';

$term = $_GET['term'] ?? '';

$sql = "SELECT branch_id, branch_name FROM tbl_admin_branch_information 
        WHERE branch_id LIKE ? OR branch_name LIKE ? 
        ORDER BY branch_name ASC 
        LIMIT 50";

$stmt = $conn->prepare($sql);
$searchTerm = "%{$term}%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$branches = [];
while ($row = $result->fetch_assoc()) {
    $branches[] = [
        "id" => $row['branch_id'],
        "text" => $row['branch_id'] . " - " . $row['branch_name']
    ];
}

echo json_encode(['results' => $branches]);
