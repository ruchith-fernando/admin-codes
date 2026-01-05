<?php
include 'connections/connection.php';
session_start();

if (!isset($_SESSION['hris']) || !isset($_SESSION['branch_code']) || !isset($_POST['item_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required session or form data.']);
    exit;
}

$itemCode = $_POST['item_code'];
$quantity = intval($_POST['quantity']);
$requestType = $_POST['request_type'];

$hris = $_SESSION['hris'];
$branchCode = $_SESSION['branch_code'];
$branchName = $_SESSION['branch_name'] ?? ''; // optional
$requestedDate = date('Y-m-d');
$createdAt = date('Y-m-d H:i:s');

// generate order_number
$orderNumber = 'ORD-' . date('Ymd-His') . '-' . rand(100, 999);

// 1. insert into tbl_admin_stationary_orders
$orderSQL = "INSERT INTO tbl_admin_stationary_orders (order_number, branch_code, branch_name, requested_date, created_by, created_at, request_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')";

$stmt1 = $conn->prepare($orderSQL);
$stmt1->bind_param('sssssss', $orderNumber, $branchCode, $branchName, $requestedDate, $hris, $createdAt, $requestType);

if (!$stmt1->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create order.']);
    exit;
}

// 2. insert into tbl_admin_stationary_stock_out
$stockSQL = "INSERT INTO tbl_admin_stationary_stock_out (order_number, item_code, quantity, issued_date, branch_code, branch_name, created_by, created_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

$stmt2 = $conn->prepare($stockSQL);
$stmt2->bind_param('ssisssss', $orderNumber, $itemCode, $quantity, $requestedDate, $branchCode, $branchName, $hris, $createdAt);

if (!$stmt2->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save item.']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully.']);
?>
