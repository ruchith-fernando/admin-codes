<?php
include 'connections/connection.php';

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'];
$vehicle_type = $conn->real_escape_string($data['vehicle_type']);
$make = $conn->real_escape_string($data['make']);
$model = $conn->real_escape_string($data['model']);
$yom = $conn->real_escape_string($data['yom']);
$new_comments = $conn->real_escape_string($data['new_comments']);

$sql = "UPDATE tbl_admin_fixed_assets 
        SET vehicle_type='$vehicle_type', make='$make', model='$model', yom='$yom', new_comments='$new_comments' 
        WHERE id='$id'";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$conn->close();
?>
