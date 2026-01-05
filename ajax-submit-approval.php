<?php
// ajax-submit-approval.php
include 'connections/connection.php';
session_start();

$hris = $_SESSION['hris'] ?? '';
$user_levels_raw = $_SESSION['user_level'] ?? '';
$user_levels = array_map('trim', explode(',', strtolower($user_levels_raw)));

if (!in_array('storekeeper', $user_levels) && !in_array('head_of_admin', $user_levels)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized action.']);
    exit;
}

$order_number = $_POST['order_number'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');
$action = $_POST['action'] ?? ''; // approved or rejected
$approved_qty = $_POST['approved_qty'] ?? [];

if (!$order_number || !in_array($action, ['approved', 'rejected']) || empty($approved_qty)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
    exit;
}

$creatorQuery = $conn->prepare("SELECT created_by FROM tbl_admin_stationary_orders WHERE order_number = ?");
$creatorQuery->bind_param("s", $order_number);
$creatorQuery->execute();
$creatorResult = $creatorQuery->get_result();
if ($creatorResult->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
    exit;
}
$created_by = $creatorResult->fetch_assoc()['created_by'];

$conn->begin_transaction();

try {
    if ($action === 'approved') {
        foreach ($approved_qty as $id => $qty) {
            $qty = (int)$qty;
            $id = (int)$id;

            $stmt = $conn->prepare("UPDATE tbl_admin_stationary_stock_out 
                                    SET approved_quantity = ?, status = 'approved',
                                        dual_control_status = 'approved',
                                        dual_control_by = ?, dual_control_at = NOW()
                                    WHERE id = ?");
            $stmt->bind_param("isi", $qty, $hris, $id);
            $stmt->execute();

            $fifoResult = processFIFO($id, $qty, $conn);
            if (!$fifoResult['success']) {
                throw new Exception("FIFO failed: " . $fifoResult['message']);
            }
        }

        $updateOrder = $conn->prepare("UPDATE tbl_admin_stationary_orders SET status = 'awaiting_ack' WHERE order_number = ?");
        $updateOrder->bind_param("s", $order_number);
        $updateOrder->execute();

        $notifMessage = "Your request $order_number has been approved and processed.";
    } else {
        foreach ($approved_qty as $id => $qty) {
            $id = (int)$id;
            $stmt = $conn->prepare("UPDATE tbl_admin_stationary_stock_out 
                                    SET status = 'rejected',
                                        dual_control_status = 'rejected',
                                        dual_control_by = ?, dual_control_at = NOW(),
                                        remarks = ?
                                    WHERE id = ?");
            $stmt->bind_param("ssi", $hris, $remarks, $id);
            $stmt->execute();
        }

        $updateOrder = $conn->prepare("UPDATE tbl_admin_stationary_orders SET status = 'rejected' WHERE order_number = ?");
        $updateOrder->bind_param("s", $order_number);
        $updateOrder->execute();

        $notifMessage = "Your request $order_number was rejected. Remarks: " . $remarks;
    }

    $approvalLog = $conn->prepare("INSERT INTO tbl_admin_stationary_approvals 
                                   (order_number, approved_by, action, remarks) 
                                   VALUES (?, ?, ?, ?)");
    $approvalLog->bind_param("ssss", $order_number, $hris, $action, $remarks);
    $approvalLog->execute();

    $notif = $conn->prepare("INSERT INTO tbl_admin_notifications 
                             (hris, message, related_order_number) 
                             VALUES (?, ?, ?)");
    $notif->bind_param("sss", $created_by, $notifMessage, $order_number);
    $notif->execute();

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Action completed successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}

function processFIFO($stockOutId, $qtyNeeded, $conn) {
    $itemQuery = $conn->prepare("SELECT item_code FROM tbl_admin_stationary_stock_out WHERE id = ?");
    $itemQuery->bind_param("i", $stockOutId);
    $itemQuery->execute();
    $itemRes = $itemQuery->get_result();
    if ($itemRes->num_rows === 0) return ['success' => false, 'message' => 'Item not found.'];
    $itemCode = $itemRes->fetch_assoc()['item_code'];

    $fifoQuery = $conn->prepare("SELECT id, remaining_quantity, unit_price 
                                 FROM tbl_admin_stationary_stock_in 
                                 WHERE item_code = ? AND remaining_quantity > 0 AND status = 'approved'
                                 ORDER BY received_date ASC");
    $fifoQuery->bind_param("s", $itemCode);
    $fifoQuery->execute();
    $fifoRes = $fifoQuery->get_result();

    $qtyToAllocate = $qtyNeeded;
    $totalCost = 0;
    $allocated = false;

    while ($row = $fifoRes->fetch_assoc()) {
        if ($qtyToAllocate <= 0) break;
        $stockInId = $row['id'];
        $availableQty = (int)$row['remaining_quantity'];
        $unitPrice = $row['unit_price'];

        $useQty = min($availableQty, $qtyToAllocate);
        $remaining = $availableQty - $useQty;

        $updateIn = $conn->prepare("UPDATE tbl_admin_stationary_stock_in SET remaining_quantity = ? WHERE id = ?");
        $updateIn->bind_param("ii", $remaining, $stockInId);
        $updateIn->execute();

        $partialCost = $useQty * $unitPrice;
        $totalCost += $partialCost;

        $allocated = true;
        $qtyToAllocate -= $useQty;
    }

    if ($qtyToAllocate > 0 || !$allocated) {
        return ['success' => false, 'message' => 'Not enough stock available.'];
    }

    $updateOut = $conn->prepare("UPDATE tbl_admin_stationary_stock_out 
                                 SET total_cost = ?, unit_price = ?
                                 WHERE id = ?");
    $unitPriceFinal = $totalCost / $qtyNeeded;
    $updateOut->bind_param("ddi", $totalCost, $unitPriceFinal, $stockOutId);
    $updateOut->execute();

    return ['success' => true];
}
