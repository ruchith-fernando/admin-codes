<?php
session_start();
include 'connections/connection.php';
include 'includes/sr-generator.php';

header('Content-Type: application/json');

$created_by = $_SESSION['hris'] ?? 'UNKNOWN';
$user_levels = explode(',', $_SESSION['user_level'] ?? '');
$user_levels = array_map('trim', $user_levels); // clean whitespace

$branch_code = $_POST['branch_code'] ?? '';
$branch_name = $_POST['branch_name'] ?? '';
$request_type = $_POST['request_type'] ?? '';
$issued_date = $_POST['issued_date'] ?? '';

$item_codes = $_POST['item_code'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$branch_stocks = $_POST['branch_stock'] ?? [];

// Validation
if (empty($request_type) || empty($issued_date) || empty($branch_code) ||
    count($item_codes) !== count($quantities) || count($item_codes) !== count($branch_stocks)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid input']);
    exit;
}

$conn->begin_transaction();
try {
    // Determine order status based on user role
    $is_boic = in_array('boic', $user_levels);
    $order_status = $is_boic ? 'pending_admin' : 'draft';

    // Generate order_number
    if ($request_type === 'stationery_pack') {
        $date = new DateTime($issued_date);
        $cutoff = new DateTime($date->format('Y-m-15 15:00:00'));
        if ($date > $cutoff) $date->modify('+1 month');
        $group_month = $date->format('F-Y');
        $order_number = strtoupper($request_type) . "-" . $branch_code . "-" . $group_month;
    } else {
        $suffix = (new DateTime($issued_date))->format('Ymd');
        $order_number = strtoupper($request_type) . "-" . $branch_code . "-" . $suffix;
    }

    // Insert order with correct status
    $insertOrder = $conn->prepare("INSERT INTO tbl_admin_stationary_orders 
        (order_number, branch_code, branch_name, requested_date, created_by, request_type, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertOrder->bind_param("sssssss", $order_number, $branch_code, $branch_name, $issued_date, $created_by, $request_type, $order_status);
    if (!$insertOrder->execute()) throw new Exception('Failed to create order');

    // Generate SR number
    $order_id = $insertOrder->insert_id;
    $sr_number = generate_sr_number($conn, 'tbl_admin_stationary_orders', $order_id);
    $stmtSR = $conn->prepare("UPDATE tbl_admin_stationary_orders SET sr_number = ? WHERE id = ?");
    $stmtSR->bind_param("si", $sr_number, $order_id);
    $stmtSR->execute();

    // Insert items with dual_control_status = pending
    $insertItem = $conn->prepare("INSERT INTO tbl_admin_stationary_stock_out 
        (order_number, item_code, branch_stock, quantity, 
         issued_date, branch_code, branch_name, created_by, status, dual_control_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");

    foreach ($item_codes as $i => $item_code) {
        $quantity_needed = intval($quantities[$i]);
        $branch_stock = intval($branch_stocks[$i]);

        $insertItem->bind_param("sssissss", 
            $order_number, $item_code, $branch_stock, $quantity_needed,
            $issued_date, $branch_code, $branch_name, $created_by);

        if (!$insertItem->execute()) {
            throw new Exception("Failed to insert item $item_code");
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
?>
