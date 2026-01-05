<?php
session_start();
include 'connections/connection.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['name']) || !in_array($_SESSION['user_level'], ['authorizer', 'super-admin'])) {
    exit('Unauthorized access.');
}

$logged_user = $_SESSION['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $approved_quantity = intval($_POST['approved_quantity']);
    $remarks = $_POST['remarks'] ?? null;

    if ($action === 'approve') {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT item_code, branch_code, quantity FROM tbl_admin_stationary_stock_out WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->bind_result($item_code, $branch_code, $requested_quantity);
            $stmt->fetch();
            $stmt->close();

            $stmtFIFO = $conn->prepare("SELECT id, unit_price, sscl_amount, vat_amount, remaining_quantity 
                                        FROM tbl_admin_stationary_stock_in 
                                        WHERE item_code = ? AND remaining_quantity > 0 
                                        ORDER BY received_date ASC");
            $stmtFIFO->bind_param("s", $item_code);
            $stmtFIFO->execute();
            $resultFIFO = $stmtFIFO->get_result();

            $qty_needed = $approved_quantity;
            $total_subtotal = 0;
            $total_sscl = 0;
            $total_vat = 0;
            $total_cost = 0;
            $first_stock_in_id = null;

            while ($row = $resultFIFO->fetch_assoc()) {
                if ($qty_needed <= 0) break;

                $use_qty = min($qty_needed, $row['remaining_quantity']);

                $line_subtotal = $row['unit_price'] * $use_qty;
                $line_sscl = $row['sscl_amount'] * $use_qty;
                $line_vat = $row['vat_amount'] * $use_qty;
                $line_total = $line_subtotal + $line_sscl + $line_vat;

                $updateIn = $conn->prepare("UPDATE tbl_admin_stationary_stock_in SET remaining_quantity = remaining_quantity - ? WHERE id = ?");
                $updateIn->bind_param("ii", $use_qty, $row['id']);
                $updateIn->execute();

                $total_subtotal += $line_subtotal;
                $total_sscl += $line_sscl;
                $total_vat += $line_vat;
                $total_cost += $line_total;

                if ($first_stock_in_id === null) {
                    $first_stock_in_id = $row['id'];
                }

                $qty_needed -= $use_qty;
            }

            $stmtFIFO->close();

            if ($qty_needed > 0) {
                throw new Exception("inadequate_stock");
            }

            $unit_final_price = ($approved_quantity > 0) ? round($total_cost / $approved_quantity, 2) : 0;
            $unit_price_used = round($total_subtotal / $approved_quantity, 2);

            $updateOut = $conn->prepare("UPDATE tbl_admin_stationary_stock_out 
                                         SET approved_quantity = ?, 
                                             unit_price = ?, 
                                             sscl_amount = ?, 
                                             vat_amount = ?, 
                                             total_cost = ?, 
                                             unit_final_price = ?, 
                                             stock_in_id = ?, 
                                             dual_control_status = 'approved', 
                                             dual_control_by = ?, 
                                             dual_control_at = NOW(),
                                             remarks = ?
                                         WHERE id = ?");
            $updateOut->bind_param("ddddddissi", 
                $approved_quantity, 
                $unit_price_used, 
                $total_sscl, 
                $total_vat, 
                $total_cost, 
                $unit_final_price, 
                $first_stock_in_id, 
                $logged_user,
                $remarks,
                $id
            );
            $updateOut->execute();

            $insertAudit = $conn->prepare("INSERT INTO tbl_admin_stock_out_approval_audit 
                (stock_out_id, item_code, branch_code, approved_by, action_taken, approved_quantity, remarks) 
                VALUES (?, ?, ?, ?, 'approved', ?, ?)");
            $insertAudit->bind_param("isssis", $id, $item_code, $branch_code, $logged_user, $approved_quantity, $remarks);
            $insertAudit->execute();

            $conn->commit();
            echo 'approved';
        } catch (Exception $e) {
            $conn->rollback();
            error_log("FIFO approval error: " . $e->getMessage());
            echo 'error:' . $e->getMessage();
        }
        exit;
    }

    if ($action === 'reject') {
        $stmtDetails = $conn->prepare("SELECT item_code, branch_code FROM tbl_admin_stationary_stock_out WHERE id = ?");
        $stmtDetails->bind_param("i", $id);
        $stmtDetails->execute();
        $stmtDetails->bind_result($item_code, $branch_code);
        $stmtDetails->fetch();
        $stmtDetails->close();

        $updateOut = $conn->prepare("UPDATE tbl_admin_stationary_stock_out 
                                     SET dual_control_status = 'rejected', 
                                         dual_control_by = ?, 
                                         dual_control_at = NOW(),
                                         remarks = ?
                                     WHERE id = ?");
        $updateOut->bind_param("ssi", $logged_user, $remarks, $id);
        $updateOut->execute();

        $insertAudit = $conn->prepare("INSERT INTO tbl_admin_stock_out_approval_audit 
            (stock_out_id, item_code, branch_code, approved_by, action_taken, approved_quantity, remarks) 
            VALUES (?, ?, ?, ?, 'rejected', 0, ?)");
        $insertAudit->bind_param("issss", $id, $item_code, $branch_code, $logged_user, $remarks);
        $insertAudit->execute();

        echo 'rejected';
        exit;
    }
}

// Handle GET request to return HTML rows
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $sql = "SELECT o.*, m.item_description, 
                   (SELECT SUM(remaining_quantity) FROM tbl_admin_stationary_stock_in WHERE item_code = o.item_code) AS remaining_quantity 
            FROM tbl_admin_stationary_stock_out o 
            JOIN tbl_admin_print_stationary_master m ON o.item_code = m.item_code 
            WHERE o.dual_control_status = 'pending'";

    if (!empty($q)) {
        $qSafe = $conn->real_escape_string($q);
        $sql .= " AND (o.item_code LIKE '%$qSafe%' OR m.item_description LIKE '%$qSafe%' OR o.branch_name LIKE '%$qSafe%')";
    }

    $sql .= " ORDER BY o.issued_date ASC, o.id ASC";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()):
?>
<tr class="align-middle">
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['item_code']) ?></td>
    <td><?= htmlspecialchars($row['item_description']) ?></td>
    <td><?= $row['quantity'] ?></td>
    <td><?= $row['remaining_quantity'] ?></td>
    <td><?= $row['issued_date'] ?></td>
    <td><?= htmlspecialchars($row['branch_name']) ?></td>
    <td>
        <input type="number" class="form-control form-control-sm qty-input" 
               value="<?= $row['quantity'] ?>" 
               min="1" 
               max="<?= $row['remaining_quantity'] ?>" 
               data-id="<?= $row['id'] ?>" 
               title="Max available: <?= $row['remaining_quantity'] ?>">
    </td>
    <td>
        <button class="btn btn-success btn-sm approve-btn" 
                data-id="<?= $row['id'] ?>" 
                data-qty="<?= $row['quantity'] ?>">Approve</button>
        <button class="btn btn-danger btn-sm reject-btn" 
                data-id="<?= $row['id'] ?>" 
                data-qty="<?= $row['quantity'] ?>">Reject</button>
    </td>
</tr>
<?php endwhile;
}
?>
