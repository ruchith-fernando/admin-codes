<?php
include 'connections/connection.php';
header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');

if (!$username) {
    echo json_encode(["status" => "error", "message" => "Username required"]);
    exit;
}

$chk = $conn->prepare("SELECT id FROM tbl_admin_users WHERE hris = ?");
$chk->bind_param("s", $username);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    echo json_encode(["status" => "exists", "message" => "❌ Username already exists."]);
} else {
    echo json_encode(["status" => "ok", "message" => "✔ Username available"]);
}
