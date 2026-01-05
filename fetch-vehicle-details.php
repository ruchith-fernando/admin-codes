<?php
require_once 'connections/connection.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(null);
    exit;
}

$id = intval($_GET['id']);
$sql = "SELECT * FROM tbl_admin_vehicle WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode($result->fetch_assoc() ?: null);
$stmt->close();
$conn->close();
?>
