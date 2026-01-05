<?php
include 'connections/connection.php';

header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$designation = trim($_POST['designation'] ?? '');
$title = trim($_POST['title'] ?? '');
$company_hierarchy = trim($_POST['company_hierarchy'] ?? '');
$location = trim($_POST['location'] ?? '');
$branch_code = trim($_POST['branch_code'] ?? '');

$utility_json = $_POST['utility_json'] ?? '[]';
$utilityData = json_decode($utility_json, true);

// Required field check
if (!$name || !$username || !$password) {
    echo json_encode(["status" => "error", "message" => "❌ Missing required fields."]);
    exit;
}

// HRIS username validation
if (!preg_match('/^\d{8}$/', $username)) {
    echo json_encode(["status" => "error", "message" => "❌ Username must be 8-digit HRIS."]);
    exit;
}

// Check if user already exists
$chk = $conn->prepare("SELECT id FROM tbl_admin_users WHERE hris=?");
$chk->bind_param("s", $username);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "❌ User already exists."]);
    exit;
}

$chk->close();

// Insert new user
$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
INSERT INTO tbl_admin_users 
(name, hris, password, designation, title, company_hierarchy, location, branch_code)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssss",
    $name, $username, $hashed,
    $designation, $title, $company_hierarchy,
    $location, $branch_code
);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "❌ Error: " . $stmt->error]);
    exit;
}

// Insert utility & branch access
foreach ($utilityData as $item) {
    foreach ($item['branches'] as $branch) {
        $conn->query("
            INSERT INTO tbl_admin_user_branch_access (user_hris, utility_name, branch_code)
            VALUES ('$username', '{$item['utility']}', '$branch')
        ");
    }
}

echo json_encode(["status" => "success", "message" => "✅ User registered successfully."]);
